<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    /**
     * NUEVO: Endpoint para ver configuración actual de MAIL
     */
    public function mailConfig()
    {
        return response()->json([
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
            'config_cache' => [
                'mail.mailer' => config('mail.mailer'),
                'mail.host' => config('mail.host'),
                'mail.port' => config('mail.port'),
                'mail.username' => config('mail.username'),
                'mail.password' => config('mail.password') ? '***' . substr(config('mail.password'), -4) : null,
                'mail.encryption' => config('mail.encryption'),
                'mail.from.address' => config('mail.from.address'),
                'mail.from.name' => config('mail.from.name'),
            ],
            'mail_default_config' => config('mail.default'),
            'all_mail_mailers' => config('mail.mailers'),
        ]);
    }
}
