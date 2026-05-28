# Phase 5 — Architecture Avancée

**Objectif**: Industrialiser et scaler l'application  
**Durée estimée**: 3-4 semaines  
**Prérequis**: Phases 1, 2, 3 et 4 validées  
**Priorité**: MOYENNE - Optimisations et fonctionnalités avancées

---

## Critères de Succès

✅ DTOs structurés avec Spatie Laravel Data  
✅ Tous les services par domaine implémentés  
✅ API Resources pour tous les modèles  
✅ PHPStan level 5 sans erreurs  
✅ Tests Pest complets (>80% coverage)  
✅ Notifications email/WhatsApp fonctionnelles  
✅ Dashboard admin avec analytics  
✅ Webhooks paiements intégrés  
✅ Optimisations performance (cache, queue)  
✅ Soft deletes sur données critiques

---

## 1. Packages Phase 5

### Installation

```bash
# DTOs structurés
composer require spatie/laravel-data

# Manipulation images (logos, signatures)
composer require intervention/image-laravel

# Static analysis
composer require --dev larastan/larastan

# IDE autocomplete
composer require --dev barryvdh/laravel-ide-helper

# Activity log (si audit_logs maison insuffisant)
composer require spatie/laravel-activitylog
```

### Configuration

```bash
# Spatie Data
php artisan vendor:publish --provider="Spatie\LaravelData\LaravelDataServiceProvider"

# Intervention Image
php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"

# Larastan
php artisan vendor:publish --provider="Larastan\Larastan\ServiceProvider"

# IDE Helper
php artisan ide-helper:generate
php artisan ide-helper:models --nowrite
php artisan ide-helper:meta

# Activity Log
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
php artisan migrate
```

---

## 2. DTOs avec Spatie Laravel Data

### Structure DTOs

```
app/Data/
├── Auth/
│   ├── LoginData.php
│   └── RegisterData.php
├── Classe/
│   ├── ClasseData.php
│   └── CreateClasseData.php
├── Student/
│   ├── StudentData.php
│   ├── CreateStudentData.php
│   └── ImportStudentData.php
├── Teacher/
│   ├── TeacherData.php
│   └── AssignCourseData.php
├── Evaluation/
│   ├── NoteData.php
│   ├── CreateNoteData.php
│   └── ImportNoteData.php
├── Bulletin/
│   ├── BulletinData.php
│   └── GenerateBulletinData.php
└── Sync/
    ├── SyncBootstrapData.php
    ├── SyncPushData.php
    └── SyncPullData.php
```

### Exemple: NoteData

**Fichier**: `app/Data/Evaluation/NoteData.php`

```php
namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;

class NoteData extends Data
{
    public function __construct(
        public ?int $id,
        
        #[Required]
        #[Numeric]
        public int $eleve_id,
        
        #[Required]
        #[Numeric]
        public int $matiere_id,
        
        #[Required]
        #[Numeric]
        public int $periode_id,
        
        #[Required]
        #[Numeric]
        public int $classe_id,
        
        #[Required]
        #[Numeric]
        #[Min(0)]
        #[Max(20)]
        public float $note,
        
        #[Required]
        #[Numeric]
        public float $maximum,
        
        public ?string $created_at,
        public ?string $updated_at,
    ) {}
}
```

### Exemple: CreateNoteData

**Fichier**: `app/Data/Evaluation/CreateNoteData.php`

```php
namespace App\Data\Evaluation;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;

class CreateNoteData extends Data
{
    public function __construct(
        #[Required]
        #[Numeric]
        public int $eleve_id,
        
        #[Required]
        #[Numeric]
        public int $matiere_id,
        
        #[Required]
        #[Numeric]
        public int $periode_id,
        
        #[Required]
        #[Numeric]
        public int $classe_id,
        
        #[Required]
        #[Numeric]
        #[Min(0)]
        public float $note,
    ) {}
}
```

### Utilisation dans Contrôleurs

**Avant**:
```php
public function store(StoreNoteRequest $request)
{
    $validated = $request->validated();
    // ...
}
```

