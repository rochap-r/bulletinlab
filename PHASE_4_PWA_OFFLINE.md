# Phase 4 — PWA Offline-first

**Objectif**: Encodage notes/présences sans connexion internet  
**Durée estimée**: 3-4 semaines  
**Prérequis**: Phases 1, 2 et 3 validées  
**Priorité**: MOYENNE - Amélioration UX pour zones à faible connectivité

---

## Critères de Succès

✅ PWA installable sur mobile/desktop  
✅ Encodage notes offline fonctionnel  
✅ Encodage présences offline fonctionnel  
✅ Synchronisation automatique au retour de connexion  
✅ Gestion des conflits de synchronisation  
✅ Bootstrap initial des données  
✅ Interface React moderne et responsive  
✅ Performance optimale (< 3s chargement initial)

---

## 1. Stack Technique Frontend

### Framework & Build

```json
{
  "dependencies": {
    "react": "^18.3.0",
    "react-dom": "^18.3.0",
    "typescript": "^5.5.0",
    "@vitejs/plugin-react": "^4.3.0",
    "vite": "^5.4.0",
    "vite-plugin-pwa": "^0.20.0"
  }
}
```

### State Management & Data

```json
{
  "dependencies": {
    "dexie": "^4.0.0",
    "dexie-react-hooks": "^1.1.0",
    "@tanstack/react-query": "^5.56.0",
    "zustand": "^4.5.0",
    "axios": "^1.7.0"
  }
}
```

### UI Components

```json
{
  "dependencies": {
    "tailwindcss": "^4.0.0",
    "@tailwindcss/forms": "^0.5.0",
    "@radix-ui/react-dialog": "^1.1.0",
    "@radix-ui/react-dropdown-menu": "^2.1.0",
    "@radix-ui/react-toast": "^1.2.0",
    "@tanstack/react-table": "^8.20.0",
    "lucide-react": "^0.446.0",
    "class-variance-authority": "^0.7.0",
    "clsx": "^2.1.0",
    "tailwind-merge": "^2.5.0"
  }
}
```

### Installation

```bash
cd resources/js
npm install react react-dom typescript
npm install -D @vitejs/plugin-react vite vite-plugin-pwa
npm install dexie dexie-react-hooks
npm install @tanstack/react-query zustand axios
npm install tailwindcss @tailwindcss/forms
npm install @radix-ui/react-dialog @radix-ui/react-dropdown-menu @radix-ui/react-toast
npm install @tanstack/react-table lucide-react
npm install class-variance-authority clsx tailwind-merge
```

---

## 2. Configuration PWA

### Vite Config

**Fichier**: `vite.config.js`

```javascript
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico', 'robots.txt', 'apple-touch-icon.png'],
            manifest: {
                name: 'BulletinLab',
                short_name: 'BulletinLab',
                description: 'Système de gestion de bulletins scolaires',
                theme_color: '#3b82f6',
                background_color: '#ffffff',
                display: 'standalone',
                orientation: 'portrait',
                scope: '/',
                start_url: '/',
                icons: [
                    {
                        src: 'pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png'
                    },
                    {
                        src: 'pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png'
                    },
                    {
                        src: 'pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable'
                    }
                ]
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/api\./i,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'api-cache',
                            expiration: {
                                maxEntries: 100,
                                maxAgeSeconds: 60 * 60 * 24 // 24 hours
                            },
                            cacheableResponse: {
                                statuses: [0, 200]
                            }
                        }
                    }
                ]
            }
        })
    ],
});
```

### Manifest Icons

Générer les icônes PWA:

```bash
# Utiliser un outil comme https://realfavicongenerator.net/
# Placer les icônes dans public/
```

---

## 3. Base de Données Locale (IndexedDB)

### Schema Dexie

**Fichier**: `resources/js/db/schema.ts`

