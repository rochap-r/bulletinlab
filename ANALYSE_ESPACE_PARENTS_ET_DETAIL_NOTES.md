# Analyse de faisabilité — Espace Parents Multi-Écoles + Détail des notes par période

**Projet :** BulletinLab / TopScolaire  
**Date :** 26 octobre 2026  
**Auteur :** Analyse technique (IA)  
**Statut :** Proposition — aucune modification de code apportée  

---

## 1. Contexte et objectifs

Le dirigeant de l'école a soulevé deux attentes majeures :

1. **Espace Parents multi-écoles** : un parent (ou tuteur) doit pouvoir se connecter avec son numéro de téléphone et voir **tous ses enfants**, qu'ils soient dans l'école A, l'école B ou n'importe quelle autre école utilisant la plateforme. Il ne doit pas créer un compte par école.

2. **Détail des notes par période** : pour le secondaire (et potentiellement le primaire), une période n'est pas une note unique. Elle est constituée de plusieurs composantes saisies par l'enseignant : interrogations, exercices, devoirs, cotation des cahiers. Le système doit agréger ces composantes pour former les points de la période, et les parents doivent pouvoir consulter ce détail (ex. : "interrogation ratée, devoir réussi").

Ce document analyse la faisabilité technique, propose une architecture, un plan de mise en œuvre par phases, et intègre une **dimension industrielle** (bonnes pratiques SaaS multi-tenant, sécurité, audit, observabilité).

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
| API REST versionnée | `routes/api/v1/*` | ✅ Existant | Endpoints parents à ajouter sous `api/v1/parents.php` |
| Frontend React + TypeScript | `frontend/src/` | ✅ Existant | Pages `/parent/*` à créer |

### 2.2 Ce qui manque (écarts à combler)

| Écran / Fonction | Statut |
|------------------|--------|
| Modèle `Parent` dédié | ❌ Absent |
| Table de liaison `parent_eleve` (N:M) | ❌ Absent |
| Rôle `PARENT` | ❌ Absent (Spatie) |
| Authentification parent (login/inscription) | ❌ Absent |
| Endpoints API parents | ❌ Absent |
| Pages frontend `/parent/*` | ❌ Absent |
| Garde d'authentification parent | ❌ Absent |
| Composantes de note (interrogation, devoir, exercice, cahier) | ❌ Absent — une `Note` stocke une seule `valeur` par période |
| Agrégation des composantes vers la période | ❌ Absent |
| Vue détail des notes pour les parents | ❌ Absent |

---

## 3. Dimension 1 — Espace Parents Multi-Écoles

### 3.1 Principe architectural

Le projet applique déjà le pattern **"1 identité humaine = 1 numéro de téléphone vérifié, N appartenances"** (voir `AUTHENTIFICATION_ROLES_V2.md` §3). Pour les parents, deux options s'offrent à nous :

| Option | Description | Recommandation |
|--------|-------------|----------------|
| **A — Compte `Parent` dédié** | Table `parents` propre, table de liaison `parent_eleve`, token parent scopé ou global | ✅ Recommandée. Propre, isolée des staff, évolutive. |
| **B — Réutiliser `Utilisateur` + rôle `PARENT`** | Un parent est un `Utilisateur` avec rôle `PARENT`, liaison via `telephone_parent` sur `Eleve` | ❌ Déconseillée. Mélange staff/parents, permissions ambiguës, moins sécurisé. |

**Architecture retenue : Option A (compte `Parent` dédié).**

### 3.2 Modèle de données proposé

#### Table `parents`

```sql
parents (
    id BIGINT PK,
    tenant_id BIGINT FK -> tenants (cascade),          -- tenant de rattachement principal (si lié à une école)
    telephone VARCHAR(30) UNIQUE,                      -- téléphone brut saisi
    telephone_normalise VARCHAR(30) UNIQUE INDEX,      -- E.164 (+243...), identité pivot
    nom VARCHAR(100),
    postnom VARCHAR(100) NULL,
    prenom VARCHAR(100),
    email VARCHAR(150) NULL,
    adresse TEXT NULL,
    sexe ENUM('M','F') NULL,
    mot_de_passe VARCHAR(255),
    actif BOOLEAN DEFAULT TRUE,
    doit_changer_mot_de_passe BOOLEAN DEFAULT FALSE,
    derniere_connexion_at DATETIME NULL,
    email_verified_at DATETIME NULL,
    created_at, updated_at
)
```

#### Table `parent_eleve` (liaison N:M)

