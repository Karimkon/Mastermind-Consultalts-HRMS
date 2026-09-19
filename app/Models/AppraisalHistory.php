<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Who moved the appraisal where, and what they said. */
class AppraisalHistory extends Model
{
    protected $table = 'appraisal_history';

    protected $fillable = ['appraisal_id', 'user_id', 'action', 'from_status', 'to_status', 'comment'];

    public function appraisal() { return $this->belongsTo(Appraisal::class); }
    public function user()      { return $this->belongsTo(User::class); }
}
