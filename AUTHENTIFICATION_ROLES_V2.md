# Authentification simplifiée par rôles — Modèle cible V2.1

> Statut : validé. Tient compte de l'état actuel du projet (API Sanctum + React SPA, mono-membership `utilisateurs.tenant_id`).

## 0. Philosophie (à retenir)

> **1 identité humaine = 1 numéro vérifié. N appartenances = N lignes `utilisateurs` reliées par `telephone_normalise`. 1 seul tenant actif à la fois (1 token = 1 tenant).**
>
> - Le **téléphone prouve qui je suis**, le **tenant borne ce que je vois**. Jamais l'inverse.
> - Le **serveur décide du périmètre** (`TenantContext` + token), le client ne l'envoie jamais.
> - On **rattache les existants, on ne duplique pas les identités** : copie du hash, propagation des mdp, pas de nouveau compte.
> - **Simplicité d'abord** : inscription courte, choix d'espace avant password, changement forcé au 1er login, zéro slug à mémoriser dans le cas nominal.

## 1. Objectif

Remplacer le parcours actuel (inscription 17 champs + login `tenant_slug + téléphone + password`) par :

1. **Inscription tenant en 1 écran court** : infos espace + admin uniquement.
2. **Construction descendante** : Admin → Écoles → Gestionnaires → Enseignants → Affectations.
3. **Enseignant multi-écoles** : `telephone_normalise` = identifiant unique ; mot de passe par défaut **à la 1re création uniquement**, réutilisé ensuite via rattachement ; portail qui liste tous ses tenants et permet de naviguer de l'un à l'autre (1 token = 1 tenant).

## 2. Rappel de l'existant (ce qui bloque)

| Point | État actuel | Fichier |
|---|---|---|
| Inscription | `POST public/tenants` exige `nom_espace, type_tenant, etablissement_nom, admin_nom/email/telephone, mot_de_passe`. 17 champs, `FailOnUnknownFields` | `app/Http/Requests/Api/V1/Onboarding/RegisterTenantRequest.php:26-42` |
| Création | TX crée `Tenant EN_ATTENTE + Etablissement + Utilisateur ADMIN_ECOLE + AuditLog`, pas de token | `app/Domains/Tenant/Services/TenantRegistrationService.php:23-77` |
| Login | `{tenant_slug, identifiant=telephone, password}`, slug manuel, 401 générique, throttle 5/min | `app/Domains/Auth/Services/AuthService.php:22-50` |
| Session | Token Sanctum `tenant:{id}`, `TenantMiddleware` vérifie ability + statut `EN_ATTENTE\|ESSAI\|ACTIF` | `app/Http/Middleware/TenantMiddleware.php:33-45` |
| Établissement | `EstablishmentMiddleware` **exige** `user.etablissement_id` non-null, rejette `tenant_id/etablissement_id` client | `app/Http/Middleware/EstablishmentMiddleware.php:31-48` |
| Rôles seed | `SUPER_ADMIN, ADMIN_ECOLE, DIRECTEUR_ETUDES, TITULAIRE, ENSEIGNANT, SECRETARIAT` (`tenant_id=null`, `system_role=true`) | `database/seeders/BulletinLabCoreSeeder.php:36-43` |
| Invitations | Par `email`, token hex 64 + `sha256`, TTL 7j, acceptation manuelle API, pas de page frontend, pas d'envoi mail/SMS | `app/Domains/Membership/Services/InvitationService.php:52-75,115-180` |
| Unicité | `UNIQUE(tenant_id, telephone)` — même numéro peut exister dans N tenants mais **sans lien entre eux** | `migrations/..._create_users_roles_permissions_tables.php:24` |
| Enseignant | `me/attributions` tenant-scopé, `AttributionCours.enseignant_id → utilisateurs` | `routes/api/v1/teachers.php`, `app/Models/AttributionCours.php:52-55` |
| Frontend | `/inscription`, `/connexion` (slug+tél+mdp), `/ecole` (admin seul via `RequireTenantAdminAuth`), pas de portail enseignant | `frontend/src/App.tsx:30-34` |

**Conséquence :** le modèle « enseignant voit tous ses tenants » est aujourd'hui **impossible** : 1 ligne `utilisateurs` = 1 tenant, 1 token = 1 tenant, pas de liaison inter-tenant, pas de login sans slug, pas de création directe sans invitation email.

## 3. Principes du modèle V2 (idée mûrie)

