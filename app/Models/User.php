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
