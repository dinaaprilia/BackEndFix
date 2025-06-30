<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AnggotaEkskul;
use App\Models\User;

class AnggotaEkskulController extends Controller
{
    public function index($ekskulId)
    {
        return response()->json(
            AnggotaEkskul::where('ekskul_id', $ekskulId)->get()
        );
    }

   public function store(Request $request, $ekskulId)
{
    $validated = $request->validate([
        'nama' => 'required|string',
        'kelas' => 'required|string',
        'nisn' => 'nullable|string|max:20',
    ]);

    // Cari user berdasarkan nama dan kelas
    $user = \App\Models\User::where('nama', $validated['nama'])
                ->where('kelas', $validated['kelas'])
                ->first();

    // Jika user tidak ditemukan, kirim error
    if (!$user) {
        return response()->json([
            'message' => 'User dengan nama dan kelas ini tidak ditemukan di tabel users.'
        ], 404);
    }

    // Cek apakah anggota dengan kombinasi ini sudah ada
    $exists = \App\Models\AnggotaEkskul::where([
        'user_id' => $user->id,
        'ekskul_id' => $ekskulId,
    ])->exists();

    if ($exists) {
        return response()->json([
            'message' => 'Anggota ini sudah terdaftar dalam ekskul tersebut.'
        ], 409);
    }

    // Simpan data anggota ekskul
    $anggota = \App\Models\AnggotaEkskul::create([
        'nama' => $validated['nama'],
        'kelas' => $validated['kelas'],
        'nisn' => $validated['nisn'],
        'status' => 'green',
        'ekskul_id' => $ekskulId,
        'user_id' => $user->id,
    ]);

    return response()->json([
        'message' => 'Anggota berhasil ditambahkan.',
        'data' => $anggota
    ], 201);
}


    public function anggotaTersedia()
{
    $anggota = \App\Models\AnggotaEkskul::select('id', 'nama', 'nisn', 'kelas')->get();

    $kelasList = $anggota->pluck('kelas')->unique()->values();

    return response()->json([
        'kelasList' => $kelasList,
        'namaList' => $anggota
    ]);
}
public function destroy($id)
{
    $anggota = AnggotaEkskul::findOrFail($id);
    $anggota->delete();

    return response()->json(['message' => 'Anggota berhasil dihapus.']);
}
public function siswaTersedia()
{
    $siswa = \App\Models\User::where('role', 'siswa')
        ->select('id', 'nama as name', 'nisn', 'kelas')
        ->get();

    $kelasList = $siswa->pluck('kelas')->unique()->values();

    return response()->json([
        'kelasList' => $kelasList,
        'namaList' => $siswa
    ]);
}

public function ekskulSaya(Request $request)
{
    $user = auth()->user();

    if ($user->role === 'orangtua') {
        $nisnAnak = str_replace('OT_', '', $user->nisn);

        $anak = \App\Models\User::where('nisn', $nisnAnak)->first();

        if (!$anak) {
            return response()->json(['message' => 'Data anak tidak ditemukan'], 404);
        }

        $anggota = \App\Models\AnggotaEkskul::with('ekskul')
            ->where('user_id', $anak->id)
            ->get();
    } else {
        $anggota = \App\Models\AnggotaEkskul::with('ekskul')
            ->where('user_id', $user->id)
            ->get();
    }

    $data = $anggota->map(function ($item) {
        return [
            'id' => $item->ekskul->id,
            'name' => $item->ekskul->name,
            'mentor' => $item->ekskul->mentor,
            'image' => $item->ekskul->image,
            'tanggalBergabung' => $item->created_at->format('Y-m-d'),
        ];
    });

    return response()->json($data);
}


}