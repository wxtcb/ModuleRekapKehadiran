<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Cuti\Entities\Cuti;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranI;
use Modules\RekapKehadiran\Exports\RekapKehadiranIExport as RekapKehadiranIExport;
use Modules\Setting\Entities\Libur;

class KehadiranIController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $tanggal = $request->input('tanggal', date("Y-m-d"));
        $namaQuery = $request->input('nama');

        $user = Auth::user();
        $isToday = $tanggal === date('Y-m-d');
        $roles = $user->getRoleNames()->toArray();

        // Cek apakah tanggal adalah hari libur (weekend atau dari tabel Libur)
        $isWeekend = Carbon::parse($tanggal)->isWeekend();
        $isLibur = Libur::whereDate('tanggal', $tanggal)->exists();
        $statusLibur = ($isWeekend || $isLibur);

        // Ambil semua pegawai jika hari ini DAN role mahasiswa/pegawai/dosen
        $pegawaiQuery = Pegawai::query();

        if (!in_array('super', $roles) && !in_array('admin', $roles)) {
            // Jika mahasiswa, pegawai, dosen
            if ($isToday) {
                // Bisa lihat semua pegawai
            } elseif (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                // Bisa lihat dirinya sendiri
                $pegawaiQuery->where('username', $user->username);
            } else {
                // Selain hari ini, mahasiswa tidak bisa lihat apa-apa
                $pegawaiQuery->whereNull('id'); // Kosongkan hasil
            }
        }

        $pegawaiList = $pegawaiQuery->select('id', 'nama', 'nip', 'username')->get();

        // Filter nama
        if ($namaQuery) {
            $pegawaiList = $pegawaiList->filter(function ($pegawai) use ($namaQuery) {
                return stripos($pegawai->nama, $namaQuery) !== false;
            });
        }

        // Ambil presensi
        $kehadiranQuery = KehadiranI::on('second_db')->whereDate('checktime', $tanggal);

        // Jika pegawai biasa/dosen, dan bukan hari ini → lihat hanya miliknya
        if (!in_array('super', $roles) && !in_array('admin', $roles)) {
            if (!$isToday && (in_array('pegawai', $roles) || in_array('dosen', $roles))) {
                $pegawai = Pegawai::where('username', $user->username)->first();
                if ($pegawai) {
                    $kehadiranQuery->where('user_id', $pegawai->id);
                } else {
                    $kehadiranQuery->whereNull('user_id');
                }
            }
        }

        $kehadiranList = $kehadiranQuery->get();
        $presensiByUser = $kehadiranList->groupBy('user_id');

        $rekapPresensi = $pegawaiList->map(function ($pegawai) use ($presensiByUser, $tanggal, $statusLibur) {
            $userPresensi = $presensiByUser->get($pegawai->id, collect());

            $datang = $userPresensi->where('checktype', 'I')->sortBy('checktime')->first();
            $pulang = $userPresensi->where('checktype', 'O')->sortBy('checktime')->last();

            $waktuDatang = $datang ? date('H:i', strtotime($datang->checktime)) : '-';
            $waktuPulang = $pulang ? date('H:i', strtotime($pulang->checktime)) : '-';

            // Cek apakah pegawai sedang cuti pada tanggal tersebut
            $isCuti = Cuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'Selesai')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->exists();

            if ($statusLibur) {
                $status = 'Libur';
            } elseif ($isCuti) {
                $status = 'Cuti';
            } elseif (!$datang && !$pulang) {
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

        $isAdmin = in_array('admin', $roles) || in_array('super', $roles);
        $pegawaiId = null;

        if (!$isAdmin) {
            $pegawaiId = Pegawai::where('username', $user->username)->value('id');
        }

        return view('rekapkehadiran::kehadirani.index', compact('rekapPresensi', 'isAdmin', 'pegawaiId', 'tanggal'));
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

    public function export(Request $request)
    {
        $pegawaiId = $request->input('pegawai_id');
        $month = $request->input('month');
        $year = $request->input('year');

        return Excel::download(new RekapKehadiranIExport($pegawaiId, $month, $year), 'rekap-kehadiran.xlsx');
    }
}
