<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\absensi;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
public function getUserData(Request $request)
{
    $user = auth('api')->user();
    $child = null;
    $kelas = $user->kelas;

    if ($user->role === 'orangtua') {
        $nisnAnak = str_replace('OT_', '', $user->nisn);
        \Log::info('NISN Anak:', [$nisnAnak]);

        $child = \App\Models\User::where('nisn', $nisnAnak)
                    ->where('role', 'siswa')
                    ->first();

        \Log::info('Child Ditemukan:', [$child]);

        if ($child) {
            $kelas = $child->kelas;
        }
    }

    return response()->json([
        'message' => 'Login berhasil!',
        'user' => [
            'id' => $user->id,
            'nama' => $user->nama,
            'role' => $user->role,
            'kelas' => $kelas,
        'foto_profil' => asset('storage/' . $user->foto_profil),
            'childId' => $child?->id,
            'student' => $child ? [
                'id' => $child->id,
                'nama' => $child->nama,
                'kelas' => $child->kelas,
                'nisn' => $child->nisn,
            ] : null,
        ]
    ]);
}


    public function getUserCounts()
    {
        try {
            $jumlahSiswa = DB::table('users')->where('role', 'siswa')->count();
            $jumlahGuru = DB::table('users')->where('role', 'guru')->count();
            $jumlahTotal = DB::table('users')->count();
    
            return response()->json([
                'siswa' => $jumlahSiswa,
                'guru' => $jumlahGuru,
                'total' => $jumlahTotal,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal mengambil data',
                'message' => $e->getMessage(),
            ], 500);
        }
}

public function getAllSiswa()
{
    $siswa = User::where('role', 'siswa')->get()->map(function ($user) {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'nisn' => $user->nisn,
            'jenis_kelamin' => $user->jenis_kelamin,
            'kelas' => $user->kelas,
            'tanggal_lahir' => $user->tanggal_lahir,
            'agama' => $user->agama,
            'nomor_hp' => $user->nomor_hp,
            'email' => $user->email,
            'foto_profil' => $user->foto_profil
                ? asset('storage/' . $user->foto_profil)
                : asset('images/profilsiswa.jpg'),
        ];
    });

    return response()->json([
        'status' => 'success',
        'data' => $siswa
    ], 200);
}

public function getAllGuru()
{
    $guru = User::where('role', 'guru')->get()->map(function ($user) {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'nip' => $user->nip,
            'jenis_kelamin' => $user->jenis_kelamin,
            'kelas' => $user->kelas,
            'tanggal_lahir' => $user->tanggal_lahir,
            'nomor_hp' => $user->nomor_hp,
            'agama' => $user->agama,
            'email' => $user->email,
            'foto_profil' => $user->foto_profil
                ? asset('storage/' . $user->foto_profil)
                : asset('images/profilguru.jpg'),
        ];
    });

    return response()->json([
        'status' => 'success',
        'data' => $guru
    ], 200);
}


    public function getUsersWithTotal()
{
    // Mengambil seluruh data user
    $users = User::all();

    // Mengambil jumlah total user
    $totalUsers = $users->count();

    // Proses menambahkan data NIP, NISN, dan foto profil sesuai kondisi
    $usersWithDetails = $users->map(function($user) {
        return [
            'id' => $user->id,
            'name' => $user->nama,
            'gender' => $user->jenis_kelamin,
            'class' => $user->kelas,
            'nip' => $user->nip ? $user->nip : null,
            'nisn' => $user->nisn ? $user->nisn : null,
            'foto_profil' => $user->foto_profil
                ? asset('storage/' . $user->foto_profil)
                : asset('images/profilsiswa.jpg'),  // fallback foto default jika foto kosong
        ];
    });

    // Mengembalikan response JSON dengan data user dan total count
    return response()->json([
        'status' => 'success',
        'total_users' => $totalUsers,
        'data' => $usersWithDetails,
    ], 200);
}


    public function getProfile(Request $request)
{
    $user = auth('api')->user();

    return response()->json([
        'status' => 'success',
        'data' => [
            'id'          => $user->id,
            'nama'        => $user->nama,
            'email'       => $user->email,
            'role'        => $user->role,
            'nip'         => $user->nip,
            'nisn'        => $user->nisn,
            'kelas'       => $user->kelas,
            'jenis_kelamin' => $user->jenis_kelamin,
            'nomor_hp'    => $user->nomor_hp,
            'agama'       => $user->agama,
            'foto_profil' => $user->foto_profil, // ✅ WAJIB ADA
        ]
    ]);
}

