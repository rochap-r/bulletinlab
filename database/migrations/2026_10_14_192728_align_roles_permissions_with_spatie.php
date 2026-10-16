<?php

use App\Models\Utilisateur;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('roles', 'name')) {
                $table->string('name', 120)->nullable()->after('tenant_id');
            }

            if (! Schema::hasColumn('roles', 'guard_name')) {
                $table->string('guard_name', 30)->nullable()->after('name');
            }
        });

        $this->addIndexIfMissing('roles', ['name', 'guard_name'], 'roles_name_guard_name_index');

        Schema::table('permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('permissions', 'name')) {
                $table->string('name', 120)->nullable()->after('id');
            }

            if (! Schema::hasColumn('permissions', 'guard_name')) {
                $table->string('guard_name', 30)->nullable()->after('name');
            }
        });

        $this->addIndexIfMissing('permissions', ['name', 'guard_name'], 'permissions_name_guard_name_index');

        DB::table('roles')->whereNull('name')->update(['name' => DB::raw('code')]);
        DB::table('roles')->whereNull('guard_name')->update(['guard_name' => 'web']);
        DB::table('permissions')->whereNull('name')->update(['name' => DB::raw('code')]);
        DB::table('permissions')->whereNull('guard_name')->update(['guard_name' => 'web']);

        Schema::table('utilisateur_roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('utilisateur_roles', 'model_type')) {
                $table->string('model_type')->nullable()->after('role_id');
            }

            if (! Schema::hasColumn('utilisateur_roles', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('model_type');
            }
        });

        $this->addIndexIfMissing('utilisateur_roles', ['tenant_id'], 'utilisateur_roles_tenant_id_index');
        $this->addIndexIfMissing('utilisateur_roles', ['utilisateur_id', 'model_type'], 'utilisateur_roles_model_index');

        DB::table('utilisateur_roles')->whereNull('model_type')->update(['model_type' => Utilisateur::class]);

        // Boucle simple (pas de chunk keyset : la table pivot n'a pas de colonne `id`,
        // et `whereKey` n'existe que sur l'Eloquent builder).
        $orphans = DB::table('utilisateur_roles')->whereNull('tenant_id')->get(['utilisateur_id', 'role_id']);

        $tenantIds = DB::table('utilisateurs')
            ->whereIn('id', $orphans->pluck('utilisateur_id')->unique()->all())
            ->pluck('tenant_id', 'id');

        foreach ($orphans as $row) {
            $tenantId = $tenantIds[$row->utilisateur_id] ?? null;

            if ($tenantId !== null) {
                DB::table('utilisateur_roles')
                    ->where('utilisateur_id', $row->utilisateur_id)
                    ->where('role_id', $row->role_id)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $tenantId]);
            }
        }

        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table): void {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('utilisateur_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index(['utilisateur_id', 'model_type'], 'model_has_permissions_model_index');
                $table->index(['tenant_id'], 'model_has_permissions_tenant_id_index');
                $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
                $table->primary(['permission_id', 'utilisateur_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        $indexes = collect(Schema::getIndexes($table))->pluck('name')->all();

        if (in_array($name, $indexes, true)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_permissions');

        Schema::table('utilisateur_roles', function (Blueprint $table): void {
            $table->dropIndex('utilisateur_roles_tenant_id_index');
            $table->dropIndex('utilisateur_roles_model_index');
            $table->dropColumn(['model_type', 'tenant_id']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropIndex(['name', 'guard_name']);
            $table->dropColumn(['name', 'guard_name']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex(['name', 'guard_name']);
            $table->dropColumn(['name', 'guard_name']);
        });
    }
};
