<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * This application's own notifications, not Laravel's.
     *
     * `Notifiable` brings a `notifications()` relationship that expects the
     * framework's polymorphic table — a uuid primary key, `notifiable_type`,
     * `notifiable_id`. This project has its own `notifications` table keyed on a
     * plain `user_id`, with title, body and action_url, and its own model and
     * controller reading it.
     *
     * The two never agreed, so `$user->notifications` threw
     * "no such column: notifications.notifiable_type" every time anything touched
     * it. Nothing had, because every screen goes through the app's own model — but
     * the relationship was there to be used and would have failed the first time
     * somebody did.
     *
     * Overridden rather than removed: `Notifiable` also supplies the mail routing
     * that password resets use, so the trait stays and only the relationship that
     * disagrees with the schema is replaced.
     */
    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Notification::class)->latest();
    }

    /** Unread only — what a bell icon is actually asking for. */
    public function unreadNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->notifications()->whereNull('read_at');
    }

    /**
     * Which guard this model's roles belong to.
     *
     * Every role and permission in this system was created on the web guard.
     * Spatie otherwise works the guard out from auth.defaults.guard, and
     * Laravel rewrites that to 'sanctum' on every authenticated API request
     * (Authenticate::authenticate calls shouldUse). The moment the sanctum
     * guard names a provider, Spatie starts looking for roles on a 'sanctum'
     * guard that has none, and every role check on the API fails.
     *
     * Saying it here fixes the answer regardless of who is asking.
     */
    protected $guard_name = 'web';
    protected $fillable = ['name', 'email', 'password', 'avatar', 'status', 'mfa_secret', 'mfa_enabled', 'mfa_confirmed_at'];
    protected $hidden   = ['password', 'remember_token', 'mfa_secret'];
    protected $appends  = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'mfa_enabled'       => 'boolean',
            'mfa_confirmed_at'  => 'datetime',
        ];
    }

    public function employee()        { return $this->hasOne(Employee::class); }
    public function client()          { return $this->hasOne(Client::class); }
    public function hrNotifications() { return $this->hasMany(Notification::class)->latest(); }
    public function managedClients()  { return $this->hasMany(Client::class, 'account_manager_id'); }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) return asset('storage/' . $this->avatar);
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=1e40af&color=fff&size=128';
    }

    public function isActive(): bool { return $this->status === 'active'; }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
