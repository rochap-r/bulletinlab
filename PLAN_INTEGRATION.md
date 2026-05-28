# Plan d'Intégration - Bulletinlab
**Inspiré de l'architecture Pakapale (EventARDC API)**

---

## Vue d'ensemble Actuelle

**Framework**: Laravel 12/13 selon version stable du projet  
**PHP**: 8.3+  
**Architecture**: Structure Laravel standard  
**Domaine**: Système de gestion de bulletins scolaires SaaS  
**Qualité**: Laravel Pint, Pest PHP 4.7

---

## 1. Packages Composer à Ajouter

### Phase 1 - Core MVP (Indispensables)
- **laravel/sanctum**: ^4.2 - Authentification API tokens
- **spatie/laravel-permission**: ^6.0 - Gestion rôles/permissions
- **maatwebsite/excel**: ^3.1 - Import/Export Excel
- **spatie/browsershot**: ^2.0 - Génération PDF via Chrome

### Phase 2 - Après validation bulletin
- **spatie/laravel-activitylog**: ^5.0 - Audit trails
- **dedoc/scramble**: ^0.13.8 - Documentation API

### Phase 3 - Architecture avancée (Optionnel)
- **spatie/laravel-data**: ^4.18 - Data Transfer Objects
- **intervention/image-laravel**: ^1.5 - Manipulation images
- **larastan/larastan**: ^3.0 - Static analysis
- **barryvdh/laravel-ide-helper**: ^3.6 - IDE autocomplete

---

## 2. Restructuration Domain-Driven

### Structure [app/Domains/] par Domaines

```
app/
├── Domains/
│   ├── Auth/                # Authentification multi-tenant
│   │   ├── Contracts/       # Interfaces
│   │   ├── Exceptions/      # Exceptions métier
│   │   └── Services/        # Sanctum, permissions
│   ├── Tenant/              # Multi-tenancy par espace client SaaS
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── Academic/            # Référentiel scolaire
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── Template/            # Modèles de bulletins
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── SchoolYear/          # Calendrier scolaire
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── Classe/              # Gestion classes
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── Teacher/             # Gestion enseignants
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── Student/             # Gestion élèves
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── Evaluation/          # Notes, appréciations
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── Bulletin/            # Cœur du système
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── Attendance/          # Présences
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   └── Services/
│   ├── ImportExport/        # Import/Export Excel
│   │   ├── Contracts/
│   │   ├── Exceptions/
│   │   ├── Services/
│   │   └── Jobs/            # Queue jobs imports
│   └── Sync/                # Offline-first sync
│       ├── Contracts/
│       ├── Exceptions/
│       └── Services/
├── Models/                  # 55 modèles Eloquent existants
├── Http/                    # API layer
│   ├── Controllers/         # Contrôleurs par domaine
│   ├── Middleware/          # Multi-tenancy, permissions
│   ├── Requests/            # Form requests
│   └── Resources/           # API resources
└── Support/                 # Helpers globaux
```

### Pattern Service Layer
- **Interface-First**: Chaque service a un contrat correspondant
- **Domain Separation**: Organisation par business domain
- **Exception Handling**: Exceptions métier par domaine
- **DTO Pattern**: Optionnel - Spatie Laravel Data (Phase 3+)

---

## 3. Structure API (routes/api.php)

### Versioning
- **Prefix**: `/api/v1/`
- **Naming**: `api.v1.{resource}.{action}`

### Principaux Endpoints

#### Authentification (Public)
- `POST /api/v1/auth/register` - Inscription établissement
- `POST /api/v1/auth/login` - Connexion admin/enseignant

#### Tenant (Protected - Résolu par token Sanctum)
- `GET /api/v1/me` - Infos utilisateur + tenant actuel
- `GET /api/v1/school-years` - Années scolaires du tenant
- `POST /api/v1/school-years` - Créer année scolaire
- `POST /api/v1/school-years/{id}/open` - Ouvrir année scolaire

