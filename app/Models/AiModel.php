<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiModel extends Model // Aap apni marzi ka class name rakh sakte hain
{
    use HasFactory;

    protected $table = 'models'; // Table ka naam

    protected $fillable = [
        'name',
    ];
}