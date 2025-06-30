<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Absensi;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AbsensiController extends Controller
{

public function getStudentsByClass(Request $request)
{
    $kelas = $request->query('kelas');
    if (!$kelas) {
        return response()->json(['message' => 'Kelas wajib disertakan'], 400);
    }

    $students = User::where('kelas', $kelas)
                    ->select('nisn', 'nama', 'tanggal_lahir', 'jenis_kelamin')
                    ->get();

    return response()->json($students);
}

public function updateStatusTidakHadirJikaWaktuSelesai($kelas, $tanggal)
{
    // Ambil data absensi yang statusnya bukan 'hadir' (misal kosong atau belum diupdate)
    // atau kamu bisa tentukan kondisi lain sesuai kebutuhan
    $absensiBelumHadir = Absensi::where('kelas', $kelas)
        ->where('tanggal', $tanggal)
        ->where('status', '!=', 'hadir') // atau kondisi lain jika status masih kosong, dll.
        ->get();

    $now = date('H:i'); // jam saat ini (format 24 jam, misal "15:30")

    // Ambil jam selesai dari absensi yang sudah ada, kalau ada banyak, ambil salah satu (asumsi sama)
    $jamSelesai = Absensi::where('kelas', $kelas)
        ->where('tanggal', $tanggal)
        ->value('selesai');

    if (!$jamSelesai) {
        // Jika belum ada jam selesai, return
        return response()->json(['message' => 'Jam selesai belum diatur.'], 400);
    }

    if ($now >= $jamSelesai) {
        // Update semua yang belum hadir jadi 'tidak hadir'
        Absensi::where('kelas', $kelas)
            ->where('tanggal', $tanggal)
            ->where('status', '!=', 'hadir')
            ->update(['status' => 'tidak hadir']);

        return response()->json(['message' => 'Status absensi otomatis diupdate menjadi tidak hadir.']);
    }

    return response()->json(['message' => 'Belum waktunya update status.']);
}

public function inputAbsensi(Request $request)
{
    $request->validate([
        'kelas' => 'required|string',
        'tanggal' => 'required|date',
        'hari' => 'required|string',
        'mulai' => 'required|string',
        'selesai' => 'required|string',
        'absensi' => 'required|array',
        'absensi.*.nisn' => 'required|string',
        'absensi.*.status' => 'required|string',
        'absensi.*.waktu_absen' => 'required|string',
    ]);

    $kelas = $request->kelas;
    $tanggal = $request->tanggal;
    $hari = $request->hari;
    $mulai = $request->mulai;
    $selesai = $request->selesai;
    $absensiData = $request->absensi;

    foreach ($absensiData as $item) {
        // Cari user berdasarkan nisn
        $user = User::where('nisn', $item['nisn'])->first();

        if (!$user) {
            // Skip jika user tidak ditemukan
            continue;
        }

        // Gunakan updateOrCreate untuk mencegah duplikasi absensi
        Absensi::updateOrCreate(
            [
                'user_id' => $user->id,
                'kelas' => $kelas,
                'tanggal' => $tanggal,
            ],
            [
                'hari' => $hari,
                'mulai' => $mulai,
                'selesai' => $selesai,
                'status' => $item['status'],
                'waktu_absen' => $item['waktu_absen'],
            ]
        );
    }

    $this->updateStatusTidakHadirJikaWaktuSelesai($kelas, $tanggal);


    return response()->json([
        'message' => 'Absensi berhasil disimpan atau diperbarui.'
    ], 200);
}


public function getAbsensi(Request $request)
{
    $request->validate([
        'kelas' => 'required|string',
        'tanggal' => 'required|date',
    ]);

    $kelas = $request->kelas;
    $tanggal = $request->tanggal;

    $absensi = Absensi::with('user')
        ->where('kelas', $kelas)
        ->where('tanggal', $tanggal)
        ->get();

    if ($absensi->isEmpty()) {
        return response()->json([
            'message' => 'Data absensi tidak ditemukan',
            'kelas' => $kelas,
            'hari' => null,
            'mulai' => null,
            'selesai' => null,
            'data' => [],
        ]);
    }

    // Ambil data hari, mulai, selesai dari record pertama
    $first = $absensi->first();

    $result = $absensi->map(function ($item) {
        return [
            'nama' => $item->user->nama,
            'nisn' => $item->user->nisn,
            'status' => $item->status,
            'waktu_absen' => $item->waktu_absen,
            'tanggal' => $item->tanggal,
            // jangan ulang hari, mulai, selesai di tiap record
        ];
    });

    return response()->json([
        'message' => 'Data absensi berhasil diambil',
        'kelas' => $kelas,
        'hari' => $first->hari,
        'mulai' => $first->mulai,
        'selesai' => $first->selesai,
        'data' => $result,
    ]);
}

 public function getChartData(Request $request)
{
    $date = $request->query('date', date('Y-m-d')); // default hari ini

    $kelasList = [
        "X-A", "X-B", "X-C",
        "XI-A", "XI-B", "XI-C",
        "XII-A", "XII-B", "XII-C",
    ];

    $data = [];

    foreach ($kelasList as $kelas) {
        $jumlahHadir = DB::table('absensi')
            ->join('users', 'absensi.user_id', '=', 'users.id')
            ->where('users.kelas', $kelas)
            ->where('users.role', 'siswa')
            ->whereDate('absensi.tanggal', $date)
            ->where('absensi.status', 'Hadir')
            ->count();

        $jumlahSiswa = DB::table('users')
            ->where('kelas', $kelas)
            ->where('role', 'siswa')
            ->count();

        $data[] = [
            'kelas' => $kelas,
            'hadir' => $jumlahHadir,
            'total' => $jumlahSiswa,
        ];
    }

    return response()->json($data);
}

public function getAbsensiStatistikHariIni()
{
    $tanggal = now()->toDateString();

    // Total siswa
    $totalSiswa = DB::table('users')
        ->where('role', 'siswa')
        ->count();

    // Jumlah hadir
    $jumlahHadir = DB::table('absensi')
        ->join('users', 'absensi.user_id', '=', 'users.id')
        ->where('users.role', 'siswa')
        ->whereDate('absensi.tanggal', $tanggal)
        ->where('absensi.status', 'Hadir')
        ->count();

    // Jumlah tidak hadir
    $jumlahTidakHadir = DB::table('absensi')
        ->join('users', 'absensi.user_id', '=', 'users.id')
        ->where('users.role', 'siswa')
        ->whereDate('absensi.tanggal', $tanggal)
        ->where('absensi.status', 'Tidak Hadir')
        ->count();

    // Jumlah terlambat
    $jumlahTerlambat = DB::table('absensi')
        ->join('users', 'absensi.user_id', '=', 'users.id')
        ->where('users.role', 'siswa')
        ->whereDate('absensi.tanggal', $tanggal)
        ->where('absensi.status', 'Terlambat')
        ->count();

    // Hitung persen tanpa koma (dibulatkan ke bawah)
    $presentase = fn($jumlah) => $totalSiswa > 0 ? floor(($jumlah / $totalSiswa) * 100) : 0;

    return response()->json([
        'message' => 'Statistik absensi hari ini',
        'tanggal' => $tanggal,
        'data' => [
            'hadir' => [
                'jumlah' => $jumlahHadir,
                'persen' => $presentase($jumlahHadir),
            ],
            'tidak_hadir' => [
                'jumlah' => $jumlahTidakHadir,
                'persen' => $presentase($jumlahTidakHadir),
            ],
            'terlambat' => [
                'jumlah' => $jumlahTerlambat,
                'persen' => $presentase($jumlahTerlambat),
            ],
            'total_siswa' => $totalSiswa,
        ]
    ]);
}

public function getAbsensiStatistikBulanan(Request $request)
{
    $bulan = $request->query('bulan', date('m'));
    $tahun = $request->query('tahun', date('Y'));

    $query = DB::table('absensi')
        ->join('users', 'absensi.user_id', '=', 'users.id')
        ->where('users.role', 'siswa')
        ->whereYear('absensi.tanggal', $tahun)
        ->whereMonth('absensi.tanggal', $bulan);

    $jumlahHadir = (clone $query)->where('absensi.status', 'Hadir')->count();
    $jumlahTidakHadir = (clone $query)->where('absensi.status', 'Tidak Hadir')->count();
    $jumlahTerlambat = (clone $query)->where('absensi.status', 'Terlambat')->count();

    $totalAbsensi = $jumlahHadir + $jumlahTidakHadir + $jumlahTerlambat;

    if ($totalAbsensi > 0) {
        $persenHadir = round(($jumlahHadir / $totalAbsensi) * 100);
        $persenTidakHadir = round(($jumlahTidakHadir / $totalAbsensi) * 100);
        $persenTerlambat = round(($jumlahTerlambat / $totalAbsensi) * 100);
    } else {
        $persenHadir = $persenTidakHadir = $persenTerlambat = 0;
    }

    return response()->json([
        'message' => 'Statistik absensi bulanan (total 100%)',
        'bulan' => $bulan,
        'tahun' => $tahun,
        'data' => [
            [
                'name' => 'Hadir',
                'jumlah' => $persenHadir,
                'color' => '#5CB338',
                'hoverColor' => '#4AA62D',
            ],
            [
                'name' => 'Tidak Hadir',
                'jumlah' => $persenTidakHadir,
                'color' => '#FB4141',
                'hoverColor' => '#D93636',
            ],
            [
                'name' => 'Terlambat',
                'jumlah' => $persenTerlambat,
                'color' => '#FFBB03',
                'hoverColor' => '#E6A800',
            ],
        ],
        'total_siswa' => DB::table('users')->where('role', 'siswa')->count(),
    ]);
}

public function listAbsensi(Request $request)
{
    $bulan = $request->query('bulan', date('m'));
    $tahun = $request->query('tahun', date('Y'));

    $query = User::query();

    // Filter hanya user dengan nisn tidak null dan tidak kosong
    $query->whereNotNull('nisn')->where('nisn', '!=', '');

    // Filter role hanya siswa
    $query->where('role', 'siswa');

    // Filter nama jika ada
    if ($request->has('search') && $request->search !== '') {
        $query->where('nama', 'like', '%' . $request->search . '%');
    }

    // Filter kelas jika ada dan bukan 'Semua Kelas'
    if ($request->has('kelas') && $request->kelas !== 'Semua Kelas') {
        $query->where('kelas', $request->kelas);
    }

    $users = $query->get();

    $data = $users->map(function ($user) use ($bulan, $tahun) {
        // Hitung absensi per status untuk user dan bulan/tahun tertentu
        $absensiStats = DB::table('absensi')
            ->where('user_id', $user->id)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->selectRaw("SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as hadir")
            ->selectRaw("SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat")
            ->selectRaw("SUM(CASE WHEN status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir")
            ->first();

        return [
            'id' => $user->id,
            'nisn' => $user->nisn,
            'nama' => $user->nama,
            'kelas' => $user->kelas,
            'hadir' => $absensiStats->hadir ?? 0,
            'terlambat' => $absensiStats->terlambat ?? 0,
            'tidakHadir' => $absensiStats->tidak_hadir ?? 0,
        ];
    });

    return response()->json($data);
}


public function getAbsensiByNisn(Request $request)
{
    $nisn = $request->query('nisn'); 
    if (!$nisn) {
        return response()->json([
            'message' => 'Parameter nisn harus disertakan.'
        ], 400);
    }

    $siswa = User::where('nisn', $nisn)->first();

    if (!$siswa) {
        return response()->json([
            'message' => 'Siswa tidak ditemukan.'
        ], 404);
    }

    $absensi = Absensi::where('user_id', $siswa->id)
        ->orderBy('tanggal', 'desc')
        ->get(['tanggal', 'status', 'waktu_absen']);

    Carbon::setLocale('id');

    $absensiFormatted = $absensi->map(function ($item) {
        return [
            'date' => Carbon::parse($item->tanggal)->translatedFormat('l, d F Y'),
            'status' => $item->status,
            'waktu' => $item->waktu_absen,
        ];
    });

    $hadir = Absensi::where('user_id', $siswa->id)->where('status', 'hadir')->count();
    $terlambat = Absensi::where('user_id', $siswa->id)->where('status', 'terlambat')->count();
    $tidakHadir = Absensi::where('user_id', $siswa->id)->where('status', 'tidak_hadir')->count();

    return response()->json([
        'nama' => $siswa->nama,
        'kelas' => $siswa->kelas,
        'nisn' => $siswa->nisn,
        'jenis_kelamin' => $siswa->jenis_kelamin,
        'agama' => $siswa->agama,
        'tanggal_lahir' => Carbon::parse($siswa->tanggal_lahir)->translatedFormat('d F Y'),
        'no_hp' => $siswa->nomor_hp,
        'email' => $siswa->email,
        'foto' => $siswa->foto,
        'statistik' => [
            'hadir' => $hadir,
            'terlambat' => $terlambat,
            'tidak_hadir' => $tidakHadir,
        ],
        'absensi' => $absensiFormatted,
    ]);
}

public function hariIni(Request $request)
{
    $tanggal = $request->query('tanggal');
    $nisn = $request->query('nisn');
    $studentId = $request->query('studentId');

    if ($studentId) {
        $user_id = $studentId;
    } elseif ($nisn) {
        $user = User::where('nisn', $nisn)->first();
        if (!$user) {
            return response()->json([
                'status' => null,
                'message' => 'User dengan NISN tidak ditemukan',
            ]);
        }
        $user_id = $user->id;
    } else {
        $user_id = auth()->id();
    }

    $absensi = \App\Models\Absensi::where('user_id', $user_id)
        ->whereDate('tanggal', $tanggal)
        ->first();

    if (!$absensi) {
        return response()->json([
            'status' => null,
            'message' => 'Absensi belum tersedia',
        ]);
    }

    return response()->json([
        'status' => $absensi->status,
        'message' => 'Berhasil',
    ]);
}

public function getAbsensiBulananByLoginUser(Request $request)
{
    try {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'error' => 'User tidak terautentikasi',
            ], 401);
        }

        $bulan = $request->query('bulan');
        $tahun = $request->query('tahun');

        if (!$bulan || !$tahun) {
            return response()->json([
                'error' => 'Parameter bulan dan tahun wajib diisi.'
            ], 422);
        }

        $absensi = Absensi::where('user_id', $user->id)
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->get();

        $total = $absensi->count();

        $hadir = $absensi->where('status', 'Hadir')->count();
        $tidakHadir = $absensi->where('status', 'Tidak Hadir')->count();
        $terlambat = $absensi->where('status', 'Terlambat')->count();

        return response()->json([
            'hadir' => $total > 0 ? round(($hadir / $total) * 100) : 0,
            'tidak_hadir' => $total > 0 ? round(($tidakHadir / $total) * 100) : 0,
            'terlambat' => $total > 0 ? round(($terlambat / $total) * 100) : 0,
            'total_hari' => $total,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
        ], 500);
    }
}