```typescript
import Dexie, { Table } from 'dexie';

export interface Eleve {
  id: number;
  matricule: string;
  nom: string;
  postnom: string;
  prenom: string;
  nom_complet: string;
  sexe: string;
  date_naissance: string;
}

export interface Classe {
  id: number;
  nom_complet: string;
  niveau_id: number;
  option_id: number;
  section: string;
  effectif: number;
}

export interface Matiere {
  id: number;
  libelle: string;
  code: string;
  coefficient: number;
}

export interface Note {
  id?: number;
  local_uuid: string;
  eleve_id: number;
  matiere_id: number;
  periode_id: number;
  classe_id: number;
  note: number;
  maximum: number;
  created_at: string;
  updated_at: string;
  synced: boolean;
}

export interface Presence {
  id?: number;
  local_uuid: string;
  eleve_id: number;
  classe_id: number;
  date: string;
  statut: 'present' | 'absent' | 'retard' | 'justifie';
  motif?: string;
  created_at: string;
  synced: boolean;
}

export interface SyncQueue {
  uuid: string;
  type: 'note' | 'presence';
  action: 'create' | 'update' | 'delete';
  payload: any;
  status: 'pending' | 'syncing' | 'synced' | 'error';
  attempts: number;
  created_at: string;
  synced_at?: string;
  error?: string;
}

export class BulletinLabDB extends Dexie {
  eleves!: Table<Eleve>;
  classes!: Table<Classe>;
  matieres!: Table<Matiere>;
  notes!: Table<Note>;
  presences!: Table<Presence>;
  syncQueue!: Table<SyncQueue>;

  constructor() {
    super('BulletinLabDB');
    
    this.version(1).stores({
      eleves: 'id, matricule, nom_complet',
      classes: 'id, nom_complet',
      matieres: 'id, code, libelle',
      notes: '++id, local_uuid, eleve_id, matiere_id, periode_id, classe_id, synced',
      presences: '++id, local_uuid, eleve_id, classe_id, date, synced',
      syncQueue: 'uuid, type, status, created_at'
    });
  }
}

export const db = new BulletinLabDB();
```

---

## 4. Backend Laravel - Domaine Sync

### Structure Domaine Sync

```bash
php artisan make:class Domains/Sync/Contracts/SyncBootstrapServiceInterface
php artisan make:class Domains/Sync/Contracts/SyncPushServiceInterface
php artisan make:class Domains/Sync/Contracts/SyncPullServiceInterface

php artisan make:exception Domains/Sync/Exceptions/SyncConflictException
php artisan make:exception Domains/Sync/Exceptions/InvalidSyncDataException

php artisan make:class Domains/Sync/Services/SyncBootstrapService
php artisan make:class Domains/Sync/Services/SyncPushService
php artisan make:class Domains/Sync/Services/SyncPullService
php artisan make:class Domains/Sync/Services/ConflictResolverService
```

### Service: SyncBootstrapService

**Fichier**: `app/Domains/Sync/Services/SyncBootstrapService.php`

**Responsabilité**: Télécharger toutes les données initiales pour l'encodage offline

```php
public function bootstrap(int $classeId, int $periodeId): array
{
    $classe = Classe::with([
        'inscriptions.eleve',
        'modeleBulletin.matieres',
        'modeleBulletin.maxima' => fn($q) => $q->where('periode_id', $periodeId),
    ])->findOrFail($classeId);
    
    // 1. Élèves de la classe
    $eleves = $classe->inscriptions->map(fn($i) => [
        'id' => $i->eleve->id,
        'matricule' => $i->eleve->matricule,
        'nom' => $i->eleve->nom,
        'postnom' => $i->eleve->postnom,
        'prenom' => $i->eleve->prenom,
        'nom_complet' => $i->eleve->nom_complet,
        'sexe' => $i->eleve->sexe,
        'date_naissance' => $i->eleve->date_naissance,
    ]);
    
    // 2. Matières de la classe
    $matieres = $classe->modeleBulletin->matieres->map(fn($m) => [
        'id' => $m->id,
        'libelle' => $m->libelle,
        'code' => $m->code,
        'coefficient' => $m->coefficient,
    ]);
    
    // 3. Maxima par matière
    $maxima = $classe->modeleBulletin->maxima->mapWithKeys(fn($m) => [
        $m->matiere_id => $m->maximum
    ]);
    
    // 4. Notes existantes
    $notes = Note::where('classe_id', $classeId)
        ->where('periode_id', $periodeId)
        ->get()
        ->map(fn($n) => [
            'id' => $n->id,
            'local_uuid' => $n->local_uuid ?? Str::uuid()->toString(),
            'eleve_id' => $n->eleve_id,
            'matiere_id' => $n->matiere_id,
            'periode_id' => $n->periode_id,
            'classe_id' => $n->classe_id,
            'note' => $n->note,
            'maximum' => $n->maximum,
            'created_at' => $n->created_at->toIso8601String(),
            'updated_at' => $n->updated_at->toIso8601String(),
            'synced' => true,
        ]);
    
    // 5. Présences du mois en cours
    $presences = PresenceEleve::where('classe_id', $classeId)
        ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
        ->get()
        ->map(fn($p) => [
            'id' => $p->id,
            'local_uuid' => $p->local_uuid ?? Str::uuid()->toString(),
            'eleve_id' => $p->eleve_id,
            'classe_id' => $p->classe_id,
            'date' => $p->date,
            'statut' => $p->statut,
            'motif' => $p->motif,
            'created_at' => $p->created_at->toIso8601String(),
            'synced' => true,
        ]);
    
    return [
        'classe' => [
            'id' => $classe->id,
            'nom_complet' => $classe->nom_complet,
            'effectif' => $eleves->count(),
        ],
        'periode' => [
            'id' => $periodeId,
        ],
        'eleves' => $eleves,
        'matieres' => $matieres,
        'maxima' => $maxima,
        'notes' => $notes,
        'presences' => $presences,
        'synced_at' => now()->toIso8601String(),
    ];
}
```

