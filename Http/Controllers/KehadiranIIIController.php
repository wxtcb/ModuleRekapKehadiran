<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranIII;
use Modules\Setting\Entities\Libur;

class KehadiranIIIController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $year = $request->input('year', now()->year);
    
        // Ambil pegawai
        $pegawaiList = $user->hasRole('admin')
            ? Pegawai::select('user_id', 'nama', 'nip')->get()
            : Pegawai::where('user_id', $user->id)->select('user_id', 'nama', 'nip')->get();
    
        // Ambil semua data presensi tahun ini
        $kehadiran = KehadiranIII::query()
            ->whereYear('checktime', $year)
            ->when(!$user->hasRole('admin'), function ($query) use ($user) {
                return $query->where('user_id', $user->id);
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '|' . Carbon::parse($item->checktime)->format('Y-m-d');
            });
    
        // Ambil daftar tanggal libur dari tabel Libur
        $tanggalLibur = Libur::whereYear('tanggal', $year)->pluck('tanggal')->map(function ($tanggal) {
            return Carbon::parse($tanggal)->format('Y-m-d');
        })->toArray();
    
        // Hitung hari kerja sebenarnya (bukan weekend & bukan hari libur)
        $hariKerja = collect();
        $start = Carbon::create($year, 1, 1);
        $end = Carbon::create($year, 12, 31);
    
        while ($start <= $end) {
            $tanggal = $start->format('Y-m-d');
            if (!$start->isWeekend() && !in_array($tanggal, $tanggalLibur)) {
                $hariKerja->push($tanggal);
            }
            $start->addDay();
        }
    
        // Mapping data presensi per pegawai
        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $hariKerja) {
            $total = [
                'D' => 0,  // Hadir lengkap
                'TM' => 0, // Tidak hadir sama sekali
                'C' => 0,  // Cuti (opsional)
                'T' => 0,  // Tidak lengkap
                'DL' => 0  // Dinas luar (opsional)
            ];
    
            foreach ($hariKerja as $tanggal) {
                $key = $pegawai->user_id . '|' . $tanggal;
                if ($kehadiran->has($key)) {
                    $absenHariItu = $kehadiran->get($key);
                    $checktypes = $absenHariItu->pluck('checktype')->unique()->sort()->values();
    
                    $hasI = $checktypes->contains('I');
                    $hasO = $checktypes->contains('O');
    
                    if ($hasI && $hasO) {
                        $total['D']++;
                    } elseif ($hasI || $hasO) {
                        $total['T']++;
                    } else {
                        $total['TM']++;
                    }
                } else {
                    $total['TM']++;
                }
            }
    
            return [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'keterangan' => 'Dosen',
                'total' => $total
            ];
        });
    
        return view('rekapkehadiran::kehadiraniii.index', compact('data', 'year'));
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
