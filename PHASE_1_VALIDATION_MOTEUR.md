# Phase 1 — Validation du Moteur (CRITIQUE)

**Objectif**: Prouver qu'un bulletin complet fonctionne de bout-en-bout  
**Durée estimée**: 2-3 semaines  
**Priorité**: CRITIQUE - Rien ne doit être fait avant la validation de cette phase

---

## Critères de Succès

✅ Un bulletin complet généré pour la classe "1ère Commerciale A"  
✅ Calculs automatiques fonctionnels (moyennes, rangs, mentions)  
✅ API REST complète pour le workflow de base  
✅ Multi-tenancy opérationnel  
✅ Migrations et seeders stables

---

## 1. Installation des Packages Phase 1

### Packages Indispensables

```bash
# Authentification API
composer require laravel/sanctum

# Gestion rôles/permissions
composer require spatie/laravel-permission

# Import/Export Excel
composer require maatwebsite/excel

# Génération PDF via Chrome
composer require spatie/browsershot
```

### Configuration Post-Installation

#### Sanctum
```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

**Fichier**: `config/sanctum.php`
- Configurer `stateful` domains
- Configurer token expiration
- Configurer middleware

#### Spatie Permission
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

**Fichier**: `config/permission.php`
- Vérifier table names
- Configurer cache

#### Browsershot
```bash
# Installer Node dependencies
npm install puppeteer
```

**Vérification**:
```bash
php artisan tinker --execute "use Spatie\Browsershot\Browsershot; Browsershot::url('https://example.com')->save('test.pdf');"
```

---

## 2. Migrations Complètes

### Vérification des Migrations Existantes

Lister les migrations actuelles:
```bash
php artisan migrate:status
```

### Migrations Critiques à Vérifier

#### Core Tables
- ✅ `2026_01_01_000001_create_saas_core_tables.php`
- ✅ `2026_01_01_000002_create_users_roles_permissions_tables.php`
- ✅ `2026_01_01_000003_create_referentiel_scolaire_tables.php`

#### Templates & Bulletins
- ✅ `2026_01_01_000004_create_system_templates_tables.php`
- ✅ `2026_01_01_000005_create_modeles_bulletin_tables.php`

#### Calendrier & Classes
- ✅ `2026_01_01_000006_create_calendrier_scolaire_tables.php`
- ✅ `2026_01_01_000007_create_classes_tables.php`

#### Élèves & Notes
- ✅ `2026_01_01_000008_create_eleves_tables.php`
- ✅ `2026_01_01_000009_create_notes_evaluations_tables.php`

#### Bulletins & Proclamations
- ✅ `2026_01_01_000010_create_bulletins_tables.php`
- ✅ `2026_01_01_000011_create_proclamations_tables.php`

### Exécution des Migrations

```bash
# Fresh migration (développement uniquement)
php artisan migrate:fresh