### Service: SyncPushService

**Fichier**: `app/Domains/Sync/Services/SyncPushService.php`

**Responsabilité**: Recevoir les modifications offline et les appliquer

```php
public function push(array $syncQueue): array
{
    $results = [
        'processed' => 0,
        'conflicts' => 0,
        'errors' => 0,
        'items' => [],
    ];
    
    DB::beginTransaction();
    
    try {
        foreach ($syncQueue as $item) {
            try {
                $result = $this->processItem($item);
                $results['items'][] = $result;
                
                if ($result['status'] === 'conflict') {
                    $results['conflicts']++;
                } else {
                    $results['processed']++;
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $results['items'][] = [
                    'uuid' => $item['uuid'],
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
    
    return $results;
}

private function processItem(array $item): array
{
    $type = $item['type'];
    $action = $item['action'];
    $payload = $item['payload'];
    
    // Check for conflicts
    if ($action === 'update' && isset($payload['id'])) {
        $existing = $this->findExisting($type, $payload['id']);
        
        if ($existing && $existing->updated_at > $payload['updated_at']) {
            return [
                'uuid' => $item['uuid'],
                'status' => 'conflict',
                'server_data' => $existing,
                'client_data' => $payload,
            ];
        }
    }
    
    // Apply change
    switch ($type) {
        case 'note':
            return $this->syncNote($action, $payload, $item['uuid']);
        case 'presence':
            return $this->syncPresence($action, $payload, $item['uuid']);
        default:
            throw new InvalidSyncDataException("Unknown type: {$type}");
    }
}

private function syncNote(string $action, array $payload, string $uuid): array
{
    if ($action === 'create' || $action === 'update') {
        $note = Note::updateOrCreate(
            ['local_uuid' => $payload['local_uuid']],
            [
                'eleve_id' => $payload['eleve_id'],
                'matiere_id' => $payload['matiere_id'],
                'periode_id' => $payload['periode_id'],
                'classe_id' => $payload['classe_id'],
                'note' => $payload['note'],
                'maximum' => $payload['maximum'],
            ]
        );
        
        return [
            'uuid' => $uuid,
            'status' => 'synced',
            'server_id' => $note->id,
            'local_uuid' => $note->local_uuid,
        ];
    }
    
    if ($action === 'delete') {
        Note::where('local_uuid', $payload['local_uuid'])->delete();
        
        return [
            'uuid' => $uuid,
            'status' => 'synced',
        ];
    }
}

private function syncPresence(string $action, array $payload, string $uuid): array
{
    if ($action === 'create' || $action === 'update') {
        $presence = PresenceEleve::updateOrCreate(
            ['local_uuid' => $payload['local_uuid']],
            [
                'eleve_id' => $payload['eleve_id'],
                'classe_id' => $payload['classe_id'],
                'date' => $payload['date'],
                'statut' => $payload['statut'],
                'motif' => $payload['motif'] ?? null,
            ]
        );
        
        return [
            'uuid' => $uuid,
            'status' => 'synced',
            'server_id' => $presence->id,
            'local_uuid' => $presence->local_uuid,
        ];
    }
    
    if ($action === 'delete') {
        PresenceEleve::where('local_uuid', $payload['local_uuid'])->delete();
        
        return [
            'uuid' => $uuid,
            'status' => 'synced',
        ];
    }
}
```

### Service: SyncPullService

**Fichier**: `app/Domains/Sync/Services/SyncPullService.php`

**Responsabilité**: Envoyer les mises à jour serveur vers le client

