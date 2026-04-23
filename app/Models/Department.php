<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'head_id', 'parent_id', 'description', 'is_active'];

    public function head()       { return $this->belongsTo(Employee::class, 'head_id'); }
    public function parent()     { return $this->belongsTo(Department::class, 'parent_id'); }
    public function children()   { return $this->hasMany(Department::class, 'parent_id'); }
    public function employees()  { return $this->hasMany(Employee::class); }
    public function designations(){ return $this->hasMany(Designation::class); }
    public function jobPostings()  { return $this->hasMany(JobPosting::class); }
}