<?php

namespace App\Http\Controllers;

use App\Models\KegiatanEkskul;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class KegiatanEkskulController extends Controller
{
    public function index($ekskulId)
    {
        $data = KegiatanEkskul::where('ekskul_id', $ekskulId)->latest()->get();

        return response()->json($data);
    }

    public function store(Request $request, $ekskulId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'date'  => 'required|date',
        ]);

        $kegiatan = KegiatanEkskul::create([
            'ekskul_id' => $ekskulId,
            'title'     => $request->title,
            'date'      => $request->date,
            'start'     => $request->start ?? now(), 
        ]);

        return response()->json(['message' => 'Kegiatan berhasil ditambahkan', 'data' => $kegiatan], 201);
    }

public function update(Request $request, $ekskulId, $id)
{
    $kegiatan = KegiatanEkskul::where('ekskul_id', $ekskulId)->where('id', $id)->first();

    if (!$kegiatan) {
        return response()->json(['message' => 'Data tidak ditemukan'], 404);
    }

    $kegiatan->update([
        'title' => $request->input('title'),
        'date'  => $request->input('date'),
        'start' => $request->input('start'), // ✅ tambahkan ini
    ]);

    return response()->json([
        'message' => 'Data berhasil diperbarui',
        'data' => $kegiatan
    ]);
}

    public function destroy($id)
    {
        $kegiatan = KegiatanEkskul::findOrFail($id);
        $kegiatan->delete();

        return response()->json(['message' => 'Kegiatan berhasil dihapus']);
    }
}