```php
public function pull(int $classeId, int $periodeId, string $lastSyncAt): array
{
    $lastSync = Carbon::parse($lastSyncAt);
    
    // Notes modifiées depuis la dernière sync
    $notes = Note::where('classe_id', $classeId)
        ->where('periode_id', $periodeId)
        ->where('updated_at', '>', $lastSync)
        ->get()
        ->map(fn($n) => [
            'id' => $n->id,
            'local_uuid' => $n->local_uuid,
            'eleve_id' => $n->eleve_id,
            'matiere_id' => $n->matiere_id,
            'periode_id' => $n->periode_id,
            'classe_id' => $n->classe_id,
            'note' => $n->note,
            'maximum' => $n->maximum,
            'updated_at' => $n->updated_at->toIso8601String(),
        ]);
    
    // Présences modifiées
    $presences = PresenceEleve::where('classe_id', $classeId)
        ->where('updated_at', '>', $lastSync)
        ->get()
        ->map(fn($p) => [
            'id' => $p->id,
            'local_uuid' => $p->local_uuid,
            'eleve_id' => $p->eleve_id,
            'classe_id' => $p->classe_id,
            'date' => $p->date,
            'statut' => $p->statut,
            'motif' => $p->motif,
            'updated_at' => $p->updated_at->toIso8601String(),
        ]);
    
    return [
        'notes' => $notes,
        'presences' => $presences,
        'synced_at' => now()->toIso8601String(),
    ];
}
```

---

## 5. API Endpoints Sync

### Routes

**Fichier**: `routes/api.php`

```php
Route::prefix('v1/sync')->middleware(['auth:sanctum', 'tenant'])->name('api.v1.sync.')->group(function () {
    Route::post('bootstrap', [SyncController::class, 'bootstrap'])->name('bootstrap');
    Route::post('push', [SyncController::class, 'push'])->name('push');
    Route::post('pull', [SyncController::class, 'pull'])->name('pull');
    Route::post('resolve-conflicts', [SyncController::class, 'resolveConflicts'])->name('resolve-conflicts');
});
```

### Contrôleur

```bash
php artisan make:controller Api/V1/SyncController
```

**Fichier**: `app/Http/Controllers/Api/V1/SyncController.php`

```php
public function bootstrap(Request $request)
{
    $validated = $request->validate([
        'classe_id' => 'required|exists:classes,id',
        'periode_id' => 'required|exists:modele_periodes,id',
    ]);
    
    $data = app(SyncBootstrapService::class)->bootstrap(
        $validated['classe_id'],
        $validated['periode_id']
    );
    
    return response()->json($data);
}

public function push(Request $request)
{
    $validated = $request->validate([
        'sync_queue' => 'required|array',
        'sync_queue.*.uuid' => 'required|string',
        'sync_queue.*.type' => 'required|in:note,presence',
        'sync_queue.*.action' => 'required|in:create,update,delete',
        'sync_queue.*.payload' => 'required|array',
    ]);
    
    $results = app(SyncPushService::class)->push($validated['sync_queue']);
    
    return response()->json($results);
}

public function pull(Request $request)
{
    $validated = $request->validate([
        'classe_id' => 'required|exists:classes,id',
        'periode_id' => 'required|exists:modele_periodes,id',
        'last_sync_at' => 'required|date',
    ]);
    
    $data = app(SyncPullService::class)->pull(
        $validated['classe_id'],
        $validated['periode_id'],
        $validated['last_sync_at']
    );
    
    return response()->json($data);
}
```

---

## 6. Frontend React - Sync Logic

### Hook: useSyncManager

**Fichier**: `resources/js/hooks/useSyncManager.ts`

