# CI/CD de TopScolaire avec GitHub Actions et Docker

Ce document décrit la mise en place complète et l'exploitation du déploiement
continu de TopScolaire. Il complète
[`DEPLOIEMENT_PRODUCTION_TOPSCOLAIRE.md`](DEPLOIEMENT_PRODUCTION_TOPSCOLAIRE.md),
qui couvre la préparation initiale du VPS, Docker, Nginx, HTTPS et Certbot.

La configuration décrite ici correspond à la production réellement utilisée :

- site public : `https://topscolaire.com` ;
- VPS : Ubuntu 24.04 LTS, adresse `72.61.105.203` ;
- utilisateur de déploiement : `deploy` ;
- backend Laravel : `rochap-r/bulletinlab` ;
- frontend React : `rochap-r/bulletinlabfrontend` ;
- backend sur le VPS : `/opt/topscolaire` ;
- frontend sur le VPS : `/opt/topscolaire/frontend` ;
- orchestration : Docker Compose ;
- reverse proxy public : Nginx installé sur le VPS ;
- certificats : Let's Encrypt avec Certbot.

> Ne jamais placer une clé privée, un token GitHub, un mot de passe ou le
> contenu réel de `.env.production` dans Git, dans les logs ou dans cette
> documentation.

## 1. Objectif et architecture

La CI vérifie le code avant tout déploiement. La CD ne démarre que si la CI du
dépôt qui a reçu le changement réussit.

```text
Push backend sur main
        │
        ▼
CI backend : Composer + audit + Pint + Pest + build Vite
        │
        ▼
CD backend : SSH vers le VPS
        │
        ├── récupère backend/main
        ├── récupère frontend/main
        ├── construit les images Docker avec --pull
        ├── démarre/vérifie PostgreSQL et Redis
        ├── sauvegarde PostgreSQL
        ├── exécute les migrations
        ├── met à jour les conteneurs
        └── vérifie les endpoints locaux et HTTPS

Push frontend sur main
        │
        ▼
CI frontend : npm ci + lint + Vitest + build TypeScript/Vite
        │
        ▼
Déclenche le workflow backend via l'API GitHub
        │
        ▼
Même CD backend que ci-dessus
```

Le workflow backend est l'unique point d'entrée du déploiement. Le workflow
frontend ne se connecte jamais directement au VPS : il demande au dépôt
backend de lancer son workflow.

## 2. Fichiers de la chaîne CI/CD

### Dépôt backend

| Fichier | Rôle |
|---|---|
| `.github/workflows/deploy-production.yml` | CI Laravel et connexion SSH de production |
| `deploy/production-deploy.sh` | procédure exécutée sur le VPS |
| `docker-compose.yml` | définition des services de production |
| `.env.production.example` | modèle sans secrets |
| `DEPLOIEMENT_PRODUCTION_TOPSCOLAIRE.md` | installation initiale complète |

### Dépôt frontend

| Fichier | Rôle |
|---|---|
| `.github/workflows/notify-production-deploy.yml` | CI React puis déclenchement du backend |
| `.oxlintrc.json` | règles Oxlint appliquées dans la CI |
| `package-lock.json` | versions npm reproductibles avec `npm ci` |

## 3. Déclencheurs

| Événement | Backend | Frontend | Déploiement |
|---|---:|---:|---:|
| Pull request backend vers `main` | CI backend | — | Non |
| Push backend sur `main` | CI backend | — | Oui, après succès |
| Pull request frontend vers `main` | — | CI frontend | Non |
| Push frontend sur `main` | — | CI frontend | Oui, via le backend |
| `workflow_dispatch` backend | CI backend | — | Oui, après succès |

Un clic sur **Re-run failed jobs** relance le même commit. Pour tester une
correction nouvellement poussée, ouvrir le nouveau run associé au nouveau SHA,
ou lancer **Run workflow** sur la branche `main`.

## 4. Préparer l'utilisateur `deploy` sur le VPS

Ces opérations initiales sont exécutées comme `root` :

```bash
id deploy
usermod -aG docker deploy

chown -R deploy:deploy /opt/topscolaire
chmod 600 /opt/topscolaire/.env.production

install -d -o deploy -g deploy -m 700 /opt/backups/topscolaire
install -d -o deploy -g deploy -m 700 /home/deploy/.ssh

touch /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
```

Vérifier ensuite :

```bash
su - deploy

id
docker ps
test -r /opt/topscolaire/.env.production
test -w /opt/topscolaire
```

L'absence de sortie pour les deux commandes `test` signifie qu'elles ont
réussi.

