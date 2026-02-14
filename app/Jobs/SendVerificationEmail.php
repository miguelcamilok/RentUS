<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VerificationCode;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    /**
     * IMPORTANTE: Solo guardamos IDs, no los modelos completos
     */
    public function __construct(
        public int $userId,
        public int $verificationCodeId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MailService $mailService): void
    {
        // Cargar los modelos desde la base de datos
        $user = User::find($this->userId);
        $verificationCode = VerificationCode::find($this->verificationCodeId);

        // Validar que existan
        if (!$user) {
            Log::error('❌ Usuario no encontrado en Job', ['user_id' => $this->userId]);
            return;
        }

        if (!$verificationCode) {
            Log::error('❌ VerificationCode no encontrado en Job', ['code_id' => $this->verificationCodeId]);
            return;
        }

        Log::info('🚀 Job iniciado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'code' => $verificationCode->code,
        ]);

        try {
            // Usar el MailService para enviar el correo
            $emailSent = $mailService->sendConfirmationEmail($user, $verificationCode);

            if ($emailSent) {
                Log::info('✅ Correo de verificación enviado exitosamente', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } else {
                Log::error('❌ sendConfirmationEmail retornó false', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                throw new \Exception('Mail service returned false');
            }
        } catch (\Exception $e) {
            Log::error('❌ Excepción al enviar correo', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('💀 Job FALLÓ completamente', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'class' => get_class($exception),
        ]);
    }
}