```typescript
import { useState, useEffect } from 'react';
import { db } from '../db/schema';
import { syncApi } from '../api/sync';

export function useSyncManager() {
  const [isSyncing, setIsSyncing] = useState(false);
  const [lastSyncAt, setLastSyncAt] = useState<string | null>(
    localStorage.getItem('lastSyncAt')
  );

  const bootstrap = async (classeId: number, periodeId: number) => {
    try {
      const data = await syncApi.bootstrap(classeId, periodeId);
      
      // Clear existing data
      await db.transaction('rw', [db.eleves, db.matieres, db.notes, db.presences], async () => {
        await db.eleves.clear();
        await db.matieres.clear();
        await db.notes.clear();
        await db.presences.clear();
      });
      
      // Populate local DB
      await db.eleves.bulkAdd(data.eleves);
      await db.matieres.bulkAdd(data.matieres);
      await db.notes.bulkAdd(data.notes);
      await db.presences.bulkAdd(data.presences);
      
      setLastSyncAt(data.synced_at);
      localStorage.setItem('lastSyncAt', data.synced_at);
      localStorage.setItem('classeId', classeId.toString());
      localStorage.setItem('periodeId', periodeId.toString());
    } catch (error) {
      console.error('Bootstrap failed:', error);
      throw error;
    }
  };

  const push = async () => {
    setIsSyncing(true);
    
    try {
      // Get pending items from sync queue
      const queue = await db.syncQueue
        .where('status')
        .equals('pending')
        .toArray();
      
      if (queue.length === 0) {
        return { processed: 0 };
      }
      
      // Send to server
      const results = await syncApi.push(queue);
      
      // Update sync queue
      for (const item of results.items) {
        if (item.status === 'synced') {
          await db.syncQueue.delete(item.uuid);
          
          // Update local record with server ID
          if (item.server_id) {
            const queueItem = queue.find(q => q.uuid === item.uuid);
            if (queueItem?.type === 'note') {
              await db.notes
                .where('local_uuid')
                .equals(item.local_uuid)
                .modify({ id: item.server_id, synced: true });
            } else if (queueItem?.type === 'presence') {
              await db.presences
                .where('local_uuid')
                .equals(item.local_uuid)
                .modify({ id: item.server_id, synced: true });
            }
          }
        } else if (item.status === 'error') {
          await db.syncQueue.update(item.uuid, {
            status: 'error',
            error: item.error,
            attempts: (queue.find(q => q.uuid === item.uuid)?.attempts || 0) + 1
          });
        }
      }
      
      return results;
    } finally {
      setIsSyncing(false);
    }
  };

  const pull = async () => {
    if (!lastSyncAt) return;
    
    const classeId = parseInt(localStorage.getItem('classeId') || '0');
    const periodeId = parseInt(localStorage.getItem('periodeId') || '0');
    
    try {
      const data = await syncApi.pull(classeId, periodeId, lastSyncAt);
      
      // Update local DB with server changes
      for (const note of data.notes) {
        await db.notes
          .where('local_uuid')
          .equals(note.local_uuid)
          .modify(note);
      }
      
      for (const presence of data.presences) {
        await db.presences
          .where('local_uuid')
          .equals(presence.local_uuid)
          .modify(presence);
      }
      
      setLastSyncAt(data.synced_at);
      localStorage.setItem('lastSyncAt', data.synced_at);
    } catch (error) {
      console.error('Pull failed:', error);
    }
  };

  const sync = async () => {
    await push();
    await pull();
  };

  // Auto-sync every 5 minutes when online
  useEffect(() => {
    if (!navigator.onLine) return;
    
    const interval = setInterval(() => {
      sync();
    }, 5 * 60 * 1000);
    
    return () => clearInterval(interval);
  }, [lastSyncAt]);

  // Sync when coming back online
  useEffect(() => {
    const handleOnline = () => {
      sync();
    };
    
    window.addEventListener('online', handleOnline);
    return () => window.removeEventListener('online', handleOnline);
  }, []);

  return {
    bootstrap,
    push,
    pull,
    sync,
    isSyncing,
    lastSyncAt,
  };
}
```

---

## 7. Frontend React - Encodage Notes Offline

### Component: NoteEncodingTable

**Fichier**: `resources/js/components/NoteEncodingTable.tsx`

