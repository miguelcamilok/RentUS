<?php

namespace App\Models;

use App\Traits\HasSmartScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory, HasSmartScopes;

    public function contract(){ return $this->belongsTo(Contract::class); }

    // Campos que se pueden asignar masivamente (por create, update, etc.)
    protected $fillable = [
        'payment_date',     // Fecha en la que se realizó el pago
        'amount',           // Monto del pago
        'status',           // Estado del pago (ej: pendiente, aprobado, rechazado)
        'payment_method',   // Método de pago utilizado (transferencia, efectivo, etc.)
        'receipt_path',     // Ruta al comprobante o recibo de pago
        'contract_id'       // ID del contrato asociado al pago
    ];

}
