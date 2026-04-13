<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $fillable = ['title', 'department_id', 'grade'];
    public function department() { return $this->belongsTo(Department::class); }
    public function employees()  { return $this->hasMany(Employee::class); }
}