#### Classes (Protected - Scoped par tenant)
- `GET/POST /api/v1/classes` - CRUD classes
- `GET /api/v1/classes/{id}` - Détails classe
- `PUT /api/v1/classes/{id}` - Modification classe
- `DELETE /api/v1/classes/{id}` - Suppression classe
- `POST /api/v1/classes/{id}/titulaire` - Assignation titulaire

#### Élèves (Protected - Scoped par tenant)
- `GET/POST /api/v1/students` - CRUD élèves
- `POST /api/v1/students/import` - Import Excel
- `GET /api/v1/students/{id}` - Détails élève
- `PUT /api/v1/students/{id}` - Modification élève
- `DELETE /api/v1/students/{id}` - Suppression élève

#### Enseignants (Protected - Scoped par tenant)
- `GET/POST /api/v1/teachers` - CRUD enseignants
- `POST /api/v1/teachers/{id}/assign-course` - Attribution cours

#### Évaluations & Notes (Protected - Scoped par tenant)
- `GET/POST /api/v1/evaluations` - CRUD évaluations
- `POST /api/v1/notes` - Encodage notes
- `POST /api/v1/notes/import` - Import notes Excel
- `GET /api/v1/classes/{id}/encoding-progress` - Suivi encodage
- `POST /api/v1/periods/{id}/lock` - Verrouillage période

#### Bulletins (Protected - Scoped par tenant)
- `GET /api/v1/classes/{id}/bulletins` - Liste bulletins classe
- `GET /api/v1/students/{id}/bulletins` - Bulletins élève
- `POST /api/v1/bulletins/generate` - Génération PDF
- `GET /api/v1/bulletins/{id}/preview` - Preview bulletin

#### Templates & Modèles Annuels (Protected - Scoped par tenant)
- `GET /api/v1/templates/available` - Templates système disponibles (lecture seule)
- `POST /api/v1/classes/{id}/clone-template` - Clone système vers modèle annuel
- `GET /api/v1/modeles-bulletin` - Modèles annuels de l'école
- `POST /api/v1/modeles-bulletin/{id}/activate` - Activation modèle annuel

#### Super Admin (Protected - Admin SaaS uniquement)
- `GET/POST /api/v1/admin/system-templates` - CRUD templates système
- `PUT /api/v1/admin/system-templates/{id}` - Modification template système
- `DELETE /api/v1/admin/system-templates/{id}` - Suppression template système

#### Proclamations (Protected - Scoped par tenant)
- `GET/POST /api/v1/proclamations` - CRUD proclamations
- `POST /api/v1/proclamations/{id}/generate` - Génération PDF proclamation

#### Multi-écoles (Optionnel - Pour groupes multi-établissements)
- `GET /api/v1/etablissements` - Liste établissements (groupe)
- `GET /api/v1/etablissements/{id}/classes` - Classes par établissement

#### Sync API (Protected - Offline-first)
- `GET /api/v1/sync/bootstrap` - Télécharger données initiales (classes, élèves, attributions, templates, périodes, maxima, notes, présences)
- `POST /api/v1/sync/push` - Envoyer modifications locales (notes, présences)
- `GET /api/v1/sync/pull` - Récupérer mises à jour serveur
- `POST /api/v1/sync/resolve-conflicts` - Résoudre conflits

#### Webhooks (Public)
- `POST /api/v1/webhooks/payment` - Notifications paiements (optionnel)

---

## 4. Schéma de Base de Données Actuel

### Tables Principales (11 migrations existantes)

#### Core
- `utilisateurs` - Utilisateurs (admins, enseignants)
- `tenants` - Espaces clients SaaS (multi-tenancy)
- `etablissements` - Écoles ou sites scolaires (appartenant à un tenant)
- `roles` - Rôles utilisateurs
- `permissions` - Permissions

