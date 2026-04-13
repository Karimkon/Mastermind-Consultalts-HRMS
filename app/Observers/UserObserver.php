<?php
namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'created',
            'model_type' => 'User',
            'model_id'   => $user->id,
            'new_values' => json_encode(['name' => $user->name, 'email' => $user->email]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function updated(User $user): void
    {
        $dirty = collect($user->getDirty())->except(['remember_token','updated_at'])->toArray();
        if (empty($dirty)) return;
        // Never log raw password changes
        unset($dirty['password']);
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'updated',
            'model_type' => 'User',
            'model_id'   => $user->id,
            'old_values' => json_encode(collect($user->getOriginal())->except(['password','remember_token'])->toArray()),
            'new_values' => json_encode($dirty),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function deleted(User $user): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'deleted',
            'model_type' => 'User',
            'model_id'   => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
