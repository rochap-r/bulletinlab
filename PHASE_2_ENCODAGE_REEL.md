# Phase 2 — Encodage Réel

**Objectif**: Rendre l'encodage notes utilisable en production  
**Durée estimée**: 2-3 semaines  
**Prérequis**: Phase 1 validée avec succès  
**Priorité**: HAUTE - Fonctionnalités pour enseignants

---

## Critères de Succès

✅ Interface d'encodage notes fonctionnelle  
✅ Validation automatique des maxima  
✅ Suivi de progression encodage par classe/période  
✅ Verrouillage de période opérationnel  
✅ Historique des modifications de notes  
✅ Import Excel notes fonctionnel  
✅ Gestion basique des présences

---

## 1. Packages Additionnels Phase 2

### Documentation API (Optionnel)

```bash
composer require dedoc/scramble
```

**Configuration**: `config/scramble.php`

```bash
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider"
```

**Accès documentation**: `https://bulletinlab.test/docs/api`

---

## 2. Liste Élèves par Classe/Cours/Période

### Endpoint API

```http
GET /api/v1/classes/{classeId}/students?periode_id={periodeId}&matiere_id={matiereId}
```

### Contrôleur

**Fichier**: `app/Http/Controllers/Api/V1/ClasseController.php`

**Méthode**:
```php
public function students(Request $request, int $classeId)
{
    $periodeId = $request->query('periode_id');
    $matiereId = $request->query('matiere_id');
    
    $classe = Classe::with([
        'inscriptions.eleve',
        'inscriptions.eleve.notes' => function ($query) use ($periodeId, $matiereId) {
            $query->where('periode_id', $periodeId)
                  ->where('matiere_id', $matiereId);
        }
    ])->findOrFail($classeId);
    
    return ClasseStudentsResource::make($classe);
}
```

### API Resource

**Fichier**: `app/Http/Resources/Api/V1/ClasseStudentsResource.php`

```bash
php artisan make:resource Api/V1/ClasseStudentsResource
```

**Structure**:
```php
public function toArray($request)
{
    return [
        'classe_id' => $this->id,
        'classe_nom' => $this->nom_complet,
        'students' => $this->inscriptions->map(function ($inscription) {
            return [
                'eleve_id' => $inscription->eleve->id,
                'nom_complet' => $inscription->eleve->nom_complet,
                'matricule' => $inscription->eleve->matricule,
                'note' => $inscription->eleve->notes->first()?->note,
                'note_id' => $inscription->eleve->notes->first()?->id,
                'maximum' => $inscription->eleve->notes->first()?->maximum,
                'encoded_at' => $inscription->eleve->notes->first()?->updated_at,
            ];
        }),
    ];
}
```

---

## 3. Validation des Maxima

### Service: NoteValidationService

**Fichier**: `app/Domains/Evaluation/Services/NoteValidationService.php`

```bash
php artisan make:class Domains/Evaluation/Services/NoteValidationService
```

**Interface**: `app/Domains/Evaluation/Contracts/NoteValidationServiceInterface.php`

**Méthodes**:
```php
public function validateMaximum(int $matiereId, int $periodeId, int $classeId, float $note): bool
{
    // 1. Récupérer le modèle bulletin de la classe
    $modele = ModeleMaximum::where('modele_bulletin_id', $classe->modele_bulletin_id)
        ->where('matiere_id', $matiereId)
        ->where('periode_id', $periodeId)
        ->first();
    
    if (!$modele) {
        throw new MaximumNotFoundException();
    }
    
    // 2. Vérifier que la note ne dépasse pas le maximum
    if ($note > $modele->maximum) {
        throw new NoteExceedsMaximumException($note, $modele->maximum);
    }
    
    return true;
}

public function getMaximum(int $matiereId, int $periodeId, int $classeId): float
{
    $modele = ModeleMaximum::where('modele_bulletin_id', $classe->modele_bulletin_id)
        ->where('matiere_id', $matiereId)
        ->where('periode_id', $periodeId)
        ->firstOrFail();
    
    return $modele->maximum;
}
```

