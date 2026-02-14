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
    public $timeout = 60; // Aumentar a 60 segundos

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public VerificationCode $verificationCode
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MailService $mailService): void
    {
        Log::info('🚀 Iniciando envío de correo', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'code' => $this->verificationCode->code,
        ]);

        try {
            $emailSent = $mailService->sendConfirmationEmail($this->user, $this->verificationCode);

            if ($emailSent) {
                Log::info('✅ Correo de verificación enviado exitosamente', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                ]);
            } else {
                Log::error('❌ sendConfirmationEmail retornó false', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                ]);
                throw new \Exception('Mail service returned false');
            }
        } catch (\Exception $e) {
            Log::error('❌ EXCEPCIÓN al enviar correo', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-lanzar para que Laravel reintente
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('💀 Job FALLÓ después de todos los intentos', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
