<?php

namespace App\Http\Controllers;

use App\Models\piket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PiketController extends Controller
{
    public function getPiket(Request $request)
    {
        $request->validate([
            'kelas' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        $kelas = $request->kelas;
        $tanggal = $request->tanggal;

        // Ambil data absensi berdasarkan kelas dan tanggal
        $piket = Piket::with('user')
            ->where('kelas', $kelas)
            ->where('tanggal', $tanggal)
            ->get();

        if ($piket->isEmpty()) {
            return response()->json([
                'message' => 'Data piket tidak ditemukan',
                'kelas' => $kelas,
                'hari' => null,
                'mulai' => null,
                'selesai' => null,
                'data' => [],
            ]);
        }

        // Ambil data hari, mulai, selesai dari record pertama
        $first = $piket->first();

        $result = $piket->map(function ($item) {
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
            'message' => 'Data absensi piket berhasil diambil',
            'kelas' => $kelas,
            'hari' => $first->hari,
            'mulai' => $first->mulai,
            'selesai' => $first->selesai,
            'data' => $result,
        ]);
    }

    public function updateStatusJikaWaktuSelesai($kelas, $tanggal)
    {
        $piketBelumHadir = Piket::where('kelas', $kelas)
            ->where('tanggal', $tanggal)
            ->where('status', '!=', 'hadir')
            ->get();

        $now = date('H:i'); 

        $jamSelesai = Piket::where('kelas', $kelas)
            ->where('tanggal', $tanggal)
            ->value('selesai');

        if (!$jamSelesai) {
            return response()->json(['message' => 'Jam selesai belum diatur.'], 400);
        }

        if ($now >= $jamSelesai) {
            Piket::where('kelas', $kelas)
                ->where('tanggal', $tanggal)
                ->where('status', '!=', 'berkontribusi')
                ->update(['status' => 'tidak berkontribusi']);

            return response()->json(['message' => 'Status piket otomatis diupdate menjadi tidak hadir.']);
        }

        return response()->json(['message' => 'Belum waktunya update status.']);
    }

        public function inputPiket(Request $request)
    {
        $request->validate([
            'kelas' => 'required|string',
            'tanggal' => 'required|date',
            'hari' => 'required|string',
            'mulai' => 'required|string',
            'selesai' => 'required|string',
            'piket' => 'required|array',
            'piket.*.nisn' => 'required|string',
            'piket.*.status' => 'required|string',
            'piket.*.waktu_absen' => 'required|string',
        ]);

        $kelas = $request->kelas;
        $tanggal = $request->tanggal;
        $hari = $request->hari;
        $mulai = $request->mulai;
        $selesai = $request->selesai;
        $piketData = $request->piket;

        foreach ($piketData as $item) {
            $user = User::where('nisn', $item['nisn'])->first();

            if (!$user) {
                continue;
            }

            Piket::updateOrCreate(
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

        $this->updateStatusJikaWaktuSelesai($kelas, $tanggal);

        return response()->json([
            'message' => 'Piket berhasil disimpan atau diperbarui.'
        ], 200);
    }
    
    public function rekapKontribusiBulanan(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:2000',
        ]);

        $bulan = $request->bulan;
        $tahun = $request->tahun;

        $rekap = DB::table('piket')
            ->select(
                'kelas',
                DB::raw("SUM(CASE WHEN status = 'berkontribusi' THEN 1 ELSE 0 END) as jumlah_berkontribusi"),
                DB::raw("SUM(CASE WHEN status != 'berkontribusi' THEN 1 ELSE 0 END) as jumlah_tidak_berkontribusi")
            )
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->groupBy('kelas')
            ->get();

        return response()->json([
            'bulan' => $bulan,
            'tahun' => $tahun,
            'data' => $rekap
        ]);
    }
public function piketSaya(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'error' => 'User tidak terautentikasi',
        ], 401);
    }

    $bulan = $request->query('bulan', now()->month);
    $tahun = $request->query('tahun', now()->year);

    if ($user->role === 'orangtua') {
        $nisnAnak = str_replace('OT_', '', $user->nisn);

        $anak = \App\Models\User::where('nisn', $nisnAnak)->first();

        if (!$anak) {
            return response()->json([
                'error' => 'Data anak tidak ditemukan',
            ], 404);
        }

        $targetUserId = $anak->id;
    } else {
        $targetUserId = $user->id;
    }

    $piket = \App\Models\Piket::where('user_id', $targetUserId)
        ->whereMonth('tanggal', $bulan)
        ->whereYear('tanggal', $tahun)
        ->orderBy('tanggal', 'asc')
        ->get();

    $data = $piket->map(function ($item) {
        return [
            'tanggal' => \Carbon\Carbon::parse($item->tanggal)->translatedFormat('l, d F Y'),
            'status' => $item->status,
            'waktu' => $item->waktu_absen,
        ];
    });

    return response()->json($data);
}

public function riwayatByNISN($nisn, Request $request)
{
    $user = User::where('nisn', $nisn)->first();

    if (!$user) {
        return response()->json([], 404);
    }

    $bulan = $request->query('bulan', now()->month);
    $tahun = $request->query('tahun', now()->year);

    $piket = Piket::where('user_id', $user->id)
        ->whereMonth('tanggal', $bulan)
        ->whereYear('tanggal', $tahun)
        ->orderBy('tanggal', 'asc')
        ->get();

    $data = $piket->map(function ($item) {
        return [
            'tanggal' => \Carbon\Carbon::parse($item->tanggal)->translatedFormat('l, d F Y'),
            'status' => $item->status,
            'waktu' => $item->waktu_absen,
        ];
    });

    return response()->json($data);
}

}
