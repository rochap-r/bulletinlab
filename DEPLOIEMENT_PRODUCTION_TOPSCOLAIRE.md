# Déploiement de TopScolaire en production

> Pour la configuration et l'exploitation détaillées de GitHub Actions,
> consulter [`CI_CD_TOPSCOLAIRE.md`](CI_CD_TOPSCOLAIRE.md).

Ce document décrit le premier déploiement de TopScolaire sur un VPS Ubuntu avec Docker Compose, Nginx et Let's Encrypt.

Il correspond à l'architecture réellement utilisée en production :

- domaine public : `https://topscolaire.com` ;
- backend Laravel : dépôt `rochap-r/bulletinlab` ;
- frontend React : dépôt `rochap-r/bulletinlabfrontend` ;
- répertoire du projet sur le VPS : `/opt/topscolaire` ;
- PostgreSQL et Redis exécutés dans Docker ;
- Nginx installé sur le VPS et utilisé comme reverse proxy HTTPS ;
- certificats TLS gérés automatiquement par Certbot.

> Ne jamais copier un mot de passe, une clé privée ou le contenu réel de `.env.production` dans Git.

## 1. Architecture de production

```text
Internet
   │
   ▼
Nginx Ubuntu : 80 / 443 + certificat Let's Encrypt
   │
   ▼
Frontend React : 127.0.0.1:8080
   │ /api
   ▼
Nginx API : 127.0.0.1:8081
   │
   ▼
Laravel PHP-FPM ── PostgreSQL ── Redis
                  Queue          Scheduler
```

Les ports PostgreSQL `5432`, Redis `6379`, frontend `8080`, API `8081` et Adminer `8082` ne sont pas exposés directement à Internet.

## 2. Préparer le domaine et le pare-feu

Créer les enregistrements DNS suivants :

| Type | Nom | Valeur |
|---|---|---|
| `A` | `@` | IPv4 du VPS |
| `A` | `www` | IPv4 du VPS |

Dans le pare-feu Hostinger, autoriser :

- le port SSH réellement utilisé, généralement `22` ;
- le port HTTP `80` ;
- le port HTTPS `443`.

Ne pas ouvrir publiquement les ports `5432`, `6379`, `8080`, `8081` ou `8082`.

Vérifier le DNS depuis le VPS :

```bash
getent ahostsv4 topscolaire.com
getent ahostsv4 www.topscolaire.com
```

Les deux noms doivent retourner l'adresse IPv4 du VPS.

## 3. Se connecter et vérifier Docker

```bash
ssh root@IP_DU_VPS

docker --version
docker compose version
```

Installer les outils système nécessaires :

```bash
apt update
apt install -y git nginx
```

PHP, Composer, Node.js, npm, PostgreSQL et Redis n'ont pas besoin d'être installés directement sur le VPS : ils sont fournis par les images Docker.

## 4. Préparer une version de production

Le déploiement continu utilise la branche `main` des deux dépôts. Après la
configuration décrite à la section 16, chaque push sur `main` du backend ou du
frontend déclenche le workflow GitHub Actions du backend, qui déploie les deux
derniers `main` ensemble. Les tags restent utiles pour marquer une version,
mais ne déclenchent pas le déploiement automatique.

Pour le premier déploiement, ou si vous choisissez de figer une version, vous
pouvez créer le même tag dans les deux dépôts :

```powershell
# Backend Laravel
git tag -a v1.0.0 -m "TopScolaire première version de production"
git push origin v1.0.0

# Frontend React
git -C frontend tag -a v1.0.0 -m "TopScolaire frontend première version"
git -C frontend push origin v1.0.0
```

L'utilisation du même numéro de version permet d'identifier la paire backend/frontend compatible.

## 5. Cloner les deux dépôts

Le frontend doit être cloné dans le dossier `frontend` du backend, car le Dockerfile frontend utilise cette arborescence.

