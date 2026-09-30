<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One thing somebody said, or sent.
 *
 * Soft-deleted rather than removed: a thread with a hole in it reads as though
 * the conversation never happened, and people rely on what was agreed.
 */
class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id', 'user_id', 'type', 'body',
        'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size',
        'duration', 'edited_at',
    ];

    protected $casts = ['edited_at' => 'datetime', 'attachment_size' => 'integer', 'duration' => 'integer'];

    public const TEXT  = 'text';
    public const IMAGE = 'image';
    public const AUDIO = 'audio';
    public const VIDEO = 'video';
    public const FILE  = 'file';

    /** What a browser may be handed inline, and how much of it. */
    public const LIMITS = [
        self::IMAGE => ['bytes' => 8 * 1024 * 1024,  'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic']],
        self::AUDIO => ['bytes' => 16 * 1024 * 1024, 'mimes' => ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/webm', 'audio/aac', 'audio/x-m4a']],
        self::VIDEO => ['bytes' => 64 * 1024 * 1024, 'mimes' => ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp']],
        self::FILE  => ['bytes' => 16 * 1024 * 1024, 'mimes' => []],
    ];

    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function sender()       { return $this->belongsTo(User::class, 'user_id'); }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    /**
     * Served through the application, never as a public file path.
     *
     * A staff member's voice note must not be readable by anyone who guesses a
     * URL, so the route checks they are in the conversation first.
     */
    public function attachmentUrl(): ?string
    {
        return $this->hasAttachment()
            ? route('chat.attachment', ['message' => $this->id])
            : null;
    }

    /** Which kind of message a file makes, from what the browser said it is. */
    /**
     * Containers that carry either sound alone or sound with pictures.
     *
     * The bytes cannot tell the two apart without reading the track list, so
     * for these the sender's own word is better evidence than the sniffer's.
     */
    public const AMBIGUOUS = [
        'video/webm', 'audio/webm',
        'video/mp4', 'audio/mp4',
        'video/ogg', 'audio/ogg', 'application/ogg',
        'video/x-matroska', 'video/3gpp', 'audio/3gpp',
    ];

    public static function containerIsAmbiguous(?string $mime): bool
    {
        return $mime !== null && in_array(strtolower(explode(';', $mime)[0]), self::AMBIGUOUS, true);
    }

    /** Re-badge a container's mime for the kind of thing it turned out to hold. */
    public static function mimeAs(string $mime, string $type): string
    {
        $bare = explode(';', $mime)[0];
        $tail = explode('/', $bare)[1] ?? 'webm';

        return match ($type) {
            self::AUDIO => 'audio/'.$tail,
            self::VIDEO => 'video/'.$tail,
            default     => $bare,
        };
    }

    public static function typeForMime(?string $mime): string
    {
        if (! $mime) {
            return self::FILE;
        }

        foreach ([self::IMAGE, self::AUDIO, self::VIDEO] as $type) {
            if (in_array($mime, self::LIMITS[$type]['mimes'], true)) {
                return $type;
            }
        }

        // A phone may report something unexpected for a recording; fall back to
        // the leading part rather than filing a voice note as a document.
        $leading = explode('/', $mime)[0] ?? '';

        return match ($leading) {
            'image' => self::IMAGE,
            'audio' => self::AUDIO,
            'video' => self::VIDEO,
            default => self::FILE,
        };
    }

    /** One line for a conversation list, where a photo has no words. */
    public function preview(): string
    {
        if ($this->body) {
            return mb_substr($this->body, 0, 140);
        }

        return match ($this->type) {
            self::IMAGE => 'Photo',
            self::AUDIO => 'Voice note',
            self::VIDEO => 'Video',
            self::FILE  => $this->attachment_name ?: 'File',
            default     => '',
        };
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->attachment_size;

        if ($bytes <= 0) {
            return '';
        }

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }

    protected static function booted(): void
    {
        // The file goes when the message is destroyed for good, not when it is
        // soft-deleted - an undelete with no file would be worse than nothing.
        static::forceDeleted(function (Message $m) {
            if ($m->attachment_path) {
                Storage::disk('local')->delete($m->attachment_path);
            }
        });
    }
}
