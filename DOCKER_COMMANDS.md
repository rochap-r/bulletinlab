# Commandes Docker — BulletinLab (projet `topscolaire`)

Ce fichier récapitule toutes les commandes Docker utiles pour ton contexte local,
avec une explication du **pourquoi** de chacune.

> **Contexte** : un seul fichier `docker-compose.yml`, un seul `.env.docker`, nom du projet `topscolaire`.
> La base de données PostgreSQL est dans le volume `topscolaire_pg_data` (jamais supprimé sans ton accord explicite).

---

## 1. Préfixe commun

Toutes les commandes utilisent ce préfixe :

```powershell
docker compose --env-file .env.docker
```

**Pourquoi ?**  
Le fichier `.env.docker` contient les variables d'environnement (ports, identifiants DB, `APP_KEY`, etc.). Sans `--env-file`, Compose ne les connaîtrait pas et l'interpolation échouerait (ex. `DB_PASSWORD` requis).

---

## 2. Gérer la stack (démarrer, arrêter, redémarrer)

### Construire les images et démarrer tous les services

```powershell
docker compose --env-file .env.docker up -d --build
```

**Pourquoi ?**  
- `up` : crée et démarre les conteneurs définis dans `docker-compose.yml`.
- `-d` : mode détaché (en arrière-plan, tu rends la main à PowerShell).
- `--build` : reconstruit les images Docker avant de démarrer. À utiliser après toute modification de code (PHP, React, Dockerfile) car cette stack reflète la production (pas de hot reload sur le code monté en volume).

---

### Démarrer sans rebuild (si tu n'as pas modifié le code)

```powershell
docker compose --env-file .env.docker up -d
```

**Pourquoi ?**  
Plus rapide si tu as seulement arrêté et redémarré la stack sans toucher au code source.

---

### Voir l'état des conteneurs

```powershell
docker compose --env-file .env.docker ps
```

**Pourquoi ?**  
Vérifie que tous les services sont `Running` ou `Healthy` avant de tester dans le navigateur.

---

### Arrêter la stack (garder la BDD et les données)

```powershell
docker compose --env-file .env.docker down
```

**Pourquoi ?**  
Arrête et supprime les conteneurs, mais **conserve les volumes Docker** (`topscolaire_pg_data`, `topscolaire_redis_data`, `topscolaire_api_storage`). Ta base de données et tes fichiers Laravel restent intacts.

> **Ne jamais utiliser** `down -v` si tu veux garder tes données : cela supprimerait les volumes et donc la base de données.

---

### Redémarrer un service spécifique (sans tout reconstruire)

```powershell
docker compose --env-file .env.docker restart api
docker compose --env-file .env.docker restart frontend
docker compose --env-file .env.docker restart api-nginx
```

**Pourquoi ?**  
Utile après un changement de variable d'environnement dans `.env.docker` (les conteneurs relisent `env_file` au redémarrage) ou si un service est bloqué.

---

## 3. Logs et débogage

### Voir les logs en temps réel d'un service

```powershell
docker compose --env-file .env.docker logs -f api
docker compose --env-file .env.docker logs -f frontend
docker compose --env-file .env.docker logs -f db
docker compose --env-file .env.docker logs -f queue
```

**Pourquoi ?**  
- `logs` : affiche la sortie standard des conteneurs.
- `-f` : follow (suivi en temps réel, comme `tail -f`).
- Indispensable pour diagnostiquer une erreur 502, un problème de connexion DB, ou un crash de Vite.

---

### Voir les logs d'un service depuis le début (sans follow)

```powershell
docker compose --env-file .env.docker logs api --tail=50
```

**Pourquoi ?**  
`--tail=50` limite aux 50 dernières lignes pour ne pas noyer le terminal.

---

## 4. Exécuter des commandes à l'intérieur des conteneurs

### Ouvrir un shell dans l'API PHP

```powershell
docker compose --env-file .env.docker exec api sh
```

**Pourquoi ?**  
`exec` lance une commande dans un conteneur déjà en cours. `sh` donne un shell interactif pour explorer le système de fichiers, vérifier des permissions, etc.

---

### Lancer une commande Artisan Laravel

```powershell
docker compose --env-file .env.docker exec api php artisan migrate:status
docker compose --env-file .env.docker exec api php artisan cache:clear
docker compose --env-file .env.docker exec api php artisan route:list
docker compose --env-file .env.docker exec api php artisan tinker
```

**Pourquoi ?**  
Permet d'interagir avec Laravel sans installer PHP localement. `tinker` donne une console PHP interactive pour manipuler les modèles Eloquent.

---

### Accéder à la base de données PostgreSQL

```powershell
docker compose --env-file .env.docker exec db psql -U bulletinlab -d bulletinlab
```

**Une fois dans `psql` :**
- `\l` : lister les bases de données
- `\dt` : lister les tables
- `\q` : quitter

**Pourquoi ?**  
L'outil `psql` est déjà installé dans l'image `postgres:18`. Tu n'as pas besoin d'installer PostgreSQL sur Windows.

---

### Se connecter à la base depuis l'extérieur (Windows)

Le port PostgreSQL n'est pas publié sur localhost dans cette configuration. Si tu veux utiliser pgAdmin ou DBeaver depuis Windows, utilise plutôt le conteneur de dev existant :