#### Référentiel Scolaire
- `ref_cycles` - Cycles (primaire, secondaire)
- `ref_degres` - Degrés
- `ref_niveaux` - Niveaux (1ère, 2ème, etc.)
- `ref_options` - Options (scientifique, littéraire)
- `ref_matieres` - Matières

#### Système de Templates
- `system_templates_bulletin` - Templates système (globaux, admin SaaS)
- `system_template_blocs` - Blocs templates système
- `system_template_nodes` - Nœuds templates système
- `system_template_periodes` - Périodes templates système
- `system_template_maxima` - Maximums templates système
- `modeles_bulletin` - Modèles annuels établissement
- `modele_blocs` - Blocs modèles annuels
- `modele_nodes` - Nœuds modèles annuels
- `modele_periodes` - Périodes modèles annuels
- `modele_maxima` - Maximums modèles annuels

#### Calendrier Scolaire
- `calendriers_scolaires` - Calendriers scolaires officiels
- `calendrier_periodes` - Périodes (trimestres, semestres)
- `annees_scolaires` - Années scolaires
- `annee_periode_mappings` - Mapping années/périodes

#### Classes & Modèles Annuels
- `classes` - Classes
- `titulaires_classes` - Titulaires classes
- `promotions_annuelles` - Promotions annuelles
- `tenant_cycles_actifs` - Cycles actifs par tenant

#### Élèves & Inscriptions
- `eleves` - Élèves
- `inscriptions` - Inscriptions
- `promotions_eleves` - Promotions élèves

#### Notes & Évaluations
- `notes` - Notes
- `notes_historiques` - Historique notes
- `evaluations_globales` - Évaluations globales
- `types_evaluations_globales` - Types évaluations
- `suivis_encodage_notes` - Suivi encodage
- `appreciations_bulletins` - Appreciations
- `baremes_appreciations` - Barèmes appréciations
- `baremes_mentions` - Barèmes mentions
- `calculation_rules` - Règles calcul

#### Bulletins
- `bulletins` - Bulletins
- `bulletin_bloc_values` - Valeurs blocs

#### Présence & Monitoring
- `presences_eleves` - Présences
- `verrouillages_periodes` - Verrouillage périodes

#### Signatures & Imports
- `signatures_enseignants` - Signatures enseignants
- `imports_fichiers` - Imports fichiers

#### Proclamations
- `proclamations` - Proclamations
- `proclamation_lignes` - Lignes proclamations

#### Autres
- `abonnements` - Abonnements SaaS
- `paiements` - Paiements
- `audit_logs` - Logs d'audit
- `system_blocs_speciaux` - Blocs spéciaux
- `system_layouts_bulletin` - Layouts bulletins

### Enums PHP à Créer
- `BulletinStatus` - Statuts bulletin (brouillon, généré, envoyé)
- `NoteStatus` - Statuts note (en_cours, validée, verrouillée)
- `PeriodeStatus` - Statuts période (ouverte, verrouillée)
- `EvaluationType` - Types évaluation (interrogation, devoir, examen)
- `ImportStatus` - Statuts import (en_cours, terminé, erreur)

---

## 5. Services Layer

### Services Globaux à Créer
- `TenantService` - Gestion multi-tenancy
- `ImageService` - Upload/optimisation logos/signatures
- `ExcelImportService` - Import Excel générique
- `ExcelExportService` - Export Excel générique
- `PdfGeneratorService` - Génération PDF
- `AuditLogService` - Logging activité
- `NotificationService` - Notifications (email/WhatsApp)

### Services par Domaine

#### Tenant (2 services)
- `TenantService` - Gestion multi-tenancy
- `TenantStatsService` - Statistiques établissement

#### SchoolYear (2 services)
- `OpenSchoolYearService` - Ouverture année scolaire
- `PeriodeService` - Gestion périodes/verrouillage

#### Template (2 services)
- `CloneSystemTemplateService` - Clone système (existe déjà)
- `TemplateActivationService` - Activation templates