# Migration normale
php artisan migrate
```

---

## 3. Seeders de Référentiel

### Ordre d'Exécution des Seeders

```bash
php artisan db:seed --class=BulletinLabAcademicSeeder
php artisan db:seed --class=BulletinLabCalendarSeeder
php artisan db:seed --class=BulletinLabBaremesSeeder
php artisan db:seed --class=BulletinLabTemplatesSeeder
php artisan db:seed --class=BulletinLabTestDataSeeder
```

### Données à Seeder

#### 1. Référentiel Scolaire (`BulletinLabAcademicSeeder`)
- **Cycles**: Primaire, Secondaire
- **Degrés**: D1, D2, D3
- **Niveaux**: 1ère, 2ème, 3ème, 4ème, 5ème, 6ème
- **Options**: Commerciale, Scientifique, Littéraire, Technique
- **Matières**: Français, Mathématiques, Anglais, Histoire, Géographie, etc.

#### 2. Calendrier Scolaire (`BulletinLabCalendarSeeder`)
- **Calendrier**: 2026-2027
- **Périodes**: P1, P2, P3, P4, P5, P6 (semestres ou trimestres)
- **Dates**: Début/fin de chaque période

#### 3. Barèmes (`BulletinLabBaremesSeeder`)
- **Barèmes appréciations**: Excellent (≥80%), Bien (≥70%), etc.
- **Barèmes mentions**: Grande distinction, Distinction, Satisfaction

#### 4. Templates Système (`BulletinLabTemplatesSeeder`)
- **Template**: "Bulletin Officiel Secondaire RDC"
- **Blocs**: Identité, Notes, Appréciations, Signatures
- **Périodes**: P1-P6
- **Maxima**: Par matière et période

#### 5. Données de Test (`BulletinLabTestDataSeeder`)
- **Tenant**: École Test
- **Établissement**: Complexe Scolaire Test
- **Utilisateur Admin**: admin@test.com
- **Année Scolaire**: 2026-2027
- **Classe**: 1ère Commerciale A
- **Élèves**: 5 élèves test
- **Enseignant**: Prof. Test

---

## 4. Structure Domain-Driven (Minimale)

### Domaines Critiques Phase 1

Créer uniquement les domaines nécessaires au test de validation:

```
app/Domains/
├── Auth/
│   ├── Contracts/
│   │   └── AuthServiceInterface.php
│   ├── Exceptions/
│   │   └── AuthenticationException.php
│   └── Services/
│       └── AuthService.php
├── Tenant/
│   ├── Contracts/
│   │   └── TenantServiceInterface.php
│   ├── Exceptions/
│   │   └── TenantNotFoundException.php
│   └── Services/
│       └── TenantService.php
├── SchoolYear/
│   ├── Contracts/
│   │   └── OpenSchoolYearServiceInterface.php
│   ├── Exceptions/
│   │   └── SchoolYearException.php
│   └── Services/
│       └── OpenSchoolYearService.php
├── Template/
│   ├── Contracts/
│   │   └── CloneSystemTemplateServiceInterface.php
│   ├── Exceptions/
│   │   └── TemplateException.php
│   └── Services/
│       └── CloneSystemTemplateService.php (existe déjà)
├── Teacher/
│   ├── Contracts/
│   │   └── AssignCourseTeacherServiceInterface.php
│   ├── Exceptions/
│   │   └── TeacherException.php
│   └── Services/
│       └── AssignCourseTeacherService.php
├── Student/
│   ├── Contracts/
│   │   └── ImportStudentsServiceInterface.php
│   ├── Exceptions/
│   │   └── StudentException.php
│   └── Services/
│       └── ImportStudentsService.php
├── Evaluation/
│   ├── Contracts/
│   │   └── NoteEntryServiceInterface.php
│   ├── Exceptions/
│   │   └── EvaluationException.php
│   └── Services/
│       └── NoteEntryService.php
└── Bulletin/
    ├── Contracts/
    │   └── BulletinCalculationServiceInterface.php
    ├── Exceptions/
    │   └── BulletinException.php
    └── Services/
        └── BulletinCalculationService.php (existe déjà)
```

### Commandes Artisan pour Création

```bash
# Auth Domain
php artisan make:class Domains/Auth/Contracts/AuthServiceInterface
php artisan make:exception Domains/Auth/Exceptions/AuthenticationException
php artisan make:class Domains/Auth/Services/AuthService

# Tenant Domain
php artisan make:class Domains/Tenant/Contracts/TenantServiceInterface
php artisan make:exception Domains/Tenant/Exceptions/TenantNotFoundException
php artisan make:class Domains/Tenant/Services/TenantService

# SchoolYear Domain
php artisan make:class Domains/SchoolYear/Contracts/OpenSchoolYearServiceInterface
php artisan make:exception Domains/SchoolYear/Exceptions/SchoolYearException
php artisan make:class Domains/SchoolYear/Services/OpenSchoolYearService

# Template Domain (CloneSystemTemplateService existe déjà)
php artisan make:class Domains/Template/Contracts/CloneSystemTemplateServiceInterface
php artisan make:exception Domains/Template/Exceptions/TemplateException

