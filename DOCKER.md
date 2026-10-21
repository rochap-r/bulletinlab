# Déploiement Docker — BulletinLab

Stack de production conteneurisée : API Laravel (PHP 8.4-FPM + nginx), SPA React (nginx), PostgreSQL 18 et Redis.

## Sommaire

- [Vue d'ensemble](#vue-densemble)
- [Prérequis](#prérequis)
- [Fichiers Docker](#fichiers-docker)
- [Premier déploiement](#premier-déploiement)
- [Importer les données de dev](#importer-les-données-de-dev)
- [Variables d'environnement](#variables-denvironnement)
- [Commandes courantes](#commandes-courantes)
- [Sauvegarde & restauration](#sauvegarde--restauration)
- [Mises à jour](#mises-à-jour)
- [Dépannage](#dépannage)
- [Évolutions possibles](#évolutions-possibles)

## Vue d'ensemble

```
                  Votre LB / nginx externe (TLS : Let's Encrypt, etc.)
                  │
  app.exemple.cd ─┼──► frontend:8080   nginx statique (SPA React) + proxy /api ─┐
  api.exemple.cd ─┼──► api-nginx:8081  nginx interne ──fastcgi──► api:9000       │
                  │                                    (php-fpm Laravel) ◄───────┘
                  │        queue     : php artisan queue:work
                  │        scheduler : php artisan schedule:work
                  │        db        : PostgreSQL 18     (volume pg_data)
                  │        redis     : cache/sessions/queue (volume redis_data)
```

| Service | Image | Rôle |
|---|---|---|
| `frontend` | build `docker/frontend` | Sert le SPA (`dist/`) et proxy `/api` vers `api-nginx` (same-origin, pas de CORS) |
| `api-nginx` | nginx:1.31-alpine | Point d'entrée HTTP de l'API, fastcgi vers `api:9000` |
| `api` | build `docker/api` | PHP 8.4-FPM, application Laravel |
| `queue` | idem image API | Workers de file d'attente (`queue:work`) |
| `scheduler` | idem image API | Planificateur (`schedule:work`, ex. `sanctum:prune-expired`) |
| `migrate` | idem image API | One-shot : `php artisan migrate --force` à chaque `up` |
| `db` | postgres:18 | Base de données PostgreSQL (volume `pg_data`) |
| `redis` | redis:7-alpine | Cache, sessions et queue (AOF activé) |
| `adminer` | adminer:5 | Console d'administration PG — **profil `tools`**, démarrée à la demande, bindée sur 127.0.0.1 |

L'authentification est stateless (tokens Sanctum Bearer) : aucun cookie cross-domaine, le sous-domaine `api.` est appelé directement ou via le proxy `/api` du frontend.

## Prérequis

- Docker Engine 24+ et le plugin Compose v2
- Linux (VPS/cloud) pour la prod. Sous Windows : Docker Desktop avec backend WSL2 (`wsl --install` + redémarrage)
- Deux enregistrements DNS : `app.votre-domaine` et `api.votre-domaine` pointant vers le serveur
- Un terminaison TLS en amont (LB de l'hébergeur, nginx + certbot, Caddy…) qui proxye vers les ports 8080/8081 en transmettant `X-Forwarded-Proto` et `Host`

## Fichiers Docker

```
docker-compose.yml                     # Stack complète (8 services + adminer en profile `tools`)
docker/
  api/
    Dockerfile                         # 3 stages : composer → assets Vite → php:8.4-fpm
    entrypoint.sh                      # Attend DB/Redis, storage:link, caches Laravel
    zz-app.ini                         # opcache production, uploads 32M
    nginx.conf                         # nginx fastcgi de l'API
  frontend/
    Dockerfile                         # 2 stages : build node:22 → nginx statique
    default.conf.template              # SPA + proxy /api
.env.production.example                # Modèle d'environnement (→ .env.production)
.dockerignore                          # Exclut vendor, .env, docs… du contexte de build
```

Notes d'image :

- L'image API inclut les extensions : `pdo_pgsql`, `redis` (pecl), `zip` et `gd` (Excel via maatwebsite/excel), `bcmath`, `pcntl` (workers), `opcache`.
- Les caches Laravel (`config`, `routes`, `views`, `events`) sont générés **au démarrage** dans l'entrypoint, jamais au build : `config:cache` fige les variables d'environnement.
- `opcache.validate_timestamps=0` : le code est figé dans l'image, toute modification nécessite un rebuild.
- Les assets Vite de la page Blade `welcome` sont construits dans l'image (stage `assets`).

## Premier déploiement

```bash
git clone <votre-repo> bulletinlab
cd bulletinlab

# 1. Environnement
cp .env.production.example .env.production
php artisan key:generate --show   # valeur à coller dans APP_KEY
#   Renseigner au minimum : DB_PASSWORD, APP_URL,
#   CORS_ALLOWED_ORIGINS, APP_KEY

# 2. Build et démarrage
docker compose --env-file .env.production up -d --build

# 3. Vérification
docker compose --env-file .env.production ps          # tous les services "healthy"
curl -s http://localhost:8081/up                      # health API : {"status":"ok"}
curl -sI http://localhost:8080                        # frontend : 200
```

> Le fichier `.env.production` sert deux fois : interpolation du `docker-compose.yml` (ports, identifiants DB, `VITE_API_BASE_URL`) **et** variables d'environnement injectées dans les conteneurs applicatifs (`env_file`). C'est pourquoi il faut toujours lancer Compose avec `--env-file .env.production`.

## Importer les données de dev

Les deux environnements sont isolés en base :

| Environnement | Base de données | Accès |
| --- | --- | --- |
| Local (Herd) | PostgreSQL natif de la machine (service `postgresql-x64-18`) | `127.0.0.1:5432` (voir `DB_PORT` dans `.env`) |
| Docker | service `db` de `docker-compose.yml` (conteneur `bulletinlab-db-1`) | réseau interne `backend`, port non publié |

Même moteur et même version majeure : l'import est un simple dump → restauration, sans transformation.

```bash
# 1. Dump de la base locale (PG natif sur 127.0.0.1:5432) vers l'hôte.
#    Le fichier est ensuite copié dans le conteneur : aucune redirection
#    PowerShell, qui corromprait le dump.
$env:PGPASSWORD = "bulletinlab"
pg_dump -h 127.0.0.1 -p 5432 -U bulletinlab -d bulletinlab `
    -Fc --clean --if-exists --no-owner --no-privileges --file=dev-dump.dump

# 2. Import dans la base de la pile Docker (services démarrés).
docker compose --env-file .env.production cp dev-dump.dump db:/tmp/dev-dump.dump
docker compose --env-file .env.production exec -T db `
    pg_restore -U bulletinlab -d bulletinlab --clean --if-exists --no-owner --exit-on-error /tmp/dev-dump.dump
docker compose --env-file .env.production exec -T db rm /tmp/dev-dump.dump
```

Notes :

- `--clean --if-exists` supprime les objets existants avant de les recréer : l'import écrase proprement le schéma (et les données) de la base Docker.
- La table `migrations` est incluse dans le dump : le service `migrate` (rejoué à chaque `up`) verra tout comme appliqué.
- `pg_restore` s'exécute en `trust` sur le socket local du conteneur : aucun mot de passe n'est à saisir.
- Les tokens Sanctum (`personal_access_tokens`) sont des hash SHA-256 indépendants de `APP_KEY` : vos sessions actives côté SPA continuent de fonctionner après l'import.
- Idéalement, importez **avant** de mettre le site en production réelle (quelques écritures de démarrage peuvent être écrasées — sans impact, sessions et cache étant sur Redis).
- Préférez ensuite un import « data only » (`pg_dump --data-only --disable-triggers`) si vous voulez préserver des réglages déjà posés en prod.

## Réinitialiser la base locale (base propre + école BB)

Procédure de référence pour repartir d'une base saine : le socle vient
**exclusivement des seeders**, puis on importe **tout ce qui est rattaché à
l'école BB** depuis le dump MySQL, dans **toutes** les tables.

```powershell
# 1. Base propre + seeders : schéma neuf, référentiels globaux, tenants de démo.
php artisan migrate:fresh --seed

# 2. Import du périmètre de l'école BB (tenant 203) : lignes tenant-scoped et
#    lignes globales rattachées à BB (pivots de rôles, jetons de session), avec
#    traduction des références vers le seed par clé naturelle.
php "<chemin>\import_tenant_final.php" --apply

# 3. Contrôle de couverture : chaque table liée à BB doit être à l'identique.
php "<chemin>\audit_bb_scope.php"
```

Règles d'import :

- **Référentiels globaux = seeders.** Les modèles système
  (`system_templates_bulletin`, nœuds, périodes, layouts, blocs spéciaux), les
  référentiels `ref_*`, les rôles plateforme et les barèmes par défaut
  (`tenant_id NULL`) sont créés par le seed ; les références de BB y sont
  raccordées **par clé naturelle** (code, chemin de nœud, modèle + période),
  jamais par identifiant brut.
- **Périmètre BB = `tenant_id = 203`**, **plus** les lignes globales rattachées à
  BB même quand `tenant_id` est `NULL` :
  - `utilisateur_roles` : pivots Spatie historiques sans contexte d'équipe
    (ex. le rôle du propriétaire du tenant), sélectionnés via `utilisateur_id` ;
  - `personal_access_tokens` : jetons de session des utilisateurs du tenant.
- Les barèmes par défaut du dump contiennent **3 jeux dupliqués identiques** ;
  le seed en fournit **1 jeu propre** (valeurs vérifiées identiques).

## Variables d'environnement

Référez-vous à `.env.production.example` (commenté). Les plus importantes :

| Variable | Exemple | Commentaire |
|---|---|---|
| `APP_KEY` | `base64:…` | `php artisan key:generate --show` — jamais régénérée en prod |
| `APP_URL` | `https://app.exemple.cd` | URL publique du frontend |
| `CORS_ALLOWED_ORIGINS` | `https://app.exemple.cd` | Utile si le navigateur appelle `api.` en direct ; ignoré avec le proxy `/api` same-origin |
| `DB_PASSWORD` | fort | Requis par Compose (le lancement échoue sinon) — identique à `DB_USERNAME` pour se connecter via Adminer/`psql` |
| `VITE_API_BASE_URL` | `/api/v1` | **Fixé au build du frontend.** `/api/v1` = proxy nginx ; `https://api.exemple.cd/v1` = appel direct |
| `FRONTEND_HTTP_PORT` / `API_HTTP_PORT` | `8080` / `8081` | Ports exposés vers le LB |
| `ADMINER_HTTP_PORT` | `8082` | Console Adminer, bindée sur 127.0.0.1 (voir `Commandes courantes`) |
| `LOG_CHANNEL` | `stderr` | Logs dans `docker logs` au lieu de fichiers |

> `VITE_API_BASE_URL` est bake-in au build : changez-la ⇒ rebuild du frontend (`docker compose --env-file .env.production build frontend`).

## Commandes courantes

```bash
COMPOSE="docker compose --env-file .env.production"

$COMPOSE up -d --build          # Déploiement / mise à jour complète
$COMPOSE ps                     # État des services
$COMPOSE logs -f api            # Logs d'un service (api, frontend, queue…)
$COMPOSE exec api php artisan tinker        # Shell artisan dans l'API
$COMPOSE exec api sh                        # Shell dans le conteneur API
$COMPOSE exec db psql -U bulletinlab bulletinlab   # Accès PostgreSQL
$COMPOSE restart frontend       # Redémarrage sans rebuild
$COMPOSE down                   # Arrêt (les volumes sont conservés)
```

Dépannage migrations : `$COMPOSE run --rm migrate php artisan migrate:status`

### Consulter la base (Adminer)

Adminer est le même outil que celui utilisé avec MySQL (même interface), avec le pilote PostgreSQL. Il n'est pas démarré par un `up -d` classique : profil `tools`, bindé sur **127.0.0.1** uniquement.

```bash
$COMPOSE --profile tools up -d adminer   # Démarrage (arrêt : $COMPOSE stop adminer)
```

Puis ouvrir **`http://127.0.0.1:8082/?pgsql=db`** — ce lien met le pilote sur PostgreSQL et pré-remplit le serveur (`ADMINER_DEFAULT_SERVER=db`) : sans ce paramètre, Adminer pointe par défaut sur MySQL. Bookmark recommandé.

| Champ | Valeur |
|---|---|
| Système | `PostgreSQL` |
| Serveur | `db` (pré-rempli via `ADMINER_DEFAULT_SERVER`) |
| Utilisateur / mot de passe / base | `DB_USERNAME` / `DB_PASSWORD` / `DB_DATABASE` de `.env.production` |

> Sur un serveur distant, la liaison 127.0.0.1 impose un tunnel SSH : `ssh -L 8082:127.0.0.1:8082 user@serveur`. Ne publiez jamais Adminer sur une interface publique : c'est un accès direct à la base.

## Sauvegarde & restauration

Les données vivent dans 3 volumes nommés : `pg_data` (PostgreSQL), `redis_data` (Redis, reconstructible), `api_storage` (fichiers Laravel). L'ancien volume MySQL `db_data` subsiste tant que vous ne le supprimez pas (`docker volume rm bulletinlab_db_data`).

```bash
COMPOSE="docker compose --env-file .env.production"

# Sauvegarde quotidienne (cron) — socket local en `trust`, pas de mot de passe
docker exec bulletinlab-db-1 pg_dump -U bulletinlab -d bulletinlab --no-owner \
  | gzip > backup-$(date +%F).sql.gz
docker run --rm -v bulletinlab_api_storage:/data -v "$PWD":/backup alpine \
  tar czf /backup/storage-$(date +%F).tar.gz -C /data .

# Restauration
gunzip -c backup-2026-10-18.sql.gz | \
  docker exec -i bulletinlab-db-1 psql -v ON_ERROR_STOP=1 -U bulletinlab -d bulletinlab
```

Adapter le nom du conteneur DB selon `docker compose ps` (suffixe `-1` si pas de `container_name`). Testez la restauration de temps en temps — une sauvegarde non testée n'est pas une sauvegarde.

## Mises à jour

```bash
git pull
docker compose --env-file .env.production up -d --build
```

- Le service `migrate` rejoue `migrate --force` à chaque `up` (idempotent).
- Les nouvelles valeurs de `.env.production` sont appliquées au redémarrage (l'entrypoint régénère les caches).
- `up -d --build` recrée les conteneurs seulement si l'image a changé (uptime quasi continu). Pour un rollout strictement sans interruption : `$COMPOSE build` puis `$COMPOSE up -d` service par service.

## Dépannage

| Symptôme | Cause probable / solution |
|---|---|
| `docker compose` échoue avec « DB_PASSWORD » | `.env.production` absent ou incomplet — Compose exige ces variables à l'interpolation |
| `frontend` reste en "starting" | Le health de `api-nginx` échoue : l'API ne répond pas sur `/up`. Voir `$COMPOSE logs api api-nginx` |
| 502 sur l'API | php-fpm arrêté ou entrypoint bloqué sur l'attente DB/Redis : `$COMPOSE logs api` |
| `Vite manifest not found` | Assets racine non construits — l'image est censée les inclure ; rebuild (`$COMPOSE build api`) |
| Erreur de cache config après changement d'env | Redémarrer les conteneurs applicatifs : `$COMPOSE restart api queue scheduler` |
| Le build frontend échoue sur `tsc` | L'image Node doit être 22+ (`node:22-alpine` est spécifié dans le Dockerfile) |
| Docker Desktop 500 au démarrage (Windows) | WSL2 non installé : `wsl --install` puis redémarrage |
| 404 « File not found. » sur toutes les routes API | `$realpath_root` vide dans api-nginx (le code n'est monté que dans fpm) — le `docker/api/nginx.conf` utilise `$document_root` précisément pour cela, ne pas « corriger » en `$realpath_root` |
| Connexions PostgreSQL refusées côté API | Le mot de passe `DB_PASSWORD` ne correspond pas à `.env.production` — `up -d` ne recrée **pas** le service DB si vous changez les identifiants après le premier démarrage (`POSTGRES_*` ne sert qu'à l'initialisation du volume) : dans ce cas `$COMPOSE up -d --force-recreate db` (les données du volume sont conservées) ou corriger `.env.production` |

## Évolutions possibles

- **Browsershot / PDF** : le package est installé mais inutilisé. Si vous l'activez, ajoutez Chromium dans l'image API (`chromium` + `chromium-sandbox`) et rendez `storage/` et le binaire accessibles à `www-data`.
- **HTTPS sans LB externe** : ajoutez un service Caddy devant `frontend` et `api-nginx` avec `CADDY_DOMAIN` — les images restent inchangées.
- **Redis sans persistance** : si vous préférez sacrifier `redis_data` (reconstructible), remplacez `--appendonly yes` par `--save ""` dans le `docker-compose.yml` pour moins d'I/O.
