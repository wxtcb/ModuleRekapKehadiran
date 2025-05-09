<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranII;
use Modules\Setting\Entities\Libur;

class KehadiranIIController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);
    
        $totalHari = Carbon::create($year, $month)->daysInMonth;
    
        // Ambil list pegawai
        $pegawaiList = $user->hasRole('admin')
            ? Pegawai::select('user_id', 'nama', 'nip')->get()
            : Pegawai::where('user_id', $user->id)->select('user_id', 'nama', 'nip')->get();
    
        // Ambil kehadiran bulan & tahun tersebut
        $kehadiran = KehadiranII::whereMonth('checktime', $month)
            ->whereYear('checktime', $year)
            ->when(!$user->hasRole('admin'), function ($query) use ($user) {
                return $query->where('user_id', $user->id);
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '|' . Carbon::parse($item->checktime)->format('Y-m-d');
            });
    
        // Ambil daftar hari libur dari DB Setting
        $liburTanggal = Libur::whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year)
            ->pluck('tanggal')
            ->map(fn($tgl) => Carbon::parse($tgl)->format('Y-m-d'))
            ->toArray();
    
        // Siapkan tanggal dan libur index
        $tanggalHari = collect();
        $liburIndex = [];
    
        for ($i = 1; $i <= $totalHari; $i++) {
            $tanggal = Carbon::create($year, $month, $i);
            $formatted = $tanggal->format('Y-m-d');
            $tanggalHari->push($formatted);
    
            // Tandai libur jika Sabtu/Minggu atau hari libur di DB
            $liburIndex[] = in_array($tanggal->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]) || in_array($formatted, $liburTanggal);
        }
    
        // Map data presensi
        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $tanggalHari, $liburIndex) {
            $presensi = [];
            $total = ['D' => 0, 'T' => 0, 'TM' => 0, 'C' => 0, 'DL' => 0];
    
            foreach ($tanggalHari as $idx => $tanggal) {
                if ($liburIndex[$idx]) {
                    $presensi[] = 'L'; // Libur
                    continue;
                }
    
                $key = $pegawai->user_id . '|' . $tanggal;
    
                if ($kehadiran->has($key)) {
                    $checktypes = $kehadiran[$key]->pluck('checktype')->unique();
                    $hasI = $checktypes->contains('I');
                    $hasO = $checktypes->contains('O');
    
                    if ($hasI && $hasO) {
                        $presensi[] = 'D'; // Datang & Pulang lengkap
                        $total['D']++;
                    } elseif ($hasI || $hasO) {
                        $presensi[] = 'T'; // Tidak lengkap
                        $total['T']++;
                    } else {
                        $presensi[] = 'TM'; // Tidak masuk
                        $total['TM']++;
                    }
                } else {
                    $presensi[] = 'TM'; // Tidak ada data kehadiran
                    $total['TM']++;
                }
            }
    
            return [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'presensi' => $presensi,
                'total' => $total
            ];
        });
    
        return view('rekapkehadiran::kehadiranii.index', compact('data', 'tanggalHari', 'month', 'year'));
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
