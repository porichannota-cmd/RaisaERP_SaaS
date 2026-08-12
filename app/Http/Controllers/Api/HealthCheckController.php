<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthCheckController extends Controller
{
    public function index(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'environment' => App::environment(),
            'release_sha' => env('APP_RELEASE_SHA', 'unknown'),
            'checks' => [],
        ];

        // Database Check
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = 'connected';
        } catch (Throwable $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = 'disconnected';
        }

        // Cache Check
        try {
            Cache::has('health-check');
            $health['checks']['cache'] = 'connected';
        } catch (Throwable $e) {
            if ($health['status'] === 'healthy') {
                $health['status'] = 'degraded';
            }
            $health['checks']['cache'] = 'disconnected';
        }

        // Queue Configuration State
        try {
            $queueConnection = config('queue.default');
            $health['checks']['queue'] = $queueConnection ? 'configured' : 'unconfigured';
        } catch (Throwable $e) {
            if ($health['status'] === 'healthy') {
                $health['status'] = 'degraded';
            }
            $health['checks']['queue'] = 'error';
        }

        $statusCode = $health['status'] === 'unhealthy' ? 503 : 200;

        return response()->json($health, $statusCode);
    }
}