1. **Téléphone = identité pivot humaine**, normalisé E.164 (`+243...`), vérifié de visu à la création. Formats acceptés et ramenés au même canonique : `+243992522582`, `243992522582`, `00243992522582`, `0992522582` (zéro national → `+243...`). L'email devient optionnel sauf pour l'admin.
2. **On garde 1 ligne `utilisateurs` par tenant** (isolation, audit, FK `attributions_cours` inchangées). Le « multi-tenant » est une **liaison logique** : même `telephone_normalise` dans N tenants = N memberships affichés comme « mes espaces ».
3. **Login en 2 temps : choix AVANT password** : `telephone → liste mes espaces (sans password) → choix → password du membership choisi → token scopé tenant:{id}` (middlewares actuels inchangés). Corrige l'impasse « 1 password pour N comptes ».
4. **Création directe sans invitation email** pour les rôles internes (gestionnaires, enseignants). L'invitation email reste pour les cas externes / optionnelle.
5. **Mot de passe par défaut + changement forcé à la 1re création uniquement** (`doit_changer_mot_de_passe=true`) ; un rattaché hérite du flag source et ne reçoit jamais un 2e défaut.
6. **Portées (scopes) par rôle** : Tenant (admin) → Établissement (gestionnaire) → Attributions (enseignant). `etablissement_id` devient nullable / contextuel, pas systématiquement requis.

## 4. Hiérarchie et rôles

```
PLATEFORME (existant, inchangé)
└── SUPER_ADMIN : valide/refuse tenants (platform/*)

TENANT (espace = école ou groupe d'écoles)
├── ADMIN_TENANT (ex ADMIN_ECOLE, à renommer ou alias)
│   ├── Crée : Etablissements (Primaire, Secondaire, Maternelle, …)
│   ├── Crée : Gestionnaires par établissement
│   └── Pilote : tout le tenant (tous établissements)
│
├── GESTIONNAIRE_ECOLE (scope = 1 etablissement_id)
│   ├── PREFET (secondaire) / DIRECTEUR_PRIMAIRE (primaire) / DIRECTEUR_ETUDES (pédagogie)
│   ├── Créent : Enseignants + Titulaires + Secrétariat de leur école
│   ├── Font : classes, affectations (attributions cours), suivi encodage
│   └── Ne voient que leur établissement
│
└── ENSEIGNANT / TITULAIRE (scope = attributions, multi-tenant)
    ├── Créé par gestionnaire : nom + telephone + mdp défaut
    ├── Login portail enseignant : voit TOUS ses tenants/écoles
    └── Actions : switch tenant → mes attributions → encoder notes
```

### Matrice permissions (cible)

| Action | ADMIN_TENANT | PREFET / DIRECTEUR | DIRECTEUR_ETUDES | ENSEIGNANT | SECRETARIAT |
|---|---|---|---|---|---|
| Créer établissement | ✅ | ❌ | ❌ | ❌ | ❌ |
| Créer gestionnaire | ✅ | ❌ | ❌ | ❌ | ❌ |
| Créer enseignant | ✅ | ✅ (son école) | ✅ (son école) | ❌ | ❌ |
| Affecter cours | ✅ | ✅ | ✅ | ❌ | ❌ |
| Encoder notes | ❌ | ❌ (sauf si aussi enseignant) | ❌ | ✅ (ses attributions) | ❌ |
| Gérer élèves/classes | ✅ | ✅ (son école) | ✅ | lecture seule (ses classes) | ✅ (son école) |
| Générer bulletins/proclamations | ✅ | ✅ | ✅ | ❌ | ❌ |

Rôles existants à conserver : `ADMIN_ECOLE→ADMIN_TENANT` (alias, pas de breaking), `DIRECTEUR_ETUDES`, `ENSEIGNANT`, `TITULAIRE`, `SECRETARIAT`. **Décision :** un seul rôle générique `GESTIONNAIRE_ECOLE` + `etablissements.type_etablissement` pour afficher le label métier (Préfet / Directeur du primaire) — pas de `PREFET`/`DIRECTEUR_PRIMAIRE` en base (anti-explosion combinatoire, cumul de rôles restant possible sur la même ligne `utilisateurs`).

## 5. Parcours détaillés

### 5.1 Inscription simplifiée (public)

**Formulaire unique, 6 champs requis :**

```
nom_espace* | type_tenant* (ECOLE | GROUPE_ECOLES | ENSEIGNANT_INDIVIDUEL)
admin_nom* | admin_telephone* (E.164) | admin_email (optionnel, recommandé) | mot_de_passe* (min 6 + confirmation)
```

- `etablissement_nom` **n'est plus requis**. Si `type_tenant=ECOLE`, proposer un champ optionnel « nom de la première école » (pré-remplit la création d'établissement, sinon l'admin le créera après validation).
- Réponse `201` : `{ tenant{slug, statut: EN_ATTENTE}, administrateur }` + message « notez votre identifiant, validation sous X ».
- Écran succès : slug copiable + bouton `Se connecter` (`?espace=` conservé en fallback) + état « en attente de validation » explicite.
- Backend : `TenantRegistrationService` crée `Tenant EN_ATTENTE + Utilisateur ADMIN (etablissement_id=null)` + 1 `Etablissement` seulement si nom fourni. Utiliser les rôles système globaux (`tenant_id=null`) directement, sans les dupliquer par tenant.

