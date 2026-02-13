<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Property;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\RentalRequest;
use App\Models\Maintenance;
use App\Services\ActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard/stats
     */
    public function getStats()
    {
        try {
            Log::info('📊 Obteniendo estadísticas del dashboard');

            // ==================== USUARIOS ====================
            $totalUsers = User::count();
            $activeUsers = User::where('status', 'active')->count();
            $pendingUsers = User::where('verification_status', 'pending')->count();

            Log::info('👥 Usuarios', [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'pending' => $pendingUsers
            ]);

            // ==================== PROPIEDADES ====================
            $propertyStatusCounts = Property::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get();

            Log::info(
                '🏠 Estados de propiedades encontrados:',
                $propertyStatusCounts->pluck('count', 'status')->toArray()
            );

            $totalProperties = Property::count();

            $activeProperties = Property::whereIn('status', [
                'active',
                'Active',
                'ACTIVE',
                'disponible',
                'Disponible',
                'available',
                'Available'
            ])->count();

            $pendingProperties = Property::whereIn('status', [
                'pending',
                'Pending',
                'PENDING',
                'pendiente',
                'Pendiente',
                'revision',
                'Revision'
            ])->count();

            Log::info('🏠 Propiedades', [
                'total' => $totalProperties,
                'active' => $activeProperties,
                'pending' => $pendingProperties
            ]);

            // ==================== CONTRATOS ====================
            $totalContracts = Contract::count();
            $activeContracts = Contract::where('status', 'active')->count();
            $pendingContracts = Contract::where('status', 'pending')->count();
            $expiredContracts = Contract::where('status', 'expired')->count();

            Log::info('📄 Contratos', [
                'total' => $totalContracts,
                'active' => $activeContracts,
                'pending' => $pendingContracts,
                'expired' => $expiredContracts
            ]);

            // ==================== PAGOS ====================
            $totalPayments = Payment::count();
            $paidPayments = Payment::where('status', 'paid')->count();
            $pendingPayments = Payment::where('status', 'pending')->count();
            $totalRevenue = Payment::where('status', 'paid')->sum('amount');

            Log::info('💰 Pagos', [
                'total' => $totalPayments,
                'paid' => $paidPayments,
                'pending' => $pendingPayments,
                'revenue' => $totalRevenue
            ]);

            // ==================== SOLICITUDES DE ARRIENDO ====================
            $totalRequests = RentalRequest::count();
            $pendingRequests = RentalRequest::where('status', 'pending')->count();

            Log::info('📋 Solicitudes', [
                'total' => $totalRequests,
                'pending' => $pendingRequests
            ]);

            // ==================== MANTENIMIENTOS ====================
            $maintenanceStatusCounts = Maintenance::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get();

            Log::info(
                '🔧 Estados de mantenimiento encontrados:',
                $maintenanceStatusCounts->pluck('count', 'status')->toArray()
            );

            $pendingMaintenances = Maintenance::where('status', 'pending')->count();
            $inProgressMaintenances = Maintenance::where('status', 'in_progress')->count();
            $finishedMaintenances = Maintenance::where('status', 'finished')->count();

            Log::info('🔧 Mantenimientos', [
                'pending' => $pendingMaintenances,
                'in_progress' => $inProgressMaintenances,
                'finished' => $finishedMaintenances
            ]);

            // ==================== RESPUESTA ====================
            $response = [
                'success' => true,
                'users' => [
                    'total'   => $totalUsers,
                    'active'  => $activeUsers,
                    'pending' => $pendingUsers,
                ],

                'properties' => [
                    'total'   => $totalProperties,
                    'active'  => $activeProperties,
                    'pending' => $pendingProperties,
                ],

                'contracts' => [
                    'total'   => $totalContracts,
                    'active'  => $activeContracts,
                    'pending' => $pendingContracts,
                    'expired' => $expiredContracts,
                ],

                'payments' => [
                    'total'   => $totalPayments,
                    'paid'    => $paidPayments,
                    'pending' => $pendingPayments,
                    'revenue' => (float) $totalRevenue,
                ],

                'requests' => [
                    'total'   => $totalRequests,
                    'pending' => $pendingRequests,
                ],

                'maintenances' => [
                    'pending'     => $pendingMaintenances,
                    'in_progress' => $inProgressMaintenances,
                    'finished'    => $finishedMaintenances,
                    'total'       => $pendingMaintenances + $inProgressMaintenances + $finishedMaintenances,
                ],
            ];

            Log::info('✅ Estadísticas obtenidas exitosamente');

            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error('❌ Error obteniendo estadísticas del dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * GET /api/admin/dashboard/activity
     * Obtener actividad reciente del sistema
     */
    public function getRecentActivity(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $limit = max(1, min($limit, 5));

            Log::info('📋 Obteniendo actividad reciente', ['limit' => $limit]);

            $activities = collect();

            // ==================== 1. OBTENER ACTIVIDADES DE CACHE ====================
            $cachedActivities = ActivityService::getRecentActivities($limit * 2);

            foreach ($cachedActivities as $activity) {
                $activities->push($activity);
            }

            // ==================== 2. OBTENER USUARIOS RECIENTES (backup) ====================
            if ($activities->count() < $limit) {
                try {
                    $recentUsers = User::select('id', 'name', 'email', 'created_at')
                        ->where('created_at', '>=', now()->subDays(30))
                        ->orderBy('created_at', 'desc')
                        ->take($limit - $activities->count())
                        ->get();

                    foreach ($recentUsers as $user) {
                        // Verificar si ya existe en actividades de cache
                        $exists = $activities->contains(function ($activity) use ($user) {
                            return $activity['type'] === 'user_registered' &&
                                isset($activity['data']['user_id']) &&
                                $activity['data']['user_id'] === $user->id;
                        });

                        if (!$exists) {
                            $activities->push([
                                'id' => 'user_' . $user->id,
                                'type' => 'user_registered',
                                'data' => [
                                    'user_name' => $user->name,
                                    'user_email' => $user->email,
                                    'user_id' => $user->id,
                                ],
                                'created_at' => $user->created_at->toISOString(),
                                'timestamp' => $user->created_at->timestamp,
                                'icon' => '👤',
                                'color' => '#3b86f7',
                                'title' => 'Nuevo usuario registrado',
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Error obteniendo usuarios recientes:', ['error' => $e->getMessage()]);
                }
            }

            // ==================== 3. OBTENER PROPIEDADES RECIENTES ====================
            try {
                $recentProperties = Property::select('id', 'title', 'created_at')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get();

                foreach ($recentProperties as $property) {
                    // Verificar si ya existe en cache
                    $exists = $activities->contains(function ($activity) use ($property) {
                        return $activity['type'] === 'property_created' &&
                            isset($activity['data']['property_id']) &&
                            $activity['data']['property_id'] === $property->id;
                    });

                    if (!$exists) {
                        $activities->push([
                            'id' => 'property_' . $property->id,
                            'type' => 'property_created',
                            'data' => [
                                'property_title' => $property->title,
                                'property_id' => $property->id,
                            ],
                            'created_at' => $property->created_at->toISOString(),
                            'timestamp' => $property->created_at->timestamp,
                            'icon' => '🏠',
                            'color' => '#10b981',
                            'title' => 'Nueva propiedad creada',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('No se pudieron obtener propiedades:', ['error' => $e->getMessage()]);
            }

            // ==================== 4. OBTENER CONTRATOS RECIENTES ====================
            try {
                $recentContracts = Contract::select('id', 'property_id', 'start_date', 'created_at')
                    ->with('property:id,title')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get();

                foreach ($recentContracts as $contract) {
                    $activities->push([
                        'id' => 'contract_' . $contract->id,
                        'type' => 'contract_signed',
                        'data' => [
                            'contract_id' => $contract->id,
                            'property_title' => $contract->property->title ?? 'Propiedad',
                            'start_date' => $contract->start_date ? Carbon::parse($contract->start_date)->format('d/m/Y') : 'No definida',
                        ],
                        'created_at' => $contract->created_at->toISOString(),
                        'timestamp' => $contract->created_at->timestamp,
                        'icon' => '📝',
                        'color' => '#f59e0b',
                        'title' => 'Contrato firmado',
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('No se pudieron obtener contratos:', ['error' => $e->getMessage()]);
            }

            // ==================== 5. OBTENER PAGOS RECIENTES ====================
            try {
                $recentPayments = Payment::select('id', 'amount', 'status', 'created_at')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->where('status', 'paid')
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get();

                foreach ($recentPayments as $payment) {
                    $activities->push([
                        'id' => 'payment_' . $payment->id,
                        'type' => 'payment_received',
                        'data' => [
                            'amount' => number_format($payment->amount, 2),
                            'payment_id' => $payment->id,
                        ],
                        'created_at' => $payment->created_at->toISOString(),
                        'timestamp' => $payment->created_at->timestamp,
                        'icon' => '💰',
                        'color' => '#8b5cf6',
                        'title' => 'Pago recibido',
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('No se pudieron obtener pagos:', ['error' => $e->getMessage()]);
            }

            // ==================== 6. ORDENAR Y LIMITAR ====================
            $sortedActivities = $activities
                ->sortByDesc('timestamp')
                ->take($limit)
                ->values()
                ->map(function ($activity) {
                    $activity['created_at'] = Carbon::parse($activity['created_at'])->toISOString();
                    return $activity;
                })
                ->toArray();

            Log::info('✅ Actividad reciente obtenida', [
                'total' => count($sortedActivities),
                'tipos' => array_count_values(array_column($sortedActivities, 'type'))
            ]);

            return response()->json([
                'success' => true,
                'data' => $sortedActivities,
                'meta' => [
                    'total' => count($sortedActivities),
                    'limit' => $limit,
                    'timestamp' => now()->toISOString(),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('❌ Error obteniendo actividad reciente', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener actividad reciente',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => []
            ], 200);
        }
    }
}
