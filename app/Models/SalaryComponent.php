<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    protected $fillable = ['name', 'code', 'type', 'is_taxable', 'is_fixed', 'amount', 'percentage', 'is_active'];
    protected $casts = ['is_taxable' => 'boolean', 'is_fixed' => 'boolean'];
}