public function updateProfile(Request $request)
{ 
    $user = auth('api')->user(); 

    $request->validate([
        'nama' => 'nullable|string|max:255',
        'email' => 'nullable|email|unique:users,email,' . $user->id,
        'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        'nomor_hp' => 'nullable|string|max:20',
        'agama' => 'nullable|string|max:255',
    ]);

    if ($request->has('nama')) $user->nama = $request->nama;
    if ($request->has('email')) $user->email = $request->email;
    if ($request->has('nomor_hp')) $user->nomor_hp = $request->nomor_hp;
    if ($request->has('agama')) $user->agama = $request->agama;

    if ($request->hasFile('foto_profil')) {
        $image = $request->file('foto_profil');
        \Log::info('✅ File diterima:', ['name' => $image->getClientOriginalName()]);

        $path = $image->store('profiles', 'public');
        \Log::info('📦 Path disimpan:', ['path' => $path]);

        $user->foto_profil = $path;
    }

    $user->save();

    // Tambahkan aktivitas (dengan try-catch agar tidak menyebabkan error)
    try {
        \App\Models\UserActivity::create([
            'user_id' => $user->id,
            'action' => 'update profile',
            'description' => 'User updated their profile information',
        ]);
    } catch (\Exception $e) {
        \Log::error("❌ Gagal simpan log aktivitas: " . $e->getMessage());
    }

    return response()->json([
        'message' => 'Profil berhasil diperbarui',
        'data' => $user
    ]);

}

public function uploadFoto(Request $request)
{
    $user = auth('api')->user();
    \Log::info('📤 uploadFoto dipanggil', ['hasFile' => $request->hasFile('foto_profil')]);

    if ($request->hasFile('foto_profil')) {
        $image = $request->file('foto_profil');
        $path = $image->store('profiles', 'public');
        $user->foto_profil = $path;
        $user->save();

        return response()->json([
            'message' => 'Foto berhasil diperbarui',
            'data' => $user
        ]);
    }

    return response()->json(['message' => 'Tidak ada file dikirim'], 400);
}


    public function index(Request $request)
{
    $role = $request->query('role');
    if ($role) {
        return response()->json(User::where('role', $role)->get());
    }

    return response()->json(User::all());
}