> L'appartenance au groupe `docker` donne des privilèges très élevés, proches
> de `root`. Le compte `deploy` et sa clé doivent rester strictement dédiés au
> déploiement.

## 5. Les deux directions SSH à ne pas confondre

Deux accès SSH différents sont utilisés :

1. **GitHub Actions vers le VPS** : la clé privée est dans un secret GitHub et
   la clé publique dans `/home/deploy/.ssh/authorized_keys` ;
2. **VPS vers GitHub** : le compte `deploy` doit pouvoir faire `git fetch` sur
   les deux dépôts, avec une clé GitHub ou une URL HTTPS selon leur visibilité.

Tester la seconde direction sur le VPS :

```bash
git -C /opt/topscolaire remote -v
git -C /opt/topscolaire/frontend remote -v

git -C /opt/topscolaire fetch origin main
git -C /opt/topscolaire/frontend fetch origin main
```

Les remotes actuellement utilisés sont :

```text
git@github.com:rochap-r/bulletinlab.git
git@github.com:rochap-r/bulletinlabfrontend.git
```

## 6. Créer la clé GitHub Actions vers le VPS

Sur le poste Windows, dans PowerShell :

```powershell
ssh-keygen -t ed25519 `
  -C "github-actions-topscolaire" `
  -f "$env:USERPROFILE\.ssh\topscolaire-actions"
```

Afficher la clé publique :

```powershell
Get-Content "$env:USERPROFILE\.ssh\topscolaire-actions.pub"
```

Ajouter cette ligne dans le fichier suivant sur le VPS :

```text
/home/deploy/.ssh/authorized_keys
```

Plusieurs clés publiques peuvent coexister dans ce fichier, une par ligne. Ne
supprimer une ancienne clé qu'après avoir validé le nouveau pipeline.

Réappliquer les permissions comme `root` :

```bash
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

Tester depuis PowerShell Windows, et non depuis le terminal Linux du VPS :

```powershell
ssh -o IdentitiesOnly=yes `
  -i "$env:USERPROFILE\.ssh\topscolaire-actions" `
  deploy@72.61.105.203
```

L'invite attendue est :

```text
deploy@srv1307723:~$
```

Si la connexion échoue :

```powershell
ssh -vvv -o IdentitiesOnly=yes `
  -i "$env:USERPROFILE\.ssh\topscolaire-actions" `
  deploy@72.61.105.203
```

## 7. Vérifier la clé d'hôte du VPS

La clé d'hôte empêche GitHub Actions de se connecter à un serveur usurpé.

Afficher son empreinte directement sur le VPS :

```bash
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub -E sha256
```

Générer la ligne `known_hosts` directement sur le VPS :

```bash
awk '{print "72.61.105.203 "$1" "$2}' \
  /etc/ssh/ssh_host_ed25519_key.pub
```

La sortie complète ressemble à :

```text
72.61.105.203 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA...
```

Elle peut être utilisée sans hachage dans le secret GitHub. La commande
Windows `ssh-keyscan` peut échouer avec
`unsupported KEX method sntrup761x25519-sha512@openssh.com` lorsqu'elle est
fournie par un client OpenSSH trop ancien. Dans ce cas, utiliser la méthode
ci-dessus depuis le VPS.

## 8. Secrets GitHub du backend

Dans le dépôt `rochap-r/bulletinlab` :

```text
Settings → Environments → New environment → production
```

Ajouter dans **Environment secrets** :

| Nom | Valeur |
|---|---|
| `TOPSCOLAIRE_PROD_HOST` | `72.61.105.203` |
| `TOPSCOLAIRE_PROD_USER` | `deploy` |
| `TOPSCOLAIRE_PROD_SSH_KEY` | clé privée OpenSSH complète |
| `TOPSCOLAIRE_PROD_KNOWN_HOSTS` | ligne `known_hosts` vérifiée |

Afficher la clé privée sur le poste Windows uniquement :

```powershell
Get-Content "$env:USERPROFILE\.ssh\topscolaire-actions" -Raw
```

La valeur doit inclure :

```text
-----BEGIN OPENSSH PRIVATE KEY-----
...
-----END OPENSSH PRIVATE KEY-----
```

Dans les règles de l'environnement `production` :

- limiter les branches de déploiement à `main` ;
- ajouter une approbation manuelle si elle est souhaitée et disponible ;
- ne pas stocker ces secrets comme variables ordinaires.

## 9. Token de déclenchement du frontend

Le frontend doit pouvoir appeler le workflow du backend. Créer un Personal
Access Token GitHub **fine-grained** :

```text
GitHub personnel
→ Settings
→ Developer settings
→ Personal access tokens
→ Fine-grained tokens
→ Generate new token
```

Configuration :

- nom : `topscolaire-frontend-deploy` ;
- propriétaire : `rochap-r` ;
- accès : **Only select repositories** ;
- dépôt sélectionné : `rochap-r/bulletinlab` ;
- permission du dépôt **Actions: Read and write** ;
- date d'expiration définie et suivie.

Dans `rochap-r/bulletinlabfrontend` :

```text
Settings → Secrets and variables → Actions → New repository secret
```

Créer :

```text
TOPSCOLAIRE_DEPLOY_DISPATCH_TOKEN
```

Sa valeur est le token `github_pat_...`, et non une clé SSH ou un mot de passe
du VPS.

## 10. Workflow CI/CD du backend

Le fichier `.github/workflows/deploy-production.yml` contient deux jobs.

### Job `Verify Laravel backend`

Il utilise Ubuntu 24.04, PHP 8.4 et Node.js 22, puis exécute :

```bash
composer install --no-interaction --prefer-dist --no-progress
cp .env.example .env
php artisan key:generate --force
composer validate --strict
composer audit --locked --no-interaction
vendor/bin/pint --test
php artisan test --compact
npm ci
npm run build
```

Un échec de Composer, de l'audit, de Pint, d'un test Pest ou du build empêche le
job de déploiement de démarrer.

### Job `Deploy production`

Ce job :

- dépend du succès du job de vérification ;
- ne s'exécute pas sur les pull requests ;
- utilise l'environnement GitHub `production` ;
- reconstruit la clé privée dans un fichier temporaire protégé ;
- impose `StrictHostKeyChecking=yes` ;
- envoie `deploy/production-deploy.sh` au VPS via l'entrée standard SSH.

Le workflow utilise :

```yaml
concurrency:
  group: topscolaire-production
  cancel-in-progress: false
