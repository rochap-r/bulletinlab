# Phase 3 — PDF et Proclamation

**Objectif**: Génération documents officiels (bulletins PDF + proclamations)  
**Durée estimée**: 2-3 semaines  
**Prérequis**: Phase 1 et Phase 2 validées  
**Priorité**: HAUTE - Documents officiels pour distribution

---

## Critères de Succès

✅ Preview bulletin HTML fonctionnelle  
✅ Génération PDF bulletins via Browsershot  
✅ Calcul automatique des rangs  
✅ Attribution automatique des mentions  
✅ Signatures enseignants intégrées  
✅ Liste de proclamation générée  
✅ Export PDF proclamation  
✅ Envoi batch bulletins (email)

---

## 1. Preview Bulletin HTML

### Service: BulletinRenderService

**Fichier**: `app/Domains/Bulletin/Services/BulletinRenderService.php`

```bash
php artisan make:class Domains/Bulletin/Services/BulletinRenderService
```

**Interface**: `app/Domains/Bulletin/Contracts/BulletinRenderServiceInterface.php`

**Méthodes**:
```php
public function renderHtml(int $bulletinId): string
{
    $bulletin = Bulletin::with([
        'eleve',
        'classe',
        'modeleBulletin.blocs.nodes',
        'blocValues',
        'appreciations',
    ])->findOrFail($bulletinId);
    
    return view('bulletins.templates.default', [
        'bulletin' => $bulletin,
        'eleve' => $bulletin->eleve,
        'classe' => $bulletin->classe,
        'blocs' => $bulletin->modeleBulletin->blocs,
        'values' => $bulletin->blocValues,
    ])->render();
}

public function renderPreview(int $bulletinId): View
{
    $bulletin = Bulletin::with([
        'eleve',
        'classe',
        'modeleBulletin.blocs.nodes',
        'blocValues',
        'appreciations',
    ])->findOrFail($bulletinId);
    
    return view('bulletins.preview', compact('bulletin'));
}
```

### Vue Blade: Template Bulletin

