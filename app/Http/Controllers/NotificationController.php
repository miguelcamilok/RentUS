<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Obtener todas las notificaciones del usuario
     */
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    /**
     * Obtener solo notificaciones no leídas
     */
    public function unread()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->where('read', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    /**
     * Obtener contador de notificaciones no leídas
     */
    public function unreadCount()
    {
        $count = Notification::where('user_id', Auth::id())
            ->where('read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);

        // Verificar que pertenece al usuario
        if ($notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $notification->update([
            'read' => true,
            'read_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Notificación marcada como leída',
            'data' => $notification
        ]);
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead()
    {
        $updated = Notification::where('user_id', Auth::id())
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => Carbon::now(),
            ]);

        return response()->json([
            'message' => 'Todas las notificaciones marcadas como leídas',
            'count' => $updated
        ]);
    }

    /**
     * Eliminar una notificación
     */
    public function delete($id)
    {
        $notification = Notification::findOrFail($id);

        // Verificar que pertenece al usuario
        if ($notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notificación eliminada']);
    }
}