### Exceptions

```bash
php artisan make:exception Domains/Evaluation/Exceptions/MaximumNotFoundException
php artisan make:exception Domains/Evaluation/Exceptions/NoteExceedsMaximumException
```

### Form Request avec Validation

**Fichier**: `app/Http/Requests/Api/V1/StoreNoteRequest.php`

```php
public function rules()
{
    return [
        'eleve_id' => 'required|exists:eleves,id',
        'matiere_id' => 'required|exists:ref_matieres,id',
        'periode_id' => 'required|exists:modele_periodes,id',
        'classe_id' => 'required|exists:classes,id',
        'note' => [
            'required',
            'numeric',
            'min:0',
            function ($attribute, $value, $fail) {
                $maximum = app(NoteValidationService::class)->getMaximum(
                    $this->matiere_id,
                    $this->periode_id,
                    $this->classe_id
                );
                
                if ($value > $maximum) {
                    $fail("La note ne peut pas dépasser le maximum de {$maximum}.");
                }
            },
        ],
    ];
}
```

---

## 4. Suivi d'Encodage (Progression)

### Table: suivis_encodage_notes

**Migration**: `2026_01_01_000009_create_notes_evaluations_tables.php` (existe déjà)

**Colonnes**:
- `id`
- `classe_id`
- `matiere_id`
- `periode_id`
- `enseignant_id`
- `total_eleves`
- `notes_encodees`
- `pourcentage_completion`
- `statut` (en_cours, termine, verrouille)
- `date_debut_encodage`
- `date_fin_encodage`

### Service: EncodageProgressService

**Fichier**: `app/Domains/Evaluation/Services/EncodageProgressService.php`

```bash
php artisan make:class Domains/Evaluation/Services/EncodageProgressService
```

**Méthodes**:
```php
public function updateProgress(int $classeId, int $matiereId, int $periodeId): void
{
    $totalEleves = Inscription::where('classe_id', $classeId)
        ->where('annee_scolaire_id', $classe->annee_scolaire_id)
        ->count();
    
    $notesEncodees = Note::where('classe_id', $classeId)
        ->where('matiere_id', $matiereId)
        ->where('periode_id', $periodeId)
        ->distinct('eleve_id')
        ->count();
    
    $pourcentage = ($notesEncodees / $totalEleves) * 100;
    
    SuiviEncodageNote::updateOrCreate(
        [
            'classe_id' => $classeId,
            'matiere_id' => $matiereId,
            'periode_id' => $periodeId,
        ],
        [
            'total_eleves' => $totalEleves,
            'notes_encodees' => $notesEncodees,
            'pourcentage_completion' => $pourcentage,
            'statut' => $pourcentage == 100 ? 'termine' : 'en_cours',
            'date_fin_encodage' => $pourcentage == 100 ? now() : null,
        ]
    );
}

public function getProgress(int $classeId, int $periodeId): Collection
{
    return SuiviEncodageNote::where('classe_id', $classeId)
        ->where('periode_id', $periodeId)
        ->with('matiere', 'enseignant')
        ->get();
}
```

### Endpoint API

```http
GET /api/v1/classes/{classeId}/encoding-progress?periode_id={periodeId}
```

**Contrôleur**:
```php
public function encodingProgress(Request $request, int $classeId)
{
    $periodeId = $request->query('periode_id');
    
    $progress = app(EncodageProgressService::class)->getProgress($classeId, $periodeId);
    
    return EncodageProgressResource::collection($progress);
}
```

### API Resource

```bash
php artisan make:resource Api/V1/EncodageProgressResource
```