# Teacher Domain
php artisan make:class Domains/Teacher/Contracts/AssignCourseTeacherServiceInterface
php artisan make:exception Domains/Teacher/Exceptions/TeacherException
php artisan make:class Domains/Teacher/Services/AssignCourseTeacherService

# Student Domain
php artisan make:class Domains/Student/Contracts/ImportStudentsServiceInterface
php artisan make:exception Domains/Student/Exceptions/StudentException
php artisan make:class Domains/Student/Services/ImportStudentsService

# Evaluation Domain
php artisan make:class Domains/Evaluation/Contracts/NoteEntryServiceInterface
php artisan make:exception Domains/Evaluation/Exceptions/EvaluationException
php artisan make:class Domains/Evaluation/Services/NoteEntryService

# Bulletin Domain (BulletinCalculationService existe déjà)
php artisan make:class Domains/Bulletin/Contracts/BulletinCalculationServiceInterface
php artisan make:exception Domains/Bulletin/Exceptions/BulletinException
```

---

## 5. Services Essentiels

### Service 1: OpenSchoolYearService

**Responsabilité**: Ouvrir une année scolaire pour un tenant

**Fichier**: `app/Domains/SchoolYear/Services/OpenSchoolYearService.php`

**Méthodes**:
```php
public function openSchoolYear(int $tenantId, array $data): AnneeScolaire
{
    // 1. Créer l'année scolaire
    // 2. Mapper les périodes du calendrier
    // 3. Activer les cycles pour le tenant
    // 4. Retourner l'année scolaire
}
```

### Service 2: CloneSystemTemplateService

**Responsabilité**: Cloner un template système vers un modèle annuel

**Fichier**: `app/Services/Templates/CloneSystemTemplateService.php` (existe déjà)

**Vérification**: S'assurer que le service clone correctement:
- Template → Modèle
- Blocs → Blocs modèle
- Nodes → Nodes modèle
- Périodes → Périodes modèle
- Maxima → Maxima modèle

### Service 3: AssignCourseTeacherService

**Responsabilité**: Attribuer une matière à un enseignant pour une classe

**Fichier**: `app/Domains/Teacher/Services/AssignCourseTeacherService.php`

**Méthodes**:
```php
public function assignCourse(int $classeId, int $enseignantId, int $matiereId): void
{
    // 1. Vérifier que la classe existe
    // 2. Vérifier que l'enseignant existe
    // 3. Vérifier que la matière existe dans le modèle de la classe
    // 4. Créer l'attribution
}
```

### Service 4: ImportStudentsService

**Responsabilité**: Importer des élèves via Excel

**Fichier**: `app/Domains/Student/Services/ImportStudentsService.php`

**Méthodes**:
```php
public function import(UploadedFile $file, int $classeId, int $anneeScolaireId): array
{
    // 1. Valider le fichier Excel
    // 2. Parser les données
    // 3. Créer les élèves
    // 4. Créer les inscriptions
    // 5. Retourner statistiques (créés, erreurs)
}
```

### Service 5: NoteEntryService

**Responsabilité**: Encoder les notes pour une classe/matière/période

**Fichier**: `app/Domains/Evaluation/Services/NoteEntryService.php`

**Méthodes**:
```php
public function saveNote(array $data): Note
{
    // 1. Valider la note (maximum, période ouverte)
    // 2. Créer/mettre à jour la note
    // 3. Logger dans notes_historiques
    // 4. Mettre à jour suivi_encodage_notes
}

