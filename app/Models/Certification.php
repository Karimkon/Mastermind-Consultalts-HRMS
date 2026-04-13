<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    protected $fillable = ['employee_id', 'name', 'issued_by', 'issue_date', 'expiry_date', 'document_path'];
    public function employee() { return $this->belongsTo(Employee::class); }
}