<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = ['nombre', 'telefono'];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}