```bash
mkdir -p /opt/topscolaire

git clone --branch v1.0.0 \
  git@github.com:rochap-r/bulletinlab.git \
  /opt/topscolaire

git clone --branch v1.0.0 \
  git@github.com:rochap-r/bulletinlabfrontend.git \
  /opt/topscolaire/frontend

cd /opt/topscolaire
```

Le compte SSH `deploy` utilisé par GitHub Actions doit pouvoir lire les deux dépôts.
Pour des dépôts publics, des URL `https://github.com/...` conviennent ; pour
des dépôts privés, configurez une clé de lecture GitHub pour ce compte.

Arborescence attendue :

```text
/opt/topscolaire/
├── app/
├── docker/
├── docker-compose.yml
├── .env.production.example
└── frontend/
    ├── package.json
    └── src/
```

Pour des dépôts publics, les URL HTTPS peuvent être utilisées. Pour des dépôts privés, configurer une clé SSH GitHub dédiée au déploiement.

## 6. Configurer l'environnement de production

```bash
cd /opt/topscolaire
cp .env.production.example .env.production
nano .env.production
chmod 600 .env.production
```

Configuration de référence, avec des secrets factices :

```dotenv
COMPOSE_PROJECT_NAME=topscolaire
IMAGE_TAG=v1.0.0

APP_NAME="TopScolaire"
APP_ENV=production
APP_KEY="base64:CLE_A_GENERER"
APP_DEBUG=false
APP_URL=https://topscolaire.com
CORS_ALLOWED_ORIGINS=https://topscolaire.com,https://www.topscolaire.com
BUSINESS_TIMEZONE=Africa/Lubumbashi
APP_LOCALE=fr

LOG_CHANNEL=stderr
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null
BCRYPT_ROUNDS=12

PLATFORM_ADMIN_PASSWORD="MOT_DE_PASSE_UNIQUE"
DEMO_ADMIN_PASSWORD="AUTRE_MOT_DE_PASSE_UNIQUE"
DEMO_TEACHER_PASSWORD="TROISIEME_MOT_DE_PASSE_UNIQUE"

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=topscolaire
DB_USERNAME=topscolaire
DB_PASSWORD="MOT_DE_PASSE_POSTGRES"

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_PATH=/
SESSION_DOMAIN=null

QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

SANCTUM_EXPIRATION=43200
SANCTUM_TOKEN_PREFIX=
TRUSTED_PROXIES=REMOTE_ADDR

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@topscolaire.com"
MAIL_FROM_NAME="${APP_NAME}"

FRONTEND_HTTP_PORT=8080
API_HTTP_PORT=8081
ADMINER_HTTP_PORT=8082
VITE_API_BASE_URL=/api/v1
```

Générer une clé Laravel :

```bash
echo "base64:$(openssl rand -base64 32)"
```

Générer un mot de passe PostgreSQL sans caractères nécessitant d'échappement :

```bash
openssl rand -hex 32
```

Règles importantes :

- utiliser trois mots de passe distincts pour les comptes seedés ;
- garder les guillemets autour des valeurs contenant `#`, `$`, des espaces ou des caractères spéciaux ;
- ne jamais utiliser `devpassword` en production ;
- ne jamais commiter `.env.production` ;
- ne plus changer `APP_KEY` après le démarrage réel sans plan de migration.

Valider la configuration Compose :

```bash
docker compose --env-file .env.production config --quiet
```

L'absence de sortie signifie que la configuration est valide.

## 7. Construire et démarrer les services Docker

Construire les images :

```bash
cd /opt/topscolaire
docker compose --env-file .env.production build --pull
```

Démarrer PostgreSQL et Redis :

```bash
docker compose --env-file .env.production up -d db redis
```

Appliquer les migrations :

```bash
docker compose --env-file .env.production \
  --profile maintenance run --rm migrate
```

Créer les référentiels, les comptes initiaux et les données de test :

```bash
docker compose --env-file .env.production \
  run --rm api php artisan db:seed --force
```

