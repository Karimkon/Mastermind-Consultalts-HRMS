<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A thread between two people, or among several.
 *
 * `last_message_at` and the preview are kept on the row so a conversation list
 * sorts and renders without touching the messages table. On a poll that runs
 * every few seconds across 900 staff, that is the difference between one cheap
 * query and one per thread.
 */
class Conversation extends Model
{
    protected $fillable = ['type', 'name', 'created_by', 'last_message_at', 'last_message_preview'];

    protected $casts = ['last_message_at' => 'datetime'];

    public const DIRECT = 'direct';
    public const GROUP  = 'group';

    public function messages()     { return $this->hasMany(Message::class); }
    public function participants() { return $this->hasMany(ConversationParticipant::class); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'muted'])->withTimestamps();
    }

    /** Everyone in the thread except the person looking at it. */
    public function otherUsers(int $exceptUserId)
    {
        return $this->users->reject(fn (User $u) => $u->id === $exceptUserId)->values();
    }

    /**
     * What to call this thread on screen.
     *
     * A direct thread has no name of its own: it is named after whoever is on
     * the other end, which differs depending on who is looking.
     */
    public function titleFor(int $userId): string
    {
        if ($this->type === self::GROUP) {
            return $this->name ?: 'Group chat';
        }

        return $this->otherUsers($userId)->first()?->name ?? 'Conversation';
    }

    public function unreadFor(int $userId): int
    {
        $participant = $this->participants->firstWhere('user_id', $userId)
            ?? $this->participants()->where('user_id', $userId)->first();

        if (! $participant) {
            return 0;
        }

        return $this->messages()
            ->where('user_id', '!=', $userId)
            ->when($participant->last_read_at, fn ($q) => $q->where('created_at', '>', $participant->last_read_at))
            ->count();
    }

    /**
     * The existing one-to-one thread between two people, or a new one.
     *
     * Two people must never end up with two threads: whoever opens it second
     * would write into a conversation the first cannot see.
     */
    public static function between(int $a, int $b): self
    {
        $existing = self::where('type', self::DIRECT)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $a))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $b))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if ($existing) {
            return $existing;
        }

        $conversation = self::create(['type' => self::DIRECT, 'created_by' => $a]);

        foreach ([$a, $b] as $id) {
            ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $id]);
        }

        return $conversation->fresh();
    }
}
