<?php

namespace App\Models;

use App\Traits\HasSmartScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory, HasSmartScopes;

    public function user(){return $this->belongsTo(User::class);}

    // Campos que se pueden asignar masivamente (por create, update, etc.)
    protected $fillable = [
        'type',             // Tipo de reporte (ej: spam, contenido inapropiado, etc.)
        'applied_filter',   // Filtro aplicado al reporte (ej: por usuario, por fecha, etc.)
        'generation_date',  // Fecha de generación del reporte
        'user_id'           // ID del usuario que generó el reporte
    ];
}