**Fichier**: `resources/views/bulletins/templates/default.blade.php`

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletin - {{ $eleve->nom_complet }}</title>
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12pt;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 18pt;
        }
        
        .header h2 {
            margin: 5px 0;
            font-size: 14pt;
            font-weight: normal;
        }
        
        .student-info {
            margin-bottom: 20px;
        }
        
        .student-info table {
            width: 100%;
        }
        
        .student-info td {
            padding: 5px;
        }
        
        .notes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .notes-table th,
        .notes-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        
        .notes-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .notes-table td.matiere {
            text-align: left;
        }
        
        .appreciation {
            margin-bottom: 15px;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            text-align: center;
            width: 30%;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
        }
        
        .mention {
            font-weight: bold;
            font-size: 14pt;
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border: 2px solid #000;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $classe->etablissement->nom }}</h1>
        <h2>Bulletin Scolaire - {{ $bulletin->periode->libelle }}</h2>
        <p>Année Scolaire: {{ $bulletin->anneeScolaire->libelle }}</p>
    </div>
    
    <div class="student-info">
        <table>
            <tr>
                <td><strong>Nom:</strong> {{ $eleve->nom_complet }}</td>
                <td><strong>Matricule:</strong> {{ $eleve->matricule }}</td>
            </tr>
            <tr>
                <td><strong>Classe:</strong> {{ $classe->nom_complet }}</td>
                <td><strong>Sexe:</strong> {{ $eleve->sexe }}</td>
            </tr>
        </table>
    </div>
    
    <table class="notes-table">
        <thead>
            <tr>
                <th>Matière</th>
                @foreach($bulletin->periodes as $periode)
                    <th>{{ $periode->code }}</th>
                @endforeach
                <th>Total</th>
                <th>Max</th>
                <th>%</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bulletin->matieres as $matiere)
                <tr>
                    <td class="matiere">{{ $matiere->libelle }}</td>
                    @foreach($bulletin->periodes as $periode)
                        <td>{{ $bulletin->getNote($matiere->id, $periode->id) }}</td>
                    @endforeach
                    <td>{{ $bulletin->getTotal($matiere->id) }}</td>
                    <td>{{ $bulletin->getMaximum($matiere->id) }}</td>
                    <td>{{ $bulletin->getPourcentage($matiere->id) }}%</td>
                </tr>
            @endforeach
            <tr style="font-weight: bold;">
                <td>TOTAL GÉNÉRAL</td>
                @foreach($bulletin->periodes as $periode)
                    <td>{{ $bulletin->getTotalPeriode($periode->id) }}</td>
                @endforeach
                <td>{{ $bulletin->total_general }}</td>
                <td>{{ $bulletin->maximum_general }}</td>
                <td>{{ $bulletin->pourcentage_general }}%</td>
            </tr>
        </tbody>
    </table>
    
    <div class="mention">
        <p>Rang: {{ $bulletin->rang }}{{ $bulletin->rang == 1 ? 'er' : 'ème' }} / {{ $bulletin->effectif_classe }}</p>
        <p>Moyenne: {{ $bulletin->moyenne_generale }}/20</p>
        <p>Mention: {{ $bulletin->mention }}</p>
    </div>
    
    @if($bulletin->appreciation_generale)
        <div class="appreciation">
            <strong>Appréciation Générale:</strong>
            <p>{{ $bulletin->appreciation_generale }}</p>
        </div>
    @endif
    
    <div class="signatures">
        <div class="signature-box">
            <p>Le Titulaire</p>
            @if($bulletin->signature_titulaire)
                <img src="{{ $bulletin->signature_titulaire }}" alt="Signature" style="max-width: 100px;">
            @endif
            <div class="signature-line">
                {{ $classe->titulaire?->nom_complet }}
            </div>
        </div>
        
        <div class="signature-box">
            <p>Le Directeur</p>
            @if($bulletin->signature_directeur)
                <img src="{{ $bulletin->signature_directeur }}" alt="Signature" style="max-width: 100px;">
            @endif
            <div class="signature-line">
                {{ $classe->etablissement->directeur?->nom_complet }}
            </div>
        </div>
        
        <div class="signature-box">
            <p>Le Parent/Tuteur</p>
            <div class="signature-line">
                
            </div>
        </div>
    </div>
</body>
</html>
```

### Endpoint API

```http
GET /api/v1/bulletins/{bulletinId}/preview
```

**Contrôleur**:
```php
public function preview(int $bulletinId)
{
    $html = app(BulletinRenderService::class)->renderHtml($bulletinId);
    
    return response($html)->header('Content-Type', 'text/html');
}
```

---

## 2. Génération PDF via Browsershot

### Service: PdfGeneratorService

**Fichier**: `app/Domains/Bulletin/Services/PdfGeneratorService.php`

```bash
php artisan make:class Domains/Bulletin/Services/PdfGeneratorService
```

**Méthodes**:
```php
use Spatie\Browsershot\Browsershot;

