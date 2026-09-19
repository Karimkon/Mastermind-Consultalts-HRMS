<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Evidence backing a score — survey forms, minutes, reports. */
class AppraisalAttachment extends Model
{
    protected $fillable = [
        'appraisal_id', 'appraisal_kpi_id', 'uploaded_by',
        'label', 'file_path', 'original_name', 'mime_type', 'size',
    ];

    public function appraisal() { return $this->belongsTo(Appraisal::class); }
    public function kpi()       { return $this->belongsTo(AppraisalKpi::class, 'appraisal_kpi_id'); }
    public function uploader()  { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function humanSize(): string
    {
        $b = (int) $this->size;
        if ($b < 1024) return $b . ' B';
        if ($b < 1048576) return round($b / 1024, 1) . ' KB';
        return round($b / 1048576, 1) . ' MB';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
