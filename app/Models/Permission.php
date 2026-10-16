<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $table = 'permissions';

    protected $fillable = [
        'code',
        'name',
        'nom',
        'guard_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (Permission $permission): void {
            if (blank($permission->name) && filled($permission->code)) {
                $permission->name = $permission->code;
            }

            if (blank($permission->code) && filled($permission->name)) {
                $permission->code = $permission->name;
            }

            if (blank($permission->guard_name)) {
                $permission->guard_name = 'web';
            }

            if (blank($permission->nom)) {
                $permission->nom = $permission->name ?? $permission->code;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes = []): static
    {
        $attributes['name'] ??= $attributes['code'] ?? null;
        $attributes['code'] ??= $attributes['name'] ?? null;
        $attributes['guard_name'] ??= 'web';

        return static::query()->create($attributes);
    }
}