```

GitHub ne lance donc pas deux déploiements simultanément et ne supprime pas un
déploiement déjà engagé.

## 11. Workflow CI/CD du frontend

Le fichier `.github/workflows/notify-production-deploy.yml` contient deux jobs.

### Job `Verify React frontend`

```bash
npm ci
npm run lint
npm run test
npm run build
```

### Job `Request production deploy`

Après un push réussi sur `main`, il exécute :

```bash
gh workflow run deploy-production.yml \
  --repo rochap-r/bulletinlab \
  --ref main
```

La variable `GH_TOKEN` reçoit le secret
`TOPSCOLAIRE_DEPLOY_DISPATCH_TOKEN`. Sur une pull request, ce job est ignoré.

## 12. Déroulement du script exécuté sur le VPS

Le script `deploy/production-deploy.sh` utilise :

```text
APP_DIR=/opt/topscolaire
FRONTEND_DIR=/opt/topscolaire/frontend
ENV_FILE=/opt/topscolaire/.env.production
BACKUP_DIR=/opt/backups/topscolaire
```

Il réalise les opérations suivantes dans cet ordre :

1. vérifie les deux dépôts Git et `.env.production` ;
2. acquiert le verrou `/tmp/topscolaire-production-deploy.lock` ;
3. force le backend local sur `origin/main` ;
4. force le frontend local sur `origin/main` ;
5. calcule les SHA backend et frontend ;
6. crée un tag Docker de la forme `main-<backend>-<frontend>` ;
7. valide Docker Compose et le projet `topscolaire` ;
8. construit les images avec `docker compose build --pull` ;
9. démarre et attend PostgreSQL et Redis ;
10. crée un dump PostgreSQL compressé et vérifié ;
11. exécute le service de migration ;
12. démarre et attend tous les services ;
13. vérifie l'API et le frontend via `127.0.0.1` ;
14. vérifie le site public et `/up` en HTTPS ;
15. affiche les deux SHA effectivement déployés.

Les sauvegardes sont créées sous la forme :

```text
/opt/backups/topscolaire/topscolaire-YYYYMMDDTHHMMSSZ-PID.sql.gz
```

Les seeders ne sont pas exécutés automatiquement par le pipeline. Une
exécution en production doit être volontaire, revue et lancée avec `--force`.

## 13. Première activation des workflows

L'ordre sûr est :

1. préparer l'utilisateur `deploy` et les deux directions SSH ;
2. créer l'environnement et les secrets backend ;
3. créer le token et le secret frontend ;
4. pousser d'abord le workflow backend ;
5. attendre la réussite complète du backend ;
6. pousser ensuite le workflow frontend ;
7. vérifier que le frontend déclenche un nouveau run backend.

Le frontend ne doit pas être poussé en premier lors de l'installation initiale,
car il essaierait d'appeler un workflow backend qui n'existe pas encore sur
GitHub.

## 14. Utilisation quotidienne

### Déployer une modification backend

```powershell
cd C:\Users\Rochap\Herd\bulletinlab