```sql
parent_eleve (
    id BIGINT PK,
    parent_id BIGINT FK -> parents (cascade),
    eleve_id BIGINT FK -> eleves (cascade),
    tenant_id BIGINT FK -> tenants (cascade),          -- tenant de l'élève (isolation)
    lien ENUM('PERE','MERE','TUTEUR') DEFAULT 'TUTEUR',
    responsable_paiement BOOLEAN DEFAULT FALSE,
    recevoir_notifications BOOLEAN DEFAULT TRUE,
    actif BOOLEAN DEFAULT TRUE,
    created_at, updated_at,
    UNIQUE (parent_id, eleve_id)
)
```

**Règle d'isolation :** un `parent_eleve` n'existe que si `parent` et `eleve` appartiennent au même `tenant` OU si le parent est déclaré global (multi-tenant via `telephone_normalise`). Voir §3.4.

### 3.3 Flux d'authentification et de rattachement

#### Cas A : Parent déjà connu dans une école (socle existant)
L'élève a déjà `telephone_parent` renseigné lors de son inscription.

1. L'école crée un compte parent (`POST /parents`) avec le téléphone de l'élève.
2. Le système crée la ligne `parents` + la liaison `parent_eleve`.
3. Le parent reçoit un mot de passe temporaire (affiché 1 fois) + changement forcé à la 1ère connexion.

#### Cas B : Parent avec enfants dans plusieurs écoles (multi-tenant)
Le parent se connecte avec son numéro de téléphone.

1. `POST /auth/login-parent { telephone, password }` → le système recherche tous les `parent_eleve` liés à ce `telephone_normalise` dans **tous les tenants** où le parent est actif.
2. Retourne la liste des enfants : `{ enfant, ecole, classe, annee_scolaire, tenant_id }`.
3. Le parent choisit un enfant (ou une école) → le système renvoie un token scopé sur le tenant de cet enfant.
4. Le parent peut switcher d'enfant/école sans se reconnecter (pattern `auth/switch` déjà existant pour les enseignants).

#### Cas C : Parent qui s'inscrit lui-même (portail public)
1. `POST /public/parents` → création de compte parent avec téléphone.
2. Le parent ajoute ses enfants manuellement en saisissant `num_permanent` + `telephone_parent` de chaque enfant.
3. L'école valide le rattachement (ou le système valide automatiquement si `telephone_parent` correspond).

### 3.4 Isolation et sécurité (règle d'or du projet)

> Toute opération métier lit et écrit dans **un seul tenant**. Le tenant vient du serveur, jamais du client.

Pour les parents multi-écoles, on introduit une **exception contrôlée** (allowlist fermée, déjà documentée dans `AUTHENTIFICATION_ROLES_V2.md` §6) :

| Opération | Lit hors tenant | Écrit hors tenant | Garde-fous |
|-----------|-----------------|-------------------|------------|
| `GET /public/parents/enfants?telephone=` | `parent_eleve` masqué (nom enfant, école, classe) | Rien | Throttle, réponse générique si inconnu, aucune donnée de notes |
| `GET /parents/{id}/enfants` | Tous les enfants du parent dans son tenant courant | Rien | Token parent + policy `viewAny` |
| `POST /auth/switch-parent {tenant_id}` | Appartenance du parent dans le tenant cible | Nouveau token `tenant:{id}` | Même `telephone_normalise` + actif, audit |

**Interdit :** lister tous les élèves d'une école via un compte parent, modifier les notes d'un enfant d'un autre tenant, exposer le mot de passe.

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

#### Table `evaluations` (composantes de note)

```sql
evaluations (
    id BIGINT PK,
    tenant_id BIGINT FK -> tenants,
    bulletin_id BIGINT FK -> bulletins,
    node_id BIGINT FK -> modele_nodes,          -- matière/rubrique
    periode_id BIGINT FK -> modele_periodes,    -- T1, T2, examen...
    type_evaluation ENUM('INTERROGATION','EXERCICE','DEVOIR','CAHIER','AUTRE'),
    libelle VARCHAR(200),                       -- ex. "Interrogation n°1 - Géo"
    valeur DECIMAL(8,2),                        -- note obtenue
    maxima DECIMAL(8,2),                        -- note max possible (ex. 10, 20)
    coefficient DECIMAL(6,2) DEFAULT 1,         -- poids dans le calcul de la période
    date_evaluation DATE NULL,
    saisi_par BIGINT FK -> utilisateurs,
    created_at, updated_at
)
```

#### Modification de la table `notes` (optionnelle)

Soit on garde `notes` comme note finale agrégée, soit on ajoute un champ `mode_calcul` :

