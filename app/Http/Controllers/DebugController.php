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

        $iterations = min($lastLine, 200); // Últimas 200 líneas

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
}