git status
git add <fichiers>
git commit -m "type: description"
git push origin main
```

### Déployer une modification frontend

```powershell
cd C:\Users\Rochap\Herd\bulletinlab\frontend

git status
git add <fichiers>
git commit -m "type: description"
git push origin main
```

Chaque push frontend réussi déclenche ensuite un run backend complet. C'est
normal même si le commit frontend ne modifie que la CI ou le lint.

### Lancer manuellement

```text
GitHub → rochap-r/bulletinlab → Actions
→ Deploy TopScolaire production
→ Run workflow
→ Branch: main
→ Run workflow
```

## 15. Vérifications après un déploiement

Sur le VPS :

```bash
su - deploy
cd /opt/topscolaire

git rev-parse --short HEAD
git -C frontend rev-parse --short HEAD

docker compose --env-file .env.production ps
ls -lh /opt/backups/topscolaire

curl -I https://topscolaire.com
curl -fsS https://topscolaire.com/up
```

Tous les services critiques doivent être `Up` ou `healthy` :

- `api` ;
- `api-nginx` ;
- `frontend` ;
- `queue` ;
- `scheduler` ;
- `db` ;
- `redis`.

Consulter les logs :

```bash
docker compose --env-file .env.production logs \
  --tail=200 api api-nginx frontend queue scheduler db redis
```

## 16. Dépannage GitHub Actions

### Le log montre encore l'ancien code

Vérifier le SHA affiché par le run. **Re-run failed jobs** relance l'ancien
commit. Utiliser le run créé par le nouveau push ou **Run workflow** sur
`main`.

### Pint retourne le code 1

Corriger localement puis pousser :

```powershell
php vendor/bin/pint --dirty --format agent
php vendor/bin/pint --test --format agent
```

### Un test Pest échoue

Exécuter d'abord le test ciblé, puis la suite :

```powershell
php artisan test --compact --filter="nom du test"
php artisan test --compact
```

Ne jamais retirer un test de sécurité uniquement pour faire passer la CI.

### Le frontend affiche des annotations de lint

Le lint utilise Oxlint et `.oxlintrc.json`. Une règle désactivée dans ce fichier
n'apparaît plus dans GitHub Actions. Conserver au minimum
`react/rules-of-hooks` en erreur.

### `Request production deploy` retourne 401

Le token frontend est invalide ou expiré. Générer un nouveau token et remplacer
`TOPSCOLAIRE_DEPLOY_DISPATCH_TOKEN`.

### `Request production deploy` retourne 403

Le token ne possède généralement pas **Actions: Read and write** sur le dépôt
backend.

### `Request production deploy` retourne 404

Le token n'a pas accès à `rochap-r/bulletinlab`, ou le fichier
`deploy-production.yml` n'existe pas encore sur `main`.

### `Permission denied (publickey)`

Vérifier :

```bash
ls -ld /home/deploy /home/deploy/.ssh
ls -l /home/deploy/.ssh/authorized_keys
```

Puis tester depuis Windows avec `-vvv`. La clé privée du secret doit
correspondre à une clé publique présente dans `authorized_keys`.

### `Host key verification failed`

Recréer `TOPSCOLAIRE_PROD_KNOWN_HOSTS` à partir de la clé d'hôte vérifiée. Ne
pas désactiver `StrictHostKeyChecking`.

### Le VPS ne peut pas faire `git fetch`

Ce problème concerne la clé **VPS vers GitHub**, pas la clé GitHub Actions vers
le VPS. Tester séparément les deux dépôts avec `git fetch origin main`.

### Un autre déploiement est déjà en cours

Le verrou `flock` bloque correctement le second déploiement. Attendre le
premier. Ne supprimer le fichier de verrou qu'après avoir vérifié qu'aucun
processus de déploiement ne tourne encore.

### Le build ou une migration échoue

Le script s'arrête immédiatement. Examiner le job GitHub et les logs Docker :

```bash
cd /opt/topscolaire
docker compose --env-file .env.production ps
docker compose --env-file .env.production logs --tail=200
```

## 17. Retour arrière

Le pipeline crée une sauvegarde de la base avant chaque migration, mais il ne
réalise pas de rollback automatique du code ou de la base.

### Retour arrière du code recommandé

Créer un commit de réversion dans le dépôt concerné puis le pousser :

```powershell
git revert <SHA_DU_COMMIT_A_ANNULER>
git push origin main
```

Le pipeline redéploiera alors une branche `main` cohérente et traçable. Si les
deux dépôts doivent revenir ensemble, réverter les deux puis déclencher le
workflow backend.

### Base de données

Privilégier des migrations rétrocompatibles et des corrections en avant. Une
restauration PostgreSQL écrase des données et nécessite une procédure
d'incident, une fenêtre d'arrêt et une validation explicite du fichier de
sauvegarde. Ne jamais lancer automatiquement `migrate:rollback` en production.

## 18. Rotation et révocation des secrets

### Clé SSH GitHub Actions

1. générer une nouvelle paire ;
2. ajouter la nouvelle clé publique à `authorized_keys` sans retirer l'ancienne ;
3. remplacer `TOPSCOLAIRE_PROD_SSH_KEY` ;
4. exécuter un déploiement manuel réussi ;
5. retirer l'ancienne clé publique ;
6. archiver ou supprimer proprement l'ancienne clé privée.

### Token frontend

1. générer un nouveau fine-grained token ;
2. remplacer `TOPSCOLAIRE_DEPLOY_DISPATCH_TOKEN` ;
3. pousser un changement frontend ou lancer un test contrôlé ;
4. révoquer l'ancien token.

### Clé d'hôte VPS

Ne modifier `TOPSCOLAIRE_PROD_KNOWN_HOSTS` que si la clé d'hôte SSH a réellement
été régénérée ou si le VPS a été remplacé. Vérifier l'empreinte par un canal de
confiance avant toute mise à jour.

## 19. Protection des branches

Dans les deux dépôts, protéger `main` et recommander :

- passage par pull request ;
- au moins une revue si plusieurs mainteneurs travaillent sur le projet ;
- exigence du contrôle `Verify Laravel backend` côté backend ;
- exigence du contrôle `Verify React frontend` côté frontend ;
- interdiction du force-push ;
- interdiction de supprimer la branche ;
- résolution des conversations avant fusion.

Le déploiement peut continuer à se déclencher sur les merges vers `main`.

## 20. Maintenance du VPS et des images

Mettre à jour Ubuntu :

```bash
sudo apt update
sudo apt upgrade
```

Vérifier si un redémarrage est demandé :

```bash
if [ -f /var/run/reboot-required ]; then
    cat /var/run/reboot-required