### 5.2 Admin construit la structure (post-validation)

1. Admin se connecte comme tout le monde (§5.4 : `telephone → mes espaces → password`, slug en fallback uniquement).
2. Si `EN_ATTENTE` : écran dédié « validation en cours », accès lecture seule au dashboard, pas de 403 surprise.
3. Si `ACTIF` : onboarding guidé en 2 étapes :
   - **Étape A — Mes écoles** : `POST /etablissements {nom, type: PRIMAIRE|SECONDAIRE|MATERNELLE, …}` (déjà sous policy `EtablissementPolicy`, étendre à `ADMIN_TENANT`).
   - **Étape B — Gestionnaires** : `POST /utilisateurs {etablissement_id, nom, telephone, role: PREFET|DIRECTEUR|DIRECTEUR_ETUDES}` → mdp défaut généré + `doit_changer_mot_de_passe=true`. Afficher le mdp **une seule fois** + bouton copier + (plus tard : SMS).
4. `etablissement_id=null` pour l'admin : **assouplir `EstablishmentMiddleware`** — exigence seulement pour gestionnaire/enseignant mono-école, bypass pour `ADMIN_TENANT` (scope = tout le tenant). Pas de header `X-Etablissement` : un filtre `?etablissement_id` éventuel est validé serveur (∈ tenant), ce n'est jamais un contexte d'écriture.

### 5.3 Gestionnaire ajoute enseignants + affectations

- Gestionnaire login → contexte auto = son `etablissement_id` (pas de choix).
- `POST /utilisateurs {nom, postnom?, prenom?, telephone*, role=ENSEIGNANT|TITULAIRE}` (contexte = tenant + établissement du gestionnaire, jamais envoyés par le client) → crée `Utilisateur{tenant_id, etablissement_id=gestionnaire, role, mot_de_passe=hash(defaut), doit_changer=true, actif=true}`. Unicité `(tenant_id, telephone_normalise)` : si le numéro existe déjà dans ce tenant → proposer « rattacher le rôle » au lieu de 500.
- Liste affiche mdp par défaut masqué + statut « jamais connecté / doit changer ».
- Affectations : existant (`AssignCourseTeacherService`, `AttributionCoursPolicy`) inchangé, juste scoped par `etablissement via classe`.

### 5.3-bis Rattachement d'un enseignant existant (même compte) — RETENU

Principe validé : si le `telephone_normalise` existe déjà sur la plateforme, **on ne crée pas une identité neuve, on rattache**. L'enseignant garde ses identifiants et voit le nouveau tenant/école apparaître dans « Mes espaces » à la prochaine connexion (ou refresh `memberships`).

Distinguer 2 cas :

- **Même tenant, autre école (établissement)** : RIEN à créer côté `utilisateurs`. On ajoute juste une `attribution_cours` (ou `titulaire_classe`) vers la nouvelle classe. Une ligne `utilisateurs` = un tenant, pas une école.
- **Autre tenant** : créer une nouvelle ligne `utilisateurs` dans le tenant cible, mais **liée logiquement** par `telephone_normalise` identique.

Flow gestionnaire (`POST /utilisateurs/rechercher` puis `POST /utilisateurs/rattacher`) :

```
1. Gestionnaire saisit telephone* (+ nom pour double-contrôle)
2. GET /utilisateurs/rechercher?telephone=+243... (throttlé, audité)
   → cas A : inconnu → création classique (§5.3, mdp défaut + doit_changer=true)
   → cas B : existe ailleurs, pas dans mon tenant → retour masqué {nom_masqué: "Mbu** J**", existe: true}
3. Gestionnaire confirme (nom complet + rôle ENSEIGNANT/TITULAIRE)
   → POST /utilisateurs/rattacher {telephone, nom, role}
   → backend crée Utilisateur{tenant_id=mon_tenant, etablissement_id=mon_école,
       telephone + telephone_normalise identiques, nom/prénom recopiés,
       mot_de_passe = COPIE du hash existant, doit_changer = COPIE du flag source, actif=true}
       + rôles()->attach(ENSEIGNANT) + AuditLog UTILISATEUR_RATTACHE_EXISTANT
4. L'enseignant se reconnecte avec SON mot de passe habituel → son nouveau tenant apparaît.
```

Règles non négociables :