Démarrer toute la plateforme :

```bash
docker compose --env-file .env.production up -d
```

Vérifier les conteneurs :

```bash
docker compose --env-file .env.production ps
```

Services attendus :

- `api` : `healthy` ;
- `api-nginx` : `healthy` ;
- `db` : `healthy` ;
- `redis` : `healthy` ;
- `frontend` : `Up` ;
- `queue` : `Up` ;
- `scheduler` : `Up`.

Les seeders `*DemoSeeder` peuvent terminer en `0 ms` en production : ils sont volontairement désactivés. Le seeder `BulletinLabTestDataSeeder` doit, lui, terminer avec `DONE`.

## 8. Vérifier Docker avant l'ouverture publique

```bash
curl -fsS http://127.0.0.1:8081/up
curl -I http://127.0.0.1:8080
```

Consulter les journaux :

```bash
docker compose --env-file .env.production logs \
  --tail=100 api api-nginx frontend queue scheduler
```

## 9. Configurer Nginx sur le VPS

Créer le virtual host :

```bash
nano /etc/nginx/sites-available/topscolaire
```

Configuration HTTP initiale, avant la création du certificat :

```nginx
server {
    listen 80;
    listen [::]:80;

    server_name topscolaire.com www.topscolaire.com;

    location = /up {
        proxy_pass http://127.0.0.1:8081/up;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_read_timeout 120s;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }
}
```

Activer le site et recharger Nginx :

```bash
ln -s /etc/nginx/sites-available/topscolaire \
  /etc/nginx/sites-enabled/topscolaire

nginx -t
systemctl reload nginx
```

Vérifier HTTP avant Certbot :

```bash
curl -I http://topscolaire.com
curl -fsS http://topscolaire.com/up
```

## 10. Installer le certificat HTTPS

Certbot a été installé depuis les paquets Ubuntu :

```bash
apt update
apt install -y certbot python3-certbot-nginx
```

Créer un seul certificat couvrant les deux domaines :

```bash
certbot --nginx \
  -d topscolaire.com \
  -d www.topscolaire.com
```

Certbot ajoute les directives TLS et la redirection HTTPS. Après l'émission du certificat, la configuration a été normalisée dans `/etc/nginx/sites-available/topscolaire` sous cette forme :

```nginx
server {
    listen 80;

    server_name topscolaire.com www.topscolaire.com;

    return 301 https://topscolaire.com$request_uri;
}

server {
    listen 443 ssl http2;

    server_name topscolaire.com www.topscolaire.com;

    ssl_certificate /etc/letsencrypt/live/topscolaire.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/topscolaire.com/privkey.pem;

    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location = /up {
        proxy_pass http://127.0.0.1:8081/up;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_read_timeout 120s;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Valider et recharger cette configuration finale :

```bash
nginx -t
systemctl reload nginx
```

Ne pas relancer ensuite une commande limitée à `-d topscolaire.com`, car cela créerait inutilement un second certificat.

Vérifier le certificat installé :

```bash
certbot certificates
```

Résultat attendu :

```text
Certificate Name: topscolaire.com
Domains: topscolaire.com www.topscolaire.com
Certificate Path: /etc/letsencrypt/live/topscolaire.com/fullchain.pem
Private Key Path: /etc/letsencrypt/live/topscolaire.com/privkey.pem
```

Tester le renouvellement automatique :

```bash
certbot renew --cert-name topscolaire.com --dry-run
```

Résultat attendu :

```text
Congratulations, all simulated renewals succeeded
```

Le port `80` doit rester ouvert afin que Certbot puisse réaliser les validations HTTP-01.

## 11. Vérifications finales

```bash
curl -I https://topscolaire.com
curl -I https://www.topscolaire.com
curl -fsS https://topscolaire.com/up
```

Résultats attendus :

- les deux domaines répondent sans erreur TLS ;
- le frontend répond avec `HTTP/2 200` ;
- `/up` affiche `Application up` ;
- `certbot renew --dry-run` réussit.

Vérifier ensuite dans un navigateur privé :

- connexion plateforme ;
- connexion administrateur de l'école de test ;
- connexion enseignant ;
- navigation entre les pages ;
- création ou modification d'une donnée ;
- génération d'un bulletin ou d'un PDF ;
- absence d'erreurs JavaScript ou CORS.

Pendant les tests :

```bash
cd /opt/topscolaire

