<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Wired into the highest-value admin write actions (user/role/coach-
 * assignment/AI-provider/rule-engine changes) rather than every model
 * write in the app - a generic "log every Eloquent event" observer would
 * bury the genuinely audit-worthy actions in routine Member/Coach
 * activity noise.
 */
class ActivityLogger
{
    public function log(string $action, ?Model $subject = null, array $meta = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