**Après**:
```php
public function store(CreateNoteData $data)
{
    $note = app(NoteEntryService::class)->saveNote($data);
    return NoteData::from($note);
}
```

---

## 3. Services Complets par Domaine

### Services Manquants à Créer

#### Tenant Domain
```bash
php artisan make:class Domains/Tenant/Services/TenantStatsService
```

**Méthodes**:
- `getStatistics(int $tenantId): array`
- `getUsageMetrics(int $tenantId): array`
- `checkQuotas(int $tenantId): array`

#### SchoolYear Domain
```bash
php artisan make:class Domains/SchoolYear/Services/PeriodeService
```

**Méthodes**:
- `createPeriode(array $data): ModelePeriode`
- `updatePeriode(int $id, array $data): ModelePeriode`
- `deletePeriode(int $id): void`

#### Template Domain
```bash
php artisan make:class Domains/Template/Services/TemplateActivationService
```

**Méthodes**:
- `activate(int $modeleId, int $classeId): void`
- `deactivate(int $modeleId, int $classeId): void`
- `isActive(int $modeleId, int $classeId): bool`

#### Classe Domain
```bash
php artisan make:class Domains/Classe/Services/ClasseService
php artisan make:class Domains/Classe/Services/TitulaireService
```

**ClasseService**:
- `create(array $data): Classe`
- `update(int $id, array $data): Classe`
- `delete(int $id): void`
- `getStatistics(int $classeId): array`

**TitulaireService**:
- `assign(int $classeId, int $enseignantId): void`
- `remove(int $classeId): void`
- `getTitulaire(int $classeId): ?Enseignant`

#### Teacher Domain
```bash
php artisan make:class Domains/Teacher/Services/TeacherService
```

**Méthodes**:
- `create(array $data): Enseignant`
- `update(int $id, array $data): Enseignant`
- `delete(int $id): void`
- `getCourses(int $enseignantId): Collection`

#### Student Domain
```bash
php artisan make:class Domains/Student/Services/StudentService
```

**Méthodes**:
- `create(array $data): Eleve`
- `update(int $id, array $data): Eleve`
- `delete(int $id): void`
- `getInscriptions(int $eleveId): Collection`

#### Evaluation Domain
```bash
php artisan make:class Domains/Evaluation/Services/AppreciationService
```

**Méthodes**:
- `generate(int $bulletinId): string`
- `save(int $bulletinId, string $appreciation): void`
- `getBareme(float $pourcentage): string`

#### Bulletin Domain
```bash
php artisan make:class Domains/Bulletin/Services/BulletinGenerationService
```

**Méthodes**:
- `generateForClasse(int $classeId, int $periodeId): Collection`
- `generateForEleve(int $eleveId, int $periodeId): Bulletin`
- `regenerate(int $bulletinId): Bulletin`

#### ImportExport Domain
```bash
php artisan make:class Domains/ImportExport/Services/ExcelImportService
php artisan make:class Domains/ImportExport/Services/ExcelExportService
```

**ExcelImportService**:
- `import(UploadedFile $file, string $type): array`
- `validate(array $data, string $type): array`
- `trackImport(int $importId): ImportFichier`

**ExcelExportService**:
- `exportNotes(int $classeId, int $periodeId): string`
- `exportBulletins(int $classeId, int $periodeId): string`
- `exportProclamation(int $proclamationId): string`

---

## 4. API Resources Complets

### Resources à Créer

```bash
# Auth
php artisan make:resource Api/V1/TenantResource

# Academic
php artisan make:resource Api/V1/CycleResource
php artisan make:resource Api/V1/DegreResource
php artisan make:resource Api/V1/NiveauResource
php artisan make:resource Api/V1/OptionResource
php artisan make:resource Api/V1/MatiereResource

# Template
php artisan make:resource Api/V1/SystemTemplateResource
php artisan make:resource Api/V1/ModeleBulletinResource

# Calendar
php artisan make:resource Api/V1/CalendrierScolaireResource
php artisan make:resource Api/V1/PeriodeResource

# Evaluation
php artisan make:resource Api/V1/EvaluationResource
php artisan make:resource Api/V1/AppreciationResource

# Attendance
php artisan make:resource Api/V1/PresenceResource
php artisan make:resource Api/V1/AttendanceStatsResource

# Import/Export
php artisan make:resource Api/V1/ImportFichierResource
```