**Structure**:
```php
public function toArray($request)
{
    return [
        'matiere' => $this->matiere->libelle,
        'enseignant' => $this->enseignant?->nom_complet,
        'total_eleves' => $this->total_eleves,
        'notes_encodees' => $this->notes_encodees,
        'pourcentage' => round($this->pourcentage_completion, 2),
        'statut' => $this->statut,
        'date_debut' => $this->date_debut_encodage,
        'date_fin' => $this->date_fin_encodage,
    ];
}
```

---

## 5. Verrouillage de Période

### Table: verrouillages_periodes

**Migration**: Existe déjà dans `2026_01_01_000009_create_notes_evaluations_tables.php`

**Colonnes**:
- `id`
- `periode_id`
- `classe_id`
- `verrouille_par` (user_id)
- `verrouille_le` (timestamp)
- `motif`

### Service: PeriodeLockService

**Fichier**: `app/Domains/SchoolYear/Services/PeriodeLockService.php`

```bash
php artisan make:class Domains/SchoolYear/Services/PeriodeLockService
```

**Méthodes**:
```php
public function lock(int $periodeId, int $classeId, int $userId, ?string $motif = null): VerrouillagePeriode
{
    // 1. Vérifier que tous les encodages sont terminés
    $progress = SuiviEncodageNote::where('classe_id', $classeId)
        ->where('periode_id', $periodeId)
        ->where('statut', '!=', 'termine')
        ->exists();
    
    if ($progress) {
        throw new EncodageIncompletException();
    }
    
    // 2. Créer le verrouillage
    return VerrouillagePeriode::create([
        'periode_id' => $periodeId,
        'classe_id' => $classeId,
        'verrouille_par' => $userId,
        'verrouille_le' => now(),
        'motif' => $motif ?? 'Encodage terminé',
    ]);
}

public function unlock(int $periodeId, int $classeId): void
{
    VerrouillagePeriode::where('periode_id', $periodeId)
        ->where('classe_id', $classeId)
        ->delete();
}

public function isLocked(int $periodeId, int $classeId): bool
{
    return VerrouillagePeriode::where('periode_id', $periodeId)
        ->where('classe_id', $classeId)
        ->exists();
}
```

### Middleware: PeriodeOuverte

**Fichier**: `app/Http/Middleware/PeriodeOuverteMiddleware.php`

```bash
php artisan make:middleware PeriodeOuverteMiddleware
```

**Logique**:
```php
public function handle(Request $request, Closure $next)
{
    $periodeId = $request->input('periode_id') ?? $request->route('periode_id');
    $classeId = $request->input('classe_id') ?? $request->route('classe_id');
    
    if (!$periodeId || !$classeId) {
        return $next($request);
    }
    
    $isLocked = app(PeriodeLockService::class)->isLocked($periodeId, $classeId);
    
    if ($isLocked) {
        throw new PeriodeVerrouillee();
    }
    
    return $next($request);
}
```

**Enregistrement**: `bootstrap/app.php` ou `app/Http/Kernel.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'periode.ouverte' => \App\Http\Middleware\PeriodeOuverteMiddleware::class,
    ]);
})
```

### Endpoint API

```http
POST /api/v1/periods/{periodeId}/lock
{
  "classe_id": 1,
  "motif": "Encodage terminé pour P1"
}

POST /api/v1/periods/{periodeId}/unlock
{
  "classe_id": 1
}
```

### Routes

```php
Route::post('periods/{periodeId}/lock', [PeriodeController::class, 'lock'])->name('periods.lock');
Route::post('periods/{periodeId}/unlock', [PeriodeController::class, 'unlock'])->name('periods.unlock');
```

---

## 6. Historique des Notes

### Table: notes_historiques

**Migration**: Existe déjà dans `2026_01_01_000009_create_notes_evaluations_tables.php`

**Colonnes**:
- `id`
- `note_id`
- `ancienne_note`
- `nouvelle_note`
- `modifie_par` (user_id)
- `modifie_le` (timestamp)
- `motif`

