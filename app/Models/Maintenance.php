<?php

namespace App\Models;

use App\Traits\HasSmartScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Maintenance extends Model
{
    use HasFactory, HasSmartScopes;

    // Campos que se pueden asignar masivamente (por create, update, etc.)
    protected $fillable = [
        'description',            // Descripción de la mantenimiento
        'request_date',           // Fecha en que se realizó la solicitud
        'status',                 // Estado actual de la solicitud (pendiente, resuelto, etc.)
        'resolution_date',        // Fecha en que se resolvió la solicitud
        'validated_by_tenant',    // Validación por parte del inquilino
        'property_id',            // ID de la propiedad relacionada con el mantenimiento
        'user_id'                 // ID del usuario que realizó la solicitud
    ];

    function property(){return $this->belongsTo(Property::class);}
    
    function user(){return $this->belongsTo(User::class);}

}
