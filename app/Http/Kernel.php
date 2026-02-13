<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\VerificationCodeService;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Limpiar códigos de verificación expirados cada día a las 2:00 AM
        $schedule->call(function () {
            app(VerificationCodeService::class)->cleanupExpiredCodes();
        })->daily()->at('02:00');

        // Alternativa: usar el comando artisan
        // $schedule->command('verification:cleanup')->daily()->at('02:00');

        // Limpiar usuarios no verificados después de 7 días (opcional)
        $schedule->call(function () {
            \App\Models\User::where('verification_status', 'pending')
                ->where('created_at', '<', now()->subDays(7))
                ->delete();
        })->weekly()->sundays()->at('03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    protected $middleware = [
        // ...
        \Illuminate\Http\Middleware\HandleCors::class,
        // ...
    ];
}
