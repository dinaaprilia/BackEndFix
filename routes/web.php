<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use App\Models\AnggotaEkskul;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
    
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return 'Storage linked';
});

Route::get('/env-test', function () {
    return response()->json([
        'env_DB_USERNAME' => env('DB_USERNAME'),
        'config_DB_USERNAME' => config('database.connections.mysql.username'),
    ]);
});

Route::get('/isi-user-id-anggota', function () {
    $anggotaList = AnggotaEkskul::whereNull('user_id')->get();

    foreach ($anggotaList as $anggota) {
        $user = User::where('nama', $anggota->nama)
            ->where('kelas', $anggota->kelas)
            ->first();

        if ($user) {
            $anggota->user_id = $user->id;
            $anggota->save();
        }
    }

    return '✅ Semua anggota ekskul berhasil diperbarui dengan user_id.';
});
Route::get('/sync-user-id-anggota', function () {
    $anggotaList = AnggotaEkskul::whereNull('user_id')->get();

    foreach ($anggotaList as $anggota) {
        $user = User::where('nama', $anggota->nama)
                    ->where('kelas', $anggota->kelas)
                    ->first();

        if ($user) {
            $anggota->user_id = $user->id;
            $anggota->save();
        }
    }

    return '✅ Semua anggota yang user_id-nya null berhasil disinkronkan.';
});