- **Copie du hash, jamais de nouveau mdp défaut** pour un rattaché : sinon on casse le « même compte » dès le 2e tenant. On copie aussi `doit_changer_mot_de_passe` source : si l'enseignant ne s'était jamais connecté (flag encore à `true`), le rattaché force également le changement au 1er login. Cas nominal : source déjà active (`doit_changer=false`) → rattaché réutilise directement le mdp habituel, aucune friction.
- **Propagation des changements de mdp** : `POST /me/password` met à jour **toutes les lignes** ayant le même `telephone_normalise` (après vérification de l'ancien sur le tenant courant). Idem pour un reset admin. Sans ça, les mdp divergent et l'illusion « même compte » meurt en 1 mois.
- **Confidentialité** : la recherche ne retourne jamais la liste des écoles de l'enseignant au gestionnaire demandeur (juste un masqué + confirmation par le nom). Throttle strict + audit `RECHERCHE_EXISTANT`.
- **Conflit d'homonyme** (même numéro, noms différents) : bloquer et exiger arbitrage (le numéro doit être vérifié de visu). Ne jamais fusionner sur le seul numéro.
- **Cible long terme** : table `comptes {telephone_normalise UNIQUE, mot_de_passe}` + `utilisateurs.compte_id`. En attendant, la copie+propagation de hash donne le même UX sans big-bang (voir §6).

### 5.4 Enseignant : portail multi-tenant (corrigé V2.1)

- Identifiant : `telephone_normalise`. Mdp initial : aléatoire 10-12 chars (jamais `Bulletin123!` ni le numéro seul — trop devinables), affiché 1x.
- **Première connexion forcée** : si `doit_changer_mot_de_passe=true` OU `derniere_connexion_at IS NULL` → `403 PASSWORD_CHANGE_REQUIRED` → écran « choisissez un nouveau mot de passe » (`POST /me/password {ancien, nouveau, confirmation}`) avant tout accès métier. Au succès : `doit_changer=false` propagé à toutes les lignes du même `telephone_normalise` + `derniere_connexion_at=now()` sur la ligne courante. Un rattaché d'un compte déjà actif n'est donc jamais bloqué.
- **Login V2 définitif (téléphone + mot de passe, sans slug) :**
  ```
  POST /auth/login {telephone, password} → 1 espace : session complète
                                        → N espaces : {memberships, select_token (5 min, ability tenant:select, usage unique)}
  POST /auth/select {tenant_id} (Bearer select) → session complète + révocation du select + audit TENANT_SWITCH
  ```
  Le tableau « Mes espaces » (`/espaces`) s'ouvre après vérification du mot de passe, sans nouveau formulaire. Le select_token ne passe aucun middleware métier (`tenant:{id}` requis). Ancien login `{slug|tenant_id,…}` gardé pour le lien `?espace=`.
- **Portail `/enseignant`** (nouveau) : carte « Mes écoles » (tous tenants où `telephone_normalise + actif`), clic → switch (nouveau token) → dashboard école : mes attributions, encoder notes (`me/attributions` existant, déjà tenant-scopé — aucun changement métier).
- **Navigation inter-tenant** : un seul token courant (`session-store`), purgé au switch. Chaque switch = `POST /auth/switch {tenant_id}` avec le Bearer courant (serveur vérifie même `telephone_normalise` + `actif` dans le tenant cible) → nouveau token `tenant:{id}`. Pas de token multi-ability, pas de map de tokens, pas de password retapé au switch.

## 6. Améliorations à apporter (par couche)

### Base de données (migrations)

- `utilisateurs` : ajouter `telephone_normalise string`, `doit_changer_mot_de_passe bool default false`, `derniere_connexion_at nullable`. Contraintes : `UNIQUE(tenant_id, telephone_normalise)` + index global non-unique sur `telephone_normalise` (pour memberships/rattachement). Backfill : normalisation E.164 de `telephone`, rapport de conflits (même numéro, noms divergents → arbitrage manuel, jamais de fusion auto).
- `etablissements` : ajouter `type_etablissement ENUM(PRIMAIRE, SECONDAIRE, MATERNELLE, MIXTE)` + `code_interne` optionnel.
- `roles` : seed `PREFET`, `DIRECTEUR_PRIMAIRE` (ou `GESTIONNAIRE_ECOLE`), alias `ADMIN_ECOLE → ADMIN_TENANT` (garder code historique + nouveau label).
- Optionnel V2 : `password_resets` par téléphone (OTP SMS) pour « mot de passe oublié » — sans ça, tout compte perdu reste bloqué.

### API

- `POST public/tenants` : rendre `etablissement_*` optionnels, ajouter `mot_de_passe_confirmation`, normaliser `admin_telephone`.
- Nouveau `POST etablissements` déjà couvert ? Vérifier policy + ajouter `type_etablissement`.
- Nouveau `POST utilisateurs` (création directe par admin/gestionnaire) avec policies : `ADMIN_TENANT` (tout tenant), `PREFET|DIRECTEUR|DIRECTEUR_ETUDES` (son établissement seul), rôle créé limité à `ENSEIGNANT|TITULAIRE|SECRETARIAT` (+ gestionnaire seulement par admin).
- Nouveau `GET /public/memberships?telephone=` (sans auth, throttlé, réponse générique) + `POST /auth/login {tenant_id OU slug, telephone, password}` (existant, étendu au `tenant_id`). Supprimé : `temp_token` / `select-tenant` (complexité inutile, voir §5.4).
- Nouveau `POST me/password` (changement forcé + volontaire), `POST me/password-oublie` (V2, OTP).
- `MeController`/`UtilisateurResource` : inclure `doit_changer_mot_de_passe`, `memberships_count`, `etablissement{nom,type}`.
- Assouplir `EstablishmentMiddleware` : bypass pour `ADMIN_TENANT` (scope tenant), exigence pour gestionnaires, optionnel pour enseignants (scope attributions).

### Frontend

- `/inscription` : passer de 3 cards / 17 champs à 2 cards / 6-7 champs + lien « ajouter mon école maintenant (optionnel) ».
- `/connexion` en 2 temps : 1) `Téléphone → Mes espaces` (liste, slug en fallback « avancé »), 2) `Mot de passe` du membership choisi + écran « Choisir mon espace » si >1.
- Nouveau `/enseignant` : mes écoles → dashboard école (réutiliser `MyCourseAssignment*`).
- `/ecole` : tableau de bord post-validation avec écoles visibles immédiatement, état `EN_ATTENTE` explicite et actions contextuelles en dialogue (créer école → ajouter gestionnaire → inviter enseignants), sans formulaires permanents.
- Unifier `auth-store` / `tenant-auth-store` → `session-store` + `memberships-store` (fini le double `auth_token`).