### Exemple: StudentResource

**Fichier**: `app/Http/Resources/Api/V1/StudentResource.php`

```php
namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom' => $this->nom,
            'postnom' => $this->postnom,
            'prenom' => $this->prenom,
            'nom_complet' => $this->nom_complet,
            'sexe' => $this->sexe,
            'date_naissance' => $this->date_naissance,
            'lieu_naissance' => $this->lieu_naissance,
            'email_parent' => $this->email_parent,
            'telephone_parent' => $this->telephone_parent,
            'adresse' => $this->adresse,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relations (when loaded)
            'inscriptions' => InscriptionResource::collection($this->whenLoaded('inscriptions')),
            'notes' => NoteResource::collection($this->whenLoaded('notes')),
            'presences' => PresenceResource::collection($this->whenLoaded('presences')),
        ];
    }
}
```

---

## 5. PHPStan Level 5

### Configuration

**Fichier**: `phpstan.neon`

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 5
    paths:
        - app
    
    excludePaths:
        - app/Console/Kernel.php
    
    ignoreErrors:
        - '#Unsafe usage of new static#'
        - '#PHPDoc tag @var#'
    
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
```

### Exécution

```bash
vendor/bin/phpstan analyse --memory-limit=2G
```

### Corrections Typiques

#### Avant:
```php
public function getClasse($id)
{
    return Classe::find($id);
}
```

#### Après:
```php
public function getClasse(int $id): ?Classe
{
    return Classe::find($id);
}
```

---

## 6. Tests Pest Complets

### Architecture Tests

**Fichier**: `tests/Architecture/ArchitectureTest.php`

```php
use Pest\Arch\Arch;

arch('models should extend Eloquent Model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('services should have interfaces')
    ->expect('App\Domains\*\Services')
    ->toHaveMethod('__construct');

arch('controllers should extend base controller')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller');

arch('exceptions should extend base exception')
    ->expect('App\Domains\*\Exceptions')
    ->toExtend('Exception');

arch('no debugging functions')
    ->expect(['dd', 'dump', 'var_dump', 'print_r'])
    ->not->toBeUsed();
```

### Feature Tests par Domaine

```bash
# Auth
php artisan make:test --pest Feature/Auth/LoginTest
php artisan make:test --pest Feature/Auth/RegisterTest
php artisan make:test --pest Feature/Auth/PermissionTest

# Tenant
php artisan make:test --pest Feature/Tenant/TenantIsolationTest
php artisan make:test --pest Feature/Tenant/TenantStatsTest

# SchoolYear
php artisan make:test --pest Feature/SchoolYear/OpenSchoolYearTest
php artisan make:test --pest Feature/SchoolYear/PeriodeManagementTest

# Template
php artisan make:test --pest Feature/Template/CloneTemplateTest
php artisan make:test --pest Feature/Template/TemplateActivationTest

# Classe
php artisan make:test --pest Feature/Classe/ClasseCrudTest
php artisan make:test --pest Feature/Classe/TitulaireAssignmentTest

# Student
php artisan make:test --pest Feature/Student/StudentCrudTest
php artisan make:test --pest Feature/Student/StudentImportTest

# Teacher
php artisan make:test --pest Feature/Teacher/TeacherCrudTest
php artisan make:test --pest Feature/Teacher/CourseAssignmentTest

# Evaluation
php artisan make:test --pest Feature/Evaluation/NoteEncodingTest
php artisan make:test --pest Feature/Evaluation/NoteValidationTest
php artisan make:test --pest Feature/Evaluation/AppreciationTest

# Bulletin
php artisan make:test --pest Feature/Bulletin/BulletinCalculationTest
php artisan make:test --pest Feature/Bulletin/BulletinGenerationTest
php artisan make:test --pest Feature/Bulletin/RankCalculationTest

# Attendance
php artisan make:test --pest Feature/Attendance/AttendanceMarkingTest
php artisan make:test --pest Feature/Attendance/AttendanceStatsTest
```

### Unit Tests

```bash
# Services
php artisan make:test --pest Unit/Services/NoteEntryServiceTest
php artisan make:test --pest Unit/Services/BulletinCalculationServiceTest
php artisan make:test --pest Unit/Services/RankCalculationServiceTest
php artisan make:test --pest Unit/Services/MentionServiceTest

# Helpers
php artisan make:test --pest Unit/Helpers/DateHelperTest
php artisan make:test --pest Unit/Helpers/MathHelperTest
```

### Exemple: BulletinCalculationTest

```php
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Note;
use App\Domains\Bulletin\Services\BulletinCalculationService;

beforeEach(function () {
    $this->service = app(BulletinCalculationService::class);
});

test('bulletin calculation computes correct average', function () {
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    
    Note::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_id' => $classe->id,
        'note' => 15,
        'maximum' => 20,
    ]);
    
    Note::factory()->create([
        'eleve_id' => $eleve->id,
        'classe_id' => $classe->id,
        'note' => 18,
        'maximum' => 20,
    ]);
    
    $this->service->calculate($classe->id, 1);
    
    $bulletin = Bulletin::where('eleve_id', $eleve->id)->first();
    
    expect($bulletin->moyenne_generale)->toBe(16.5);
});

