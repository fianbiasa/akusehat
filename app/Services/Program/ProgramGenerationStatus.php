<?php

namespace App\Services\Program;

use Illuminate\Support\Facades\Cache;

/**
 * Ephemeral generation-status tracking for the async status-polling
 * endpoint (05-API-Specification.md §16). Not persisted to a column -
 * mysql.sql's user_programs has no "generation_status" field, and this
 * is inherently transient (a completed/failed job's plan rows are the
 * durable record; this cache entry just answers "is it still running").
 */
class ProgramGenerationStatus
{
    private const TTL_MINUTES = 15;

    public static function markPending(int $userProgramId, string $date): void
    {
        Cache::put(self::key($userProgramId, $date), 'pending', now()->addMinutes(self::TTL_MINUTES));
    }

    public static function markReady(int $userProgramId, string $date): void
    {
        Cache::put(self::key($userProgramId, $date), 'ready', now()->addMinutes(self::TTL_MINUTES));
    }

    public static function markFailed(int $userProgramId, string $date): void
    {
        Cache::put(self::key($userProgramId, $date), 'failed', now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * Falls back to 'unknown' rather than assuming 'ready', since the
     * cache entry expiring doesn't mean generation happened - callers
     * combine this with checking whether plan rows actually exist.
     */
    public static function get(int $userProgramId, string $date): string
    {
        return Cache::get(self::key($userProgramId, $date), 'unknown');
    }

    private static function key(int $userProgramId, string $date): string
    {
        return "program_generation.{$userProgramId}.{$date}";
    }
}