### Contrat d'isolation tenant — règle d'or (AJOUT)

> **Toute opération métier lit et écrit dans UN seul tenant.** Le tenant vient du serveur (`TenantContext` + token `tenant:{id}`), jamais du client. Le rattachement multi-tenant n'y déroge pas : seules 4 opérations listées ci-dessous sont cross-tenant, tout le reste reste strictement scopé.

Garanties existantes à ne jamais affaiblir :

1. **Token mono-tenant** : `TenantMiddleware` exige l'ability `tenant:{user.tenant_id}` (`app/Http/Middleware/TenantMiddleware.php:33-38`). Un switch d'espace = un nouveau token. Interdit : token multi-ability, `tenant_id` en body/query.
2. **Scope global** : `TenantScope` filtre `WHERE tenant_id = contexte` et `HasTenant` force `tenant_id` au `creating/updating` (`app/Models/Concerns/HasTenant.php:14-28`, `app/Models/Scopes/TenantScope.php:16`). Tout modèle métier (`Etablissement`, `AttributionCours`, `Bulletin`, `Note`, …) garde ce trait. Toute requête `withoutGlobalScopes()` doit être justifiée + revue.
3. **Contexte client rejeté** : `EstablishmentMiddleware::rejectClientManagedContext` refuse `tenant_id/etablissement_id` envoyés par le client (`app/Http/Middleware/EstablishmentMiddleware.php:56-69`). Étendre au header `X-Tenant` si introduit.
4. **Policies redondantes** : chaque policy revérifie `ressource.tenant_id === acteur.tenant_id` (pattern `InvitationPolicy::revoke:31-33`). Ne pas se reposer sur le seul scope global.
5. **Frontend** : une instance axios par tenant courant, token précédent purgé de la mémoire au switch (pas de map de tokens réutilisables entre tenants sauf stockage chiffré + envoi strict par tenant).

Seules opérations cross-tenant autorisées (allowlist fermée) :

| Opération | Lit quoi hors tenant | Écrit quoi hors tenant | Garde-fous |
|---|---|---|---|
| `GET /public/memberships?telephone=` | `utilisateurs{tenant_id, nom_espace, rôle}` masqués | rien | throttle 5/min, réponse générique si inconnu, aucune donnée métier (notes, bulletins, élèves) |
| `POST /utilisateurs/rechercher+rattacher` | existence + nom masqué | **1 ligne** `utilisateurs` dans MON tenant (copie hash+flag) | acteur authentifié + policy gestionnaire, confirmation nom complet (homonyme → 422), audit `UTILISATEUR_RATTACHE_EXISTANT` ; même-espace → attache le rôle manquant (`UTILISATEUR_ROLE_RATTACHE`) |
| `POST /auth/switch {tenant_id}` (Bearer tenant courant) | appartenance cible du même `telephone_normalise` | 1 token `tenant:{id}` cible | même numéro + `actif` des 2 côtés, audit `TENANT_SWITCH`, ancien token conservé ou révoqué selon choix logout |
| Propagation `POST /me/password` | vérifie ancien mdp sur tenant courant | `mot_de_passe + doit_changer` sur les lignes au même `telephone_normalise` | après vérification mdp courant, `withoutGlobalScopes()` explicite + `where telephone_normalise`, audit 1 ligne par tenant touché, jamais d'autres colonnes |

Interdit : lister les élèves/notes/bulletins d'un autre tenant via le rattachement, copier des attributions entre tenants, exposer `token_hash`, logger un mdp.

