<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nama', 'role', 'nisn', 'nip', 'kelas', 'jenis_kelamin', 'agama', 'email', 'password', 'foto_profil', 'tanggal_lahir', 'nomor_hp', 'anak_nisn'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'tanggal_lahir' => 'date',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function ekskulDetail()
    {
        return $this->hasMany(DetailEkskul::class, 'anggota_user_id');
    }
//     public function anak()
// {
//     return $this->hasOne(User::class, 'nisn', 'anak_nisn');
// }

public static function distinctKelas()
{
    return self::whereNotNull('kelas')->distinct()->pluck('kelas');
}
public function absensiKaryaWisata()
{
    return $this->hasMany(AbsensiKaryaWisata::class, 'user_id');
}
// User.php

// Untuk orangtua -> anak
public function anak()
{
    return $this->hasMany(User::class, 'orangtua_id');
}

// Untuk anak -> orangtua
public function orangtua()
{
    return $this->belongsTo(User::class, 'orangtua_id');
}

public function informasi()
{
    return $this->hasMany(InformasiUmum::class);
}


}