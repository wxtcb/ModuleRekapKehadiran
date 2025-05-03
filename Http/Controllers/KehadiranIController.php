<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranI;

class KehadiranIController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $pegawaiList = Pegawai::select('user_id', 'nama', 'nip')->get()->keyBy('user_id');
        $kehadiranList = KehadiranI::all();
    
        $rekapPresensi = $kehadiranList->groupBy(function ($item) {
            return $item->user_id . '|' . Carbon::parse($item->checktime)->format('Y-m-d');
        })->map(function ($items, $key) use ($pegawaiList) {
            [$userId, $tanggal] = explode('|', $key);
            $pegawai = $pegawaiList->get($userId);
    
            $datang = $items->where('checktype', 'I')->sortBy('checktime')->first();
            $pulang = $items->where('checktype', 'O')->sortBy('checktime')->last();
    
            $waktuDatang = $datang ? Carbon::parse($datang->checktime) : null;
            $waktuPulang = $pulang ? Carbon::parse($pulang->checktime) : null;
    
            // Hitung durasi jam dan menit
$durasiJam = '-';
if ($waktuDatang && $waktuPulang) {
    $diffInSeconds = $waktuDatang->diffInSeconds($waktuPulang);
    $jam = floor($diffInSeconds / 3600);
    $menit = floor(($diffInSeconds % 3600) / 60);
    $durasiJam = "{$jam} jam {$menit} menit";
}

$status = 'Alpha';
if ($waktuDatang && $waktuPulang) {
    $status = 'Hadir';
} elseif ($waktuDatang && !$waktuPulang) {
    $status = 'Hadir, lupa presensi pulang';
} elseif (!$waktuDatang && $waktuPulang) {
    $status = 'Hadir, lupa presensi datang';
}
    
            return (object) [
                'nama' => $pegawai->nama ?? 'Tidak Diketahui',
                'nip' => $pegawai->nip ?? '-',
                'tanggal' => $tanggal,
                'waktu_datang' => $waktuDatang ? $waktuDatang->format('H:i') : '-',
                'waktu_pulang' => $waktuPulang ? $waktuPulang->format('H:i') : '-',
                'status' => $status,
                'durasi_jam' => $durasiJam,
            ];
        })->values();
    
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