docker compose --env-file .env.production logs \
  --tail=100 -f api api-nginx frontend queue scheduler
```

## 12. Incident Certbot rencontré et résolution

Une seconde commande Certbot limitée au seul domaine principal avait créé le certificat dupliqué `topscolaire.com-0001` :

```text
/etc/letsencrypt/live/topscolaire.com-0001/
```

La résolution appliquée a été :

1. vérifier les certificats avec `certbot certificates` ;
2. remplacer dans Nginx les chemins `topscolaire.com-0001` par `topscolaire.com` ;
3. vérifier puis recharger Nginx ;
4. tester les deux domaines ;
5. supprimer uniquement le doublon avec Certbot ;
6. retester le renouvellement.

Commandes de contrôle :

```bash
grep -R "topscolaire.com-0001" /etc/nginx
nginx -t
systemctl reload nginx

curl -I https://topscolaire.com
curl -I https://www.topscolaire.com
```

Une fois toutes les références retirées :

```bash
certbot delete --cert-name topscolaire.com-0001
certbot renew --cert-name topscolaire.com --dry-run
```

Ne jamais supprimer manuellement les fichiers dans `/etc/letsencrypt`.

## 13. Avertissement Nginx non bloquant

Le VPS héberge plusieurs sites. Un avertissement de ce type peut apparaître si leurs options d'écoute HTTPS diffèrent :

```text
protocol options redefined for 0.0.0.0:443
```

La syntaxe Nginx reste valide et le site fonctionne. Pour localiser les déclarations concernées :

```bash
grep -RnsE 'listen .*443' /etc/nginx/sites-enabled/
```

Les options d'écoute `443` pourront être harmonisées ultérieurement entre les différents virtual hosts.

## 14. Sauvegarde initiale

Créer un dossier privé :

```bash
install -d -m 700 /opt/backups
cd /opt/topscolaire
```

Sauvegarder PostgreSQL :

```bash
docker compose --env-file .env.production exec -T db \
  sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
  | gzip > /opt/backups/topscolaire-initial.sql.gz

chmod 600 /opt/backups/topscolaire-initial.sql.gz
ls -lh /opt/backups
```

Sauvegarder les fichiers Laravel :

```bash
docker run --rm \
  -v topscolaire_api_storage:/data:ro \
  -v /opt/backups:/backup \
  alpine \
  tar czf /backup/topscolaire-storage-initial.tar.gz -C /data .

chmod 600 /opt/backups/topscolaire-storage-initial.tar.gz
```

Créer également un snapshot temporaire depuis le tableau de bord Hostinger et activer les sauvegardes planifiées.

## 15. Configurer les e-mails

Avec la configuration suivante, les e-mails sont écrits dans les logs et ne sont pas envoyés :

```dotenv
MAIL_MAILER=log
```

Avant d'utiliser les invitations et réinitialisations de mot de passe, configurer un SMTP réel, puis recréer les conteneurs applicatifs :

```bash
cd /opt/topscolaire
docker compose --env-file .env.production up -d --force-recreate \
  api queue scheduler
