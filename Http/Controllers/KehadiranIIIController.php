<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranIII;
use Modules\RekapKehadiran\Exports\RekapKehadiranIIIExport;
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
        $roles = $user->getRoleNames()->toArray();
        $data = $this->getRekapData($request, $year);

        $pegawaiList = Pegawai::query();

        if (!in_array('admin', $roles) && !in_array('super', $roles)) {
            if (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                $pegawaiList->where('id', $user->id);
            } else {
                $pegawaiList->whereNull('id');
            }
        }

        $pegawaiList = $pegawaiList->select('id', 'nama', 'nip')->get();

        $kehadiran = KehadiranIII::query()
            ->whereYear('checktime', $year)
            ->when(!in_array('admin', $roles), function ($query) use ($user, $roles) {
                if (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                    return $query->where('user_id', $user->id);
                }
                return $query->whereNull('user_id');
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '|' . Carbon::parse($item->checktime)->format('Y-m-d');
            });

        $tanggalLibur = Libur::whereYear('tanggal', $year)->pluck('tanggal')->map(function ($tanggal) {
            return Carbon::parse($tanggal)->format('Y-m-d');
        })->toArray();

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

        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $hariKerja) {
            $total = ['D' => 0, 'TM' => 0, 'C' => 0, 'T' => 0, 'DL' => 0];

            foreach ($hariKerja as $tanggal) {
                $key = $pegawai->id . '|' . $tanggal;

                if ($kehadiran->has($key)) {
                    $absenHariItu = $kehadiran->get($key);
                    $checktypes = $absenHariItu->pluck('checktype')->unique();
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

    private function getRekapData(Request $request, $year)
    {
        $user = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        $pegawaiList = Pegawai::query();

        if (!in_array('admin', $roles) && !in_array('super', $roles)) {
            if (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                $pegawaiList->where('id', $user->id);
            } else {
                $pegawaiList->whereNull('id');
            }
        }

        $pegawaiList = $pegawaiList->select('id', 'nama', 'nip')->get();

        $kehadiran = KehadiranIII::query()
            ->whereYear('checktime', $year)
            ->when(!in_array('admin', $roles), function ($query) use ($user, $roles) {
                if (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                    return $query->where('user_id', $user->id);
                }
                return $query->whereNull('user_id');
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '|' . \Carbon\Carbon::parse($item->checktime)->format('Y-m-d');
            });

        $tanggalLibur = Libur::whereYear('tanggal', $year)->pluck('tanggal')->map(function ($tanggal) {
            return \Carbon\Carbon::parse($tanggal)->format('Y-m-d');
        })->toArray();

        $hariKerja = collect();
        $start = \Carbon\Carbon::create($year, 1, 1);
        $end = \Carbon\Carbon::create($year, 12, 31);

        while ($start <= $end) {
            $tanggal = $start->format('Y-m-d');
            if (!$start->isWeekend() && !in_array($tanggal, $tanggalLibur)) {
                $hariKerja->push($tanggal);
            }
            $start->addDay();
        }

        return $pegawaiList->map(function ($pegawai) use ($kehadiran, $hariKerja) {
            $total = ['D' => 0, 'TM' => 0, 'C' => 0, 'T' => 0, 'DL' => 0];

            foreach ($hariKerja as $tanggal) {
                $key = $pegawai->id . '|' . $tanggal;

                if ($kehadiran->has($key)) {
                    $absenHariItu = $kehadiran->get($key);
                    $checktypes = $absenHariItu->pluck('checktype')->unique();
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
                'total' => $total
            ];
        })->toArray(); // penting: array untuk Excel
    }

    public function export(Request $request)
    {
        $year = $request->input('year', now()->year);
        $data = $this->getRekapData($request, $year); // sudah array, aman

        return Excel::download(new RekapKehadiranIIIExport($data, $year), 'rekap_kehadiran_' . $year . '.xlsx');
    }

}
