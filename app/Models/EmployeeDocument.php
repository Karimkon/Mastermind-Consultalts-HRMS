<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id','document_type','title','file_path','file_name',
        'mime_type','expiry_date','notes','uploaded_by',
    ];

    protected $casts = ['expiry_date' => 'date'];

    public function employee()    { return $this->belongsTo(Employee::class); }
    public function uploadedBy()  { return $this->belongsTo(User::class, 'uploaded_by'); }
}
