<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One file on an improvement plan, either against an objective or the plan.
 */
class PipAttachment extends Model
{
    protected $fillable = [
        'pip_id', 'objective_index', 'uploaded_by', 'note',
        'file_path', 'original_name', 'mime_type', 'size',
    ];

    protected $casts = ['objective_index' => 'integer', 'size' => 'integer'];

    public function pip()      { return $this->belongsTo(Pip::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    /** Size in the units people read, rather than bytes. */
    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->size;
        if ($bytes <= 0)        return '—';
        if ($bytes < 1024)      return $bytes . ' B';
        if ($bytes < 1048576)   return round($bytes / 1024) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
