# Analyse de faisabilité — Espace Parents Multi-Écoles + Détail des notes par période

**Projet :** BulletinLab / TopScolaire  
**Date :** 27 octobre 2026
**Version :** 2.1
**Statut :** Phases 1 à 6 implémentées — espace parents multi-écoles, détail pédagogique des notes, interface enseignant des évaluations, audit, feature flags, PWA offline et monitoring ; seul le guide utilisateur parent (optionnel) reste à rédiger

---

## 1. Contexte et objectifs

Le dirigeant de l'école a soulevé deux attentes majeures :

1. **Espace Parents multi-écoles** : un parent (ou tuteur) doit pouvoir se connecter avec son numéro de téléphone et voir **tous ses enfants**, qu'ils soient dans l'école A, l'école B ou n'importe quelle autre école utilisant la plateforme. Il ne doit pas créer un compte par école.

2. **Détail des notes par période** : pour le secondaire (et potentiellement le primaire), une période n'est pas une note unique. Elle est constituée de plusieurs composantes saisies par l'enseignant : interrogations, exercices, devoirs, cotation des cahiers. Le système doit agréger ces composantes pour former les points de la période, et les parents doivent pouvoir consulter ce détail (ex. : "interrogation ratée, devoir réussi").

Ce document analyse la faisabilité technique, propose une architecture, un plan de mise en œuvre par phases, et intègre une **dimension industrielle** (bonnes pratiques SaaS multi-tenant, sécurité, audit, observabilité).

### Décisions d’architecture confirmées

La première analyse confirmait correctement la faisabilité, mais trois points devaient être durcis avant l’implémentation :

1. `Parent` ne peut pas être le nom d’un modèle PHP, car `parent` est un mot réservé. Le domaine utilise désormais **`Responsable`**.
2. Une identité multi-écoles ne doit pas porter de `tenant_id`. Le compte `Responsable` est global ; seul son lien avec un élève est tenant-scopé.
3. Selon la décision produit du 27/10/2026, `eleves.telephone_parent` est la source de rattachement automatique. Un même numéro normalisé regroupe donc les enfants de toutes les écoles TopScolaire.
4. Le compte est initialisé avec le mot de passe temporaire `123456`. La première session ne peut consulter aucun enfant tant que le parent ne l’a pas remplacé par un mot de passe d’au moins 6 caractères.
5. Ce compromis simplifie fortement l’accès, mais la connaissance du numéro et du mot de passe commun suffit à la première connexion. Le produit doit donc conserver le throttling, l’audit, la révocation immédiate de l’ancien jeton et prévoir une authentification plus forte à terme.

Le périmètre pédagogique de ce document reste le **secondaire**. Le primaire sera spécifié séparément avant de coder ses règles de calcul.

---

## 2. Analyse de l'existant (état actuel)

### 2.1 Ce qui est déjà en place (socle réutilisable)

| Élément | Table / Fichier | État | Relevance pour le nouveau module |
|---------|----------------|------|----------------------------------|
| Infos parent sur l'élève | `eleves.nom_pere`, `eleves.nom_mere`, `eleves.telephone_parent` | ✅ Existant | Socle de donnée pour relier un parent à ses enfants |
| Isolation multi-tenant | `HasTenant` trait + `TenantScope` | ✅ Existant | Chaque donnée est portée par un `tenant_id` |
| Authentification staff | `Utilisateur` + Sanctum + rôles Spatie | ✅ Existant | Pattern à réutiliser pour les parents (option A) ou compte dédié (option B) |
| Multi-tenant par téléphone | `telephone_normalise` + rattachement inter-tenant | ✅ Documenté (AUTHENTIFICATION_ROLES_V2.md §6) | Pattern déjà validé pour les enseignants, transposable aux parents |
| Bulletins et notes | `bulletins`, `notes`, `modele_periodes`, `modele_nodes` | ✅ Existant | Une note = 1 valeur par (bulletin, node, periode) |
| API REST versionnée | `routes/api/v1/*` | ✅ Existant | Endpoints parents implémentés sous `routes/api/v1/responsables.php` |
| Frontend React + TypeScript | `frontend/src/` | ✅ Existant | Portail `/parents/*` implémenté |

### 2.2 Ce qui manque (écarts à combler)

| Écran / Fonction | Statut |
|------------------|--------|
| Modèle `Responsable` global dédié | ✅ Ajouté sans `tenant_id` |
| Liaison riche `lien_responsable_eleves` | ✅ Fondations ajoutées le 27/10/2026 |
| Rôle `PARENT` staff | Non requis : le portail utilise un modèle et un contexte Sanctum séparés |
| Authentification parent par téléphone + mot de passe initial | ✅ Backend ajouté le 27/10/2026 |
| Endpoints API profil/enfants/session | ✅ Backend ajouté sous `/api/v1/responsables/*` |
| Pages frontend `/parents/*` | ✅ Connexion, changement initial, tableau de bord et fiche enfant ajoutés |
| Garde d'authentification parent | ✅ Sanctum + capacité `context:responsable` + changement initial obligatoire |
| Composantes de note (interrogation, devoir, exercice, cahier) | ✅ Tables `types_evaluations`, `evaluations`, `resultats_evaluations`, modèles, enums et seeder (itération 4.1) |
| Agrégation des composantes vers la période | ✅ `EvaluationCalculationService` (compute + forInscription, DTO `EvaluationAggregate`, 4 tests unitaires) — itération 4.2 |
| Vue détail des notes pour les parents | ✅ Endpoint parent et affichage dans la fiche enfant (itérations 4.4 et 5.2) |

