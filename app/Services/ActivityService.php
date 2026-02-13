<?php

// app/Services/ActivityService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ActivityService
{
    /**
     * Guardar una actividad en cache
     */
    public static function log(string $type, array $data, string $icon, string $color, string $title, $timestamp = null)
    {
        $activityKey = 'activity_' . time() . '_' . uniqid();
        $timestamp = $timestamp ?? now();

        $activityData = [
            'id' => $activityKey,
            'type' => $type,
            'data' => $data,
            'created_at' => $timestamp instanceof \Carbon\Carbon ? $timestamp->toISOString() : $timestamp,
            'timestamp' => $timestamp instanceof \Carbon\Carbon ? $timestamp->timestamp : strtotime($timestamp),
            'icon' => $icon,
            'color' => $color,
            'title' => $title,
        ];

        // Guardar actividad individual
        Cache::put($activityKey, $activityData, now()->addHours(24));

        // Agregar a lista de actividades recientes
        $recentActivities = Cache::get('recent_activities', []);
        array_unshift($recentActivities, $activityKey);
        $recentActivities = array_slice($recentActivities, 0, 50); // Mantener solo 50
        Cache::put('recent_activities', $recentActivities, now()->addHours(24));

        return $activityKey;
    }

    /**
     * Obtener actividades recientes
     */
    public static function getRecentActivities($limit = 10)
    {
        $activityKeys = Cache::get('recent_activities', []);
        $activities = [];

        foreach ($activityKeys as $key) {
            if ($activity = Cache::get($key)) {
                $activities[] = $activity;
            }

            if (count($activities) >= $limit) {
                break;
            }
        }

        return $activities;
    }

    // ==================== USUARIOS ====================

    public static function logUserDeleted($user, $deletedBy = null)
    {
        return self::log(
            'user_deleted',
            [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_id' => $user->id,
                'deleted_by' => $deletedBy ? $deletedBy->name : 'Sistema',
                'deleted_by_id' => $deletedBy ? $deletedBy->id : null,
                'deleted_at' => now()->toISOString(),
            ],
            '🗑️',
            '#ef4444',
            'Usuario eliminado',
            now()
        );
    }

    public static function logUserStatusChanged($user, $oldStatus, $changedBy = null)
    {
        return self::log(
            'user_status_changed',
            [
                'user_name' => $user->name,
                'user_id' => $user->id,
                'old_status' => $oldStatus,
                'new_status' => $user->status,
                'changed_by' => $changedBy ? $changedBy->name : 'Sistema',
                'changed_by_id' => $changedBy ? $changedBy->id : null,
                'changed_at' => now()->toISOString(),
            ],
            '🔄',
            '#06b6d4',
            'Estado de usuario cambiado',
            now()
        );
    }

    public static function logUserRoleChanged($user, $oldRole, $changedBy = null)
    {
        return self::log(
            'user_role_changed',
            [
                'user_name' => $user->name,
                'user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $user->role,
                'changed_by' => $changedBy ? $changedBy->name : 'Sistema',
                'changed_by_id' => $changedBy ? $changedBy->id : null,
                'changed_at' => now()->toISOString(),
            ],
            '👑',
            '#8b5cf6',
            'Rol de usuario cambiado',
            now()
        );
    }

    public static function logUserCreatedByAdmin($user, $createdBy = null)
    {
        return self::log(
            'user_registered',
            [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_id' => $user->id,
                'created_by' => $createdBy ? $createdBy->name : 'Sistema',
                'created_by_id' => $createdBy ? $createdBy->id : null,
            ],
            '👤',
            '#3b86f7',
            'Nuevo usuario registrado',
            $user->created_at
        );
    }

    public static function logUserUpdated($user, $changes, $updatedBy = null)
    {
        return self::log(
            'user_updated',
            [
                'user_name' => $user->name,
                'user_id' => $user->id,
                'changes' => $changes,
                'updated_by' => $updatedBy ? $updatedBy->name : 'Sistema',
                'updated_by_id' => $updatedBy ? $updatedBy->id : null,
                'updated_at' => now()->toISOString(),
            ],
            '✏️',
            '#f59e0b',
            'Usuario actualizado',
            now()
        );
    }

    // ==================== PROPIEDADES ====================

    public static function logPropertyCreated($property, $createdBy = null)
    {
        return self::log(
            'property_created',
            [
                'property_title' => $property->title,
                'property_id' => $property->id,
                'created_by' => $createdBy ? $createdBy->name : 'Sistema',
                'created_by_id' => $createdBy ? $createdBy->id : null,
            ],
            '🏠',
            '#10b981',
            'Nueva propiedad creada',
            $property->created_at
        );
    }

    public static function logPropertyDeleted($property, $deletedBy = null)
    {
        return self::log(
            'property_deleted',
            [
                'property_title' => $property->title,
                'property_id' => $property->id,
                'deleted_by' => $deletedBy ? $deletedBy->name : 'Sistema',
                'deleted_by_id' => $deletedBy ? $deletedBy->id : null,
                'deleted_at' => now()->toISOString(),
            ],
            '🗑️',
            '#ef4444',
            'Propiedad eliminada',
            now()
        );
    }

    public static function logPropertyUpdated($property, $changes, $updatedBy = null)
    {
        return self::log(
            'property_updated',
            [
                'property_title' => $property->title,
                'property_id' => $property->id,
                'changes' => $changes,
                'updated_by' => $updatedBy ? $updatedBy->name : 'Sistema',
                'updated_by_id' => $updatedBy ? $updatedBy->id : null,
                'updated_at' => now()->toISOString(),
            ],
            '✏️',
            '#f59e0b',
            'Propiedad actualizada',
            now()
        );
    }

    public static function logPropertyStatusChanged($property, $oldStatus, $changedBy = null)
    {
        return self::log(
            'property_status_changed',
            [
                'property_title' => $property->title,
                'property_id' => $property->id,
                'old_status' => $oldStatus,
                'new_status' => $property->status,
                'changed_by' => $changedBy ? $changedBy->name : 'Sistema',
                'changed_by_id' => $changedBy ? $changedBy->id : null,
                'changed_at' => now()->toISOString(),
            ],
            '🔄',
            '#06b6d4',
            'Estado de propiedad cambiado',
            now()
        );
    }

    // ==================== CONTRATOS ====================

    public static function logContractCreated($contract, $createdBy = null)
    {
        return self::log(
            'contract_created',
            [
                'contract_id' => $contract->id,
                'property_title' => $contract->property->title ?? 'Propiedad',
                'created_by' => $createdBy ? $createdBy->name : 'Sistema',
                'created_by_id' => $createdBy ? $createdBy->id : null,
            ],
            '📝',
            '#f59e0b',
            'Contrato creado',
            $contract->created_at
        );
    }

    // ==================== PAGOS ====================

    public static function logPaymentReceived($payment, $receivedBy = null)
    {
        return self::log(
            'payment_received',
            [
                'amount' => $payment->amount,
                'payment_id' => $payment->id,
                'received_by' => $receivedBy ? $receivedBy->name : 'Sistema',
                'received_by_id' => $receivedBy ? $receivedBy->id : null,
            ],
            '💰',
            '#8b5cf6',
            'Pago recibido',
            $payment->created_at
        );
    }

    // ==================== MANTENIMIENTOS ====================

    public static function logMaintenanceRequested($maintenance, $requestedBy = null)
    {
        return self::log(
            'maintenance_requested',
            [
                'maintenance_id' => $maintenance->id,
                'property_title' => $maintenance->property->title ?? 'Propiedad',
                'requested_by' => $requestedBy ? $requestedBy->name : 'Sistema',
                'requested_by_id' => $requestedBy ? $requestedBy->id : null,
            ],
            '🔧',
            '#ef4444',
            'Mantenimiento solicitado',
            $maintenance->created_at
        );
    }

    // ==================== SOLICITUDES DE ARRIENDO ====================

    public static function logRentalRequest($request, $requestedBy = null)
    {
        return self::log(
            'rental_request',
            [
                'request_id' => $request->id,
                'property_title' => $request->property->title ?? 'Propiedad',
                'requested_by' => $requestedBy ? $requestedBy->name : 'Sistema',
                'requested_by_id' => $requestedBy ? $requestedBy->id : null,
            ],
            '📋',
            '#06b6d4',
            'Solicitud de arriendo',
            $request->created_at
        );
    }

}
