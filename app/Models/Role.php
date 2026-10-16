<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $table = 'roles';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'nom',
        'guard_name',
        'system_role',
    ];

    protected function casts(): array
    {
        return [
            'system_role' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            if (blank($role->name) && filled($role->code)) {
                $role->name = $role->code;
            }

            if (blank($role->code) && filled($role->name)) {
                $role->code = $role->name;
            }

            if (blank($role->guard_name)) {
                $role->guard_name = 'web';
            }

            if (blank($role->nom)) {
                $role->nom = $role->name ?? $role->code;
            }
        });
    }

    /**
     * Surcharge compatible avec l'historique : `code` accepté comme alias
     * de `name`, unicité par `[tenant_id, code]` (pas globale Spatie).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes = []): static
    {
        $attributes['name'] ??= $attributes['code'] ?? null;
        $attributes['code'] ??= $attributes['name'] ?? null;
        $attributes['guard_name'] ??= 'web';

        return static::query()->create($attributes);
    }

    /** @param Builder<Role> $query */
    public function scopeValidForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where(function (Builder $inner) use ($tenantId): void {
            $inner->whereNull('roles.tenant_id');

            if ($tenantId !== null) {
                $inner->orWhere('roles.tenant_id', $tenantId);
            }
        });
    }

    public function isSystem(): bool
    {
        return $this->tenant_id === null || (bool) $this->system_role;
    }
}