### Observer: NoteObserver

**Fichier**: `app/Observers/NoteObserver.php`

```bash
php artisan make:observer NoteObserver --model=Note
```

**Méthodes**:
```php
public function updated(Note $note)
{
    if ($note->isDirty('note')) {
        NoteHistorique::create([
            'note_id' => $note->id,
            'ancienne_note' => $note->getOriginal('note'),
            'nouvelle_note' => $note->note,
            'modifie_par' => auth()->id(),
            'modifie_le' => now(),
            'motif' => 'Modification manuelle',
        ]);
    }
}
```

**Enregistrement**: `app/Providers/AppServiceProvider.php`

```php
use App\Models\Note;
use App\Observers\NoteObserver;

public function boot()
{
    Note::observe(NoteObserver::class);
}
```

### Endpoint API

```http
GET /api/v1/notes/{noteId}/history
```

**Contrôleur**:
```php
public function history(int $noteId)
{
    $history = NoteHistorique::where('note_id', $noteId)
        ->with('modifiePar')
        ->orderByDesc('modifie_le')
        ->get();
    
    return NoteHistoryResource::collection($history);
}
```

---

## 7. Import Excel Notes

### Service: NoteImportService

**Fichier**: `app/Domains/Evaluation/Services/NoteImportService.php`

```bash
php artisan make:class Domains/Evaluation/Services/NoteImportService
```

**Méthodes**:
```php
public function import(UploadedFile $file, int $classeId, int $matiereId, int $periodeId): array
{
    // 1. Valider le fichier
    $this->validateFile($file);
    
    // 2. Parser Excel
    $data = Excel::toArray(new NotesImport, $file)[0];
    
    // 3. Valider les données
    $validated = $this->validateData($data, $classeId, $matiereId, $periodeId);
    
    // 4. Importer les notes
    $results = [
        'success' => 0,
        'errors' => [],
    ];
    
    foreach ($validated as $row) {
        try {
            app(NoteEntryService::class)->saveNote([
                'eleve_id' => $row['eleve_id'],
                'matiere_id' => $matiereId,
                'periode_id' => $periodeId,
                'classe_id' => $classeId,
                'note' => $row['note'],
            ]);
            
            $results['success']++;
        } catch (\Exception $e) {
            $results['errors'][] = [
                'row' => $row['row_number'],
                'error' => $e->getMessage(),
            ];
        }
    }
    
    // 5. Mettre à jour la progression
    app(EncodageProgressService::class)->updateProgress($classeId, $matiereId, $periodeId);
    
    return $results;
}

private function validateFile(UploadedFile $file): void
{
    if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls', 'csv'])) {
        throw new InvalidFileFormatException();
    }
}

private function validateData(array $data, int $classeId, int $matiereId, int $periodeId): array
{
    $validated = [];
    $maximum = app(NoteValidationService::class)->getMaximum($matiereId, $periodeId, $classeId);
    
    foreach ($data as $index => $row) {
        // Skip header
        if ($index === 0) continue;
        
        // Validate row
        if (empty($row[0]) || empty($row[1])) {
            continue;
        }
        
        $matricule = $row[0];
        $note = (float) $row[1];
        
        // Find student
        $eleve = Eleve::where('matricule', $matricule)->first();
        
        if (!$eleve) {
            throw new StudentNotFoundException($matricule);
        }
        
        // Validate note
        if ($note > $maximum) {
            throw new NoteExceedsMaximumException($note, $maximum);
        }
        
        $validated[] = [
            'row_number' => $index + 1,
            'eleve_id' => $eleve->id,
            'note' => $note,
        ];
    }
    
    return $validated;
}
```

### Import Class (Maatwebsite Excel)

**Fichier**: `app/Imports/NotesImport.php`

```bash
php artisan make:import NotesImport --model=Note
```

### Endpoint API

