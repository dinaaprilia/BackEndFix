<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InformasiUmum;
use Illuminate\Support\Facades\Auth;
use App\Models\User;


class InformasiUmumController extends Controller
{
    public function index()
    {
        return response()->json(InformasiUmum::orderBy('date', 'desc')->get());
    }

public function store(Request $request)
{
    $user = Auth::guard('api')->user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $info = InformasiUmum::create([
        'title' => $request->title,
        'text' => $request->text,
        'photo' => $user->foto_profil ?? 'default.jpg',
        'date' => $request->date,
        'time' => $request->time,
        'color' => $request->color,
        'author' => $user->nama,
        'user_id' => $user->id,
    ]);

    return response()->json($info);
}



  public function update(Request $request, $id)
{
    $info = InformasiUmum::findOrFail($id);

    $data = $request->validate([
        'title' => 'required|string|max:255', // ⬅️ tambahkan ini
        'text' => 'required|string',
    ]);

    $info->update($data);
    $info->refresh(); // ambil data terupdate dari database

    return response()->json($info);
}

    public function destroy($id)
{
    try {
        \Log::info("🗑️ Request hapus untuk ID: $id");

        $info = InformasiUmum::find($id);

        if (!$info) {
            \Log::warning("⚠️ Informasi dengan ID $id tidak ditemukan.");
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $info->delete();
        \Log::info("✅ Berhasil hapus informasi ID: $id");

        return response()->json(['message' => 'Deleted'], 200);
    } catch (\Exception $e) {
        \Log::error("❌ Gagal hapus informasi: " . $e->getMessage());
        return response()->json(['message' => 'Terjadi kesalahan server.'], 500);
    }
}


}