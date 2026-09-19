<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalaryGrade extends Model
{
    // `label` is what the grade is called in conversation -- Junior, Associate,
    // Lead. The form has always posted one and it was not fillable, so every
    // grade created through the screen was saved with an empty label while the
    // person creating it watched themselves type one in.
    protected $fillable = ['grade', 'label', 'basic_min', 'basic_max'];
}