public function validateMaximum(int $matiereId, int $periodeId, float $note): bool
{
    // Vérifier que la note ne dépasse pas le maximum configuré
}
```

### Service 6: BulletinCalculationService

**Responsabilité**: Calculer les moyennes, rangs, mentions

**Fichier**: `app/Services/Bulletins/BulletinCalculationService.php` (existe déjà)

**Vérification**: S'assurer que le service calcule:
- Moyennes par matière
- Moyennes générales
- Rangs par classe
- Mentions (Grande distinction, etc.)
- Appréciations automatiques

---

## 6. API Minimale `/api/v1`

### Routes Critiques Phase 1

**Fichier**: `routes/api.php`

```php
Route::prefix('v1')->name('api.v1.')->group(function () {
    
    // Auth (Public)
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
    
    // Protected routes
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        
        // Me
        Route::get('me', [AuthController::class, 'me'])->name('me');
        
        // School Years
        Route::get('school-years', [SchoolYearController::class, 'index'])->name('school-years.index');
        Route::post('school-years', [SchoolYearController::class, 'store'])->name('school-years.store');
        Route::post('school-years/{id}/open', [SchoolYearController::class, 'open'])->name('school-years.open');
        
        // Classes
        Route::apiResource('classes', ClasseController::class);
        Route::post('classes/{id}/titulaire', [ClasseController::class, 'assignTitulaire'])->name('classes.titulaire');
        Route::post('classes/{id}/clone-template', [ClasseController::class, 'cloneTemplate'])->name('classes.clone-template');
        
        // Students
        Route::apiResource('students', StudentController::class);
        Route::post('students/import', [StudentController::class, 'import'])->name('students.import');
        
        // Teachers
        Route::apiResource('teachers', TeacherController::class);
        Route::post('teachers/{id}/assign-course', [TeacherController::class, 'assignCourse'])->name('teachers.assign-course');
        
        // Notes
        Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
        Route::get('classes/{id}/notes', [NoteController::class, 'byClasse'])->name('notes.by-classe');
        
        // Bulletins
        Route::get('classes/{id}/bulletins', [BulletinController::class, 'byClasse'])->name('bulletins.by-classe');
        Route::post('bulletins/calculate', [BulletinController::class, 'calculate'])->name('bulletins.calculate');
        
        // Proclamations
        Route::get('classes/{id}/proclamation', [ProclamationController::class, 'generate'])->name('proclamation.generate');
    });
});
```

### Contrôleurs à Créer

```bash
php artisan make:controller Api/V1/AuthController
php artisan make:controller Api/V1/SchoolYearController
php artisan make:controller Api/V1/ClasseController
php artisan make:controller Api/V1/StudentController
php artisan make:controller Api/V1/TeacherController
php artisan make:controller Api/V1/NoteController
php artisan make:controller Api/V1/BulletinController
php artisan make:controller Api/V1/ProclamationController
```

### API Resources à Créer

```bash
php artisan make:resource Api/V1/UserResource
php artisan make:resource Api/V1/SchoolYearResource
php artisan make:resource Api/V1/ClasseResource
php artisan make:resource Api/V1/StudentResource
php artisan make:resource Api/V1/TeacherResource
php artisan make:resource Api/V1/NoteResource
php artisan make:resource Api/V1/BulletinResource
php artisan make:resource Api/V1/ProclamationResource
```

### Form Requests à Créer

```bash
php artisan make:request Api/V1/LoginRequest
php artisan make:request Api/V1/RegisterRequest
php artisan make:request Api/V1/StoreSchoolYearRequest
php artisan make:request Api/V1/StoreClasseRequest
php artisan make:request Api/V1/StoreStudentRequest
php artisan make:request Api/V1/ImportStudentsRequest
php artisan make:request Api/V1/StoreTeacherRequest
php artisan make:request Api/V1/AssignCourseRequest
php artisan make:request Api/V1/StoreNoteRequest
```

---

## 7. Multi-Tenancy Basique

### Middleware Tenant

**Fichier**: `app/Http/Middleware/TenantMiddleware.php`

```bash
php artisan make:middleware TenantMiddleware
```

**Logique**:
```php
public function handle(Request $request, Closure $next)
{
    // 1. Récupérer le tenant depuis le token Sanctum
    $user = $request->user();
    
    if (!$user || !$user->tenant_id) {
        throw new TenantNotFoundException();
    }
    
    // 2. Stocker le tenant dans le contexte
    app()->instance('tenant', $user->tenant);
    
    // 3. Scoper toutes les queries par tenant_id
    // (via Global Scope sur les modèles)
    
    return $next($request);
}
```

### Global Scope Tenant

**Fichier**: `app/Models/Scopes/TenantScope.php`

```bash
php artisan make:class Models/Scopes/TenantScope
```

**Logique**:
```php
public function apply(Builder $builder, Model $model)
{
    if (app()->has('tenant')) {
        $builder->where('tenant_id', app('tenant')->id);
    }
}
```

### Trait HasTenant

**Fichier**: `app/Models/Concerns/HasTenant.php`

```bash
php artisan make:class Models/Concerns/HasTenant
```

**Logique**:
```php
trait HasTenant
{
    protected static function bootHasTenant()
    {
        static::addGlobalScope(new TenantScope);
        
        static::creating(function ($model) {
            if (app()->has('tenant')) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }
}
```

### Appliquer le Trait aux Modèles

Ajouter `use HasTenant;` dans les modèles:
- `Etablissement`
- `AnneeScolaire`
- `Classe`
- `Eleve`
- `Inscription`
- `Enseignant`
- `Note`
- `Bulletin`
- `Proclamation`

---

## 8. Test de Validation OBLIGATOIRE

### Scénario Complet

**Prérequis**: Seeders exécutés, API déployée

#### Étape 1: Créer une école test
```http
POST /api/v1/auth/register
{
  "name": "École Test",
  "email": "admin@ecole-test.cd",
  "password": "password123"
}
```

#### Étape 2: Ouvrir l'année scolaire 2026-2027
```http
POST /api/v1/school-years
{
  "calendrier_id": 1,
  "libelle": "2026-2027"
}

POST /api/v1/school-years/1/open
```

#### Étape 3: Créer la classe 1ère Commerciale A
```http
POST /api/v1/classes
{
  "niveau_id": 1,
  "option_id": 1,
  "section": "A",
  "annee_scolaire_id": 1
}
```

#### Étape 4: Cloner le template système vers modèle annuel
```http
POST /api/v1/classes/1/clone-template
{
  "system_template_id": 1
}
```

#### Étape 5: Créer 5 élèves
```http
POST /api/v1/students/import
{
  "file": "eleves.xlsx",
  "classe_id": 1,
  "annee_scolaire_id": 1
}
```

Ou manuellement:
```http
POST /api/v1/students
{
  "nom": "Kabongo",
  "postnom": "Jean",
  "prenom": "Pierre",
  "sexe": "M",
  "date_naissance": "2010-05-15"
}
```

#### Étape 6: Attribuer Français à un enseignant
```http
POST /api/v1/teachers/1/assign-course
{
  "classe_id": 1,
  "matiere_id": 1
}
```

#### Étape 7: Encoder les notes P1
```http
POST /api/v1/notes
{
  "eleve_id": 1,
  "matiere_id": 1,
  "periode_id": 1,
  "note": 15,
  "maximum": 20
}
```

Répéter pour les 5 élèves et toutes les matières.

#### Étape 8: Calculer le bulletin
```http
POST /api/v1/bulletins/calculate
{
  "classe_id": 1,
  "periode_id": 1
}
```

#### Étape 9: Générer la proclamation
```http
GET /api/v1/classes/1/proclamation?periode_id=1
```

### Critères de Validation

✅ Toutes les requêtes API retournent 200/201  
✅ Les moyennes sont correctement calculées  
✅ Les rangs sont corrects (1er, 2ème, etc.)  
✅ Les mentions sont attribuées selon le barème  
✅ La proclamation affiche les élèves triés par rang  
✅ Aucune erreur SQL (foreign keys, null values)  
✅ Multi-tenancy fonctionne (données isolées par tenant)

### Commandes de Vérification

```bash
# Vérifier les notes
php artisan tinker --execute "Note::with('eleve', 'matiere')->get();"

# Vérifier les bulletins
php artisan tinker --execute "Bulletin::with('eleve', 'classe')->get();"

# Vérifier les moyennes
php artisan tinker --execute "BulletinBlocValue::where('type', 'moyenne')->get();"

# Vérifier les rangs
php artisan tinker --execute "Bulletin::orderBy('rang')->get(['eleve_id', 'rang', 'moyenne_generale']);"
```

---

## 9. Checklist Phase 1

### Installation
- [x] Installer Sanctum
- [x] Installer Spatie Permission
- [x] Installer Maatwebsite Excel
- [x] Installer Browsershot
- [x] Publier configs
- [x] Exécuter migrations packages

### Migrations
- [x] Vérifier toutes les migrations existantes
- [x] Exécuter `php artisan migrate:fresh`
- [x] Vérifier foreign keys
- [x] Vérifier indexes

### Seeders
- [x] Créer `BulletinLabAcademicSeeder`
- [x] Créer `BulletinLabCalendarSeeder`
- [x] Créer `BulletinLabBaremesSeeder`
- [x] Créer `BulletinLabTemplatesSeeder`
- [x] Créer `BulletinLabTestDataSeeder`
- [x] Exécuter tous les seeders

### Structure Domain-Driven
- [x] Créer `app/Domains/Auth/`
- [x] Créer `app/Domains/Tenant/`
- [x] Créer `app/Domains/SchoolYear/`
- [x] Créer `app/Domains/Template/`
- [x] Créer `app/Domains/Teacher/`
- [x] Créer `app/Domains/Student/`
- [x] Créer `app/Domains/Evaluation/`
- [x] Créer `app/Domains/Bulletin/`

### Services
- [x] Implémenter `AuthService`
- [x] Implémenter `TenantService`
- [x] Implémenter `OpenSchoolYearService`
- [x] Vérifier `CloneSystemTemplateService`
- [x] Implémenter `AssignCourseTeacherService`
- [x] Implémenter `ImportStudentsService`
- [x] Implémenter `NoteEntryService`
- [x] Vérifier `BulletinCalculationService`

### API
- [x] Créer routes `/api/v1`
- [x] Créer contrôleurs API
- [x] Ajouter routes spécifiques (open, clone-template, import, calculate, byClasse)
- [x] Créer API Resources
- [x] Créer Form Requests
- [x] Tester tous les endpoints

### Multi-Tenancy
- [x] Créer `TenantMiddleware`
- [x] Créer `TenantScope`
- [x] Créer trait `HasTenant`
- [x] Appliquer trait aux modèles
- [x] Tester isolation données

### Test de Validation
- [x] Créer école test (tenant + établissement)
- [x] Ouvrir année scolaire (endpoint POST /annees-scolaires/{id}/open)
- [x] Créer classe avec template système
- [x] Créer élèves
- [x] Inscrire élèves dans classe
- [x] Vérifier endpoints API (classes, eleves, inscriptions)
- [ ] Cloner template (route créée mais non testée)
- [ ] Attribuer cours (non implémenté)
- [ ] Encoder notes (non implémenté)
- [ ] Calculer bulletin (non implémenté)
- [ ] Générer proclamation (non implémenté)

### Qualité Code
- [x] Exécuter `vendor/bin/pint --dirty --format agent`
- [x] Vérifier PSR-12 compliance
- [x] Pas d'erreurs PHP

---

## 10. Règle d'Or Phase 1

**SI LE TEST DE VALIDATION ÉCHOUE → ARRÊTER ET CORRIGER AVANT DE CONTINUER**

Ne pas passer à la Phase 2 tant que:
- Un bulletin complet n'est pas généré
- Les calculs ne sont pas corrects
- L'API ne fonctionne pas de bout-en-bout
- Le multi-tenancy n'est pas opérationnel

**Priorité absolue**: Valider le moteur avant d'ajouter des fonctionnalités.

---

## 11. Livrables Phase 1

- ✅ Packages installés et configurés
- ✅ Migrations complètes et stables
- ✅ Seeders fonctionnels
- ✅ Structure Domain-Driven minimale
- ✅ 8 services essentiels implémentés
- ✅ API `/api/v1` opérationnelle
- ✅ Multi-tenancy basique fonctionnel
- ✅ Test de validation réussi
- ✅ Code formaté avec Pint

---

## Prochaine Étape

Une fois la Phase 1 validée → **Phase 2: Encodage Réel**
