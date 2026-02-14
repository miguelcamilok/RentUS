<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class DebugController extends Controller
{
    public function failedJobs()
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'failed_jobs' => $failedJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'payload' => json_decode($job->payload, true),
                    'exception' => $job->exception,
                    'failed_at' => $job->failed_at,
                ];
            })
        ]);
    }

    public function latestLogs()
    {
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            return response()->json(['error' => 'Log file not found']);
        }

        $lines = [];
        $file = new \SplFileObject($logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $iterations = min($lastLine, 200);

        for ($i = 0; $i < $iterations; $i++) {
            $file->seek($lastLine - $i);
            $lines[] = $file->current();
        }

        return response()->json([
            'logs' => array_reverse(array_filter($lines))
        ]);
    }

    public function queueJobs()
    {
        $jobs = DB::table('jobs')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'pending_jobs' => $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'payload' => json_decode($job->payload, true),
                    'attempts' => $job->attempts,
                    'created_at' => date('Y-m-d H:i:s', $job->created_at),
                ];
            })
        ]);
    }

    public function mailConfig()
    {
        // Verificar si existe cache de config
        $configCached = file_exists(base_path('bootstrap/cache/config.php'));

        return response()->json([
            'config_cached' => $configCached,
            'cache_file_exists' => file_exists(base_path('bootstrap/cache/config.php')),
            'env_vars' => [
                'MAIL_MAILER' => env('MAIL_MAILER'),
                'MAIL_HOST' => env('MAIL_HOST'),
                'MAIL_PORT' => env('MAIL_PORT'),
                'MAIL_USERNAME' => env('MAIL_USERNAME'),
                'MAIL_PASSWORD' => env('MAIL_PASSWORD') ? '***' . substr(env('MAIL_PASSWORD'), -4) : null,
                'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
                'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
                'MAIL_FROM_NAME' => env('MAIL_FROM_NAME'),
            ],
            'config_values' => [
                'mail.default' => config('mail.default'),
                'mail.mailers.smtp.transport' => config('mail.mailers.smtp.transport'),
                'mail.mailers.smtp.host' => config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.username' => config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => config('mail.mailers.smtp.password') ? '***' . substr(config('mail.mailers.smtp.password'), -4) : null,
                'mail.mailers.smtp.encryption' => config('mail.mailers.smtp.encryption'),
                'mail.from.address' => config('mail.from.address'),
                'mail.from.name' => config('mail.from.name'),
            ],
        ]);
    }

    /**
     * 🔧 NUEVO: Forzar recache de configuración
     */
    public function recacheConfig()
    {
        try {
            Artisan::call('config:clear');
            Artisan::call('config:cache');

            return response()->json([
                'success' => true,
                'message' => 'Configuración recacheada',
                'config_clear_output' => Artisan::output(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🧪 NUEVO: Test de envío de correo
     */
    public function testMail()
    {
        try {
            $testEmail = env('MAIL_USERNAME');

            \Illuminate\Support\Facades\Mail::raw('Test email from Railway deployment', function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Test Email - ' . now());
            });

            return response()->json([
                'success' => true,
                'message' => 'Correo de prueba enviado a ' . $testEmail,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