#### Classe (2 services)
- `ClasseService` - CRUD classes
- `TitulaireService` - Gestion titulaires

#### Teacher (2 services)
- `TeacherService` - CRUD enseignants
- `AssignCourseTeacherService` - Attribution cours/enseignant

#### Student (2 services)
- `StudentService` - CRUD élèves
- `ImportStudentsService` - Import Excel élèves

#### Evaluation (3 services)
- `NoteEntryService` - Encodage notes
- `NoteImportService` - Import Excel notes
- `AppreciationService` - Gestion appréciations

#### Bulletin (2 services)
- `BulletinCalculationService` - Calculs bulletin (existe déjà)
- `BulletinGenerationService` - Génération PDF

#### Attendance (1 service)
- `AttendanceService` - Gestion présences

#### Sync (3 services)
- `SyncBootstrapService` - Bootstrap initial PWA
- `SyncPushService` - Réception modifications PWA
- `SyncPullService` - Envoi mises à jour PWA

#### Auth (2 services)
- `AuthService` - Authentification
- `PermissionService` - Gestion permissions

---

## 6. Middlewares Clés

- `auth:sanctum` - Authentification API
- `tenant` - Résolution par token Sanctum, slug ou domaine selon le contexte
- `tenant.active` - Vérification tenant actif
- `permission:*` - Vérification permissions Spatie
- `throttle:*` - Rate limiting par endpoint
- `periode.ouverte` - Vérification période ouverte
- `verrouillage.periode` - Vérification verrouillage

---

## 7. Qualité Code & Outils

### Static Analysis
- **PHPStan Level 5** - Analyse statique stricte
- Configuration `phpstan.neon` avec ignored errors pour IDE helpers

### Tests
- **Pest PHP 4.7** - Framework de tests moderne
- Testsuites: Unit + Feature
- Tests par domaine

### Formatage
- **Laravel Pint** - PSR-12 compliance
- Script: `vendor/bin/pint --dirty --format agent`

### Scripts Composer
- `composer setup` - Installation complète
- `composer dev` - Serveur + queue + Vite
- `composer test` - Exécution tests
- `composer pint` - Formatage code

---

## 8. Patterns Architecture

### API Resources
- Toutes les réponses API utilisent Laravel API Resources
- Standardisation format JSON via traits
- Versioning V1

### Form Requests
- Validation au niveau request
- Séparation logique validation par domaine

### Soft Deletes
- À ajouter progressivement Phase 2/3 sur données critiques (eleves, classes, bulletins, inscriptions)
- Pas de suppression permanente

### Foreign Keys
- Contraintes avec indexation
- Cascade deletes appropriés
- Scoped queries par tenant

### Activity Log
- Phase 1: Utiliser `audit_logs` existant (maison)
- Phase 5: Remplacer ou compléter par Spatie Activitylog si nécessaire
- Tracking par tenant et batch UUID

---

## 9. Fonctionnalités Métier

### Multi-tenancy
- Isolation complète par tenant (espace client SaaS)
- Un tenant peut avoir plusieurs établissements
- Slug unique par tenant
- Scoped queries par tenant
- Middleware de résolution automatique

### Abonnements SaaS
- Plans tarifaires (Free, Pro, Enterprise)
- Quotas par établissement
- Gestion paiements
- Limites fonctionnalités

