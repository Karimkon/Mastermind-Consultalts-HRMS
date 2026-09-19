<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    // `type` was here and `holidays` has no such column, in any environment.
    // Laravel discards an unknown fillable silently, so anything setting it was
    // losing the value on save rather than failing. Nothing writes it.
    protected $fillable = ['name', 'date'];
}