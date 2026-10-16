<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Support\Config;
use Spatie\Permission\Traits\HasRoles;

class Utilisateur extends Model implements Authenticatable, AuthorizableContract
{
    use Authorizable;
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
    use HasRoles;
    use \Illuminate\Auth\Authenticatable;

    protected $table = 'utilisateurs';

    protected string $guard_name = 'web';

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'nom',
        'postnom',
        'prenom',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'email',
        'telephone',
        'adresse',
        'matricule',
        'qualification',
        'specialite',
        'date_embauche',
        'mot_de_passe',
        'doit_changer_mot_de_passe',
        'derniere_connexion_at',
        'photo_path',
        'actif',
    ];

    protected $hidden = [
        'mot_de_passe',
    ];

    protected $attributes = [
        'doit_changer_mot_de_passe' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (Utilisateur $utilisateur): void {
            if ($utilisateur->isDirty('telephone') || blank($utilisateur->telephone_normalise)) {
                $utilisateur->telephone_normalise = PhoneNumberNormalizer::normalize($utilisateur->telephone);
            }
        });
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relation Spatie avec compatibilité ascendante : inclut les pivots
     * historiques sans `tenant_id` (créés via `attach()` dans les tests
     * et le code existant) en plus du scope d'équipe courant.
     * Le filtre métier sur `roles.tenant_id` (système ou même tenant)
     * reste appliqué, donc aucun rôle d'un autre tenant n'est exposé.
     */
    public function roles(): BelongsToMany
    {
        $relation = $this->morphToMany(
            Config::roleModel(),
            'model',
            Config::modelHasRolesTable(),
            Config::morphKey(),
            app(PermissionRegistrar::class)->pivotRole
        );

        if (! Config::teamsEnabled()) {
            return $relation;
        }

        $teamsKey = Config::teamForeignKey();
        $relation->withPivot($teamsKey);
        $teamField = Config::rolesTable().'.'.$teamsKey;
        $teamId = getPermissionsTeamId();

        if ($teamId === null) {
            return $relation;
        }

        $pivotColumn = $relation->qualifyPivotColumn($teamsKey);

        return $relation
            ->where(fn ($q) => $q->where($pivotColumn, $teamId)->orWhereNull($pivotColumn))
            ->where(fn ($q) => $q->whereNull($teamField)->orWhere($teamField, $teamId));
    }

    /**
     * Permissions directes Spatie, même compatibilité ascendante.
     */
    public function permissions(): BelongsToMany
    {
        $relation = $this->morphToMany(
            Config::permissionModel(),
            'model',
            Config::modelHasPermissionsTable(),
            Config::morphKey(),
            app(PermissionRegistrar::class)->pivotPermission
        );

        if (! Config::teamsEnabled()) {
            return $relation;
        }

        $teamsKey = Config::teamForeignKey();
        $relation->withPivot($teamsKey);
        $teamId = getPermissionsTeamId();

        if ($teamId === null) {
            return $relation;
        }

        $pivotColumn = $relation->qualifyPivotColumn($teamsKey);

        return $relation->where(fn ($q) => $q->where($pivotColumn, $teamId)->orWhereNull($pivotColumn));
    }

    public function attributionsCours(): HasMany
    {
        return $this->hasMany(AttributionCours::class, 'enseignant_id');
    }

    /**
     * Rôle Spatie + vérification du scope tenant.
     *
     * Avec `teams=true` (`tenant_id` = team), Spatie filtre déjà par équipe
     * courante via `setPermissionsTeamId()`. On ajoute ici la garde
     * métier : le rôle doit être système (`tenant_id=null`) ou appartenir
     * au tenant de l'utilisateur, sinon le rôle est ignoré même si le
     * pivot existe (défense en profondeur contre les rattachements
     * inter-tenant forgés).
     */
    public function hasTenantRole(string|array $code, ?int $tenantId = null): bool
    {
        $tenantId ??= $this->tenant_id;

        if (! $this->hasRole($code)) {
            return false;
        }

        $codes = is_array($code) ? $code : [$code];

        return $this->roles()
            ->whereIn('roles.name', $codes)
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('roles.tenant_id');

                if ($tenantId !== null) {
                    $query->orWhere('roles.tenant_id', $tenantId);
                }
            })
            ->exists();
    }

    /**
     * Permission Spatie + vérification du scope tenant.
     */
    public function hasTenantPermission(string $permission, ?int $tenantId = null): bool
    {
        $tenantId ??= $this->tenant_id;

        if (! $this->hasPermissionTo($permission)) {
            return false;
        }

        if ($tenantId === null) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('permissions.name', $permission))
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('roles.tenant_id')->orWhere('roles.tenant_id', $tenantId);
            })
            ->exists()
            || $this->permissions()
                ->where('permissions.name', $permission)
                ->exists();
    }

    /** @return list<string> */
    public function tenantRoleCodes(): array
    {
        return $this->roles()
            ->validForTenant($this->tenant_id)
            ->pluck('roles.name')
            ->unique()->values()->all();
    }

    /** @return list<string> */
    public function tenantPermissionCodes(): array
    {
        return $this->getAllPermissions()
            ->pluck('name')->unique()->values()->all();
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::get(
            fn (): string => collect([$this->nom, $this->postnom, $this->prenom])
                ->filter()
                ->implode(' '),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
            'derniere_connexion_at' => 'datetime',
            'date_naissance' => 'date',
            'date_embauche' => 'date',
        ];
    }
}