```powershell
# Connexion via le conteneur bulletinlab-dev-postgres-1 (port 5433 publié)
psql -h 127.0.0.1 -p 5433 -U bulletinlab -d bulletinlab
```

Ou publie le port de `topscolaire-db-1` en modifiant `docker-compose.yml` (ajouter `ports: - "127.0.0.1:5433:5432"` sur le service `db`).

---

## 5. Migrations et schéma de base de données

### Lancer les migrations (manuellement, comme en production)

```powershell
docker compose --env-file .env.docker --profile maintenance run --rm migrate
```

**Pourquoi ?**  
- `--profile maintenance` : active le service `migrate` qui est désactivé par défaut (profil).
- `run --rm` : lance le conteneur à la volée et le supprime après exécution (pas de conteneur zombie).
- `migrate --force` : applique toutes les migrations en attente.

> Les migrations ne s'exécutent **pas automatiquement** au `up` (comme en production). Tu dois les lancer explicitement après un `git pull` qui ajoute des fichiers de migration.

---

### Voir le statut des migrations

```powershell
docker compose --env-file .env.docker --profile maintenance run --rm migrate php artisan migrate:status
```

**Pourquoi ?**  
Vérifie quelles migrations ont été appliquées ou sont en attente, sans rien modifier.

---

## 6. Administration de la base de données (Adminer)

### Démarrer Adminer (console web PostgreSQL)

```powershell
docker compose --env-file .env.docker --profile tools up -d adminer
```

**Pourquoi ?**  
- `--profile tools` : active le service `adminer` (désactivé par défaut).
- Adminer est accessible sur `http://127.0.0.1:8082/?pgsql=db`
- Sélectionne **PostgreSQL** comme système, serveur `db`, utilisateur `bulletinlab`, mot de passe `bulletinlab`, base `bulletinlab`.

### Arrêter Adminer

```powershell
docker compose --env-file .env.docker stop adminer
```

---

## 7. Sauvegarde et restauration de la base de données

### Sauvegarder la base PostgreSQL

```powershell
docker exec topscolaire-db-1 pg_dump -U bulletinlab -d bulletinlab > backup-$(Get-Date -Format "yyyy-MM-dd").sql
```

**Pourquoi ?**  
`docker exec` lance `pg_dump` directement dans le conteneur DB. La sortie est redirigée vers un fichier sur l'hôte (Windows). Le volume `topscolaire_pg_data` reste intact.

### Restaurer une sauvegarde

```powershell
# D'abord copier le fichier dans le conteneur
docker compose --env-file .env.docker cp backup-2026-10-27.sql db:/tmp/backup.sql

# Puis restaurer
docker compose --env-file .env.docker exec -T db psql -U bulletinlab -d bulletinlab -f /tmp/backup.sql
```

**Pourquoi ?**  
`cp` transfère le fichier de l'hôte vers le conteneur. `psql -f` exécute le dump SQL pour restaurer les données.

---

## 8. Nettoyage et maintenance Docker

### Voir les volumes du projet

```powershell
docker volume ls | Select-String topscolaire
```

**Pourquoi ?**  
Confirme que `topscolaire_pg_data` (ta BDD) existe bien et n'a pas été supprimé par erreur.

### Supprimer les conteneurs arrêtés (nettoyage)

```powershell
docker container prune -f
```

**Pourquoi ?**  
Supprime les conteneurs arrêtés qui prennent de l'espace, sans toucher aux volumes (données conservées).

### Supprimer les images inutilisées

```powershell
docker image prune -f
```

**Pourquoi ?**  
Libère de l'espace disque après plusieurs `build` qui créent de nouvelles versions d'images.

---

## 9. Exemple de workflow complet (après une modification de code)

```powershell
# 1. Construire et démarrer
docker compose --env-file .env.docker up -d --build

# 2. Appliquer les migrations si le schéma a changé
docker compose --env-file .env.docker --profile maintenance run --rm migrate

# 3. Vérifier l'état
docker compose --env-file .env.docker ps

# 4. Tester
curl http://localhost:8081/up
start http://localhost:8080

# 5. En cas de problème, consulter les logs
docker compose --env-file .env.docker logs -f api
```

---

## Récapitulatif des ports utilisés

| Service | Port hôte | Accès |
|---------|-----------|-------|
| API Laravel (via Nginx) | `127.0.0.1:8081` | `http://localhost:8081/up` |
| SPA React | `127.0.0.1:8080` | `http://localhost:8080` |
| Adminer (profil `tools`) | `127.0.0.1:8082` | `http://127.0.0.1:8082/?pgsql=db` |
| PostgreSQL | Interne uniquement | Via `docker compose exec db psql` |
| Redis | Interne uniquement | Via `docker compose exec redis redis-cli` |

Tous les ports sont bindés sur `127.0.0.1` uniquement (accès local, pas exposé sur le réseau).

---

## ⚠️ Règles d'or

1. **Toujours préciser `--env-file .env.docker`** sans quoi les variables d'environnement sont absentes.
2. **Jamais `down -v`** si tu veux conserver ta base de données.
3. **Rebuild requis** après modification du code (pas de hot reload dans cette config "mirroir production").
4. **Migrations manuelles** via le profil `maintenance` avant ou après le démarrage des services applicatifs.