```sql
notes (
    ... colonnes existantes ...
    mode_calcul ENUM('DIRECT','AGREGATION') DEFAULT 'DIRECT',  -- DIRECT = saisie manuelle, AGREGATION = calculée depuis evaluations
    note_periode_calculee DECIMAL(8,2) NULL,                  -- résultat du calcul
)
```

**Recommandation :** garder `notes` comme note finale de la période, et ajouter `evaluations` comme détail. La note de période est calculée en agrégeant `evaluations` (somme des `valeur * coefficient` / somme des `maxima * coefficient`, ou selon barème école).

### 4.3 Règles de calcul (exemple secondaire)

Pour une période donnée (ex. Trimestre 1) et une matière (ex. Mathématiques) :

| Composante | Valeur | Maxima | Coefficient | Points |
|------------|--------|--------|-------------|--------|
| Interrogation 1 | 7 | 10 | 1 | 7 |
| Devoir maison | 14 | 20 | 1 | 14 |
| Cahier | 15 | 15 | 0.5 | 7.5 |
| **Total** | — | — | — | **28.5 / 45** |

Note de période = 28.5 / 45 = 63.3%

Le barème (somme simple, moyenne pondérée, note sur 20, etc.) doit être configurable par école/tenant (`modele_maxima` existe déjà et peut être étendu).

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
- **Row-level security (RLS)** au niveau base de données pour les tables sensibles (`notes`, `evaluations`, `parent_eleve`) en environnement de production critique.
- **Token scoped** : `tenant:{id}` pour les staff, `parent:{id}` ou `tenant:{id}` selon le choix d'authentification parent. Jamais de token multi-tenant.

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

### 5.5 RGPD & protection des données (enfants)

- Consentement explicite pour le rattachement parent-enfant.
- Droit à l'oubli : suppression d'un parent supprime les liaisons `parent_eleve` mais pas les données scolaires de l'enfant (conservation légale).
- Chiffrement des données sensibles (téléphone, email) au repos si exigé par la législation locale (RDC/OHADA).
- Pas de stockage du mot de passe en clair, hash bcrypt/argon2.

### 5.6 Design industriel (frontend)

- **Design System** : composants shadcn/ui déjà présents, à étendre avec des composants parent (`ChildCard`, `EvaluationRow`, `BulletinSummary`).
- **Responsive mobile-first** : les parents consomment majoritairement sur téléphone.
- **Offline-first (PWA)** : le projet a déjà une phase PWA (`PHASE_4_PWA_OFFLINE.md`). Le parent doit pouvoir consulter les derniers bulletins même sans réseau.
- **États de chargement, erreur, vide** : déjà une convention dans le projet, à appliquer strictement aux pages parent.

### 5.7 Déploiement & opérations

