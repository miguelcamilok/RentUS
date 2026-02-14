<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VerificationCode;
use App\Services\VerificationCodeService;
use App\Services\MailService;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Jobs\SendVerificationEmail;

class AuthController extends Controller
{
    protected $verificationService;
    protected $mailService;
    protected $tokenService;

    public function __construct(
        VerificationCodeService $verificationService,
        MailService $mailService,
        TokenService $tokenService
    ) {
        $this->verificationService = $verificationService;
        $this->mailService = $mailService;
        $this->tokenService = $tokenService;
    }

    /**
     * Registrar nuevo usuario
     */
    /**
     * Registrar nuevo usuario
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|min:2',
            'phone' => 'required|string|regex:/^[0-9]{10,20}$/|unique:users,phone',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'address' => 'required|string|max:255|min:5',
            'id_documento' => 'required|string|max:50|unique:users,id_documento',
        ], [
            'name.required' => 'El nombre es obligatorio',
            'name.min' => 'El nombre debe tener al menos 2 caracteres',
            'phone.required' => 'El teléfono es obligatorio',
            'phone.regex' => 'El teléfono debe contener entre 10 y 20 dígitos',
            'phone.unique' => 'Este teléfono ya está registrado',
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'Debe ingresar un correo válido',
            'email.unique' => 'Este correo ya está registrado',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'address.required' => 'La dirección es obligatoria',
            'address.min' => 'La dirección debe tener al menos 5 caracteres',
            'id_documento.required' => 'El documento de identidad es obligatorio',
            'id_documento.unique' => 'Este documento ya está registrado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Crear usuario con estado pendiente y rol por defecto 'user'
            $user = User::create([
                'name' => $request->name,
                'email' => strtolower(trim($request->email)),
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'password_hash' => Hash::make($request->password),
                'address' => $request->address,
                'id_documento' => $request->id_documento,
                'status' => 'inactive', // Usuario inactivo hasta verificar correo
                'verification_status' => 'pending',
                'role' => 'user',
            ]);

            // Generar código de verificación
            $verificationCode = $this->verificationService->generateCode(
                $user->email,
                'email_verification'
            );

            // ===== ENVIAR CORREO EN SEGUNDO PLANO =====
            SendVerificationEmail::dispatch($user, $verificationCode);

            Log::info('📧 Correo de verificación en cola', [
                'user_id' => $user->id,
                'email' => $user->email,
                'code' => $verificationCode->code,
                'token' => $verificationCode->token,
            ]);

            DB::commit();

            // Responder INMEDIATAMENTE sin esperar el correo
            return response()->json([
                'success' => true,
                'message' => 'Usuario registrado exitosamente. Por favor, verifica tu correo electrónico.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'verification_status' => $user->verification_status,
                        'role' => $user->role,
                    ],
                    'verification_required' => true,
                    'verification_token' => $verificationCode->token,
                    'email' => $user->email
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error en registro', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar usuario',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Verificar código de correo electrónico
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'token' => 'required|string',
        ]);

        $verification = VerificationCode::where('token', $request->token)
            ->where('code', $request->code)
            ->where('used', false)
            ->first();

        if (!$verification || $verification->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Código inválido o expirado']);
        }

        $user = User::where('email', $verification->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado']);
        }

        $user->email_verified_at = now();
        $user->status = 'active';
        $user->verification_status = 'verified';
        $user->save();

        // Marcar código como usado
        $verification->used = true;
        $verification->save();

        // Retornar token para que el frontend guarde sesión
        $token = $this->tokenService->generateToken($user, false);

        return response()->json([
            'success' => true,
            'message' => 'Correo verificado exitosamente',
            'token' => $token,
            'token_type' => 'bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'verification_status' => $user->verification_status,
                'role' => $user->role, // ← INCLUIR ROL
            ]
        ]);
    }

    public function checkToken(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $verification = VerificationCode::where('token', $request->token)
            ->where('used', false)
            ->first();

        if (!$verification || $verification->isExpired()) {
            return response()->json(['success' => false], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Reenviar código de verificación
     */
    public function resendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ], [
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'Debe ingresar un correo válido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', strtolower(trim($request->email)))->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Verificar si ya está verificado
            if ($user->verification_status === 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este correo ya ha sido verificado'
                ], 400);
            }

            // Verificar cooldown
            $cooldownCheck = $this->verificationService->checkCooldown(
                $user->email,
                'email_verification'
            );

            if (!$cooldownCheck['can_resend']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes esperar antes de solicitar un nuevo código',
                    'retry_after' => $cooldownCheck['remaining']
                ], 429);
            }

            // Generar nuevo código
            $verificationCode = $this->verificationService->generateCode(
                $user->email,
                'email_verification'
            );

            // Enviar correo
            $emailSent = $this->mailService->sendCodeResendEmail($user, $verificationCode);

            if (!$emailSent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar el correo. Intenta nuevamente.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Código de verificación enviado exitosamente',
                'data' => [
                    'email' => $user->email,
                    'expires_in' => 10, // minutos
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al reenviar código', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reenviar código',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Login de usuario
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'nullable|boolean',
        ], [
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'Debe ingresar un correo válido',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $credentials = [
                'email' => strtolower(trim($request->email)),
                'password' => $request->password
            ];

            // Verificar si el usuario existe
            $user = User::where('email', $credentials['email'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Las credenciales no coinciden con nuestros registros'
                ], 401);
            }

            // Verificar si el correo está verificado
            if ($user->verification_status !== 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes verificar tu correo electrónico antes de iniciar sesión',
                    'verification_required' => true,
                    'email' => $user->email
                ], 403);
            }

            // Verificar si el usuario está activo
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu cuenta está inactiva. Contacta al administrador'
                ], 403);
            }

            // Verificar credenciales
            if (!JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contraseña incorrecta'
                ], 401);
            }

            // Configurar TTL del token según "Recordarme"
            $remember = $request->input('remember', false);
            $token = $this->tokenService->generateToken($user, $remember);

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar token'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'photo' => $user->photo,
                    'verification_status' => $user->verification_status,
                    'role' => $user->role, // ← INCLUIR ROL
                ],
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->tokenService->getTTLInSeconds($remember),
                'remember' => $remember
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en login', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en el inicio de sesión',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Solicitar recuperación de contraseña
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ], [
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'Debe ingresar un correo válido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', strtolower(trim($request->email)))->first();

            if (!$user) {
                // Por seguridad, no revelar si el correo existe
                return response()->json([
                    'success' => true,
                    'message' => 'Si el correo existe, recibirás instrucciones para recuperar tu contraseña'
                ], 200);
            }

            // Verificar cooldown
            $cooldownCheck = $this->verificationService->checkCooldown(
                $user->email,
                'password_reset'
            );

            if (!$cooldownCheck['can_resend']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes esperar antes de solicitar un nuevo código',
                    'retry_after' => $cooldownCheck['remaining']
                ], 429);
            }

            // Generar código de recuperación
            $verificationCode = $this->verificationService->generateCode(
                $user->email,
                'password_reset'
            );

            // Enviar correo
            $emailSent = $this->mailService->sendResetPasswordEmail($user, $verificationCode);

            if (!$emailSent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar el correo. Intenta nuevamente.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Si el correo existe, recibirás instrucciones para recuperar tu contraseña',
                'data' => [
                    'expires_in' => 10, // minutos
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en forgot password', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Restablecer contraseña
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required_without:token|string|size:6',
            'token' => 'required_without:code|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.required' => 'El correo electrónico es obligatorio',
            'email.email' => 'Debe ingresar un correo válido',
            'code.required_without' => 'El código o el token es obligatorio',
            'code.size' => 'El código debe tener 6 dígitos',
            'token.required_without' => 'El código o el token es obligatorio',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', strtolower(trim($request->email)))->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Verificar código o token
            $verificationCode = null;

            if ($request->has('code')) {
                $verificationCode = $this->verificationService->verifyCode(
                    $user->email,
                    $request->code,
                    'password_reset'
                );
            } elseif ($request->has('token')) {
                $verificationCode = $this->verificationService->verifyToken(
                    $request->token,
                    'password_reset'
                );
            }

            if (!$verificationCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Código o token inválido o expirado'
                ], 400);
            }

            DB::beginTransaction();

            // Actualizar contraseña
            $user->password = Hash::make($request->password);
            $user->password_hash = Hash::make($request->password);
            $user->save();

            // Marcar código como usado
            $verificationCode->markAsUsed();

            DB::commit();

            // Enviar notificación de cambio exitoso
            $this->mailService->sendPasswordChangedNotification($user);

            return response()->json([
                'success' => true,
                'message' => 'Contraseña restablecida exitosamente'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al restablecer contraseña', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al restablecer la contraseña',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Cambiar contraseña del usuario autenticado
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria',
            'new_password.required' => 'La nueva contraseña es obligatoria',
            'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres',
            'new_password.confirmed' => 'Las contraseñas no coinciden',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $this->tokenService->getUserFromToken();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Verificar contraseña actual
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual es incorrecta'
                ], 401);
            }

            // Verificar que la nueva contraseña sea diferente
            if (Hash::check($request->new_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La nueva contraseña debe ser diferente a la actual'
                ], 422);
            }

            // Actualizar contraseña
            $user->password = Hash::make($request->new_password);
            $user->password_hash = Hash::make($request->new_password);
            $user->save();

            // Enviar notificación
            $this->mailService->sendPasswordChangedNotification($user);

            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al actualizar contraseña', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la contraseña',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener usuario autenticado
     */
    public function me()
    {
        try {
            $user = $this->tokenService->getUserFromToken();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'id_documento' => $user->id_documento,
                    'status' => $user->status,
                    'verification_status' => $user->verification_status,
                    'photo' => $user->photo,
                    'bio' => $user->bio,
                    'department' => $user->department,
                    'city' => $user->city,
                    'role' => $user->role, // ← INCLUIR ROL
                    'created_at' => $user->created_at,
                    'email_verified_at' => $user->email_verified_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener usuario', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuario'
            ], 500);
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        try {
            $this->tokenService->invalidateToken();

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar sesión'
            ], 500);
        }
    }

    /**
     * Refrescar token
     */
    public function refresh()
    {
        try {
            $newToken = $this->tokenService->refreshToken();

            if (!$newToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token expirado, por favor inicia sesión nuevamente'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'token' => $newToken,
                'token_type' => 'bearer'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al refrescar token'
            ], 500);
        }
    }
}