Tests d'isolation obligatoires (à ajouter) : `GET /me/attributions` avec token du tenant A ne voit jamais le tenant B (404, pas 403 avec fuite d'existence) ; `POST /utilisateurs/rattacher` avec `tenant_id` forgé → 422 ; test d'arch : tout modèle du domaine métier utilise `HasTenant` sauf allowlist (`Tenant`, `Compte`, `Plan`…).

### Sécurité / UX non négociable

- Normalisation téléphone : `PhoneNumberNormalizer` (séparateurs, `00→+`, `0→+243`, `243→+243`) avant validation E.164, côté `FormRequest` + hook modèle + frontend `inputmode=tel`.
- Mdp défaut : min 10 chars aléatoires, hashés, affichés 1x, jamais loggés ; `doit_changer` bloque tout sauf `POST me/password`.
- Anti-énumération : `GET memberships` ne révèle pas si le numéro existe (réponse générique + délai constant) ; login garde 401 générique + dummy-hash existant.
- Throttle : `5/min` login, `10/min` memberships, `10/min` switch, `3/j` reset.
- Audit : `UTILISATEUR_CREE_DIRECT`, `MDP_DEFAUT_GENERE`, `MDP_CHANGE_FORCE`, `TENANT_SWITCH` dans `audit_logs` (pattern existant `TENANT_INSCRIT_EN_ATTENTE`, `INVITATION_*`).

## 7. Plan de mise en œuvre

### Suivi d'implémentation

| Phase | État | Revue et vérification |
|---|---|---|
| P0 | ✅ Terminée le 13/10/2026 | Migration additive avec backfill E.164 bloquant en cas de donnée invalide ou de conflit, index global et unicité par tenant. Normalisation centralisée non assignable par le client, casts du modèle et factory E.164. Revue sécurité effectuée. `Pint` appliqué ; 60 tests ciblés passent (220 assertions). |
| P1 | ✅ Terminée le 13/10/2026 | Inscription API ramenée aux informations espace + admin, e-mail et première école optionnels, confirmation du mot de passe, admin global avec `etablissement_id=null`, ressource nullable et bypass établissement limité aux admins tenant sans établissement. UI ramenée à 2 cartes et 6 champs requis avec école en divulgation progressive. Revue de compatibilité des admins historiques effectuée. `Pint` passe ; 20 tests backend (73 assertions), 2 tests UI et le build TypeScript/Vite passent ; détecteur UI sans anomalie. |
| P2 | ✅ Terminée le 14/10/2026 | Migration établissements (`type_etablissement`, `code_interne`), rôles de gestion, création directe sécurisée des gestionnaires/enseignants, mot de passe temporaire affiché une fois, forçage du changement initial et audits sans secret. L’espace tenant a été revu en tableau de bord opérationnel : liste des écoles prioritaire, configuration vide compacte et formulaires ouverts à la demande. Revue d’isolation, policies, validation et UX effectuée. `Pint` passe ; 401 tests backend (17 018 assertions), 196 tests frontend et le build TypeScript/Vite passent. |
| P3 | ✅ Terminée le 14/10/2026 | Login global et sélection d’espace, rattachement inter-tenant, switch sécurisé, portail enseignant et tableau « Mes espaces ». La page `/enseignant` est maintenant organisée par classes : union des classes enseignées et des titulariats, cours imbriqués, année scolaire active et changement d’espace. Revue UX Impeccable, isolation tenant/établissement et états chargement/erreur/vide effectués. `Pint` passe ; 403 tests backend (17 029 assertions), 196 tests frontend et le build TypeScript/Vite passent. |
| P4 | ⏳ À faire | — |