public function generateBulletinPdf(int $bulletinId): string
{
    $html = app(BulletinRenderService::class)->renderHtml($bulletinId);
    
    $bulletin = Bulletin::findOrFail($bulletinId);
    $filename = "bulletin_{$bulletin->eleve->matricule}_{$bulletin->periode->code}.pdf";
    $path = storage_path("app/bulletins/{$filename}");
    
    // Ensure directory exists
    if (!file_exists(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    
    Browsershot::html($html)
        ->format('A4')
        ->margins(10, 10, 10, 10)
        ->showBackground()
        ->save($path);
    
    // Update bulletin with PDF path
    $bulletin->update(['pdf_path' => "bulletins/{$filename}"]);
    
    return $path;
}

public function generateBatch(array $bulletinIds): array
{
    $results = [];
    
    foreach ($bulletinIds as $bulletinId) {
        try {
            $path = $this->generateBulletinPdf($bulletinId);
            $results['success'][] = $bulletinId;
        } catch (\Exception $e) {
            $results['errors'][] = [
                'bulletin_id' => $bulletinId,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    return $results;
}

public function downloadBulletin(int $bulletinId): BinaryFileResponse
{
    $bulletin = Bulletin::findOrFail($bulletinId);
    
    if (!$bulletin->pdf_path || !Storage::exists($bulletin->pdf_path)) {
        $this->generateBulletinPdf($bulletinId);
        $bulletin->refresh();
    }
    
    return response()->download(storage_path("app/{$bulletin->pdf_path}"));
}
```

### Configuration Browsershot

**Fichier**: `config/bulletin.php`

```bash
php artisan make:class --type=config bulletin
```

```php
return [
    'pdf' => [
        'format' => 'A4',
        'margins' => [
            'top' => 10,
            'right' => 10,
            'bottom' => 10,
            'left' => 10,
        ],
        'timeout' => 60,
        'node_binary' => env('NODE_BINARY', '/usr/bin/node'),
        'npm_binary' => env('NPM_BINARY', '/usr/bin/npm'),
    ],
];
```

### Endpoints API

```http
POST /api/v1/bulletins/{bulletinId}/generate-pdf

POST /api/v1/bulletins/generate-batch
{
  "bulletin_ids": [1, 2, 3, 4, 5]
}

GET /api/v1/bulletins/{bulletinId}/download
```

**Routes**:
```php
Route::post('bulletins/{bulletinId}/generate-pdf', [BulletinController::class, 'generatePdf'])->name('bulletins.generate-pdf');
Route::post('bulletins/generate-batch', [BulletinController::class, 'generateBatch'])->name('bulletins.generate-batch');
Route::get('bulletins/{bulletinId}/download', [BulletinController::class, 'download'])->name('bulletins.download');
```

---

## 3. Calcul des Rangs

### Service: RankCalculationService

**Fichier**: `app/Domains/Bulletin/Services/RankCalculationService.php`

```bash
php artisan make:class Domains/Bulletin/Services/RankCalculationService
```

**Méthodes**:
```php
public function calculateRanks(int $classeId, int $periodeId): void
{
    // 1. Récupérer tous les bulletins de la classe pour la période
    $bulletins = Bulletin::where('classe_id', $classeId)
        ->where('periode_id', $periodeId)
        ->orderByDesc('moyenne_generale')
        ->get();
    
    // 2. Attribuer les rangs
    $rank = 1;
    $previousMoyenne = null;
    $sameRankCount = 0;
    
    foreach ($bulletins as $index => $bulletin) {
        if ($previousMoyenne !== null && $bulletin->moyenne_generale == $previousMoyenne) {
            // Même moyenne = même rang
            $sameRankCount++;
        } else {
            // Nouvelle moyenne
            $rank = $index + 1;
            $sameRankCount = 0;
        }
        
        $bulletin->update([
            'rang' => $rank,
            'effectif_classe' => $bulletins->count(),
        ]);
        
        $previousMoyenne = $bulletin->moyenne_generale;
    }
}

public function getRankSuffix(int $rank): string
{
    if ($rank == 1) {
        return 'er';
    }
    
    return 'ème';
}
```

### Intégration dans BulletinCalculationService

**Fichier**: `app/Services/Bulletins/BulletinCalculationService.php`

Ajouter après le calcul des moyennes:

```php
public function calculate(int $classeId, int $periodeId): void
{
    // ... calcul des moyennes existant
    
    // Calculer les rangs
    app(RankCalculationService::class)->calculateRanks($classeId, $periodeId);
}
```

---

## 4. Attribution des Mentions

### Service: MentionService

**Fichier**: `app/Domains/Bulletin/Services/MentionService.php`

```bash
php artisan make:class Domains/Bulletin/Services/MentionService
```

**Méthodes**:
```php
public function calculateMention(float $pourcentage): string
{
    $bareme = BaremeMention::where('pourcentage_min', '<=', $pourcentage)
        ->where('pourcentage_max', '>=', $pourcentage)
        ->first();
    
    return $bareme?->mention ?? 'Échec';
}

public function applyMentions(int $classeId, int $periodeId): void
{
    $bulletins = Bulletin::where('classe_id', $classeId)
        ->where('periode_id', $periodeId)
        ->get();
    
    foreach ($bulletins as $bulletin) {
        $mention = $this->calculateMention($bulletin->pourcentage_general);
        
        $bulletin->update(['mention' => $mention]);
    }
}

public function getMentionColor(string $mention): string
{
    return match($mention) {
        'Grande Distinction' => '#28a745',
        'Distinction' => '#17a2b8',
        'Satisfaction' => '#ffc107',
        'Échec' => '#dc3545',
        default => '#6c757d',
    };
}
```

### Barèmes Mentions (Seeder)

**Fichier**: `database/seeders/BulletinLabBaremesSeeder.php`

```php
// Barèmes mentions
$mentions = [
    ['mention' => 'Grande Distinction', 'pourcentage_min' => 80, 'pourcentage_max' => 100],
    ['mention' => 'Distinction', 'pourcentage_min' => 70, 'pourcentage_max' => 79.99],
    ['mention' => 'Satisfaction', 'pourcentage_min' => 60, 'pourcentage_max' => 69.99],
    ['mention' => 'Échec', 'pourcentage_min' => 0, 'pourcentage_max' => 59.99],
];

foreach ($mentions as $mention) {
    BaremeMention::create($mention);
}
```

### Intégration dans BulletinCalculationService

```php
public function calculate(int $classeId, int $periodeId): void
{
    // ... calcul des moyennes existant
    
    // Calculer les rangs
    app(RankCalculationService::class)->calculateRanks($classeId, $periodeId);
    
    // Attribuer les mentions
    app(MentionService::class)->applyMentions($classeId, $periodeId);
}
```

---

## 5. Signatures Enseignants

### Table: signatures_enseignants

**Migration**: Existe déjà dans les migrations

**Colonnes**:
- `id`
- `enseignant_id`
- `signature_path`
- `uploaded_at`

### Service: SignatureService

**Fichier**: `app/Domains/Teacher/Services/SignatureService.php`

```bash
php artisan make:class Domains/Teacher/Services/SignatureService
```

**Méthodes**:
```php
use Illuminate\Http\UploadedFile;

public function uploadSignature(int $enseignantId, UploadedFile $file): SignatureEnseignant
{
    // 1. Valider le fichier
    $this->validateSignature($file);
    
    // 2. Stocker le fichier
    $path = $file->store('signatures', 'public');
    
    // 3. Créer/mettre à jour la signature
    return SignatureEnseignant::updateOrCreate(
        ['enseignant_id' => $enseignantId],
        [
            'signature_path' => $path,
            'uploaded_at' => now(),
        ]
    );
}

private function validateSignature(UploadedFile $file): void
{
    if (!in_array($file->getClientOriginalExtension(), ['png', 'jpg', 'jpeg'])) {
        throw new InvalidSignatureFormatException();
    }
    
    if ($file->getSize() > 500000) { // 500KB max
        throw new SignatureTooLargeException();
    }
}

public function getSignatureUrl(int $enseignantId): ?string
{
    $signature = SignatureEnseignant::where('enseignant_id', $enseignantId)->first();
    
    return $signature ? Storage::url($signature->signature_path) : null;
}

public function deleteSignature(int $enseignantId): void
{
    $signature = SignatureEnseignant::where('enseignant_id', $enseignantId)->first();
    
    if ($signature) {
        Storage::delete($signature->signature_path);
        $signature->delete();
    }
}
```

### Endpoints API

```http
POST /api/v1/teachers/{enseignantId}/signature
Content-Type: multipart/form-data

{
  "signature": [Image file]
}

GET /api/v1/teachers/{enseignantId}/signature

DELETE /api/v1/teachers/{enseignantId}/signature
```

**Routes**:
```php
Route::post('teachers/{enseignantId}/signature', [TeacherController::class, 'uploadSignature'])->name('teachers.upload-signature');
Route::get('teachers/{enseignantId}/signature', [TeacherController::class, 'getSignature'])->name('teachers.get-signature');
Route::delete('teachers/{enseignantId}/signature', [TeacherController::class, 'deleteSignature'])->name('teachers.delete-signature');
```

---

## 6. Liste de Proclamation

### Service: ProclamationService

**Fichier**: `app/Domains/Bulletin/Services/ProclamationService.php`

```bash
php artisan make:class Domains/Bulletin/Services/ProclamationService
```

**Méthodes**:
```php
public function generate(int $classeId, int $periodeId): Proclamation
{
    // 1. Récupérer tous les bulletins triés par rang
    $bulletins = Bulletin::where('classe_id', $classeId)
        ->where('periode_id', $periodeId)
        ->with('eleve')
        ->orderBy('rang')
        ->get();
    
    // 2. Créer la proclamation
    $proclamation = Proclamation::create([
        'classe_id' => $classeId,
        'periode_id' => $periodeId,
        'date_proclamation' => now(),
        'created_by' => auth()->id(),
    ]);
    
    // 3. Créer les lignes de proclamation
    foreach ($bulletins as $bulletin) {
        ProclamationLigne::create([
            'proclamation_id' => $proclamation->id,
            'eleve_id' => $bulletin->eleve_id,
            'rang' => $bulletin->rang,
            'moyenne_generale' => $bulletin->moyenne_generale,
            'total_points' => $bulletin->total_general,
            'mention' => $bulletin->mention,
        ]);
    }
    
    return $proclamation;
}

public function getStatistics(int $proclamationId): array
{
    $proclamation = Proclamation::with('lignes')->findOrFail($proclamationId);
    
    $mentions = $proclamation->lignes->groupBy('mention')->map->count();
    
    return [
        'total_eleves' => $proclamation->lignes->count(),
        'moyenne_classe' => $proclamation->lignes->avg('moyenne_generale'),
        'mentions' => $mentions,
        'taux_reussite' => ($proclamation->lignes->where('mention', '!=', 'Échec')->count() / $proclamation->lignes->count()) * 100,
    ];
}
```

### Vue Blade: Template Proclamation

**Fichier**: `resources/views/proclamations/template.blade.php`

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Proclamation - {{ $classe->nom_complet }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .proclamation-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .proclamation-table th,
        .proclamation-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        
        .proclamation-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .proclamation-table td.nom {
            text-align: left;
        }
        
        .stats {
            margin-top: 20px;
            display: flex;
            justify-content: space-around;
        }
        
        .stat-box {
            text-align: center;
            padding: 10px;
            border: 1px solid #000;
            width: 22%;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $classe->etablissement->nom }}</h1>
        <h2>Proclamation - {{ $classe->nom_complet }}</h2>
        <p>{{ $proclamation->periode->libelle }} - {{ $proclamation->anneeScolaire->libelle }}</p>
        <p>Date: {{ $proclamation->date_proclamation->format('d/m/Y') }}</p>
    </div>
    
    <table class="proclamation-table">
        <thead>
            <tr>
                <th>Rang</th>
                <th>Matricule</th>
                <th>Nom Complet</th>
                <th>Total Points</th>
                <th>Moyenne</th>
                <th>Pourcentage</th>
                <th>Mention</th>
            </tr>
        </thead>
        <tbody>
            @foreach($proclamation->lignes as $ligne)
                <tr>
                    <td>{{ $ligne->rang }}{{ $ligne->rang == 1 ? 'er' : 'ème' }}</td>
                    <td>{{ $ligne->eleve->matricule }}</td>
                    <td class="nom">{{ $ligne->eleve->nom_complet }}</td>
                    <td>{{ $ligne->total_points }}</td>
                    <td>{{ number_format($ligne->moyenne_generale, 2) }}</td>
                    <td>{{ number_format(($ligne->total_points / $ligne->maximum_total) * 100, 2) }}%</td>
                    <td style="color: {{ app(MentionService::class)->getMentionColor($ligne->mention) }}">
                        <strong>{{ $ligne->mention }}</strong>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="stats">
        <div class="stat-box">
            <h3>Total Élèves</h3>
            <p style="font-size: 24pt;">{{ $stats['total_eleves'] }}</p>
        </div>
        <div class="stat-box">
            <h3>Moyenne Classe</h3>
            <p style="font-size: 24pt;">{{ number_format($stats['moyenne_classe'], 2) }}</p>
        </div>
        <div class="stat-box">
            <h3>Taux de Réussite</h3>
            <p style="font-size: 24pt;">{{ number_format($stats['taux_reussite'], 2) }}%</p>
        </div>
    </div>
</body>
</html>
```

### Endpoints API

```http
POST /api/v1/classes/{classeId}/proclamation
{
  "periode_id": 1
}

GET /api/v1/proclamations/{proclamationId}

GET /api/v1/proclamations/{proclamationId}/statistics

GET /api/v1/proclamations/{proclamationId}/download
```

---

## 7. Export PDF Proclamation

### Service: ProclamationPdfService

**Fichier**: `app/Domains/Bulletin/Services/ProclamationPdfService.php`

```bash
php artisan make:class Domains/Bulletin/Services/ProclamationPdfService
```

**Méthodes**:
```php
public function generatePdf(int $proclamationId): string
{
    $proclamation = Proclamation::with([
        'classe.etablissement',
        'periode',
        'anneeScolaire',
        'lignes.eleve',
    ])->findOrFail($proclamationId);
    
    $stats = app(ProclamationService::class)->getStatistics($proclamationId);
    
    $html = view('proclamations.template', [
        'proclamation' => $proclamation,
        'classe' => $proclamation->classe,
        'stats' => $stats,
    ])->render();
    
    $filename = "proclamation_{$proclamation->classe->code}_{$proclamation->periode->code}.pdf";
    $path = storage_path("app/proclamations/{$filename}");
    
    if (!file_exists(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    
    Browsershot::html($html)
        ->format('A4')
        ->landscape()
        ->margins(10, 10, 10, 10)
        ->save($path);
    
    $proclamation->update(['pdf_path' => "proclamations/{$filename}"]);
    
    return $path;
}

public function download(int $proclamationId): BinaryFileResponse
{
    $proclamation = Proclamation::findOrFail($proclamationId);
    
    if (!$proclamation->pdf_path || !Storage::exists($proclamation->pdf_path)) {
        $this->generatePdf($proclamationId);
        $proclamation->refresh();
    }
    
    return response()->download(storage_path("app/{$proclamation->pdf_path}"));
}
```

---

## 8. Envoi Batch Bulletins (Email)

### Service: BulletinNotificationService

**Fichier**: `app/Domains/Bulletin/Services/BulletinNotificationService.php`

```bash
php artisan make:class Domains/Bulletin/Services/BulletinNotificationService
```

**Méthodes**:
```php
use Illuminate\Support\Facades\Mail;
use App\Mail\BulletinMail;

public function sendBatch(array $bulletinIds): array
{
    $results = [
        'sent' => 0,
        'failed' => 0,
        'errors' => [],
    ];
    
    foreach ($bulletinIds as $bulletinId) {
        try {
            $this->sendSingle($bulletinId);
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

public function sendSingle(int $bulletinId): void
{
    $bulletin = Bulletin::with('eleve')->findOrFail($bulletinId);
    
    // Ensure PDF exists
    if (!$bulletin->pdf_path) {
        app(PdfGeneratorService::class)->generateBulletinPdf($bulletinId);
        $bulletin->refresh();
    }
    
    // Get parent email
    $email = $bulletin->eleve->email_parent ?? $bulletin->eleve->email_tuteur;
    
    if (!$email) {
        throw new NoEmailException("Aucun email trouvé pour l'élève {$bulletin->eleve->nom_complet}");
    }
    
    // Send email
    Mail::to($email)->send(new BulletinMail($bulletin));
    
    // Update bulletin
    $bulletin->update([
        'envoye_le' => now(),
        'envoye_a' => $email,
    ]);
}
```

### Mailable: BulletinMail

**Fichier**: `app/Mail/BulletinMail.php`

```bash
php artisan make:mail BulletinMail
```

```php
use Illuminate\Mail\Mailable;

class BulletinMail extends Mailable
{
    public function __construct(public Bulletin $bulletin)
    {
    }
    
    public function build()
    {
        return $this->subject("Bulletin scolaire - {$this->bulletin->periode->libelle}")
            ->markdown('emails.bulletin')
            ->attach(storage_path("app/{$this->bulletin->pdf_path}"), [
                'as' => "bulletin_{$this->bulletin->eleve->matricule}.pdf",
                'mime' => 'application/pdf',
            ]);
    }
}
```

### Vue Email

**Fichier**: `resources/views/emails/bulletin.blade.php`

```blade
@component('mail::message')
# Bulletin Scolaire

Bonjour,

Veuillez trouver ci-joint le bulletin scolaire de **{{ $bulletin->eleve->nom_complet }}** pour la période **{{ $bulletin->periode->libelle }}**.

**Résultats:**
- Moyenne générale: {{ $bulletin->moyenne_generale }}/20
- Rang: {{ $bulletin->rang }}{{ $bulletin->rang == 1 ? 'er' : 'ème' }} / {{ $bulletin->effectif_classe }}
- Mention: {{ $bulletin->mention }}

@component('mail::button', ['url' => route('bulletins.download', $bulletin->id)])
Télécharger le bulletin
@endcomponent

Cordialement,<br>
{{ $bulletin->classe->etablissement->nom }}
@endcomponent
```

### Endpoints API

```http
POST /api/v1/bulletins/{bulletinId}/send

POST /api/v1/bulletins/send-batch
{
  "bulletin_ids": [1, 2, 3, 4, 5]
}
```

---

## 9. Tests Pest Phase 3

### Tests à Créer

```bash
# Preview bulletin
php artisan make:test --pest Feature/BulletinPreviewTest

# Génération PDF
php artisan make:test --pest Feature/BulletinPdfTest

# Calcul rangs
php artisan make:test --pest Feature/RankCalculationTest

# Mentions
php artisan make:test --pest Feature/MentionTest

# Signatures
php artisan make:test --pest Feature/SignatureTest

# Proclamation
php artisan make:test --pest Feature/ProclamationTest

# Envoi emails
php artisan make:test --pest Feature/BulletinNotificationTest
```

### Exemple: RankCalculationTest

```php
use App\Models\Bulletin;
use App\Models\Classe;
use App\Domains\Bulletin\Services\RankCalculationService;

test('ranks are calculated correctly', function () {
    $classe = Classe::factory()->create();
    
    $bulletin1 = Bulletin::factory()->create([
        'classe_id' => $classe->id,
        'moyenne_generale' => 18,
    ]);
    
    $bulletin2 = Bulletin::factory()->create([
        'classe_id' => $classe->id,
        'moyenne_generale' => 15,
    ]);
    
    $bulletin3 = Bulletin::factory()->create([
        'classe_id' => $classe->id,
        'moyenne_generale' => 12,
    ]);
    
    app(RankCalculationService::class)->calculateRanks($classe->id, 1);
    
    expect($bulletin1->fresh()->rang)->toBe(1);
    expect($bulletin2->fresh()->rang)->toBe(2);
    expect($bulletin3->fresh()->rang)->toBe(3);
});

test('students with same average get same rank', function () {
    $classe = Classe::factory()->create();
    
    $bulletin1 = Bulletin::factory()->create([
        'classe_id' => $classe->id,
        'moyenne_generale' => 15,
    ]);
    
    $bulletin2 = Bulletin::factory()->create([
        'classe_id' => $classe->id,
        'moyenne_generale' => 15,
    ]);
    
    app(RankCalculationService::class)->calculateRanks($classe->id, 1);
    
    expect($bulletin1->fresh()->rang)->toBe(1);
    expect($bulletin2->fresh()->rang)->toBe(1);
});
```

---

## 10. Checklist Phase 3

### Preview Bulletin
- [ ] Service `BulletinRenderService`
- [ ] Vue Blade template bulletin
- [ ] Endpoint preview HTML
- [ ] Tests preview

### Génération PDF
- [ ] Service `PdfGeneratorService`
- [ ] Configuration Browsershot
- [ ] Endpoint génération PDF
- [ ] Endpoint génération batch
- [ ] Endpoint téléchargement
- [ ] Tests PDF

### Calcul Rangs
- [ ] Service `RankCalculationService`
- [ ] Intégration dans `BulletinCalculationService`
- [ ] Tests rangs
- [ ] Tests rangs ex-aequo

### Mentions
- [ ] Service `MentionService`
- [ ] Seeder barèmes mentions
- [ ] Intégration dans `BulletinCalculationService`
- [ ] Tests mentions

### Signatures
- [ ] Service `SignatureService`
- [ ] Endpoints upload/get/delete
- [ ] Validation fichiers
- [ ] Tests signatures

### Proclamation
- [ ] Service `ProclamationService`
- [ ] Service `ProclamationPdfService`
- [ ] Vue Blade template proclamation
- [ ] Endpoints CRUD proclamation
- [ ] Tests proclamation

### Envoi Emails
- [ ] Service `BulletinNotificationService`
- [ ] Mailable `BulletinMail`
- [ ] Vue email
- [ ] Endpoints envoi
- [ ] Tests emails

### Qualité Code
- [ ] Exécuter `vendor/bin/pint --dirty --format agent`
- [ ] Exécuter `php artisan test --compact`
- [ ] Tous les tests passent

---

## 11. Validation Phase 3

### Tests Manuels

#### 1. Preview Bulletin
```http
GET /api/v1/bulletins/1/preview
```

✅ HTML affiché correctement  
✅ Données élève présentes  
✅ Notes affichées  
✅ Rang et mention corrects

#### 2. Génération PDF
```http
POST /api/v1/bulletins/1/generate-pdf
```

✅ PDF généré  
✅ Fichier stocké  
✅ Téléchargement fonctionnel

#### 3. Proclamation
```http
POST /api/v1/classes/1/proclamation
```

✅ Proclamation créée  
✅ Élèves triés par rang  
✅ Statistiques correctes  
✅ PDF proclamation généré

---

## 12. Livrables Phase 3

- ✅ Preview bulletin HTML
- ✅ Génération PDF bulletins
- ✅ Calcul automatique rangs
- ✅ Attribution automatique mentions
- ✅ Gestion signatures enseignants
- ✅ Liste proclamation
- ✅ Export PDF proclamation
- ✅ Envoi batch emails
- ✅ Tests Pest complets
- ✅ Code formaté avec Pint

---

## Prochaine Étape

Une fois la Phase 3 validée → **Phase 4: PWA Offline-first**
