<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranII;

class KehadiranIIController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $tanggalHari = collect();
        $totalHari = Carbon::create($year, $month)->daysInMonth;

        for ($i = 1; $i <= $totalHari; $i++) {
            $tanggalHari->push(Carbon::create($year, $month, $i)->format('Y-m-d'));
        }

        $pegawaiList = Pegawai::select('user_id', 'nama', 'nip')->get();

        $kehadiran = KehadiranII::whereMonth('checktime', $month)
                        ->whereYear('checktime', $year)
                        ->get()
                        ->groupBy(function ($item) {
                            return $item->user_id . '|' . Carbon::parse($item->checktime)->format('Y-m-d');
                        });

        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $tanggalHari) {
            $presensi = [];

            foreach ($tanggalHari as $tanggal) {
                $key = $pegawai->user_id . '|' . $tanggal;
                $presensi[] = $kehadiran->has($key) ? 'D' : 'TM';
            }

            return [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'presensi' => $presensi
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