### Bulletins
- Génération PDF automatique
- Templates officiels configurés par Super Admin
- Modèles annuels clonés pour les écoles
- Personnalisation école désactivée par défaut (bulletins officiels de l'État)
- Calculs automatiques (moyennes, rangs, mentions)
- Signatures enseignants
- Envoi batch (email/WhatsApp)

### Notes & Évaluations
- Encodage notes par classe/matière
- Import Excel bulk
- Suivi progression encodage
- Verrouillage périodes
- Historique modifications

### Classes
- Gestion classes par établissement
- Assignation titulaires
- Effectifs par classe
- Promotions annuelles

### Calendrier Scolaire
- Calendriers personnalisables
- Périodes configurables (trimestres, semestres)
- Verrouillage périodes
- Années scolaires

### Imports/Exports
- Import Excel élèves
- Import Excel notes
- Export Excel bulletins
- Tracking imports

---

## 10. Configuration

### Configs Spécifiques à Créer
- `bulletin.php` - Configuration génération bulletins
- `tenant.php` - Configuration multi-tenancy
- `import.php` - Configuration imports/exports
- `scramble.php` - Documentation API
- `notification.php` - Configuration notifications

### Environment
- Support Laravel Boost
- Queue configuration
- Cache configuration
- Multi-database support (optionnel)

---

## 11. Roadmap d'Implémentation Pragmatique

### Phase 1 — Valider le moteur (CRITIQUE)

**Objectif**: Prouver qu'un bulletin complet fonctionne de bout-en-bout

**Tâches**:
1. Installer packages Phase 1 (Sanctum, Permission, Excel, Browsershot)
2. Mettre les migrations complètes avec noms de tables corrects
3. Seeder les référentiels + données Commerciale 1ère
4. Créer structure `app/Domains/` minimale (seulement domaines critiques)
5. Créer les services essentiels:
   - `OpenSchoolYearService`
   - `CloneSystemTemplateService`
   - `AssignCourseTeacherService`
   - `ImportStudentsService`
   - `NoteEntryService`
   - `BulletinCalculationService`
6. Créer API minimale sous `/api/v1`
7. Implémenter multi-tenancy basique (middleware tenant)

**Test de validation OBLIGATOIRE**:
```text
1. Créer une école test
2. Ouvrir l'année scolaire 2026-2027
3. Créer la classe 1ère Commerciale A
4. Cloner le template système vers modèle annuel
5. Créer 5 élèves
6. Attribuer Français à un enseignant
7. Encoder les notes P1
8. Calculer le bulletin
9. Générer la proclamation
```

**Si ce test échoue → Arrêter et corriger avant de continuer.**

### Phase 2 — Encodage réel

**Objectif**: Rendre l'encodage notes utilisable en production

**Tâches**:
- Liste élèves par classe/cours/période
- Validation des maxima
- Suivi d'encodage (progression)
- Verrouillage période
- Historique des notes
- Présences de base
- Import Excel notes

**Packages à ajouter**:
```bash
composer require dedoc/scramble  # Documentation API (optionnel)
```

**Note**: `spatie/laravel-activitylog` ajouté Phase 5 si `audit_logs` maison devient insuffisant.

### Phase 3 — PDF et proclamation

**Objectif**: Génération documents officiels

**Tâches**:
- Preview bulletin HTML
- Génération PDF via Browsershot
- Liste de proclamation
- Calcul des rangs
- Génération des mentions
- Signatures enseignants
- Export PDF proclamation

### Phase 4 — PWA Offline-first

**Objectif**: Encodage notes/présences sans internet

**Frontend**:
- React + Vite
- Dexie.js pour IndexedDB
- TanStack Query pour API calls
- TanStack Table pour tableaux
- Service Worker pour offline

**Backend Laravel**:
- Créer domaine `Sync/`
- Implémenter services sync:
  - `SyncBootstrapService`
  - `SyncPushService`
  - `SyncPullService`
- Ajouter endpoints sync
- Créer conflict resolver

**Table locale PWA**:
```sql
CREATE TABLE sync_queue (
  uuid TEXT PRIMARY KEY,
  type TEXT NOT NULL,
  payload JSON NOT NULL,
  status TEXT DEFAULT 'pending',
  attempts INTEGER DEFAULT 0,
  created_at TEXT,
  synced_at TEXT
);
```

**Fonctionnalités offline**:
- Édition notes offline
- Présences offline
- Sync automatique quand connexion revient

### Phase 5 — Architecture avancée

**Objectif**: Industrialiser et scaler

**Tâches**:
1. Ajouter `spatie/laravel-data` pour DTOs structurés
2. Créer tous les services manquants par domaine
3. API Resources pour tous les modèles
4. Configurer PHPStan level 5
5. Écrire tests Pest par domaine
6. Intégration notifications (email/WhatsApp)
7. Dashboard admin avec analytics
8. Webhooks paiements
9. Optimisations performance (cache, queue)

---

## 12. Préparation Offline-first - UUIDs Locaux

Pour préparer la synchronisation PWA (Phase 4), certaines tables critiques devraient inclure un `local_uuid` :

```php
// Tables critiques pour sync offline (ajout via migration)
Schema::table('notes', function (Blueprint $table) {
    $table->uuid('local_uuid')->nullable()->unique()->after('id');
});

Schema::table('presences_eleves', function (Blueprint $table) {
    $table->uuid('local_uuid')->nullable()->unique()->after('id');
});

Schema::table('evaluations_globales', function (Blueprint $table) {
    $table->uuid('local_uuid')->nullable()->unique()->after('id');
});
```

**Plus tard (optionnel)**:
```php
Schema::table('eleves', function (Blueprint $table) {
    $table->uuid('local_uuid')->nullable()->unique()->after('id');
});

Schema::table('inscriptions', function (Blueprint $table) {
    $table->uuid('local_uuid')->nullable()->unique()->after('id');
});
```

**Pourquoi ?** En offline, le frontend peut créer/modifier une note avec un identifiant local avant que le serveur réponde. Le `local_uuid` permet de mapper les modifications offline aux enregistrements serveur lors de la synchronisation.

**Note**: Ceci peut être ajouté progressivement, pas nécessaire pour Phase 1.

---

## 13. Résumé des Packages à Installer

```bash
# Phase 1 - Core MVP (Indispensables)
composer require laravel/sanctum
composer require spatie/laravel-permission
composer require maatwebsite/excel
composer require spatie/browsershot

# Phase 2 - Après validation bulletin
composer require dedoc/scramble  # Documentation API (optionnel)

# Phase 5 - Architecture avancée (optionnel)
composer require spatie/laravel-data
composer require intervention/image-laravel
composer require --dev larastan/larastan
composer require --dev barryvdh/laravel-ide-helper
```

---

## 14. Frontend PWA - Stack Technique

### Framework & Build
- **React 18+** - Framework UI
- **Vite** - Build tool ultra-rapide
- **TypeScript** - Typage strict

### State & Data
- **Dexie.js** - IndexedDB wrapper (stockage offline)
- **TanStack Query (React Query)** - Server state management
- **Zustand** - Client state management

### UI Components
- **TailwindCSS v4** - Styling
- **shadcn/ui** - Composants UI modernes
- **TanStack Table** - Tableaux complexes (notes, présences)
- **Lucide React** - Icônes

### Offline & Sync
- **Service Worker** - Cache offline
- **sync_queue** - Queue mutations locales
- **Conflict Resolver** - Gestion conflits sync

### PWA Features
- **Manifest** - Installation app
- **Background Sync** - Sync automatique

**Note**: Push Notifications ajoutées Phase 5 (pas prioritaires pour MVP offline)

## Conclusion

Ce plan pragmatique évite le piège du "beau projet technique" et se concentre sur:

1. **Phase 1**: Valider le moteur - un bulletin complet de bout-en-bout
2. **Phase 2**: Encodage réel - outils de production pour les enseignants
3. **Phase 3**: PDF et proclamation - documents officiels
4. **Phase 4**: PWA offline-first - notes/présences sans internet
5. **Phase 5**: Architecture avancée - industrialisation et scalabilité

**Règle d'or**: Ne pas construire le château avant d'avoir validé une classe, une matière, une période, une note et un bulletin généré. C'est ce test qui va dire si BulletinLab tient debout.
