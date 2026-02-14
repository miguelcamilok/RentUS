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

    public $tries = 3; // Intentar 3 veces si falla
    public $timeout = 30; // Timeout de 30 segundos

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
        try {
            $emailSent = $mailService->sendConfirmationEmail($this->user, $this->verificationCode);

            if ($emailSent) {
                Log::info('✅ Correo de verificación enviado', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                ]);
            } else {
                Log::error('❌ Fallo al enviar correo de verificación', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Excepción al enviar correo', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-lanzar para que Laravel reintente
        }
    }
}
