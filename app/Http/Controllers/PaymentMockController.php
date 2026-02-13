<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

class PaymentMockController extends Controller
{
    public function pay(Request $request, $contractId)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'No autorizado'], 401);

        $contract = Contract::find($contractId);
        if (!$contract || $contract->tenant_id != $user->id) {
            return response()->json(['message' => 'Contrato no válido'], 403);
        }

        $payment = Payment::create([
            'payment_date' => now(),
            'amount' => '1000', // monto de prueba
            'status' => 'paid',
            'payment_method' => 'fake',
            'receipt_path' => null,
            'contract_id' => $contract->id,
        ]);

        return response()->json([
            'message' => 'Pago simulado exitoso',
            'payment' => $payment,
        ]);
    }
}