```

## 16. Déployer une nouvelle version

### Déploiement automatique à chaque push sur `main`

Les workflows sont stockés dans les deux dépôts :

- `rochap-r/bulletinlab/.github/workflows/deploy-production.yml` se connecte au
  exécute la CI Laravel, puis se connecte au VPS et lance le déploiement ;
- `rochap-r/bulletinlabfrontend/.github/workflows/notify-production-deploy.yml`
  exécute la CI React, puis demande le même déploiement après un push frontend.

Sur chaque pull request vers `main`, la CI backend exécute Composer, son audit
de sécurité, Pint, Pest et le build
des assets Laravel. La CI frontend exécute npm, le lint, Vitest et le build
TypeScript/Vite. Aucun déploiement n'est demandé si un de ces contrôles échoue.
Le job CD est ignoré sur les pull requests et ne s'exécute qu'après un push ou
un déclenchement manuel du workflow backend.

Le job de CD récupère les deux branches `main`, construit des images nommées avec
leurs deux SHA, attend PostgreSQL et Redis, crée une sauvegarde PostgreSQL
compressée dans `/opt/backups/topscolaire`, exécute les migrations, met à jour
Compose et vérifie `/up` ainsi que `https://topscolaire.com`. Un verrou sur le
VPS empêche deux déploiements de tourner en parallèle. Une erreur de build,
migration ou vérification fait échouer le job ; elle n'est pas ignorée.

Chaque dépôt peut déclencher le déploiement, qui prend alors le `main` le plus
récent des deux dépôts. Garder backend et frontend compatibles à chaque push
sur `main` (déploiements progressifs et migrations rétrocompatibles). Protéger
les deux branches `main` contre les pushes directs et exiger la revue/tests
souhaités avant fusion. Les contrôles CI intégrés constituent le minimum de
déploiement ; les branches protégées doivent les exiger avant toute fusion.

Avant d'activer le workflow, préparer un compte SSH de déploiement dédié sur le
VPS (les commandes sont à exécuter comme `root`) :

```bash
id deploy
usermod -aG docker deploy

chown -R deploy:deploy /opt/topscolaire
chmod 600 /opt/topscolaire/.env.production
install -d -o deploy -g deploy -m 700 \
  /opt/backups/topscolaire
install -d -o deploy -g deploy -m 700 \
  /home/deploy/.ssh
```

L'accès au groupe `docker` donne des privilèges équivalents à `root` sur le
VPS. La clé dédiée doit être privée, limitée à ce déploiement et révocable.
Ajouter sa clé publique dans
`/home/deploy/.ssh/authorized_keys`, puis appliquer :

```bash
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
```

Créer une paire Ed25519 dédiée sur une machine de confiance et garder la clé
privée uniquement dans les secrets GitHub :

```bash
ssh-keygen -t ed25519 -C "github-actions-topscolaire" \
  -N "" -f topscolaire-actions
```

Ajouter la clé publique `topscolaire-actions.pub` au compte SSH dédié.
Depuis le poste d'administration, afficher la clé publique à installer dans
`/home/deploy/.ssh/authorized_keys` sur le VPS :

```bash
cat topscolaire-actions.pub
```

Vérifier l'empreinte de la clé d'hôte SSH du VPS par un canal de confiance ;
ne pas se fier aveuglément à une sortie `ssh-keyscan`. Enregistrer l'entrée
`known_hosts` vérifiée (générée avec `ssh-keyscan -H`, puis contrôlée par
empreinte) dans le secret GitHub. Dans `rochap-r/bulletinlab`, créer
l'environnement GitHub `production` (Settings → Environments), limiter son
déploiement à `main` et y ajouter les secrets suivants. Le job CD référence
explicitement cet environnement ; une approbation manuelle peut aussi y être
activée si le plan GitHub du dépôt la prend en charge :

| Secret GitHub | Valeur |
|---|---|
| `TOPSCOLAIRE_PROD_HOST` | Adresse IP ou nom SSH du VPS |
| `TOPSCOLAIRE_PROD_USER` | `deploy` |
| `TOPSCOLAIRE_PROD_SSH_KEY` | Contenu complet de la clé privée `topscolaire-actions` |
| `TOPSCOLAIRE_PROD_KNOWN_HOSTS` | Entrée `known_hosts` vérifiée pour le VPS |