---

## 3. Dimension 1 — Espace Parents Multi-Écoles

### 3.1 Principe architectural

Le projet applique déjà le pattern **"1 identité humaine = 1 numéro de téléphone vérifié, N appartenances"** (voir `AUTHENTIFICATION_ROLES_V2.md` §3). Pour les responsables, deux options s'offrent à nous :

| Option | Description | Recommandation |
|--------|-------------|----------------|
| **A — Compte `Responsable` global dédié** | Table `responsables` sans `tenant_id`, liens tenant-scopés avec les élèves, session globale | ✅ Recommandée. Propre, isolée du staff, réellement multi-écoles. |
| **B — Réutiliser `Utilisateur` + rôle `PARENT`** | Un parent est un `Utilisateur` avec rôle `PARENT`, liaison via `telephone_parent` sur `Eleve` | ❌ Déconseillée. Mélange staff/parents, permissions ambiguës, moins sécurisé. |

**Architecture retenue : Option A (identité `Responsable` globale).**

### 3.2 Modèle de données proposé

#### Table `responsables`

```sql
responsables (
    id BIGINT PK,
    public_id ULID UNIQUE,
    telephone VARCHAR(30),
    telephone_normalise VARCHAR(16) UNIQUE,            -- E.164 (+243...), identité pivot globale
    nom VARCHAR(100),
    postnom VARCHAR(100) NULL,
    prenom VARCHAR(100),
    email VARCHAR(150) NULL,
    adresse TEXT NULL,
    mot_de_passe VARCHAR(255) NULL,                    -- hash uniquement
    doit_changer_mot_de_passe BOOLEAN DEFAULT TRUE,
    actif BOOLEAN DEFAULT TRUE,
    derniere_connexion_at DATETIME NULL,
    telephone_verifie_at DATETIME NULL,
    created_at, updated_at
)
```

Le compte ne porte volontairement aucun `tenant_id`. Il peut donc agréger les liens vérifiés provenant de plusieurs écoles sans dupliquer mot de passe et identité.

#### Table `lien_responsable_eleves` (liaison riche)

```sql
lien_responsable_eleves (
    id BIGINT PK,
    public_id ULID UNIQUE,
    responsable_id BIGINT FK -> responsables (cascade),
    eleve_id BIGINT FK -> eleves (cascade),
    tenant_id BIGINT FK -> tenants (cascade),          -- tenant de l'élève (isolation)
    type_lien VARCHAR(20) DEFAULT 'TUTEUR',             -- PERE, MERE, TUTEUR, AUTRE
    statut VARCHAR(20) DEFAULT 'EN_ATTENTE',            -- EN_ATTENTE, VERIFIE, REVOQUE
    principal BOOLEAN DEFAULT FALSE,
    peut_consulter_notes BOOLEAN DEFAULT TRUE,
    peut_consulter_presences BOOLEAN DEFAULT TRUE,
    recevoir_notifications BOOLEAN DEFAULT TRUE,
    declare_par BIGINT NULL,
    verifie_par BIGINT NULL,
    verifie_at DATETIME NULL,
    methode_verification VARCHAR(40) NULL,
    revoque_par BIGINT NULL,
    revoque_at DATETIME NULL,
    motif_revocation TEXT NULL,
    created_at, updated_at,
    UNIQUE (responsable_id, eleve_id),
    FOREIGN KEY (tenant_id, eleve_id) REFERENCES eleves(tenant_id, id)
)
```

**Règle d'isolation :** le responsable est global, mais chaque lien appartient obligatoirement au même tenant que l'élève. La contrainte composite le garantit aussi au niveau de la base. Seul un lien `VERIFIE`, non révoqué et autorisé pour la ressource demandée ouvre un accès.

### 3.3 Flux d'authentification et de rattachement

#### Cas A : Parent déjà connu dans une école
L'élève a déjà `telephone_parent` renseigné lors de son inscription.

1. Le numéro est normalisé au format E.164 dans `eleves.telephone_parent_normalise`.
2. Le système retrouve ou crée l’identité globale `responsables` et initialise son mot de passe avec le hash de `123456`.
3. Un lien tenant-scopé est créé avec la méthode `DOSSIER_ELEVE` et le statut `VERIFIE`.
4. Si le numéro du dossier élève change, l’ancien lien automatique est révoqué et le nouveau est créé.

#### Cas B : Parent avec enfants dans plusieurs écoles (multi-tenant)
Le parent se connecte avec son numéro de téléphone.

