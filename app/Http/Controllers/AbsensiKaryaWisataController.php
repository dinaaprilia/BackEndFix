<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AbsensiKaryaWisata;
use App\Models\InfoKaryaWisata;

class AbsensiKaryaWisataController extends Controller
{
  // Controller: AbsensiKaryaWisataController
public function store(Request $request)
{
    $request->validate([
        'kelas' => 'required|string',
        'judul' => 'required|string',
        'data' => 'required|array',
        'data.*.user_id' => 'required|exists:users,id',
        'data.*.status' => 'required|string',
        'data.*.waktu' => 'required|string',
    ]);

    // ✅ Ambil tanggal dari info_karya_wisata berdasarkan judul
    $info = InfoKaryaWisata::whereRaw('LOWER(title) = ?', [strtolower($request->judul)])->first();

    if (!$info) {
        return response()->json([
            'status' => 'error',
            'message' => 'Judul tidak ditemukan di info_karya_wisata.'
        ], 404);
    }

    foreach ($request->data as $absen) {
        AbsensiKaryaWisata::create([
            'user_id' => $absen['user_id'],
            'kelas' => $request->kelas,
            'status' => $absen['status'],
            'waktu' => $absen['waktu'],
            'tanggal' => $info->tanggal, // ✅ Gunakan tanggal resmi dari info_karya_wisata
            'judul' => $request->judul,
        ]);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Data absensi berhasil disimpan.'
    ]);
}


public function index(Request $request)
{
    $query = AbsensiKaryaWisata::with('user');

    if ($request->has('kelas')) {
        $query->where('kelas', $request->kelas);
    }

    if ($request->has('judul')) {
        $query->where('judul', $request->judul);
    }

    // ✅ Tambahkan filter tanggal agar tidak error saat query
  if ($request->has('tanggal')) {
    $query->whereDate('tanggal', $request->tanggal);
    }

    $data = $query->get();

    return response()->json(['data' => $data]);
}
public function getPesertaByJudulTanggal(Request $request)
{
    $judul = strtolower($request->judul);
    $tanggal = $request->tanggal;

    $data = AbsensiKaryaWisata::with('user')
        ->whereRaw('LOWER(judul) = ?', [$judul])
        ->whereDate('tanggal', $tanggal)
        ->get();

    return response()->json([
        'status' => 'success',
        'data' => $data->map(function ($item) {
            return [
                'nama' => $item->user->nama ?? '-',
                'kelas' => $item->kelas ?? '-',
                'status' => $item->status,
                'waktu' => $item->waktu,
            ];
        })
    ]);
}

}
