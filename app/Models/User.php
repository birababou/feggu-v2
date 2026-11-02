<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'Administrateur',
        'superviseur' => 'Superviseur régional',
        'agent' => 'Agent de structure',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'structure_id', 'region_id', 'telephone', 'actif', 'legacy_md5',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'legacy_md5',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'actif' => 'boolean',
        ];
    }

    public function structure()
    {
        return $this->belongsTo(Structure::class);
    }

    public function estAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function aDeuxFacteurs(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function deuxFacteursObligatoire(): bool
    {
        return in_array($this->role, config('feggu.deux_facteurs_obligatoire', []), true);
    }
}