1. `POST /api/v1/responsables/auth/login` reçoit `telephone` et `mot_de_passe`.
2. À la première connexion, le parent utilise `123456` et reçoit un jeton limité au changement du mot de passe.
3. `POST /api/v1/responsables/me/password-initial` exige un nouveau mot de passe confirmé d’au moins 6 caractères, différent de `123456`.
4. Tous les anciens jetons sont révoqués et une nouvelle session de lecture est émise.
5. `GET /api/v1/responsables/enfants` retourne les enfants autorisés avec leur école et leur classe, tous tenants confondus.
6. Chaque futur endpoint métier devra revalider le lien, le tenant et la permission demandée.

#### Cas C : Parent qui s'inscrit lui-même (portail public)
L'inscription libre et la revendication manuelle d'un enfant sont reportées. Le rattachement est alimenté uniquement par le numéro saisi dans le dossier scolaire par une école TopScolaire.

### 3.4 Isolation et sécurité (règle d'or du projet)

> Toute opération métier lit et écrit dans **un seul tenant**. Le tenant vient du serveur, jamais du client.

Pour les parents multi-écoles, on introduit une **exception contrôlée** (allowlist fermée, déjà documentée dans `AUTHENTIFICATION_ROLES_V2.md` §6) :

| Opération | Lit hors tenant | Écrit hors tenant | Garde-fous |
|-----------|-----------------|-------------------|------------|
| `POST /api/v1/responsables/auth/login` | Élèves correspondant au téléphone + identité globale | Synchronisation des liens et session | Réponse générique, double throttle, mot de passe hashé |
| `POST /api/v1/responsables/me/password-initial` | Identité courante | Nouveau hash + révocation des anciens jetons | Minimum 6 caractères, confirmation, ancien mot de passe interdit, audit |
| `GET /api/v1/responsables/enfants` | Liens vérifiés du responsable authentifié | Rien | Pas de `tenant_id` client, eager loading borné, identifiants publics uniquement |
| `GET /api/v1/responsables/enfants/{public_id}/...` | Une ressource scolaire liée | Rien | À réaliser : policy objet responsable + lien + tenant + permission |

**Interdit :** retourner les enfants avant authentification et changement du mot de passe initial, lister les élèves d’une école sans lien, modifier les notes depuis l’espace parent ou accepter un `tenant_id` fourni par le client.

---

## 4. Dimension 2 — Détail des notes par période

### 4.1 Problème métier

Aujourd'hui, la table `notes` stocke **une seule valeur** par couple `(bulletin_id, node_id, periode_id)`. L'enseignant saisit une note finale pour la période.

Le dirigeant demande que la période soit constituée de plusieurs **composantes d'évaluation** :
- Interrogation (quiz oral/écrit)
- Exercice (travail en classe)
- Devoir (travail à la maison)
- Cotation des cahiers (tenue du cahier, exercices faits)

