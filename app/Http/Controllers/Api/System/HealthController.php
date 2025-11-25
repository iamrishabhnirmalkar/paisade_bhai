<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    use ApiResponseTrait;

    public function check()
    {
        // 🧠 Database Check
        try {
            DB::connection()->getPdo();
            $dbStatus = true;
            $dbDriver = DB::connection()->getDriverName();
        } catch (Throwable $e) {
            $dbStatus = false;
            $dbDriver = null;
        }

        // ⚙️ Redis Check
        try {
            $redisStatus = Redis::connection()->ping() ? true : false;
        } catch (Throwable $e) {
            $redisStatus = false;
        }

        // 💾 Storage Check (local, public, s3 if configured)
        $storages = [];
        $disks = array_keys(config('filesystems.disks'));
        foreach ($disks as $disk) {
            try {
                Storage::disk($disk)->exists('/');
                $storages[$disk] = 'accessible';
            } catch (Throwable $e) {
                $storages[$disk] = 'unavailable';
            }
        }

        // 💻 CPU Load (handles Windows safely)
        $cpuLoad = null;
        if (\function_exists('sys_getloadavg')) {
            $load = \sys_getloadavg();
            $cpuLoad = $load[0] ?? null;
        }

        // 📊 Prepare Data
        $data = [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
            ],
            'database' => [
                'status' => $dbStatus ? 'connected' : 'disconnected',
                'driver' => $dbDriver,
            ],
            'redis' => [
                'status' => $redisStatus ? 'connected' : 'disconnected',
            ],
            'storage' => $storages,
            'server' => [
                'php_version' => phpversion(),
                'laravel_version' => app()->version(),
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'disk_free_space' => round(disk_free_space("/") / 1024 / 1024 / 1024, 2) . ' GB',
                'cpu_load' => $cpuLoad,
            ],
        ];

        return $this->successResponse($data, 'System health check successful');
    }
}