```http
POST /api/v1/notes/import
Content-Type: multipart/form-data

{
  "file": [Excel file],
  "classe_id": 1,
  "matiere_id": 1,
  "periode_id": 1
}
```

### Form Request

```bash
php artisan make:request Api/V1/ImportNotesRequest
```

**Validation**:
```php
public function rules()
{
    return [
        'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        'classe_id' => 'required|exists:classes,id',
        'matiere_id' => 'required|exists:ref_matieres,id',
        'periode_id' => 'required|exists:modele_periodes,id',
    ];
}
```

### Format Excel Attendu

| Matricule | Note |
|-----------|------|
| EL001     | 15   |
| EL002     | 12.5 |
| EL003     | 18   |

---

## 8. Présences de Base

### Table: presences_eleves

**Migration**: Existe déjà dans `2026_01_01_000009_create_notes_evaluations_tables.php`

**Colonnes**:
- `id`
- `eleve_id`
- `classe_id`
- `date`
- `statut` (present, absent, retard, justifie)
- `motif`
- `created_by`

### Service: AttendanceService

**Fichier**: `app/Domains/Attendance/Services/AttendanceService.php`

```bash
php artisan make:class Domains/Attendance/Services/AttendanceService
```

**Méthodes**:
```php
public function markAttendance(int $eleveId, int $classeId, string $date, string $statut, ?string $motif = null): PresenceEleve
{
    return PresenceEleve::updateOrCreate(
        [
            'eleve_id' => $eleveId,
            'classe_id' => $classeId,
            'date' => $date,
        ],
        [
            'statut' => $statut,
            'motif' => $motif,
            'created_by' => auth()->id(),
        ]
    );
}

public function getAttendance(int $classeId, string $date): Collection
{
    return PresenceEleve::where('classe_id', $classeId)
        ->where('date', $date)
        ->with('eleve')
        ->get();
}

public function getAttendanceStats(int $eleveId, int $periodeId): array
{
    $periode = ModelePeriode::findOrFail($periodeId);
    
    $presences = PresenceEleve::where('eleve_id', $eleveId)
        ->whereBetween('date', [$periode->date_debut, $periode->date_fin])
        ->get();
    
    return [
        'total_jours' => $presences->count(),
        'presents' => $presences->where('statut', 'present')->count(),
        'absents' => $presences->where('statut', 'absent')->count(),
        'retards' => $presences->where('statut', 'retard')->count(),
        'justifies' => $presences->where('statut', 'justifie')->count(),
    ];
}
```

### Endpoints API

```http
POST /api/v1/attendance
{
  "eleve_id": 1,
  "classe_id": 1,
  "date": "2026-10-15",
  "statut": "present"
}

GET /api/v1/classes/{classeId}/attendance?date=2026-10-15

GET /api/v1/students/{eleveId}/attendance-stats?periode_id=1
```

### Routes

```php
Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
Route::get('classes/{classeId}/attendance', [AttendanceController::class, 'byClasse'])->name('attendance.by-classe');
Route::get('students/{eleveId}/attendance-stats', [AttendanceController::class, 'stats'])->name('attendance.stats');
```

---

## 9. Tests Pest Phase 2

### Tests à Créer

```bash
# Validation maxima
php artisan make:test --pest Feature/NoteValidationTest

# Suivi encodage
php artisan make:test --pest Feature/EncodageProgressTest

# Verrouillage période
php artisan make:test --pest Feature/PeriodeLockTest

# Historique notes
php artisan make:test --pest Feature/NoteHistoryTest

# Import notes
php artisan make:test --pest Feature/NoteImportTest

# Présences
php artisan make:test --pest Feature/AttendanceTest
```

### Exemple: NoteValidationTest

**Fichier**: `tests/Feature/NoteValidationTest.php`

