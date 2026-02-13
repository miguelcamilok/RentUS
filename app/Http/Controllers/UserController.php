<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        // Construir query con filtros
        $query = User::query();

        // Filtro de búsqueda
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filtro de rol
        if ($request->has('role') && !empty($request->role)) {
            $query->where('role', $request->role);
        }

        // Filtro de estado
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // FILTRO DE VERIFICACIÓN
        if ($request->has('verification_status') && !empty($request->verification_status)) {
            $query->where('verification_status', $request->verification_status);
        }

        // Ordenamiento
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $users = $query->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20',
                'role' => 'required|in:user,admin,support',
                'status' => 'required|in:active,pending,inactive,suspended',
                'address' => 'required|string',
                'id_documento' => 'required|string|unique:users,id_documento',
                'department' => 'nullable|string',
                'city' => 'nullable|string',
                'password' => 'required|min:8',
                'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userData = $request->all();
            $userData['password'] = Hash::make($request->password);
            $userData['password_hash'] = Hash::make($request->password);
            $userData['verification_status'] = 'pending';

            // Manejo de foto
            if ($request->hasFile('photo')) {
                $image = $request->file('photo');
                if ($image->isValid()) {
                    $imageData = file_get_contents($image->getRealPath());
                    $base64 = base64_encode($imageData);
                    $mimeType = $image->getMimeType();
                    $userData['photo'] = 'data:' . $mimeType . ';base64,' . $base64;
                }
            }

            $user = User::create($userData);

            // Registrar actividad si fue creado por admin
            // CORRECCIÓN: Usar Auth::check() en lugar de auth()->check()
            if (Auth::check()) {
                ActivityService::logUserCreatedByAdmin($user, Auth::user());
            }

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado correctamente',
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creando usuario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $oldData = $user->toArray();

            // Validación dinámica según los campos enviados
            $rules = [];

            if ($request->has('name')) {
                $rules['name'] = 'string|max:255';
            }

            if ($request->has('email')) {
                $rules['email'] = 'email|unique:users,email,' . $id;
            }

            if ($request->has('phone')) {
                $rules['phone'] = 'string|max:20';
            }

            if ($request->has('role')) {
                $rules['role'] = 'in:user,admin,support';
            }

            if ($request->has('status')) {
                $rules['status'] = 'in:active,pending,inactive,suspended';
            }

            if ($request->has('address')) {
                $rules['address'] = 'string';
            }

            if ($request->has('id_documento')) {
                $rules['id_documento'] = 'string|unique:users,id_documento,' . $id;
            }

            if ($request->has('department')) {
                $rules['department'] = 'nullable|string|max:100';
            }

            if ($request->has('city')) {
                $rules['city'] = 'nullable|string|max:100';
            }

            if ($request->has('bio')) {
                $rules['bio'] = 'nullable|string|max:500';
            }

            if ($request->hasFile('photo')) {
                $rules['photo'] = 'image|mimes:jpeg,png,jpg,gif,webp|max:10240';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Actualizar solo los campos que vienen en la request
            $fieldsToUpdate = [
                'name',
                'email',
                'phone',
                'role',
                'status',
                'address',
                'id_documento',
                'department',
                'city',
                'bio'
            ];

            foreach ($fieldsToUpdate as $field) {
                if ($request->has($field)) {
                    $user->$field = $request->input($field);
                }
            }

            // Manejo de foto en base64
            if ($request->hasFile('photo')) {
                $image = $request->file('photo');
                if ($image->isValid()) {
                    $imageData = file_get_contents($image->getRealPath());
                    $base64 = base64_encode($imageData);
                    $mimeType = $image->getMimeType();
                    $user->photo = 'data:' . $mimeType . ';base64,' . $base64;
                    Log::info('Foto actualizada para usuario: ' . $id);
                }
            }

            $user->save();

            // Registrar actividad si cambió el rol
            if ($request->has('role') && $oldData['role'] !== $user->role) {
                $loggedUser = Auth::user();
                if ($loggedUser) {
                    ActivityService::logUserRoleChanged($user, $oldData['role'], $loggedUser);
                }
            }

            // Recargar el usuario para obtener todos los datos actualizados
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente',
                'user' => $user
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación actualizando usuario: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error actualizando usuario: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $oldStatus = $user->status;

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:active,pending,inactive,suspended'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->status = $request->status;
            $user->save();

            // Registrar actividad - CORRECCIÓN: Verificar autenticación
            $loggedUser = Auth::user();
            if ($loggedUser) {
                ActivityService::logUserStatusChanged($user, $oldStatus, $loggedUser);
            }

            return response()->json([
                'success' => true,
                'message' => 'Estado del usuario actualizado',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error actualizando estado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // Registrar actividad ANTES de eliminar - CORRECCIÓN: Verificar autenticación
            $loggedUser = Auth::user();
            if ($loggedUser) {
                ActivityService::logUserDeleted($user, $loggedUser);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado correctamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error eliminando usuario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStats()
    {
        $total = User::count();
        $active = User::where('status', 'active')->count();
        $inactive = User::where('status', 'inactive')->count();
        $pending = User::where('status', 'pending')->count();
        $suspended = User::where('status', 'suspended')->count();

        $byRole = [
            'user' => User::where('role', 'user')->count(),
            'admin' => User::where('role', 'admin')->count(),
            'support' => User::where('role', 'support')->count(),
        ];

        return response()->json([
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'pending' => $pending,
            'suspended' => $suspended,
            'byRole' => $byRole,
        ]);
    }
}