Le système doit :
1. Permettre à l'enseignant de saisir ces composantes.
2. Calculer automatiquement la note de période (somme pondérée ou moyenne selon barème de l'école).
3. Afficher le détail aux parents (ex. : "Interrogation : 8/10, Devoir : 12/20, Cahier : 15/15 → Période : 35/45").

### 4.2 Modèle de données proposé

#### Table `types_evaluations`

Les catégories doivent être configurables et non figées dans un enum SQL. Les codes initiaux sont `INTERROGATION`, `EXERCICE`, `DEVOIR`, `CAHIER` et `AUTRE`, mais une école pourra ajouter « laboratoire » ou « projet ».

#### Table `evaluations` (activité commune à une classe)

```sql
evaluations (
    id BIGINT PK,
    tenant_id BIGINT FK -> tenants,
    etablissement_id BIGINT FK -> etablissements,
    annee_scolaire_id BIGINT FK -> annees_scolaires,
    classe_id BIGINT FK -> classes,
    attribution_cours_id BIGINT FK -> attributions_cours,
    node_id BIGINT FK -> modele_nodes,
    periode_id BIGINT FK -> modele_periodes,    -- T1, T2, examen...
    type_evaluation_id BIGINT FK -> types_evaluations,
    libelle VARCHAR(200),                       -- ex. "Interrogation n°1 - Géo"
    maxima DECIMAL(8,2),                        -- note max possible (ex. 10, 20)
    coefficient DECIMAL(6,2) DEFAULT 1,         -- poids dans le calcul de la période
    date_evaluation DATE,
    statut VARCHAR(20),                         -- BROUILLON, PUBLIEE, VERROUILLEE, ANNULEE
    visible_parents_at DATETIME NULL,
    cree_par BIGINT FK -> utilisateurs,
    lock_version INTEGER DEFAULT 1,
    created_at, updated_at
)
```

Une évaluation est créée une seule fois pour toute la classe. Elle ne référence donc pas directement un bulletin individuel.

#### Table `resultats_evaluations` (résultat individuel)

```sql
resultats_evaluations (
    id BIGINT PK,
    tenant_id BIGINT FK -> tenants,
    evaluation_id BIGINT FK -> evaluations,
    inscription_id BIGINT FK -> inscriptions,
    eleve_id BIGINT FK -> eleves,
    statut_resultat VARCHAR(20),                -- SAISIE, ABSENTE, DISPENSEE, ANNULEE
    valeur DECIMAL(8,2) NULL,
    commentaire_parent TEXT NULL,
    commentaire_interne TEXT NULL,
    saisi_par BIGINT FK -> utilisateurs,
    date_saisie DATETIME NULL,
    lock_version INTEGER DEFAULT 1,
    created_at, updated_at,
    UNIQUE (evaluation_id, inscription_id)
)
```

Séparer `evaluations` et `resultats_evaluations` évite de dupliquer le libellé, la date, le maximum et le coefficient pour chaque enfant. Cette structure correspond aussi au vocabulaire industriel catégories / activités (`LineItems`) / résultats de [OneRoster Gradebook](https://standards.1edtech.org/oneroster/?view=category).

#### Projection vers la table `notes`

Soit on garde `notes` comme note finale agrégée, soit on ajoute un champ `mode_calcul` :

```sql
notes (
    ... colonnes existantes ...
    origine VARCHAR(20) DEFAULT 'DIRECTE',     -- DIRECTE ou EVALUATIONS
    calcul_version VARCHAR(40) NULL,
    calcul_hash CHAR(64) NULL,
    calculee_at DATETIME NULL
)
```

**Recommandation :** conserver `notes.valeur` comme projection finale consommée par le moteur de bulletin. Une école peut rester en saisie `DIRECTE` pendant la migration, puis passer à `EVALUATIONS` après validation de sa règle. Il ne faut pas ajouter une deuxième colonne de valeur qui créerait deux sources de vérité.

### 4.3 Règles de calcul validées pour le secondaire

Pour chaque cours et période, le mode par évaluations repose sur un canevas référentiel unique : Exercice / Devoir `¼`, Cotation du cahier `¼` et Interrogation `½`. Le maximum officiel reste la seule source de vérité du bulletin.

Le barème de correction affiché à l’enseignant et au parent peut être plus familier que son poids réel au bulletin. Le quart est arrondi vers un barème lisible, puis l’interrogation reçoit exactement le double de ce barème visible. Chaque résultat est ensuite converti ainsi : `résultat × poids bulletin / barème de correction`.

| Maximum officiel | Barèmes de correction | Poids bulletin | Exemple de conversion |
|------------------|------------------------|----------------|-----------------------|
| 5 | `5 + 5 + 10` | `1,25 + 1,25 + 2,5` | `4/5` au devoir vaut `1/5` au bulletin |
| 20 | `5 + 5 + 10` | `5 + 5 + 10` | `4 + 3 + 8 = 15/20` |
| 30 | `10 + 10 + 20` | `7,5 + 7,5 + 15` | `8/10 + 6/10 + 14/20 = 21/30` |
| 40 | `10 + 10 + 20` | `10 + 10 + 20` | `6 + 7 + 15 = 28/40` |
| 50 | `20 + 20 + 40` | `12,5 + 12,5 + 25` | l’interrogation `/40` pèse toujours 25 points |

Cette règle fixe concerne le secondaire demandé. Le primaire reste volontairement hors de ce canevas jusqu’à réception de ses règles pédagogiques propres.

### 4.4 Primaire vs Secondaire

- **Secondaire :** la demande porte sur ce niveau. Les composantes (interrogation, devoir, exercice, cahier) sont pertinentes.
- **Primaire :** le dirigeant indique qu'il y aura "une autre dimension" à préciser ultérieurement. Le modèle `evaluations` est assez générique pour accueillir des composantes spécifiques primaire (ex. : "participation", "comportement", "atelier") via `type_evaluation` extensible ou `metadata` JSON.

### 4.5 Impact sur le bulletin

Le bulletin actuel stocke `total_obtenu`, `total_maxima`, `pourcentage` par inscription. Avec le nouveau modèle :
- Le bulletin peut afficher, pour chaque période et chaque matière, le détail des composantes.
- Le PDF du bulletin peut inclure une annexe "Détail des évaluations" par période.
- Les parents voient, sur leur espace, pour chaque enfant : période → matière → détail des évaluations.

---

## 5. Dimension industrielle — Bonnes pratiques de l'industrie

Pour enrichir l'analyse au-delà du simple développement fonctionnel, voici les pratiques industrielles recommandées pour un produit SaaS éducatif multi-tenant :

### 5.1 Multi-tenant & isolation

- **Shared database, shared schema, tenant_id discriminator** (déjà appliqué). Chaque requête métier est filtrée par `tenant_id` via scope global.
- **Row-level security (RLS)** à évaluer au niveau base pour les tables sensibles (`notes`, `evaluations`, `lien_responsable_eleves`) lorsque l'exploitation le justifiera.
- **Token scoped** : `tenant:{id}` pour le staff et `context:responsable` pour l’espace parent. Le jeton parent agrège uniquement les liens explicitement rattachés à son identité globale.

### 5.2 Audit & traçabilité (indispensable en éducation)

Toute modification de note ou de rattachement parent-enfant doit être tracée :

```sql
audit_logs (
    id, action (ex: NOTE_SAISIE, PARENT_LIEN_CREEE, PARENT_LIEN_SUPPRIMEE),
    actor_type (ENSEIGNANT|PARENT|ADMIN), actor_id,
    tenant_id, cible_type, cible_id,
    anciennes_valeurs JSON, nouvelles_valeurs JSON,
    ip_address, user_agent, created_at
)
```

### 5.3 API-first & contract testing

- Tous les endpoints parents doivent être documentés dans l'OpenAPI existant (`openapi-v1.json`).
- Tests d'isolation obligatoires : un parent du tenant A ne voit jamais un enfant du tenant B (404, pas 403 pour ne pas fuiter l'existence).
- Tests de contrat (Pact ou équivalent) entre backend et frontend.

### 5.4 Observabilité

- Logging structuré (JSON) pour les actions parent et enseignant.
- Métriques : nombre de connexions parent/jour, temps de chargement du dashboard parent, taux d'erreur d'authentification.
- Alerting sur les tentatives de rattachement anormales (ex. : même téléphone liant 50 élèves dans 10 écoles).

### 5.5 Protection des données des enfants

- Rattachement automatique et traçable depuis le numéro enregistré dans le dossier élève, conformément à la décision produit.
- Révocation d'un lien sans suppression des données scolaires soumises à conservation.
- Minimisation : aucun enfant, école ou classe n’est renvoyé par le login ; ces données ne sont accessibles qu’après authentification et changement du mot de passe initial.
- Chiffrement des données sensibles au repos lorsque requis et accès strictement journalisé.
- Pas de stockage du mot de passe en clair : `123456` et le mot de passe personnel sont toujours hashés.
- Le téléphone ne doit pas être marqué « vérifié » par la seule saisie du mot de passe initial ; ce champ reste réservé à une future vérification réelle du canal.

La conformité locale et les durées de conservation devront être validées par un juriste connaissant l'Ordonnance-Loi n° 23/010 du 13 mars 2023 portant Code du numérique en RDC. Ce document constitue une architecture technique, pas un avis juridique.

### 5.6 Design industriel (frontend)

- **Design System** : composants shadcn/ui déjà présents, à étendre avec des composants parent (`ChildCard`, `EvaluationRow`, `BulletinSummary`).
- **Responsive mobile-first** : les parents consomment majoritairement sur téléphone.
- **Offline-first (PWA)** : le projet a déjà une phase PWA (`PHASE_4_PWA_OFFLINE.md`). Le parent doit pouvoir consulter les derniers bulletins même sans réseau.
- **États de chargement, erreur, vide** : déjà une convention dans le projet, à appliquer strictement aux pages parent.

### 5.7 Déploiement & opérations

- **Feature flags** : activer/désactiver le module parent par tenant (certaines écoles ne veulent pas encore d'espace parent).
- **Migrations additives** : les nouvelles tables ne changent pas le comportement courant. La création de l'index composite sur `eleves` devra néanmoins être planifiée sur une base volumineuse, car elle peut prendre un verrou selon PostgreSQL et la stratégie de déploiement.
- **Tests** : après la Phase 2 backend, la suite vérifiée compte 564 tests Pest et 18 029 assertions. Les futurs calculs devront ajouter leurs tests unitaires et d'isolation.

---

## 6. Plan de mise en œuvre par phases

### Phase 1 — Fondations responsables (Semaine 1-2)

**État : ✅ fondations implémentées le 27 octobre 2026.** Les migrations restent à appliquer sur les environnements déployés selon la procédure habituelle.

| Livrable | Description |
|----------|-------------|
| Migration `create_responsables_table` | Identité globale avec `telephone_normalise` unique, sans `tenant_id` |
| Migration `create_lien_responsable_eleves_table` | Liaison riche et tenant-scopée avec cycle de vérification |
| Contrainte composite | Interdire en base un lien dont le tenant diffère de celui de l'élève |
| Modèle `Responsable` | Relations `eleves()` et `liensEleves()` |
| Relation `responsables()` sur `Eleve` | `belongsToMany` via `lien_responsable_eleves` |
| Factories et seeders explicites | Données de démonstration réservées à local/testing |
| Tests d'isolation | Une identité globale, liens filtrés par tenant, liens en attente/révoqués sans accès, seeders idempotents et incohérences rejetées par la base |

### Phase 2 — Authentification responsable (Semaine 2-3)

**État : ✅ backend implémenté et tests ciblés validés le 27 octobre 2026.**

| Livrable | Description |
|----------|-------------|
| `POST /api/v1/responsables/auth/login` | Connexion globale par téléphone + mot de passe, réponse générique et throttle |
| Synchronisation du dossier élève | Création du compte global et des liens multi-écoles à partir de `telephone_parent_normalise` |
| Changement initial | `POST /me/password-initial`, minimum 6 caractères, révocation de tous les anciens jetons |
| `GET /api/v1/responsables/enfants` | Liens vérifiés, écoles et classes, avec identifiants publics uniquement |
| Sessions | Déconnexion courante, déconnexion globale, transport Bearer ou cookie HttpOnly |

### Phase 3 — API Parents (Semaine 3-4)

| Livrable | Description |
|----------|-------------|
| `ResponsableController` | Profil courant, enfants et documents autorisés |
| Policies objet | Vérification responsable + lien + tenant + permission sur chaque ressource |
| Resources API | Enfant, école, classe, période et publications, sans données internes |
| Routes `routes/api/v1/responsables.php` | Middleware d'auth responsable + policies objet |
| OpenAPI `openapi-v1.json` | Documentation des nouveaux endpoints |
| Tests feature | CRUD parent, isolation, calcul de note |

### Phase 4 — Détail des notes par période (Semaine 4-5)

| Livrable | Description | État |
|----------|-------------|------|
| Migrations | `types_evaluations`, `evaluations`, `resultats_evaluations` (+ colonnes `origine`, `calcul_version`, `calcul_hash`, `calculee_at` sur `notes`) | ✅ Itération 4.1 appliquée |
| Modèles | `TypeEvaluation`, `Evaluation`, `ResultatEvaluation` (activité commune séparée des résultats individuels) | ✅ Itération 4.1 appliquée |
| Énumérations + seeder | `StatutEvaluation`, `NoteOrigine`, `TypeEvaluationSeeder` (5 codes initiaux) | ✅ Itération 4.1 appliquée |
| Service `EvaluationCalculationService` | Agrégation des composantes → note de période (compute pur + forInscription) | ✅ Itération 4.2 (tests : 28,5/37,5 = 76%) |
| Endpoints `POST /evaluations`, `GET /evaluations`, `POST /evaluations/{id}/resultats` | Saisie et consultation des composantes (enseignant) | ✅ Itération 4.3 (5 tests, 20 assertions) |
| Endpoint parent `GET enfants/{lien}/evaluations` | Consultation du détail pour le parent (isolation + calcul) | ✅ Itération 4.4 (2 tests, 15 assertions) |
| Mise à jour `Note` | Origine, version et hash de calcul ; `valeur` reste l'unique projection officielle | ✅ Colonnes ajoutées (itération 4.1) |
| Tests de calcul | Vérification du total pondéré | ✅ Itération 4.2 (4 tests, 16 assertions) |

### Phase 5 — Frontend Espace Parent (Semaine 5-7)

**État : ✅ socle multi-écoles et détail des évaluations implémentés.**

| Livrable | Description |
|----------|-------------|
| Page `/parents/connexion` | ✅ Connexion parent et rappel du mot de passe temporaire |
| Page `/parents/mot-de-passe` | ✅ Remplacement obligatoire de `123456` par au moins 6 caractères |
| Page `/parents` | ✅ Liste des enfants avec leurs écoles et classes |
| Page `/parents/enfants/{lienPublicId}` | ✅ Situation scolaire et permissions ; emplacement du futur détail pédagogique |
| Page bulletin / évaluations | ✅ Détail des évaluations, résultat de l'enfant et calcul de période affichés dans `ResponsableChildPage` (itérations 5.1 et 5.2) ; durcissement : état vide au lieu de 404 sans inscription active + fallback `queryFn` frontend |
| Garde `RequireResponsableAuth` | ✅ Session séparée, redirections et changement initial obligatoire |
| API client `frontend/src/api/responsable.ts` | ✅ Login, profil, enfants, mot de passe, déconnexion et enfantEvaluations |
| Lien public | ✅ « Espace parents » dans l’en-tête mobile/desktop et le pied de page |
| Tests Vitest | ✅ 45 fichiers et 346 tests, dont le contrat `enfantEvaluations` ; build TypeScript/Vite/PWA validé (itération 5.2) |

### Phase 6 — Industrialisation (Semaine 7-8)

| Livrable | Description |
|----------|-------------|
| Audit logs | ✅ Itération 6.1 — création d'évaluation et création/modification de résultat tracées dans `audit_logs` avec acteur, tenant, anciennes/nouvelles valeurs, IP et user-agent (5 tests enseignant, 30 assertions) |
| Feature flags | ✅ Itération 6.2 — `tenants.espace_parents_actif`, gestion via l'API plateforme, filtrage immédiat des enfants et du détail des évaluations ; réactivation testée (6 tests portail, 55 assertions ; 21 tests plateforme, 109 assertions) |
| Correctif affichage évaluations | ✅ `ResponsableEnfantEvaluationsController` renvoie un état vide (élève, classe, évaluations vides) au lieu de 404 quand l'élève n'a pas d'inscription/classe active ; `EnfantEvaluationsSection` ajoute un fallback `queryFn` pour ne jamais passer `undefined` à React Query |
| Interface enseignant des évaluations | ✅ Itération 6.4 (29/10/2026) — le formulaire de création libre a été remplacé par un flux attaché au créneau officiel : choix d'une période éligible, activation idempotente du canevas, puis grille unique avec tous les élèves et les colonnes « Exercice / Devoir », « Cotation du cahier », « Interrogation » et « Total période ». Les anciennes pages unitaires restent compatibles, mais ne sont plus le parcours principal. |
| Notation de période — deux modes de saisie | ✅ Implémenté selon la règle métier corrigée — Option 1 : canevas automatique fixe `¼ + ¼ + ½` dérivé du maximum officiel du cours ; Option 2 : saisie directe dans `notes`. Le choix du mode évaluations crée exactement trois lignes référentielles et verrouille la saisie directe pour ce cours et cette période. Aucune valeur directe n'est stockée dans `resultats_evaluations` : `notes.valeur` reste l'unique projection officielle du bulletin. |
| PWA offline | ✅ Présent — `responsable-offline.ts` : snapshots chiffrés AES-GCM (clé dérivée du marqueur de session), TTL 24 h, repli automatique sur le cache en cas de panne réseau pour la liste des enfants et le détail des évaluations |
| Monitoring | ✅ Présent — middleware `MonitorResponsableRead` sur les lectures parent + `Platform/ResponsableMonitoringController` pour la supervision plateforme |
| Documentation utilisateur | ⏳ Guide parent (non demandé, mais recommandé) |

---

## 8. Tâche prioritaire — Notation de période : deux modes de saisie (enseignant + parent)

### Contexte

Pour chaque cours et chaque période saisissable du secondaire, l'enseignant choisit l'un des deux modes. Le maximum ne vient jamais du formulaire d'évaluation : il vient exclusivement du `ModeleMaximum` officiel du cours et de la période.

### Analyse des deux options

| Option | Principe | Faisabilité | Impact enseignant | Impact parent | Complexité |
|--------|----------|-------------|-------------------|---------------|------------|
| **Option 1 — Par évaluations** | Le système crée exactement trois composantes pondérées : Exercice / Devoir = `max / 4`, Cotation du cahier = `max / 4`, Interrogation = `max / 2`. Le quart est arrondi vers un barème lisible et l’interrogation affiche exactement le double. | **Haute** — barème déterministe et contrôlé par la base. | Une seule grille classe × trois colonnes ; aucun maximum ni coefficient à saisir. | Barèmes familiers (`4/5`, `12/20`) et note officielle convertie. | Faible |
| **Option 2 — Saisie directe** | L'enseignant saisit directement la note officielle de période dans la feuille de notes. | **Déjà supportée** — `notes.origine = DIRECTE`. | Une seule cellule par élève et période. | Note de période uniquement, sans faux détail d'évaluations. | Faible |

### Recommandation d'implémentation

1. Ajouter `evaluations.composante_modele`, nullable pour préserver l'historique, avec une contrainte unique `(tenant, classe, cours, période, composante)`. Le canevas survit ainsi au remplacement d'un enseignant.
2. Exposer un endpoint idempotent lié à l'attribution et au créneau officiel. Il crée les trois composantes publiées et journalise chaque création.
3. Répartir le maximum en centimes : deux quarts arrondis vers le bas, puis affecter le reliquat à l'interrogation. La somme des poids reste exactement égale au maximum officiel.
4. Séparer `maxima` (barème de correction visible) et `maxima_bulletin` (poids réel). Arrondir le quart à 5 jusqu'à 5, 10 jusqu'à 10, puis à la dizaine supérieure ; le barème visible de l’interrogation est toujours le double du quart arrondi. Exemples : cours sur 30 → correction `10 + 10 + 20`, poids `7,5 + 7,5 + 15` ; cours sur 50 → correction `20 + 20 + 40`, poids `12,5 + 12,5 + 25`.
5. Convertir chaque résultat par `résultat × maxima_bulletin / maxima`. Ainsi `8/10` pesant 7,5 vaut 6 points au bulletin.
6. Durant une saisie progressive, réserver le poids des trois composantes : une seule composante ne peut jamais être extrapolée comme si elle représentait toute la période.
7. Projeter à l'écriture le total dans `notes` avec `origine = EVALUATIONS`. Les lectures du bulletin et du parent utilisent cette projection indexée et ne recalculent pas toute la classe.
8. Côté parent, afficher le barème lisible et le poids bulletin pour les trois composantes seulement en mode évaluations ; en saisie directe, afficher uniquement la note de période.

### Statut

✅ **Implémenté et revu le 30/10/2026** — canevas automatique idempotent, distinction barème de correction / poids bulletin, interrogation affichée sur le double du quart arrondi, cas 5, 20, 30, 40 et 50 couverts, grille unique, conversion et projection progressive correctes, blocage de la saisie directe et détail parent conservé. Une migration aligne également les canevas déjà créés et convertit proportionnellement leurs résultats, sans modifier la note officielle. Les anciennes recommandations plaçant une note directe dans `resultats_evaluations` sont abandonnées : cette responsabilité appartient à `notes`.

---

## 7. Risques et mitigations

| Risque | Gravité | Mitigation |
|--------|---------|------------|
| Connaissance du numéro + mot de passe initial commun | Critique | Changement obligatoire avant toute donnée enfant, réponse générique, throttling par IP et identifiant, audit et révocation des jetons ; OTP/passkey recommandé à terme |
| Numéro erroné dans un dossier élève | Critique | Accès école strict sur la saisie, normalisation, audit, révocation automatique de l’ancien lien lors de la correction et procédure de support |
| Fuite de données inter-tenant | Critique | Tests d'isolation obligatoires, scope global `HasTenant`, policy vérifiant `ressource.tenant_id === acteur.tenant_id` |
| Calcul de note erroné | Majeure | Tests unitaires sur `EvaluationCalculationService`, barème configurable par école, double lecture (enseignant valide le calcul) |
| Adoption faible par les parents | Mineure | Inscription simplifiée (6 champs), mobile-first, notifications SMS optionnelles, PWA |
| Complexité technique multi-écoles | Majeure | Réutiliser le pattern `telephone_normalise` déjà validé pour les enseignants, pas de table `comptes` globale en phase 1 |

---

## 8. Critères d'acceptation

- [x] Un responsable authentifié voit tous les enfants liés au même numéro normalisé, dans toutes les écoles concernées.
- [x] La première session est limitée au changement obligatoire du mot de passe ; aucun enfant n’est accessible avant cette opération.
- [x] Le mot de passe personnel comporte au moins 6 caractères et ne peut pas rester `123456`.
- [x] Les anciens jetons sont révoqués dès le changement initial.
- [x] Un parent ne voit jamais les enfants d'un autre numéro, même dans la même école.
- [x] Un lien `EN_ATTENTE` ou révoqué ne donne aucun accès via les relations et scopes vérifiés de la fondation.
- [x] La base refuse un lien dont le `tenant_id` diffère de celui de l'élève.
- [x] Le login ne révèle aucun enfant ni aucune école ; la réponse d’échec reste générique.
- [x] Un enseignant active un canevas unique par cours et période : Exercice / Devoir `¼`, Cotation du cahier `¼`, Interrogation `½`.
- [x] Le maximum de chaque composante est dérivé automatiquement du maximum officiel, sans saisie manuelle ni duplication du barème.
- [x] Le barème de correction visible est séparé du poids bulletin et la conversion proportionnelle couvre notamment les maxima 5, 20, 30, 40 et 50.
- [x] Le système additionne les points réels et projette automatiquement la note de période dans `notes`.
- [x] Un parent consulte le détail des évaluations de son enfant pour chaque période. (endpoint itération 4.4 et affichage itération 5.2)
- [x] Notation de période — deux modes exclusifs : canevas d'évaluations automatique ou saisie directe dans la feuille de notes.
- [x] Un cours sur 20 produit `5 + 5 + 10`, un cours sur 30 produit `10 + 10 + 20`, un cours sur 40 produit `10 + 10 + 20` et un cours sur 50 produit `20 + 20 + 40`, sans modifier leurs poids officiels respectifs.
- [x] Aucun `tenant_id` n'est accepté en provenance du client (règle d'or du projet) : les endpoints enseignant et parent résolvent le tenant via `EstablishmentContext` / `$request->user()`.
- [x] La suite backend complète passe : 604 tests, 18 277 assertions (30/10/2026).
- [x] `pint --dirty --format agent` passe.
- [x] La suite frontend complète passe : 48 fichiers, 359 tests Vitest, plus le typage, le lint et le build TypeScript/Vite/PWA (30/10/2026).
- [x] Le lien « Espace parents », la connexion, le changement initial, le tableau de bord multi-écoles et la fiche enfant sont présents.

---

## 9. Estimation indicative

| Phase | Durée | Ressources |
|-------|-------|------------|
| Phase 1 — Données | 1-2 semaines | 1 développeur backend |
| Phase 2 — Auth parent | 1 semaine | 1 développeur backend + frontend |
| Phase 3 — API Parent | 1-2 semaines | 1 développeur backend |
| Phase 4 — Détail notes | 1-2 semaines | 1 développeur backend + tests |
| Phase 5 — Frontend | 2-3 semaines | 1 développeur frontend |
| Phase 6 — Industrialisation | 1-2 semaines | 1 développeur fullstack + DevOps |
| **Total** | **8-12 semaines** | **1-2 développeurs** |

---

## 10. Recommandation immédiate et ordre de réalisation

**Le plan est désormais implémenté de bout en bout et corrigé selon le barème référentiel (30/10/2026).** L'identité globale `Responsable`, les liens tenant-scopés, l'authentification Sanctum, le changement initial obligatoire, le portail parent multi-écoles, le canevas automatique `¼ + ¼ + ½`, la projection de la note de période, les deux modes exclusifs de saisie, l'audit, les feature flags, le PWA offline et le monitoring sont en place.

Prochaines étapes fonctionnelles possibles (hors plan initial) : spécification du primaire avec les écoles pilotes, renforcement de l'authentification parent (OTP/passkey), guide utilisateur parent, et annexe « Détail des évaluations » dans le PDF du bulletin.

Avant un déploiement élargi, les écoles pilotes doivent toujours répondre aux huit questions de barème : catégories obligatoires, nombre minimal d'activités, coefficients, traitement des absences, retrait éventuel de la plus mauvaise note, moment de visibilité parent, droit de correction après verrouillage et affichage ou non d'une moyenne provisoire.

---

*Document vivant : les états de phase et les résultats de vérification doivent être mis à jour après chaque incrément.*
