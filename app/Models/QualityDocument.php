<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityDocument extends Model
{
    protected $fillable = [
        'doc_number', 'title', 'category', 'description', 'status',
        'initiator_id', 'editor_id', 'approver_id', 'start_date', 'end_date',
        'published_at', 'current_version',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'published_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'policy' => 'Policy',
        'procedure' => 'Procedure',
        'work_instruction' => 'Work Instruction',
        'form' => 'Form',
        'manual' => 'Manual',
        'record' => 'Record',
    ];

    /**
     * The upload ceiling in kilobytes, for validation and for the hint on screen.
     *
     * A controlled document is whatever the company issues - a PDF policy, an
     * Excel register, a CAD drawing, a scanned manual, a training video - so no
     * format is refused and only the size is capped.
     *
     * PHP refuses an oversized upload at the door, before validation runs, and
     * the request arrives with an empty $_FILES and a misleading "required"
     * error. So the configured cap is clamped to what this server will actually
     * accept, and the screen shows that same number.
     */
    public static function maxUploadKb(): int
    {
        $limit = (int) config('quality.max_upload_kb', 51200);

        foreach ([ini_get('upload_max_filesize'), ini_get('post_max_size')] as $ini) {
            $kb = (int) (self::iniBytes($ini) / 1024);
            if ($kb > 0 && $kb < $limit) {
                $limit = $kb;
            }
        }

        return max(1024, $limit);
    }

    /** "100M" / "8G" / "512K" as bytes. 0 when unlimited or unreadable. */
    private static function iniBytes($value): int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function initiator(): BelongsTo { return $this->belongsTo(User::class, 'initiator_id'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'editor_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }

    public function files(): HasMany { return $this->hasMany(QualityDocumentFile::class)->orderByDesc('version'); }
    public function events(): HasMany { return $this->hasMany(QualityDocumentEvent::class)->latest(); }

    public function currentFile(): ?QualityDocumentFile
    {
        return $this->files()->where('version', $this->current_version)->first() ?? $this->files()->first();
    }

    public function isPublished(): bool { return $this->status === 'published'; }

    public static function nextNumber(): string
    {
        return 'QD-' . str_pad((string) ((int) static::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
