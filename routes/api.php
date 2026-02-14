<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPropertyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RentalRequestController;
use App\Http\Controllers\ReportController;

// ============================================
// RUTAS DE AUTENTICACIÓN PÚBLICAS
// ============================================
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('verify-email-check', [AuthController::class, 'checkToken']);
    Route::post('resend-code', [AuthController::class, 'resendVerificationCode']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// ============================================
// RUTAS PÚBLICAS (sin autenticación)
// ============================================
Route::get('properties', [PropertyController::class, 'index']);
Route::get('properties/count', [PropertyController::class, 'count']);
Route::get('properties/{property}', [PropertyController::class, 'show']);
Route::get('users', [UserController::class, 'index']);
Route::post('properties/{property}/view', [PropertyController::class, 'incrementViews']);
Route::get('dashboard/recent-activity', [AdminDashboardController::class, 'getRecentActivity']);

// TEMPORAL - Debug endpoints
Route::get('/debug/failed-jobs', [App\Http\Controllers\DebugController::class, 'failedJobs']);
Route::get('/debug/logs', [App\Http\Controllers\DebugController::class, 'latestLogs']);
Route::get('/debug/queue-jobs', [App\Http\Controllers\DebugController::class, 'queueJobs']);
Route::get('/debug/mail-config', [App\Http\Controllers\DebugController::class, 'mailConfig']); // NUEVO

// ============================================
// RUTAS PROTEGIDAS (requieren autenticación)
// ============================================
Route::middleware('auth:api')->group(function () {

    // AUTH - Gestión de sesión y perfil
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::put('password', [AuthController::class, 'updatePassword']);
    });

    // USERS - CRUD de usuarios
    Route::prefix('users')->group(function () {
        Route::get('{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store'])->middleware('role:admin,support');
        Route::post('{id}', [UserController::class, 'update']);
        Route::put('{id}', [UserController::class, 'update']);
        Route::delete('{id}', [UserController::class, 'destroy'])->middleware('role:admin,support');
    });

    // PROPERTIES - Gestión de propiedades
    Route::prefix('properties')->group(function () {
        Route::post('/', [PropertyController::class, 'store']);
        // ⚠️ IMPORTANTE: Primero POST (para FormData con _method=PUT), luego PUT
        Route::post('{property}', [PropertyController::class, 'update']);
        Route::put('{property}', [PropertyController::class, 'update']);
        Route::delete('{property}', [PropertyController::class, 'destroy']);
        Route::post('{property}/point', [PropertyController::class, 'savePoint']);
    });

    // CONTRACTS - Contratos
    Route::prefix('contracts')->group(function () {
        Route::get('/', [ContractController::class, 'index']);
        Route::get('stats', [ContractController::class, 'stats']);
        Route::put('{id}/accept', [ContractController::class, 'accept']);
        Route::put('{id}/reject', [ContractController::class, 'reject']);
        Route::post('{id}/validate', [ContractController::class, 'validate'])
            ->middleware('role:admin,support');
        Route::post('{id}/cancel', [ContractController::class, 'cancel'])
            ->middleware('role:admin,support');
    });

    // PAYMENTS - Gestión de pagos
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('{id}', [PaymentController::class, 'show']);
        Route::put('{id}', [PaymentController::class, 'update']);
        Route::delete('{id}', [PaymentController::class, 'destroy']);
    });

    // RATINGS - Calificaciones
    Route::prefix('ratings')->group(function () {
        Route::get('/', [RatingController::class, 'index']);
        Route::post('/', [RatingController::class, 'store']);
        Route::get('{id}', [RatingController::class, 'show']);
        Route::put('{id}', [RatingController::class, 'update']);
        Route::delete('{id}', [RatingController::class, 'destroy']);
    });

    // MAINTENANCES - Mantenimientos
    Route::prefix('maintenances')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index']);
        Route::post('/', [MaintenanceController::class, 'store']);
        Route::get('{id}', [MaintenanceController::class, 'show']);
        Route::put('{id}', [MaintenanceController::class, 'update']);
        Route::delete('{id}', [MaintenanceController::class, 'destroy']);
    });

    // REPORTS - Reportes
    Route::prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::post('/', [ReportController::class, 'store']);
        Route::get('{id}', [ReportController::class, 'show']);
        Route::put('{id}', [ReportController::class, 'update']);
        Route::delete('{id}', [ReportController::class, 'destroy']);
    });

    // RENTAL REQUESTS - Solicitudes de alquiler
    Route::post('/rental-requests', [RentalRequestController::class, 'create']);
    Route::get('/rental-requests/my-requests', [RentalRequestController::class, 'getMyRequests']);
    Route::put('/rental-requests/{id}/accept-counter', [RentalRequestController::class, 'acceptCounterProposal']);
    Route::put('/rental-requests/{id}/reject-counter', [RentalRequestController::class, 'rejectCounterProposal']);
    Route::get('/rental-requests/owner', [RentalRequestController::class, 'getOwnerRequests']);
    Route::put('/rental-requests/{id}/accept', [RentalRequestController::class, 'acceptRequest']);
    Route::put('/rental-requests/{id}/reject', [RentalRequestController::class, 'rejectRequest']);
    Route::put('/rental-requests/{id}/counter-propose', [RentalRequestController::class, 'counterPropose']);
    Route::get('/rental-requests/{id}/visit-status', [RentalRequestController::class, 'checkVisitStatus']);
    Route::post('/rental-requests/send-contract', [RentalRequestController::class, 'sendContractTerms']);
    Route::get('/rental-requests/{id}', [RentalRequestController::class, 'getDetails']);
    Route::delete('/rental-requests/{id}', [RentalRequestController::class, 'cancel']);

    // NOTIFICATIONS - Notificaciones
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'delete']);

    // ============================================
    // ⭐ ADMIN PANEL - RUTAS SOLO PARA ADMIN Y SUPPORT
    // ============================================
    Route::prefix('admin')->middleware('role:admin,support')->group(function () {

        // Dashboard
        Route::get('dashboard/stats', [AdminDashboardController::class, 'getStats']);

        // Usuarios
        Route::put('users/{id}/status', [UserController::class, 'updateStatus']);

        // ⭐ PROPIEDADES - ADMIN
        Route::prefix('properties')->group(function () {
            // Estadísticas de propiedades
            Route::get('stats', [AdminPropertyController::class, 'stats']);

            // Propiedades pendientes de aprobación
            Route::get('pending', [AdminPropertyController::class, 'pending']);

            // Actividad reciente
            Route::get('recent-activity', [AdminPropertyController::class, 'recentActivity']);

            // Actualizar estado de aprobación
            Route::put('{id}/approval', [AdminPropertyController::class, 'updateApproval']);

            // Actualizar visibilidad
            Route::put('{id}/visibility', [AdminPropertyController::class, 'updateVisibility']);

            // Acción masiva (bulk action)
            Route::post('bulk-action', [AdminPropertyController::class, 'bulkAction']);
        });
    });
});
