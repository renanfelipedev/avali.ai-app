<?php

namespace App\Services;

use App\Models\SystemErrorLog;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ErrorLoggingService
{
    /**
     * Capture and store an unhandled or manually reported exception.
     */
    public static function logException(Throwable $throwable, array $context = [], string $level = 'error'): ?SystemErrorLog
    {
        try {
            $user = Auth::user();
            $request = request();

            return SystemErrorLog::create([
                'level' => $level,
                'exception_class' => get_class($throwable),
                'message' => $throwable->getMessage() ?: 'Erro sem mensagem explícita',
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'context' => ! empty($context) ? $context : null,
                'stack_trace' => $throwable->getTraceAsString(),
                'user_id' => $user?->id,
                'url' => $request ? substr($request->fullUrl(), 0, 255) : null,
                'method' => $request ? $request->method() : null,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            ]);
        } catch (Throwable $e) {
            // Fail silently to prevent recursive crash during error logging
            return null;
        }
    }

    /**
     * Log a message directly without an exception instance.
     */
    public static function logMessage(string $message, string $level = 'error', array $context = []): ?SystemErrorLog
    {
        try {
            $user = Auth::user();
            $request = request();

            return SystemErrorLog::create([
                'level' => $level,
                'exception_class' => null,
                'message' => $message,
                'file' => null,
                'line' => null,
                'context' => ! empty($context) ? $context : null,
                'stack_trace' => null,
                'user_id' => $user?->id,
                'url' => $request ? substr($request->fullUrl(), 0, 255) : null,
                'method' => $request ? $request->method() : null,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            ]);
        } catch (Throwable $e) {
            return null;
        }
    }
}