test('bulletin calculation handles missing notes', function () {
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    
    $this->service->calculate($classe->id, 1);
    
    $bulletin = Bulletin::where('eleve_id', $eleve->id)->first();
    
    expect($bulletin)->toBeNull();
});
```

### Coverage Report

```bash
php artisan test --coverage --min=80
```

---

## 7. Notifications Email/WhatsApp

### Service: NotificationService

**Fichier**: `app/Domains/Notification/Services/NotificationService.php`

```bash
php artisan make:class Domains/Notification/Services/NotificationService
```

**Méthodes**:
```php
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

public function sendBulletinEmail(Bulletin $bulletin): void
{
    $email = $bulletin->eleve->email_parent;
    
    if (!$email) {
        throw new NoEmailException();
    }
    
    Mail::to($email)->send(new BulletinMail($bulletin));
}

public function sendBulletinWhatsApp(Bulletin $bulletin): void
{
    $phone = $bulletin->eleve->telephone_parent;
    
    if (!$phone) {
        throw new NoPhoneException();
    }
    
    $message = "Bonjour, le bulletin de {$bulletin->eleve->nom_complet} est disponible. "
             . "Moyenne: {$bulletin->moyenne_generale}/20. "
             . "Téléchargez-le ici: " . route('bulletins.download', $bulletin->id);
    
    $this->sendWhatsAppMessage($phone, $message);
}

private function sendWhatsAppMessage(string $phone, string $message): void
{
    // Using Twilio or similar service
    Http::post('https://api.twilio.com/2010-04-01/Accounts/{AccountSid}/Messages.json', [
        'To' => "whatsapp:{$phone}",
        'From' => "whatsapp:" . config('services.twilio.whatsapp_number'),
        'Body' => $message,
    ])->throw();
}