Le dépôt frontend a besoin d'un secret supplémentaire
`TOPSCOLAIRE_DEPLOY_DISPATCH_TOKEN`. Créer un token GitHub fine-grained limité
au dépôt `rochap-r/bulletinlab`, avec la permission **Actions: Read and write**,
puis l'ajouter dans les secrets Actions de `rochap-r/bulletinlabfrontend`. Il
sert uniquement à demander l'exécution du workflow du dépôt backend.

Avant le premier déploiement automatique, mettre à jour le Nginx de l'hôte
selon les blocs de la section 9/10 : il doit remplacer `X-Forwarded-For` par
`$remote_addr` (ne pas utiliser `$proxy_add_x_forwarded_for`) et définir
`X-Forwarded-Proto` avec `$scheme`. Tester avec `nginx -t`, puis recharger le
service.

Après avoir préparé le compte VPS et ces secrets, pousser les workflows sur
`main`. Un push backend lance le déploiement directement ; un push frontend
déclenche le workflow de notification puis le déploiement backend. L'exécution
est visible dans l'onglet **Actions** de `rochap-r/bulletinlab`.

Le workflow fait une sauvegarde PostgreSQL avant chaque migration, mais une
sauvegarde ne remplace pas un plan de restauration testé. Les migrations
incompatibles avec l'ancienne version et les changements applicatifs risqués
doivent toujours être revus avant le push en production.
Ces sauvegardes sont locales au VPS ; maintenir aussi une copie hors serveur
et surveiller l'espace disponible dans `/opt/backups/topscolaire`.

### Déploiement manuel / récupération après incident

Pour relancer le processus manuellement depuis le VPS :

```bash
sudo -u deploy bash -s < /opt/topscolaire/deploy/production-deploy.sh
```

Vérifier après chaque mise à jour :

```bash
curl -I https://topscolaire.com
curl -fsS https://topscolaire.com/up

docker compose --env-file .env.production logs \
  --tail=100 api api-nginx frontend queue scheduler
```

## 17. Commandes courantes

```bash
cd /opt/topscolaire

# État des services
docker compose --env-file .env.production ps

# Journaux
docker compose --env-file .env.production logs -f api
docker compose --env-file .env.production logs -f frontend
docker compose --env-file .env.production logs -f queue

# Redémarrage applicatif
docker compose --env-file .env.production restart \
  api api-nginx frontend queue scheduler

# État des migrations
docker compose --env-file .env.production \
  run --rm api php artisan migrate:status

# Relancer les seeders configurés
docker compose --env-file .env.production \
  run --rm api php artisan db:seed --force

# Vérifier Nginx
nginx -t
systemctl status nginx --no-pager

# Vérifier les certificats
certbot certificates
systemctl status certbot.timer --no-pager
```

## 18. Commandes dangereuses à éviter

Ne jamais exécuter en production sans sauvegarde et sans intention explicite :

```bash
docker compose down -v
php artisan migrate:fresh
php artisan db:wipe
```

`docker compose down -v` supprimerait notamment les volumes PostgreSQL et Redis de la stack.

Ne jamais commiter ou afficher publiquement :

- `.env.production` ;
- `APP_KEY` ;
- `DB_PASSWORD` ;
- les mots de passe des comptes seedés ;
- les clés privées de `/etc/letsencrypt`.

## 19. État final attendu

La production est considérée comme opérationnelle lorsque :

- les sept services principaux Docker sont démarrés ;
- PostgreSQL, Redis, Laravel et `api-nginx` sont `healthy` ;
- `https://topscolaire.com` répond avec un code `200` ;
- `https://www.topscolaire.com` présente un certificat valide ;
- `https://topscolaire.com/up` atteint Laravel ;
- le certificat couvre les deux noms de domaine ;
- le test `certbot renew --dry-run` réussit ;
- une sauvegarde PostgreSQL et une sauvegarde du volume `storage` existent ;
- les principaux parcours fonctionnels ont été testés dans le navigateur.
