<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranI;

class KehadiranIController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $tanggal = $request->input('tanggal', date('Y-m-d'));
        $namaQuery = $request->input('nama');
    
        $user = Auth::user(); // User login
        $isToday = $tanggal === date('Y-m-d');
    
        // Ambil pegawai sesuai user login (kecuali super)
        $pegawaiQuery = Pegawai::query();
    
        if ($user->username !== 'super') {
            // Jika bukan super user dan bukan hari ini, hanya lihat diri sendiri
            if (!$isToday) {
                $pegawaiQuery->where('username', $user->username);
            }
        }
    
        $pegawaiList = $pegawaiQuery->select('user_id', 'nama', 'nip', 'username')->get();
    
        // Filter nama pegawai jika dicari
        if ($namaQuery) {
            $pegawaiList = $pegawaiList->filter(function ($pegawai) use ($namaQuery) {
                return stripos($pegawai->nama, $namaQuery) !== false;
            });
        }
    
        // Ambil data presensi dari koneksi second_db
        $kehadiranQuery = KehadiranI::on('second_db')->whereDate('checktime', $tanggal);
    
        // Jika bukan super dan bukan hari ini, filter user_id sesuai user login
        if ($user->username !== 'super' && !$isToday) {
            $pegawai = Pegawai::where('username', $user->username)->first();
            if ($pegawai) {
                $kehadiranQuery->where('user_id', $pegawai->user_id);
            } else {
                $kehadiranQuery->whereNull('user_id'); // Untuk menghindari error jika pegawai tidak ditemukan
            }
        }
    
        $kehadiranList = $kehadiranQuery->get();
        $presensiByUser = $kehadiranList->groupBy('user_id');
    
        $rekapPresensi = $pegawaiList->map(function ($pegawai) use ($presensiByUser, $tanggal) {
            $userPresensi = $presensiByUser->get($pegawai->user_id, collect());
    
            $datang = $userPresensi->where('checktype', 'I')->sortBy('checktime')->first();
            $pulang = $userPresensi->where('checktype', 'O')->sortBy('checktime')->last();
    
            $waktuDatang = $datang ? date('H:i', strtotime($datang->checktime)) : '-';
            $waktuPulang = $pulang ? date('H:i', strtotime($pulang->checktime)) : '-';
    
            if (!$datang && !$pulang) {
                $status = 'Alpha';
            } elseif ($datang && !$pulang) {
                $status = 'Hadir (Lupa presensi pulang)';
            } elseif (!$datang && $pulang) {
                $status = 'Hadir (Lupa presensi datang)';
            } else {
                $status = 'Hadir';
            }
    
            $durasi_jam = '-';
            if ($datang && $pulang) {
                $start = strtotime($datang->checktime);
                $end = strtotime($pulang->checktime);
                $diff = $end - $start;
                $jam = floor($diff / 3600);
                $menit = floor(($diff % 3600) / 60);
                $durasi_jam = "{$jam} jam {$menit} menit";
            }
    
            return (object)[
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'tanggal' => $tanggal,
                'waktu_datang' => $waktuDatang,
                'waktu_pulang' => $waktuPulang,
                'status' => $status,
                'durasi_jam' => $durasi_jam,
            ];
        });
    
        return view('rekapkehadiran::kehadirani.index', compact('rekapPresensi'));
    }
    
    
    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('rekapkehadiran::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('rekapkehadiran::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('rekapkehadiran::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }
}