- **Feature flags** : activer/désactiver le module parent par tenant (certaines écoles ne veulent pas encore d'espace parent).
- **Migrations zéro downtime** : les tables `parents` et `evaluations` sont additives, pas de risque pour les données existantes.
- **Tests** : 527 tests Pest (backend) + 308 Vitest (frontend) existants. Ajouter des tests d'isolation parent et de calcul de note.

---

## 6. Plan de mise en œuvre par phases

### Phase 1 — Fondations données et modèle (Semaine 1-2)

| Livrable | Description |
|----------|-------------|
| Migration `create_parents_table` | Table `parents` avec `telephone_normalise` unique |
| Migration `create_parent_eleve_table` | Table de liaison N:M avec `lien`, `responsable_paiement` |
| Modèle `Parent` | Relations `eleves()`, `tenant()` |
| Relation `parents()` sur `Eleve` | `belongsToMany` via `parent_eleve` |
| Seeder de démo | Quelques parents + enfants dans 2 tenants différents |
| Tests d'isolation | Parent ne voit que ses enfants, scoped par tenant |

### Phase 2 — Authentification parent (Semaine 2-3)

| Livrable | Description |
|----------|-------------|
| Rôle `PARENT` (Spatie) | Système si option B, sinon pas nécessaire |
| `POST /auth/login-parent` | Connexion par téléphone + mot de passe |
| `GET /public/parents/enfants?telephone=` | Liste masquée des enfants (sans auth) |
| `POST /auth/switch-parent` | Switch d'enfant/école sans reconnexion |
| Mot de passe temporaire + changement forcé | Pattern déjà utilisé pour les staff |

### Phase 3 — API Parents (Semaine 3-4)

| Livrable | Description |
|----------|-------------|
| `ParentController` | `index`, `show`, `enfants`, `bulletin-enfant` |
| `ParentPolicy` | `viewAny`, `view`, `create`, `update` (scopé tenant) |
| `ParentResource` | Transformation API (enfant, école, classe, bulletin récent) |
| Routes `routes/api/v1/parents.php` | Middleware `auth:sanctum` + `tenant` |
| OpenAPI `openapi-v1.json` | Documentation des nouveaux endpoints |
| Tests feature | CRUD parent, isolation, calcul de note |

### Phase 4 — Détail des notes par période (Semaine 4-5)

| Livrable | Description |
|----------|-------------|
| Migration `create_evaluations_table` | Table `evaluations` (composantes) |
| Modèle `Evaluation` | Relations avec `bulletin`, `node`, `periode`, `enseignant` |
| Service `EvaluationCalculationService` | Agrégation des composantes → note de période |
| Endpoints `POST /evaluations`, `GET /evaluations` | Saisie et consultation des composantes |
| Mise à jour `Note` | `mode_calcul`, `note_periode_calculee` |
| Tests de calcul | Vérification du total pondéré |

### Phase 5 — Frontend Espace Parent (Semaine 5-7)

| Livrable | Description |
|----------|-------------|
| Page `/parent/login` | Connexion parent |
| Page `/parent` (dashboard) | Liste des enfants avec écoles, classes, bulletins récents |
| Page `/parent/enfants/{id}` | Détail d'un enfant |
| Page `/parent/enfants/{id}/bulletin` | Bulletin avec détail des évaluations par période |
| Composants `ChildCard`, `EvaluationRow`, `BulletinSummary` | UI réutilisable |
| Garde `RequireParentAuth` | Protection des routes parent |
| API client `frontend/src/api/parent.ts` | Méthodes `parentApi` |
| Routes React Router | Intégration dans `App.tsx` |
| Tests Vitest | Pages parent, calcul affichage |

### Phase 6 — Industrialisation (Semaine 7-8)

| Livrable | Description |
|----------|-------------|
| Audit logs | Traçabilité des actions parent et enseignant |
| Feature flags | Activation/désactivation module parent par tenant |
| PWA offline | Consultation des bulletins sans réseau |
| Monitoring | Métriques et alertes |
| Documentation utilisateur | Guide parent (non demandé, mais recommandé) |

---

## 7. Risques et mitigations

| Risque | Gravité | Mitigation |
|--------|---------|------------|
| Rattachement abusif (un parent lie des enfants qui ne sont pas les siens) | Critique | Validation par l'école, confirmation du `telephone_parent` sur la fiche élève, throttle strict, audit log |
| Fuite de données inter-tenant | Critique | Tests d'isolation obligatoires, scope global `HasTenant`, policy vérifiant `ressource.tenant_id === acteur.tenant_id` |
| Calcul de note erroné | Majeure | Tests unitaires sur `EvaluationCalculationService`, barème configurable par école, double lecture (enseignant valide le calcul) |
| Adoption faible par les parents | Mineure | Inscription simplifiée (6 champs), mobile-first, notifications SMS optionnelles, PWA |
| Complexité technique multi-écoles | Majeure | Réutiliser le pattern `telephone_normalise` déjà validé pour les enseignants, pas de table `comptes` globale en phase 1 |

---

## 8. Critères d'acceptation

- [ ] Un parent avec son téléphone voit tous ses enfants dans toutes les écoles où il est lié (multi-tenant).
- [ ] Un parent ne voit jamais les enfants d'un autre parent, même dans la même école.
- [ ] Un enseignant peut saisir interrogation, devoir, exercice, cahier pour une période et une matière.
- [ ] Le système calcule automatiquement la note de période à partir des composantes.
- [ ] Un parent consulte le détail des évaluations de son enfant pour chaque période.
- [ ] Aucun `tenant_id` n'est accepté en provenance du client (règle d'or du projet).
- [ ] Tous les tests d'isolation passent (527 tests backend + nouveaux tests parent).
- [ ] `pint` passe, le build TypeScript/Vite réussit.

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

## 10. Recommandation immédiate

**Ne pas toucher au code existant pour l'instant.** Valider d'abord ce document avec le dirigeant et les écoles partenaires, puis démarrer par la **Phase 1** (modèle `parents` + `parent_eleve`), qui est purement additive et sans risque pour les données actuelles.

Le socle existe déjà : `eleves.telephone_parent`, `HasTenant`, le pattern `telephone_normalise` multi-tenant, et l'API REST versionnée. Le projet est donc **techniquement prêt** à accueillir ces deux dimensions.

---

*Document généré dans le cadre d'une analyse de faisabilité. Aucun fichier de code n'a été modifié.*