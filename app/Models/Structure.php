<?php

namespace App\Models;

use App\Models\Ref\Departement;
use Illuminate\Database\Eloquent\Model;

class Structure extends Model
{
    protected $guarded = [];

    protected $casts = ['services' => 'array', 'actif' => 'boolean'];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** Services effectifs : ceux de l'antenne, sinon ceux de l'organisation. */
    public function servicesEffectifs(): array
    {
        return $this->services ?: ($this->organisation?->services ?? []);
    }
}
