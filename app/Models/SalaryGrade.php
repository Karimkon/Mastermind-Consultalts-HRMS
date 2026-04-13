<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalaryGrade extends Model
{
    protected $fillable = ['grade', 'basic_min', 'basic_max'];
}