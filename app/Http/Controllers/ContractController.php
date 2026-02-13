<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContractController extends Controller
{
    /**
     * Obtener todos los contratos del usuario autenticado
     */
    public function index()
    {
        try {
            $userId = Auth::id();

            // Obtener contratos donde el usuario sea tenant o landlord
            $contracts = Contract::with(['property', 'tenant', 'landlord'])
                ->where(function ($query) use ($userId) {
                    $query->where('tenant_id', $userId)
                        ->orWhere('landlord_id', $userId);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            // ✅ Log para debugging
            Log::info('Contratos obtenidos', [
                'user_id' => $userId,
                'total_contracts' => $contracts->count(),
                'pending_as_tenant' => $contracts->where('status', 'pending')->where('tenant_id', $userId)->count()
            ]);

            // ✅ CRÍTICO: Asegurar que tenant_id y landlord_id se incluyan en la respuesta
            $contractsData = $contracts->map(function ($contract) use ($userId) {
                return [
                    'id' => $contract->id,
                    'start_date' => $contract->start_date,
                    'end_date' => $contract->end_date,
                    'status' => $contract->status,
                    'document_path' => $contract->document_path,
                    'deposit' => $contract->deposit,
                    'validated_by_support' => $contract->validated_by_support,
                    'accepted_by_tenant' => $contract->accepted_by_tenant,

                    // ✅ IDs críticos para la lógica frontend
                    'tenant_id' => $contract->tenant_id,
                    'landlord_id' => $contract->landlord_id,
                    'property_id' => $contract->property_id,

                    // Relaciones
                    'property' => $contract->property ? [
                        'id' => $contract->property->id,
                        'title' => $contract->property->title,
                        'address' => $contract->property->address,
                        'image_url' => $contract->property->image_url,
                        'monthly_price' => $contract->property->monthly_price,
                        'area_m2' => $contract->property->area_m2,
                        'num_bedrooms' => $contract->property->num_bedrooms,
                        'num_bathrooms' => $contract->property->num_bathrooms,
                    ] : null,

                    'tenant' => $contract->tenant ? [
                        'id' => $contract->tenant->id,
                        'name' => $contract->tenant->name,
                        'email' => $contract->tenant->email,
                        'id_documento' => $contract->tenant->id_documento,
                    ] : null,

                    'landlord' => $contract->landlord ? [
                        'id' => $contract->landlord->id,
                        'name' => $contract->landlord->name,
                        'email' => $contract->landlord->email,
                        'id_documento' => $contract->landlord->id_documento,
                    ] : null,

                    'created_at' => $contract->created_at,
                    'updated_at' => $contract->updated_at,
                ];
            });

            return response()->json($contractsData, 200);
        } catch (\Exception $e) {
            Log::error('Error obteniendo contratos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al obtener contratos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de contratos del usuario
     */
    public function stats()
    {
        try {
            $userId = Auth::id();

            $stats = [
                'active' => Contract::where(function ($query) use ($userId) {
                    $query->where('tenant_id', $userId)
                        ->orWhere('landlord_id', $userId);
                })
                    ->where('status', 'active')
                    ->count(),

                'pending' => Contract::where(function ($query) use ($userId) {
                    $query->where('tenant_id', $userId)
                        ->orWhere('landlord_id', $userId);
                })
                    ->where('status', 'pending')
                    ->count(),

                'total' => Contract::where(function ($query) use ($userId) {
                    $query->where('tenant_id', $userId)
                        ->orWhere('landlord_id', $userId);
                })
                    ->count(),
            ];

            return response()->json($stats, 200);
        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al obtener estadísticas',
            ], 500);
        }
    }

    /**
     * Aceptar contrato (solo inquilino)
     */
    public function accept($id)
    {
        try {
            $userId = Auth::id();
            $contract = Contract::findOrFail($id);

            // Verificar que el usuario sea el inquilino
            if ($contract->tenant_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para aceptar este contrato'
                ], 403);
            }

            // Verificar que esté pendiente
            if ($contract->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este contrato ya no está pendiente'
                ], 400);
            }

            // Actualizar contrato
            $contract->status = 'active';
            $contract->accepted_by_tenant = 'yes';
            $contract->tenant_acceptance_date = now();
            $contract->save();

            Log::info('Contrato aceptado', [
                'contract_id' => $id,
                'tenant_id' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contrato aceptado exitosamente',
                'contract' => $contract
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error aceptando contrato', [
                'contract_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aceptar el contrato'
            ], 500);
        }
    }

    /**
     * Rechazar contrato (solo inquilino)
     */
    public function reject($id)
    {
        try {
            $userId = Auth::id();
            $contract = Contract::findOrFail($id);

            // Verificar que el usuario sea el inquilino
            if ($contract->tenant_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para rechazar este contrato'
                ], 403);
            }

            // Verificar que esté pendiente
            if ($contract->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este contrato ya no está pendiente'
                ], 400);
            }

            // Actualizar contrato
            $contract->status = 'rejected';
            $contract->accepted_by_tenant = 'no';
            $contract->save();

            Log::info('Contrato rechazado', [
                'contract_id' => $id,
                'tenant_id' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contrato rechazado',
                'contract' => $contract
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error rechazando contrato', [
                'contract_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar el contrato'
            ], 500);
        }
    }
}