```php
use App\Models\Note;
use App\Models\Eleve;
use App\Models\Classe;

test('note cannot exceed maximum', function () {
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    
    $response = $this->postJson('/api/v1/notes', [
        'eleve_id' => $eleve->id,
        'classe_id' => $classe->id,
        'matiere_id' => 1,
        'periode_id' => 1,
        'note' => 25, // Maximum is 20
    ]);
    
    $response->assertStatus(422);
    $response->assertJsonValidationErrors('note');
});

test('note can be saved within maximum', function () {
    $classe = Classe::factory()->create();
    $eleve = Eleve::factory()->create();
    
    $response = $this->postJson('/api/v1/notes', [
        'eleve_id' => $eleve->id,
        'classe_id' => $classe->id,
        'matiere_id' => 1,
        'periode_id' => 1,
        'note' => 15,
    ]);
    
    $response->assertStatus(201);
    expect(Note::count())->toBe(1);
});
```

---

## 10. Checklist Phase 2

### Packages
- [ ] Installer `dedoc/scramble` (optionnel)
- [ ] Configurer documentation API

### Liste Élèves
- [x] Endpoint `GET /api/v1/classes/{id}/students`
- [x] API Resource `ClasseStudentsResource`
- [x] Tester avec filtres période/matière

### Validation Maxima
- [x] Service `NoteValidationService`
- [x] Exceptions personnalisées
- [x] Validation dans `StoreNoteRequest`
- [x] Tests validation

### Suivi Encodage
- [x] Service `EncodageProgressService`
- [x] Endpoint `GET /api/v1/classes/{id}/encoding-progress`
- [x] API Resource `EncodageProgressResource`
- [x] Mise à jour automatique après encodage

### Verrouillage Période
- [x] Service `PeriodeLockService`
- [x] Middleware `PeriodeOuverteMiddleware`
- [x] Endpoints lock/unlock
- [x] Tests verrouillage

### Historique Notes
- [x] Observer `NoteObserver`
- [x] Endpoint `GET /api/v1/notes/{id}/history`
- [x] API Resource `NoteHistoryResource`
- [x] Tests historique

### Import Notes
- [x] Service `NoteImportService`
- [x] Import class `NotesImport`
- [x] Endpoint `POST /api/v1/notes/import`
- [x] Form Request `ImportNotesRequest`
- [x] Tests import

### Présences
- [x] Service `AttendanceService`
- [x] Endpoints présences
- [x] API Resources
- [x] Tests présences

### Qualité Code
- [x] Exécuter `vendor/bin/pint --dirty --format agent`
- [x] Exécuter `php artisan test --compact`
- [x] Tous les tests passent

---

## 11. Validation Phase 2

### Tests Manuels

#### 1. Encodage Notes
```http
POST /api/v1/notes
{
  "eleve_id": 1,
  "classe_id": 1,
  "matiere_id": 1,
  "periode_id": 1,
  "note": 15
}
```

✅ Note enregistrée  
✅ Validation maximum respectée  
✅ Progression mise à jour  
✅ Historique créé

#### 2. Verrouillage Période
```http
POST /api/v1/periods/1/lock
{
  "classe_id": 1
}
```

✅ Période verrouillée  
✅ Encodage bloqué  
✅ Message d'erreur approprié

#### 3. Import Notes
```http
POST /api/v1/notes/import
{
  "file": "notes.xlsx",
  "classe_id": 1,
  "matiere_id": 1,
  "periode_id": 1
}
```

✅ Notes importées  
✅ Erreurs signalées  
✅ Progression mise à jour

---

## 12. Livrables Phase 2

- ✅ Documentation API (Scramble)
- ✅ Liste élèves avec notes
- ✅ Validation automatique maxima
- ✅ Suivi progression encodage
- ✅ Verrouillage période
- ✅ Historique modifications notes
- ✅ Import Excel notes
- ✅ Gestion basique présences
- ✅ Tests Pest complets
- ✅ Code formaté avec Pint

---

## Prochaine Étape

Une fois la Phase 2 validée → **Phase 3: PDF et Proclamation**