public function byUser(Request $request)
{
    $userId = $request->query('user_id');
    $bulan = $request->query('bulan');
    $tahun = $request->query('tahun');

    $absensi = \App\Models\Absensi::where('user_id', $userId)
        ->whereMonth('tanggal', $bulan)
        ->whereYear('tanggal', $tahun)
        ->orderBy('tanggal')
        ->get()
        ->map(function ($item) {
            return [
                'hari' => Carbon::parse($item->tanggal)->translatedFormat('l'), // ➕ Nama hari
                'tanggal' => $item->tanggal,                                    // ➕ Tanggal
                'status' => $item->status,
                'waktu_absen' => $item->waktu_absen,                            // ➕ Waktu
            ];
        });

    return response()->json(['absensi' => $absensi]);
}
public function absensiHariIniByKelas(Request $request)
{
    $kelas = $request->query('kelas');
    $tanggal = now()->toDateString();

    $absensi = Absensi::with('user')
        ->where('kelas', $kelas)
        ->where('tanggal', $tanggal)
        ->get();

    if ($absensi->isEmpty()) {
        return response()->json([
            'data' => [],
            'hari' => '-',
            'mulai' => '-',
            'selesai' => '-',
            'last_edit' => null,
        ]);
    }

    $first = $absensi->first();

    $data = $absensi->map(function ($item) {
        return [
            'id' => $item->user->id ?? null,
            'nama' => $item->user->nama ?? '-',
            'status' => $item->status ?? '-',
            'waktu' => $item->waktu_absen ?? '-',
        ];
    });

    return response()->json([
        'data' => $data,
        'hari' => $first->hari,
        'mulai' => $first->mulai,
        'selesai' => $first->selesai,
        'last_edit' => $first->updated_at->format('d F Y - H:i'),
    ]);
}

public function absensiAnak(Request $request)
{
    $user = auth()->user();

    if ($user->role !== 'orangtua') {
        return response()->json([
            'status' => null,
            'message' => 'Hanya role orangtua yang bisa mengakses absensi anak.'
        ], 403);
    }

    // Ambil NISN dari nip ortu (OT_1234123)
    $nisn = str_replace('OT_', '', $user->nisn);

    // Cari user anak (role siswa) berdasarkan NISN
    $siswa = User::where('role', 'siswa')->where('nisn', $nisn)->first();

    if (!$siswa) {
        return response()->json([
            'status' => null,
            'message' => 'Anak tidak ditemukan.'
        ], 404);
    }

    $tanggal = $request->query('tanggal') ?? date('Y-m-d');

    // Cek absensi berdasarkan user_id siswa
    $absensi = Absensi::where('user_id', $siswa->id)
        ->whereDate('tanggal', $tanggal)
        ->first();

    if (!$absensi) {
        return response()->json([
            'status' => null,
            'message' => 'Absensi belum tersedia'
        ]);
    }

    return response()->json([
        'status' => $absensi->status,
        'message' => 'Berhasil mengambil absensi anak'
    ]);
}

}