public function updatePassword(Request $request)
{
    $user = auth('api')->user();

    $request->validate([
        'old_password' => 'required',
        'new_password' => 'required|min:8'
    ]);

    if (!Hash::check($request->old_password, $user->password)) {
        return response()->json(['message' => 'Password lama salah'], 400);
    }

    $user->password = Hash::make($request->new_password);
    $user->save();

        // ✅ Tambahan simpan password
    if ($request->has('password')) {
        $user->password = \Hash::make($request->password);
    }


    return response()->json(['message' => 'Password berhasil diperbarui']);
}
public function siswaGender()
    {
        $lakiLaki = User::where('role', 'siswa')->where('jenis_kelamin', 'L')->count();
        $perempuan = User::where('role', 'siswa')->where('jenis_kelamin', 'P')->count();

        return response()->json([
            'laki_laki' => $lakiLaki,
            'perempuan' => $perempuan,
        ]);
    }

    // Endpoint untuk guru
    public function guruGender()
    {
        $lakiLaki = User::where('role', 'guru')->where('jenis_kelamin', 'L')->count();
        $perempuan = User::where('role', 'guru')->where('jenis_kelamin', 'P')->count();

        return response()->json([
            'laki_laki' => $lakiLaki,
            'perempuan' => $perempuan,
        ]);
    }

   public function detailSiswa(Request $request)
{
    // Ambil nisn dari query string: ?nisn=...
    $nisn = $request->query('nisn');

    if (!$nisn) {
        return response()->json([
            'message' => 'NISN tidak diberikan.'
        ], 400);
    }

    $siswa = User::where('nisn', $nisn)->first();

    if (!$siswa) {
        return response()->json([
            'message' => 'Siswa tidak ditemukan.'
        ], 404);
    }

    // Hitung statistik dari tabel absensi berdasarkan user_id
    $hadir = Absensi::where('user_id', $siswa->id)->where('status', 'Hadir')->count();
    $terlambat = Absensi::where('user_id', $siswa->id)->where('status', 'Terlambat')->count();
    $tidakHadir = Absensi::where('user_id', $siswa->id)->where('status', 'Tidak Hadir')->count();

    return response()->json([
        'nama' => $siswa->nama,
        'kelas' => $siswa->kelas,
        'nisn' => $siswa->nisn,
        'jenis_kelamin' => $siswa->jenis_kelamin,
        'agama' => $siswa->agama,
        'tanggal_lahir' => $siswa->tanggal_lahir,
        'nomor_hp' => $siswa->nomor_hp,
        'email' => $siswa->email,
        'foto' => $siswa->foto,
        'statistik' => [
            'hadir' => $hadir,
            'terlambat' => $terlambat,
            'tidak_hadir' => $tidakHadir,
        ]
    ]);
}
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'nama' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'role' => ['required', Rule::in(['admin', 'guru', 'siswa'])],
        'nip' => 'nullable|unique:users,nip',
        'nisn' => 'nullable|unique:users,nisn',
        'kelas' => 'nullable|string|max:255',
        'jenis_kelamin' => 'required|in:L,P',
        'agama' => 'required|string|max:255',
        'tanggal_lahir' => 'nullable|date',
        'nomor_hp' => 'nullable|string|max:20',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validasi gagal',
            'errors' => $validator->errors(),
        ], 422);
    }

    $user = User::create([
        'nama' => $request->nama,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => $request->role,
        'nip' => $request->nip,
        'nisn' => $request->nisn,
        'kelas' => $request->kelas,
        'jenis_kelamin' => $request->jenis_kelamin,
        'agama' => $request->agama,
        'tanggal_lahir' => $request->tanggal_lahir,
        'nomor_hp' => $request->nomor_hp,
    ]);

    return response()->json([
        'message' => 'User berhasil dibuat',
        'data' => $user
    ], 201);
}
public function getWaliKelas(Request $request)
{
    $kelas = $request->query('kelas');

    $guru = User::where('role', 'guru')->where('kelas', $kelas)->first();

    return response()->json([
        'nama' => $guru ? $guru->nama : '-'
    ]);
}
public function storeOrangtua(Request $request)
{
    $request->validate([
        'nama' => 'required|string',
        'email' => 'required|email|unique:users',
        'anak_nisn' => 'required|exists:users,nisn',
    ]);

    $tanggalLahirAnak = User::where('nisn', $request->anak_nisn)->first()->tanggal_lahir;

    User::create([
        'nama' => $request->nama,
        'role' => 'orangtua',
        'nisn' => 'OT_' . $request->anak_nisn,
        'anak_nisn' => $request->anak_nisn,
        'email' => $request->email,
        'password' => bcrypt($tanggalLahirAnak), // atau format ddmmyyyy jika mau
    ]);

    return redirect()->back()->with('success', 'Akun orangtua berhasil dibuat');
}
public function getAnak()
{
    $user = auth()->user();

    if ($user->role !== 'orangtua') {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $anak = $user->anak;

    return response()->json([
        'nama_orangtua' => $user->nama,
        'anak' => [
            'nama' => $anak?->nama,
            'kelas' => $anak?->kelas,
            'tanggal_lahir' => $anak?->tanggal_lahir,
        ]
    ]);
}

public function update(Request $request, $id)
{
    $user = User::findOrFail($id);

    $request->validate([
        'nama' => 'nullable|string|max:255',
        'email' => 'nullable|email|unique:users,email,' . $id,
        'nomor_hp' => 'nullable|string|max:20',
        'agama' => 'nullable|string|max:255',
        'kelas' => 'nullable|string|max:100',
        'tanggal_lahir' => 'nullable|date',
        'jenis_kelamin' => 'nullable|in:L,P',
        'password' => 'nullable|string|min:8'
    ]);

    $user->fill($request->only([
        'nama', 'email', 'nomor_hp', 'agama', 'kelas', 'tanggal_lahir', 'jenis_kelamin'
    ]));

    if ($request->filled('password')) {
        $user->password = \Hash::make($request->password);
    }

    $user->save();

    // ✅ Update nama orang tua jika user adalah siswa
    if ($user->role === 'siswa' && $user->nisn) {
        $ortu = User::where('role', 'orangtua')
            ->where('nisn', 'OT_' . $user->nisn)
            ->first();

        if ($ortu) {
            $ortu->nama = 'Orang Tua ' . $user->nama;
            $ortu->save();
        }
    }

    return response()->json([
        'message' => 'Data pengguna berhasil diperbarui',
        'data' => $user
    ]);
}


public function getAllKelas()
{
    $kelasList = User::whereNotNull('kelas')
        ->where('kelas', '!=', '')
        ->select('kelas')
        ->distinct()
        ->orderBy('kelas')
        ->pluck('kelas');

    return response()->json([
        'kelas' => $kelasList
    ]);
}
public function getAllJabatan()
{
    $jabatanList = User::whereNotNull('kelas') // Ubah 'kelas' menjadi 'jabatan' jika sudah ada kolomnya
        ->where('role', 'guru')
        ->where('kelas', '!=', '')
        ->select('kelas') // ubah ke 'jabatan' jika sudah tersedia
        ->distinct()
        ->orderBy('kelas') // atau 'jabatan'
        ->pluck('kelas');  // atau 'jabatan'

    return response()->json([
        'jabatan' => $jabatanList
    ]);
}
public function destroy($id)
{
    try {
        $user = User::findOrFail($id);

        // ✅ Jika yang dihapus adalah siswa, cari dan hapus akun orangtuanya
        if ($user->role === 'siswa' && $user->nisn) {
            $orangTua = User::where('role', 'orangtua')
                ->where('nisn', 'OT_' . $user->nisn)
                ->first();

            if ($orangTua) {
                // Hapus foto profil orangtua jika ada
                if ($orangTua->foto_profil && \Storage::disk('public')->exists($orangTua->foto_profil)) {
                    \Storage::disk('public')->delete($orangTua->foto_profil);
                }
                $orangTua->delete();
            }
        }

        // Hapus foto profil siswa jika ada
        if ($user->foto_profil && \Storage::disk('public')->exists($user->foto_profil)) {
            \Storage::disk('public')->delete($user->foto_profil);
        }

        $user->delete();

        return response()->json([
            'message' => 'Akun siswa dan akun orang tua (jika ada) berhasil dihapus'
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Gagal menghapus akun',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function profile()
{
    $user = auth()->user();

    return response()->json([
        'user' => $user->load('anak') // Menyertakan data anak-anak
    ]);
}
// Orangtua bisa punya banyak anak
public function anak()
{
    return $this->hasMany(User::class, 'orangtua_id');
}

// Anak punya satu orangtua
public function orangtua()
{
    return $this->belongsTo(User::class, 'orangtua_id');
}

}
