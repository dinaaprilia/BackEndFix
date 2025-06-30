<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnggotaEkskul extends Model
{
    protected $fillable = ['nama', 'nisn', 'kelas', 'status', 'ekskul_id', 'user_id'];

    protected $table = 'anggota_ekskul'; // ← tambahkan ini!

public function ekskul()
{
    return $this->belongsTo(\App\Models\Ekskul::class, 'ekskul_id');
}
}