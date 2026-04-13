<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Kpi extends Model
{
    protected $table = 'kpis';
    protected $fillable = ['name', 'description', 'category', 'weight'];
}