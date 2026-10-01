<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateDocument extends Model
{
    /**
     * The papers the business asks applicants for, in the order they are
     * listed on the form. `required` is what the applicant cannot submit
     * without; the rest are accepted when they have them.
     */
    public const TYPES = [
        'cv'               => ['label' => 'Curriculum Vitae',  'required' => true,  'multiple' => false],
        'academic'         => ['label' => 'Academic Papers',   'required' => false, 'multiple' => true],
        'passport_photo'   => ['label' => 'Passport Photo',    'required' => false, 'multiple' => false],
        'reference_letter' => ['label' => 'Reference Letters', 'required' => false, 'multiple' => true],
        'lc_letter'        => ['label' => 'LC Letter',         'required' => false, 'multiple' => false],
        'police_letter'    => ['label' => 'Police Letter',     'required' => false, 'multiple' => false],
        'other'            => ['label' => 'Other Document',    'required' => false, 'multiple' => true],
    ];

    protected $fillable = [
        'candidate_id', 'type', 'path', 'original_name', 'size_bytes', 'mime_type',
    ];

    public function candidate() { return $this->belongsTo(Candidate::class); }

    public function getLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    /** Human size, for a list the applicant and the recruiter both read. */
    public function getSizeLabelAttribute(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes <= 0)         return '';
        if ($bytes < 1024)       return $bytes . ' B';
        if ($bytes < 1048576)    return round($bytes / 1024) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
