<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KegiatanEkskul extends Model
{
    use HasFactory;

    protected $fillable = ['ekskul_id', 'title', 'date', 'start'];
}