```typescript
import { useState } from 'react';
import { useLiveQuery } from 'dexie-react-hooks';
import { db, Note } from '../db/schema';
import { v4 as uuidv4 } from 'uuid';

interface Props {
  classeId: number;
  matiereId: number;
  periodeId: number;
}

export function NoteEncodingTable({ classeId, matiereId, periodeId }: Props) {
  const eleves = useLiveQuery(() => db.eleves.toArray());
  const notes = useLiveQuery(() =>
    db.notes
      .where({ matiere_id: matiereId, periode_id: periodeId })
      .toArray()
  );
  
  const [editingId, setEditingId] = useState<number | null>(null);

  const saveNote = async (eleveId: number, value: string) => {
    const noteValue = parseFloat(value);
    
    if (isNaN(noteValue)) return;
    
    const existingNote = notes?.find(n => n.eleve_id === eleveId);
    const localUuid = existingNote?.local_uuid || uuidv4();
    
    const noteData: Note = {
      local_uuid: localUuid,
      eleve_id: eleveId,
      matiere_id: matiereId,
      periode_id: periodeId,
      classe_id: classeId,
      note: noteValue,
      maximum: 20,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      synced: false,
    };
    
    if (existingNote?.id) {
      // Update
      await db.notes.update(existingNote.id, noteData);
      
      await db.syncQueue.add({
        uuid: uuidv4(),
        type: 'note',
        action: 'update',
        payload: { ...noteData, id: existingNote.id },
        status: 'pending',
        attempts: 0,
        created_at: new Date().toISOString(),
      });
    } else {
      // Create
      await db.notes.add(noteData);
      
      await db.syncQueue.add({
        uuid: uuidv4(),
        type: 'note',
        action: 'create',
        payload: noteData,
        status: 'pending',
        attempts: 0,
        created_at: new Date().toISOString(),
      });
    }
    
    setEditingId(null);
  };

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
              Matricule
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
              Nom Complet
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
              Note /20
            </th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
              Statut
            </th>
          </tr>
        </thead>
        <tbody className="bg-white divide-y divide-gray-200">
          {eleves?.map(eleve => {
            const note = notes?.find(n => n.eleve_id === eleve.id);
            const isEditing = editingId === eleve.id;
            
            return (
              <tr key={eleve.id}>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {eleve.matricule}
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {eleve.nom_complet}
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {isEditing ? (
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      max="20"
                      defaultValue={note?.note || ''}
                      onBlur={(e) => saveNote(eleve.id, e.target.value)}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          saveNote(eleve.id, e.currentTarget.value);
                        }
                      }}
                      autoFocus
                      className="w-20 px-2 py-1 border rounded"
                    />
                  ) : (
                    <button
                      onClick={() => setEditingId(eleve.id)}
                      className="w-20 px-2 py-1 text-left hover:bg-gray-100 rounded"
                    >
                      {note?.note || '-'}
                    </button>
                  )}
                </td>
                <td className="px-6 py-4 whitespace-nowrap text-sm">
                  {note?.synced ? (
                    <span className="text-green-600">✓ Synchronisé</span>
                  ) : note ? (
                    <span className="text-orange-600">⏳ En attente</span>
                  ) : (
                    <span className="text-gray-400">-</span>
                  )}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
```

---

## 8. Tests Phase 4

### Tests Backend

```bash
php artisan make:test --pest Feature/SyncBootstrapTest
php artisan make:test --pest Feature/SyncPushTest
php artisan make:test --pest Feature/SyncPullTest
php artisan make:test --pest Feature/ConflictResolutionTest
```

### Tests Frontend (Vitest)

**Fichier**: `resources/js/tests/sync.test.ts`

```typescript
import { describe, it, expect, beforeEach } from 'vitest';
import { db } from '../db/schema';

describe('Sync Manager', () => {
  beforeEach(async () => {
    await db.delete();
    await db.open();
  });

  it('should bootstrap data correctly', async () => {
    // Test bootstrap logic
  });

  it('should queue offline changes', async () => {
    // Test sync queue
  });

  it('should sync when coming back online', async () => {
    // Test auto-sync
  });
});
```

---

## 9. Checklist Phase 4

### Configuration
- [ ] Installer packages npm
- [ ] Configurer Vite PWA
- [ ] Générer icônes PWA
- [ ] Configurer manifest

### Base de Données Locale
- [ ] Créer schema Dexie
- [ ] Définir tables IndexedDB
- [ ] Tester CRUD local

### Backend Sync
- [ ] Service `SyncBootstrapService`
- [ ] Service `SyncPushService`
- [ ] Service `SyncPullService`
- [ ] Service `ConflictResolverService`
- [ ] Endpoints API sync
- [ ] Tests backend sync

### Frontend React
- [ ] Hook `useSyncManager`
- [ ] Component `NoteEncodingTable`
- [ ] Component `AttendanceTable`
- [ ] Gestion offline/online
- [ ] Auto-sync
- [ ] Tests frontend

### Qualité
- [ ] Exécuter `vendor/bin/pint --dirty --format agent`
- [ ] Exécuter `php artisan test --compact`
- [ ] Exécuter `npm run test`
- [ ] PWA installable
- [ ] Performance < 3s

---

## 10. Livrables Phase 4

- ✅ PWA installable
- ✅ Encodage notes offline
- ✅ Encodage présences offline
- ✅ Synchronisation automatique
- ✅ Gestion conflits
- ✅ Bootstrap initial
- ✅ Interface React moderne
- ✅ Tests complets
- ✅ Performance optimale

---

## Prochaine Étape

Une fois la Phase 4 validée → **Phase 5: Architecture Avancée**
