<?php

namespace App\Http\Controllers;

use App\Models\InformasiEkskul;
use Illuminate\Http\Request;

class InformasiEkskulController extends Controller
{
    public function index($id)
{
    $informasi = InformasiEkskul::where('ekskul_id', $id)
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json($informasi);
}


public function store(Request $request, $id)
{
    $validated = $request->validate([
        'date' => 'required|date',
        'description' => 'required|string',
        'author' => 'nullable|string',
        'color' => 'nullable|string',
    ]);

    $validated['ekskul_id'] = $id;

    $validated['time'] = now()->format('H:i');

    $informasi = InformasiEkskul::create($validated);

    return response()->json([
        'message' => 'Informasi berhasil ditambahkan.',
        'data' => $informasi
    ], 201);
}


    public function update(Request $request, $id)
{
    $validated = $request->validate([
        'description' => 'required|string'
    ]);

    $informasi = InformasiEkskul::findOrFail($id);
    $informasi->update($validated);

    return response()->json([
        'message' => 'Informasi berhasil diperbarui.',
        'data' => $informasi
    ]);
}


    public function destroy($id)
    {
        $informasi = InformasiEkskul::findOrFail($id);
        $informasi->delete();

        return response()->json([
            'message' => 'Informasi berhasil dihapus.'
        ]);
    }
}