fi
```

Ne pas redémarrer pendant un workflow actif. Après redémarrage :

```bash
cd /opt/topscolaire
docker compose --env-file .env.production ps
curl -fsS https://topscolaire.com/up
```

Le PHP installé directement sur Ubuntu et le PHP de l'image Docker sont deux
installations indépendantes. Le pipeline met à jour la base des images Docker
avec `docker compose build --pull`. Une mise à jour APT du PHP hôte ne met pas à
jour le conteneur API.

## 21. Checklist de validation finale

- [ ] l'utilisateur `deploy` peut exécuter `docker ps` ;
- [ ] `deploy` peut lire `.env.production` et écrire dans `/opt/topscolaire` ;
- [ ] le VPS peut faire `git fetch` dans les deux dépôts ;
- [ ] la clé GitHub Actions permet une connexion SSH au VPS ;
- [ ] la clé d'hôte SSH est vérifiée ;
- [ ] les quatre secrets backend existent dans l'environnement `production` ;
- [ ] le token frontend est limité au seul dépôt backend ;
- [ ] une pull request backend exécute la CI sans déployer ;
- [ ] une pull request frontend exécute la CI sans déployer ;
- [ ] un push backend réussi déploie les deux `main` ;
- [ ] un push frontend réussi déclenche le workflow backend ;
- [ ] une sauvegarde PostgreSQL est créée avant les migrations ;
- [ ] tous les conteneurs sont actifs ou sains ;
- [ ] `https://topscolaire.com` répond ;
- [ ] `https://topscolaire.com/up` affiche `Application up` ;
- [ ] la rotation des clés et tokens est documentée et planifiée.

## 22. Commandes de référence rapides

```bash
# État de la production
cd /opt/topscolaire
docker compose --env-file .env.production ps

# Versions Git déployées
git rev-parse --short HEAD
git -C frontend rev-parse --short HEAD

# Logs
docker compose --env-file .env.production logs --tail=200

# Santé
curl -fsS http://127.0.0.1:8081/up
curl -I http://127.0.0.1:8080
curl -fsS https://topscolaire.com/up

# Sauvegardes automatiques du pipeline
ls -lh /opt/backups/topscolaire

# Déclenchement manuel depuis GitHub
# Actions → Deploy TopScolaire production → Run workflow → main
```