| Phase | Contenu | État |
|---|---|---|
| P0 | Normalisation téléphone (`PhoneNumberNormalizer`, `telephone_normalise` + `UNIQUE(tenant_id, telephone_normalise)`, backfill avec détection de conflits) + `doit_changer_mot_de_passe` + `derniere_connexion_at` + mdp défaut 12 chars affiché 1x | ✅ implémenté + testé (`PhoneNumberNormalizerTest`, migration `2026_10_13_000001`) |
| P1 | Inscription simplifiée (établissement optionnel, `admin_email` optionnel, `admin_telephone` E.164, mdp min 6 + `confirmed`, admin `etablissement_id=null`, rôles système globaux) + bypass `EstablishmentMiddleware` pour `ADMIN_TENANT` + `POST /etablissements` scopé admin | ✅ implémenté + testé (`TenantRegistrationTest` 13 tests) |
| P2a | Création directe gestionnaires/enseignants (`POST /utilisateurs`, `DirectUtilisateurService`, `UtilisateurPolicy`, `etablissement_id` verrouillé au périmètre de l'acteur, audit sans mdp) + contrat d'isolation (tests `TenantIsolationTest`, `DirectUtilisateurManagementTest`) | ✅ implémenté + testé |
| P2b | Forçage 1re connexion (`RequirePasswordChange` → `403 PASSWORD_CHANGE_REQUIRED`, alias `require.password.change`, allowlist `me/logout/password`) + `POST /me/password` (min 6 + `confirmed` + `different:actuel`, throttle `me.password` 5/min, propagation hash+flag à toutes les lignes du même `telephone_normalise`, révocation des autres sessions, audit `MDP_CHANGE_FORCE` par tenant) + `derniere_connexion_at` au login + normalisation de l'identifiant au login (+ clé throttle sur identifiant normalisé) + flag exposé dans `UtilisateurResource` | ✅ implémenté + testé (`PasswordChangeEnforcementTest` 4 tests) |
| P3 | Rattachement existant + `GET /public/memberships` + `POST /auth/switch` + `POST /auth/select` (jeton usage unique) + portail `/enseignant` organisé par classes enseignées/titulariats + `/connexion` unique + dashboard `/espaces` | ✅ implémenté + testé (`GlobalLoginTest`, `MultiTenantAccessTest`, `TeacherClassDashboardTest`, couverture d’isolation dans `AcademicMutationBoundaryTest`, vitest 196 tests) |
| P4 | Reset OTP + envoi SMS/mail invitations + révocation multi-session | ⏳ |

> Correctif de review P3 (2026-10-14) : la contrainte d’eager loading de `attributionsCours` reçoit une relation `HasMany`, pas uniquement un `Builder` — type corrigé après reproduction sur le compte `+243000000003`. Vérification HTTP réelle via le proxy Vite : 6 classes retournées et 18 cours dans la première classe. L’interface ne mélange plus l’état d’erreur avec l’état vide et retente une fois les erreurs transitoires. Suite frontend : 196 tests et build réussis ; détecteur Impeccable sans anomalie.

> Extension de review P3 (2026-10-15) : l’espace école permet désormais au préfet ou directeur d’études d’organiser en une seule opération les cycles et options réellement ouverts (éducation de base, pédagogie, scientifique, électricité, etc.). Le modal présente le catalogue complet autorisé par le type d’établissement, accepte une sélection multiple et crée transactionnellement tous les niveaux correspondants avec le bon modèle de bulletin, sans doublonner les classes déjà présentes. À la réouverture, les options déjà enregistrées pour l’année et la parallèle choisies sont précochées, verrouillées et identifiées par « Déjà ajoutée » ; seules les nouvelles sélections sont envoyées. L’année scolaire courante est désormais résolue globalement par `GET /annees-scolaires/courante`, affichée dans tous les en-têtes opérationnels (administration, école, enseignant) et imposée en lecture seule dans le modal : aucun choix local divergent n’est possible. La liste « Classes et options » expose ensuite les liaisons cycle, niveau et option en groupes lisibles. Autorisation `manageAcademicStructure`, validation stricte, isolation tenant/établissement et idempotence revues. `Pint` passe ; 445 tests backend (17 194 assertions), 238 tests frontend et le build TypeScript/Vite passent ; détecteur Impeccable sans anomalie.

> Normalisation des noms de classes (2026-10-15) : pour une classe avec option, le cycle « Humanités » n’est plus répété dans le nom généré. Exemple contractuel : `3ème Humanités` + `Commerciale & Gestion` + parallèle `A` produit exactement `3ème Commerciale & Gestion A`. Une migration corrective, limitée aux anciens noms correspondant exactement au format généré, est prête pour les données existantes. Review : test Pest dédié inclus ; 445 tests backend (17 195 assertions) et Pint passent.

> Note de review P0/P1 (2026-10-13) : `AuthService::login` ne forçait pas le changement et ne posait pas `derniere_connexion_at` — corrigé en P2b. Reste à surveiller : l'ancien `UNIQUE(tenant_id, telephone)` coexiste avec le nouveau sur `telephone_normalise` (inoffensif puisque `telephone` est désormais stocké normalisé) ; `TenantRegistrationService` assigne `ADMIN_ECOLE` (alias conservé de `ADMIN_TENANT`, sans breaking).

> Note de review complète (2026-10-13, 4 rapports) — corrections appliquées :
> - Rôle onboarding corrigé en `ADMIN_TENANT` (le service créait `ADMIN_ECOLE` avec le libellé d'`ADMIN_TENANT`) ; hash mdp sorti de la transaction ; fallback slug `espace` si nom non sluggifiable ; rejet 422 des champs `etablissement_*` orphelins ; `etablissement_type_etablissement` optionnel à l'inscription.
> - `manageAcademicStructure` : bypass admin tenant (même tenant) + `GESTIONNAIRE_ECOLE` ajouté aux rôles de gestion — sans quoi l'admin ne pouvait rien faire d'académique et le gestionnaire ne pouvait ni classes ni affectations, contrairement à la matrice §4.
> - `InvitationPolicy` : bypass admin tenant (création/liste/révocation scopée tenant) — l'ancien flux était mort pour l'admin sans établissement.
> - `DirectUtilisateurService` : `etablissement_id` de l'admin respecté (plus d'écrasement silencieux), whitelist de rôles côté service (422 au lieu de 404/500), race doublon → 422 métier, audit avec `ip_address`/`user_agent`.
> - `POST /me/password` : `different:actuel`, throttle dédié, révocation des autres sessions sœurs.
> - Conventions : `FailOnUnknownFields` sur login + `POST /etablissements`, `code_interne` vide→null, alias renommé `require.password.change`, `noContent()` platform, normalizer élargi (`/`, NBSP).
> - Arbitrages conservés (pas de changement) : propagation mdp inter-tenant = décision produit « même compte » (allowlist §6, + révocation sessions) ; ordre `RequirePasswordChange` avant `tenant.validated` (laisse changer le mdp pendant `EN_ATTENTE`) ; `me/logout` sans middleware `tenant` (tokens toujours créés avec ability) ; throttle login normalisé ; seeder démo avec `doit_changer=true`.
> - Limites connues reportées P3/P4 : enseignant multi-établissements intra-tenant (policy `AttributionCours` exige `etablissement_id` = contexte) ; reset OTP ; quotas `limite_etablissements` non branchés ; `PeriodeLockService` non scopé tenant.

## 8. Critères d'acceptation

- [ ] Inscription en < 2 min, 6 champs, sans connaissance préalable d'un slug.
- [ ] Admin sans établissement peut se connecter et créer 2 écoles (primaire + secondaire) + 1 préfet chacune.
- [ ] Préfet ne voit que son école, crée 3 enseignants avec mdp défaut, fait 1 affectation.
- [ ] Enseignant rattaché dans 2 tenants (même téléphone, même mdp) voit « Mes 2 écoles » et passe de l'une à l'autre via switch sans nouveau compte ni nouveau mdp.
- [ ] Première connexion enseignant impose le changement de mdp, sinon 403 explicite.
- [ ] Aucune régression : tokens `tenant:{id}`, `me/attributions`, encodage notes, proclamations.

## 9. Avantage de cette approche

**Le meilleur des deux mondes : UX de compte unique, sécurité de silos étanches.**

1. **Zéro mémorisation** : plus de slug à recopier, inscription 6 champs, login téléphone → espaces → password. Un enseignant dupliqué sur 3 écoles n'a qu'un seul mot de passe à connaître.
2. **Zéro big-bang** : pas de table `comptes` globale, pas de migration FK (`attributions_cours`, audit, `TenantScope` intacts). La liaison logique + copie/propagation de hash donne l'illusion du compte unique pour un coût P0-P3 additif.
3. **Isolation prouvable** : 1 token = 1 tenant, allowlist cross-tenant fermée à 3 opérations, tests d'isolation dédiés. Le rattachement ne fait fuir aucune donnée métier — argument clé face aux écoles qui craignent le partage entre établissements concurrents.
4. **Opérable sans SMS le jour 1** : création directe + mdp affiché 1x + changement forcé 72h fonctionnent sur papier/WhatsApp ; l'OTP SMS se branche plus tard sans changer le modèle.
5. **Évolutif** : quand le volume le justifiera, la table `comptes {telephone_normalise UNIQUE}` remplacera la propagation de hash sans changer l'UX — les endpoints `memberships` + `login {tenant_id}` restent identiques.

## 10. Comptes et données de test (interface)

Seeder : `database/seeders/AuthDemoSeeder.php` (réservé `local`/`testing`, appelé par `DatabaseSeeder`).
Commandes : `php artisan migrate --force` puis `php artisan db:seed --class='Database\Seeders\AuthDemoSeeder' --force`.
Interface : `npm run dev` dans `frontend/` → http://localhost:5173/connexion (proxy `/api` → `https://bulletinlab.test`).

| Compte | Téléphone | Mot de passe | Espaces (slug) | Rôle |
|---|---|---|---|---|
| Enseignant multi-tenant | `+243000000003` | `password123` | École Test (`ecole-test`) + École Démo B (`ecole-demo-b`) | `ENSEIGNANT` (même hash des 2 côtés) |
| Nouveau (1re connexion) | `+243000000004` | `Temporaire-1234` | École Démo B | `ENSEIGNANT`, `doit_changer=true` → forcé vers `/mot-de-passe` |
| Admin Démo B | `+243000000005` | `password123` | École Démo B | `ADMIN_TENANT` (crée écoles/gestionnaires, rattache) |
| Admin Test | `+243000000002` | `password123` | École Test | `ADMIN_ECOLE` (espace historique) |

Parcours à tester : ① `+243000000003` → tableau « Mes espaces » (2 espaces) → ouvrir → switch sans reconnexion ; ② admin B rattache un existant (`POST /utilisateurs/rattacher`, nom `Prof`) → l'espace apparaît côté enseignant ; ③ `+243000000004` → changement forcé ; ④ inscription publique → `EN_ATTENTE` → validation plateforme → construction (écoles → gestionnaires → enseignants).