public function sendBatch(array $bulletinIds, string $channel = 'email'): array
{
    $results = [
        'sent' => 0,
        'failed' => 0,
        'errors' => [],
    ];
    
    foreach ($bulletinIds as $bulletinId) {
        try {
            $bulletin = Bulletin::findOrFail($bulletinId);
            
            if ($channel === 'email') {
                $this->sendBulletinEmail($bulletin);
            } elseif ($channel === 'whatsapp') {
                $this->sendBulletinWhatsApp($bulletin);
            }
            
            $results['sent']++;
        } catch (\Exception $e) {
            $results['failed']++;
            $results['errors'][] = [
                'bulletin_id' => $bulletinId,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    return $results;
}
```

### Configuration

**Fichier**: `config/services.php`

```php
'twilio' => [
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'whatsapp_number' => env('TWILIO_WHATSAPP_NUMBER'),
],
```

---

## 8. Dashboard Admin avec Analytics

### Contrôleur

```bash
php artisan make:controller Api/V1/DashboardController
```

**Méthodes**:
```php
public function index(Request $request)
{
    $tenantId = auth()->user()->tenant_id;
    
    $stats = [
        'total_eleves' => Eleve::count(),
        'total_classes' => Classe::count(),
        'total_enseignants' => Enseignant::count(),
        'total_bulletins' => Bulletin::count(),
        
        'bulletins_generes_ce_mois' => Bulletin::whereMonth('created_at', now()->month)->count(),
        'notes_encodees_ce_mois' => Note::whereMonth('created_at', now()->month)->count(),
        
        'taux_encodage' => $this->getTauxEncodage(),
        'taux_reussite' => $this->getTauxReussite(),
        
        'top_classes' => $this->getTopClasses(),
        'mentions_distribution' => $this->getMentionsDistribution(),
    ];
    
    return response()->json($stats);
}

private function getTauxEncodage(): float
{
    $totalNotes = SuiviEncodageNote::sum('total_eleves');
    $notesEncodees = SuiviEncodageNote::sum('notes_encodees');
    
    return $totalNotes > 0 ? ($notesEncodees / $totalNotes) * 100 : 0;
}

private function getTauxReussite(): float
{
    $totalBulletins = Bulletin::count();
    $bulletinsReussis = Bulletin::where('mention', '!=', 'Échec')->count();
    
    return $totalBulletins > 0 ? ($bulletinsReussis / $totalBulletins) * 100 : 0;
}

private function getTopClasses(): array
{
    return Bulletin::selectRaw('classe_id, AVG(moyenne_generale) as moyenne')
        ->groupBy('classe_id')
        ->orderByDesc('moyenne')
        ->limit(5)
        ->with('classe')
        ->get()
        ->map(fn($b) => [
            'classe' => $b->classe->nom_complet,
            'moyenne' => round($b->moyenne, 2),
        ])
        ->toArray();
}

private function getMentionsDistribution(): array
{
    return Bulletin::selectRaw('mention, COUNT(*) as count')
        ->groupBy('mention')
        ->get()
        ->mapWithKeys(fn($b) => [$b->mention => $b->count])
        ->toArray();
}
```

---

## 9. Webhooks Paiements

### Service: PaymentWebhookService

**Fichier**: `app/Domains/Payment/Services/PaymentWebhookService.php`

```bash
php artisan make:class Domains/Payment/Services/PaymentWebhookService
```

**Méthodes**:
```php
public function handleWebhook(array $payload): void
{
    $event = $payload['event'];
    
    match($event) {
        'payment.succeeded' => $this->handlePaymentSucceeded($payload),
        'payment.failed' => $this->handlePaymentFailed($payload),
        'subscription.created' => $this->handleSubscriptionCreated($payload),
        'subscription.cancelled' => $this->handleSubscriptionCancelled($payload),
        default => throw new UnknownWebhookEventException($event),
    };
}

private function handlePaymentSucceeded(array $payload): void
{
    $paiement = Paiement::create([
        'tenant_id' => $payload['tenant_id'],
        'montant' => $payload['amount'],
        'devise' => $payload['currency'],
        'statut' => 'reussi',
        'reference' => $payload['reference'],
        'methode' => $payload['method'],
        'date_paiement' => now(),
    ]);
    
    // Update subscription
    $abonnement = Abonnement::where('tenant_id', $payload['tenant_id'])->first();
    $abonnement->update([
        'statut' => 'actif',
        'date_fin' => now()->addMonth(),
    ]);
}

private function handlePaymentFailed(array $payload): void
{
    Paiement::create([
        'tenant_id' => $payload['tenant_id'],
        'montant' => $payload['amount'],
        'devise' => $payload['currency'],
        'statut' => 'echoue',
        'reference' => $payload['reference'],
        'methode' => $payload['method'],
        'motif_echec' => $payload['reason'],
    ]);
}
```

### Contrôleur Webhook

```bash
php artisan make:controller Api/V1/WebhookController
```

**Méthodes**:
```php
public function payment(Request $request)
{
    // Verify signature
    $signature = $request->header('X-Webhook-Signature');
    
    if (!$this->verifySignature($request->getContent(), $signature)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }
    
    app(PaymentWebhookService::class)->handleWebhook($request->all());
    
    return response()->json(['status' => 'success']);
}

private function verifySignature(string $payload, string $signature): bool
{
    $secret = config('services.payment.webhook_secret');
    $computed = hash_hmac('sha256', $payload, $secret);
    
    return hash_equals($computed, $signature);
}
```

---

## 10. Optimisations Performance

### Cache

**Fichier**: `app/Domains/Cache/Services/CacheService.php`

```php
use Illuminate\Support\Facades\Cache;

public function cacheReferentiel(): void
{
    Cache::remember('referentiel.cycles', 3600, fn() => RefCycle::all());
    Cache::remember('referentiel.degres', 3600, fn() => RefDegre::all());
    Cache::remember('referentiel.niveaux', 3600, fn() => RefNiveau::all());
    Cache::remember('referentiel.options', 3600, fn() => RefOption::all());
    Cache::remember('referentiel.matieres', 3600, fn() => RefMatiere::all());
}

public function cacheSystemTemplates(): void
{
    Cache::remember('templates.system', 3600, fn() => 
        SystemTemplateBulletin::with('blocs', 'periodes', 'maxima')->get()
    );
}

public function clearTenantCache(int $tenantId): void
{
    Cache::tags(["tenant:{$tenantId}"])->flush();
}
```

### Queue Jobs

**Fichier**: `app/Jobs/GenerateBulletinsBatchJob.php`

```bash
php artisan make:job GenerateBulletinsBatchJob
```

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateBulletinsBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $classeId,
        public int $periodeId,
    ) {}

    public function handle(): void
    {
        $bulletins = Bulletin::where('classe_id', $this->classeId)
            ->where('periode_id', $this->periodeId)
            ->get();
        
        foreach ($bulletins as $bulletin) {
            GenerateBulletinPdfJob::dispatch($bulletin->id);
        }
    }
}
```

### Eager Loading

**Avant**:
```php
$bulletins = Bulletin::all();
foreach ($bulletins as $bulletin) {
    echo $bulletin->eleve->nom_complet; // N+1 query
}
```

**Après**:
```php
$bulletins = Bulletin::with('eleve', 'classe', 'periode')->get();
foreach ($bulletins as $bulletin) {
    echo $bulletin->eleve->nom_complet; // 1 query
}
```

---

## 11. Soft Deletes

### Migration

```bash
php artisan make:migration add_soft_deletes_to_critical_tables
```

**Fichier**: `database/migrations/xxxx_add_soft_deletes_to_critical_tables.php`

```php
public function up()
{
    Schema::table('eleves', function (Blueprint $table) {
        $table->softDeletes();
    });
    
    Schema::table('classes', function (Blueprint $table) {
        $table->softDeletes();
    });
    
    Schema::table('bulletins', function (Blueprint $table) {
        $table->softDeletes();
    });
    
    Schema::table('inscriptions', function (Blueprint $table) {
        $table->softDeletes();
    });
    
    Schema::table('notes', function (Blueprint $table) {
        $table->softDeletes();
    });
}
```

### Modèles

Ajouter `use SoftDeletes;` dans les modèles:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Eleve extends Model
{
    use SoftDeletes;
    
    protected $dates = ['deleted_at'];
}
```

---

## 12. Checklist Phase 5

### Packages
- [ ] Installer Spatie Laravel Data
- [ ] Installer Intervention Image
- [ ] Installer Larastan
- [ ] Installer IDE Helper
- [ ] Installer Activity Log (optionnel)

### DTOs
- [ ] Créer DTOs pour Auth
- [ ] Créer DTOs pour Classe
- [ ] Créer DTOs pour Student
- [ ] Créer DTOs pour Teacher
- [ ] Créer DTOs pour Evaluation
- [ ] Créer DTOs pour Bulletin
- [ ] Créer DTOs pour Sync

### Services
- [ ] Implémenter tous les services manquants
- [ ] Ajouter interfaces pour tous les services
- [ ] Documenter méthodes publiques

### API Resources
- [ ] Créer resources pour tous les modèles
- [ ] Standardiser format JSON
- [ ] Tester toutes les resources

### PHPStan
- [ ] Configurer PHPStan level 5
- [ ] Corriger toutes les erreurs
- [ ] Intégrer dans CI/CD

### Tests
- [ ] Écrire tests architecture
- [ ] Écrire tests feature par domaine
- [ ] Écrire tests unit pour services
- [ ] Atteindre >80% coverage

### Notifications
- [ ] Service notifications email
- [ ] Service notifications WhatsApp
- [ ] Tests notifications

### Dashboard
- [ ] Contrôleur dashboard
- [ ] Statistiques temps réel
- [ ] Analytics avancées

### Webhooks
- [ ] Service webhooks paiements
- [ ] Vérification signatures
- [ ] Tests webhooks

### Performance
- [ ] Implémenter cache
- [ ] Créer queue jobs
- [ ] Optimiser queries (eager loading)
- [ ] Tests performance

### Soft Deletes
- [ ] Migration soft deletes
- [ ] Ajouter trait aux modèles
- [ ] Tests soft deletes

### Qualité
- [ ] Exécuter `vendor/bin/pint --dirty --format agent`
- [ ] Exécuter `vendor/bin/phpstan analyse`
- [ ] Exécuter `php artisan test --coverage --min=80`
- [ ] Tous les tests passent

---

## 13. Validation Phase 5

### Tests Manuels

#### 1. DTOs
```php
$data = CreateNoteData::from($request->all());
// Validation automatique
```

✅ Validation automatique  
✅ Type safety  
✅ Autocomplete IDE

#### 2. PHPStan
```bash
vendor/bin/phpstan analyse
```

✅ Aucune erreur level 5  
✅ Types corrects partout

#### 3. Tests Coverage
```bash
php artisan test --coverage
```

✅ Coverage >80%  
✅ Tous les tests passent

#### 4. Performance
```bash
php artisan route:list --compact
```

✅ Queries optimisées  
✅ Cache fonctionnel  
✅ Jobs en queue

---

## 14. Livrables Phase 5

- ✅ DTOs structurés (Spatie Data)
- ✅ Tous les services implémentés
- ✅ API Resources complets
- ✅ PHPStan level 5 validé
- ✅ Tests Pest >80% coverage
- ✅ Notifications email/WhatsApp
- ✅ Dashboard admin analytics
- ✅ Webhooks paiements
- ✅ Optimisations performance
- ✅ Soft deletes implémentés
- ✅ Code production-ready

---

## 15. Post-Phase 5: Maintenance et Évolution

### Monitoring

- **Laravel Telescope**: Debugging et monitoring
- **Laravel Horizon**: Queue monitoring
- **Sentry**: Error tracking
- **New Relic**: Performance monitoring

### CI/CD

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run tests
        run: |
          composer install
          php artisan test --coverage --min=80
          vendor/bin/phpstan analyse
          vendor/bin/pint --test
```

### Documentation

- **API Documentation**: Scramble auto-generated
- **Code Documentation**: PHPDoc blocks
- **User Documentation**: Wiki ou GitBook

### Backup & Recovery

- **Database Backups**: Daily automated backups
- **File Backups**: S3 or similar
- **Disaster Recovery Plan**: Documented procedures

---

## Conclusion

**Phase 5 complète l'industrialisation de BulletinLab:**

1. ✅ **Phase 1**: Moteur validé - bulletin fonctionnel
2. ✅ **Phase 2**: Encodage réel - outils production
3. ✅ **Phase 3**: PDF et proclamation - documents officiels
4. ✅ **Phase 4**: PWA offline-first - encodage sans internet
5. ✅ **Phase 5**: Architecture avancée - scalabilité et qualité

**L'application est maintenant production-ready avec:**
- Architecture Domain-Driven solide
- Tests complets et coverage élevé
- Performance optimisée
- Code maintenable et évolutif
- Monitoring et observabilité
- Documentation complète

**Prochaines évolutions possibles:**
- Mobile app native (React Native)
- Intégration SMS
- Tableau de bord parents
- Plateforme e-learning
- Gestion financière (frais scolaires)
