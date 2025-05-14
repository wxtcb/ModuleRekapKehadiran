<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranII;
use Modules\RekapKehadiran\Exports\RekapKehadiranIIExport;
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
        $roles = $user->getRoleNames()->toArray();

        $pegawaiQuery = Pegawai::query();

        if (!in_array('admin', $roles) && !in_array('super', $roles)) {
            if (in_array('mahasiswa', $roles) && (now()->month != $month || now()->year != $year)) {
                $pegawaiQuery->whereNull('id'); // tidak tampilkan apa-apa
            } elseif (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                $pegawaiQuery->where('username', $user->username); // 💡 filter by username
            }
        }

        $pegawaiList = $pegawaiQuery->select('id', 'nama', 'nip', 'username')->get();

        // Dapatkan daftar ID pegawai yang ada
        $pegawaiIDs = $pegawaiList->pluck('id')->toArray();

        // Ambil presensi berdasarkan user_id (== pegawai.id)
        $kehadiran = KehadiranII::whereMonth('checktime', $month)
            ->whereYear('checktime', $year)
            ->when(!in_array('admin', $roles), function ($query) use ($pegawaiIDs) {
                return $query->whereIn('user_id', $pegawaiIDs);
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '|' . Carbon::parse($item->checktime)->format('Y-m-d');
            });

        // Ambil daftar tanggal libur (mingguan & nasional)
        $liburTanggal = Libur::whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year)
            ->pluck('tanggal')
            ->map(fn($tgl) => Carbon::parse($tgl)->format('Y-m-d'))
            ->toArray();

        $tanggalHari = collect();
        $liburIndex = [];

        for ($i = 1; $i <= $totalHari; $i++) {
            $tanggal = Carbon::create($year, $month, $i);
            $formatted = $tanggal->format('Y-m-d');
            $tanggalHari->push($formatted);
            $liburIndex[] = $tanggal->isWeekend() || in_array($formatted, $liburTanggal);
        }

        // Proses presensi
        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $tanggalHari, $liburIndex) {
            $presensi = [];
            $total = ['D' => 0, 'T' => 0, 'TM' => 0, 'C' => 0, 'DL' => 0];

            foreach ($tanggalHari as $idx => $tanggal) {
                if ($liburIndex[$idx]) {
                    $presensi[] = 'L';
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;

                if ($kehadiran->has($key)) {
                    $checktypes = $kehadiran[$key]->pluck('checktype')->unique();
                    $hasI = $checktypes->contains('I');
                    $hasO = $checktypes->contains('O');

                    if ($hasI && $hasO) {
                        $presensi[] = 'D';
                        $total['D']++;
                    } elseif ($hasI || $hasO) {
                        $presensi[] = 'T';
                        $total['T']++;
                    } else {
                        $presensi[] = 'TM';
                        $total['TM']++;
                    }
                } else {
                    $presensi[] = 'TM';
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

    private function getRekapData($month, $year)
    {
        $user = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        $totalHari = \Carbon\Carbon::create($year, $month)->daysInMonth;

        $pegawaiQuery = Pegawai::query();

        if (!in_array('admin', $roles) && !in_array('super', $roles)) {
            if (in_array('mahasiswa', $roles) && (now()->month != $month || now()->year != $year)) {
                $pegawaiQuery->whereNull('id'); // Tidak tampilkan apa-apa
            } elseif (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                $pegawaiQuery->where('username', $user->username);
            }
        }

        $pegawaiList = $pegawaiQuery->select('id', 'nama', 'nip', 'username')->get();
        $pegawaiIDs = $pegawaiList->pluck('id')->toArray();

        $kehadiran = KehadiranII::whereMonth('checktime', $month)
            ->whereYear('checktime', $year)
            ->when(!in_array('admin', $roles), function ($query) use ($pegawaiIDs) {
                return $query->whereIn('user_id', $pegawaiIDs);
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '|' . \Carbon\Carbon::parse($item->checktime)->format('Y-m-d');
            });

        $liburTanggal = Libur::whereMonth('tanggal', $month)
            ->whereYear('tanggal', $year)
            ->pluck('tanggal')
            ->map(fn($tgl) => \Carbon\Carbon::parse($tgl)->format('Y-m-d'))
            ->toArray();

        $tanggalHari = collect();
        $liburIndex = [];

        for ($i = 1; $i <= $totalHari; $i++) {
            $tanggal = \Carbon\Carbon::create($year, $month, $i);
            $formatted = $tanggal->format('Y-m-d');
            $tanggalHari->push($formatted);
            $liburIndex[] = $tanggal->isWeekend() || in_array($formatted, $liburTanggal);
        }

        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $tanggalHari, $liburIndex) {
            $presensi = [];
            $total = ['D' => 0, 'T' => 0, 'TM' => 0, 'C' => 0, 'DL' => 0];

            foreach ($tanggalHari as $idx => $tanggal) {
                if ($liburIndex[$idx]) {
                    $presensi[] = 'L';
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;

                if ($kehadiran->has($key)) {
                    $checktypes = $kehadiran[$key]->pluck('checktype')->unique();
                    $hasI = $checktypes->contains('I');
                    $hasO = $checktypes->contains('O');

                    if ($hasI && $hasO) {
                        $presensi[] = 'D';
                        $total['D']++;
                    } elseif ($hasI || $hasO) {
                        $presensi[] = 'T';
                        $total['T']++;
                    } else {
                        $presensi[] = 'TM';
                        $total['TM']++;
                    }
                } else {
                    $presensi[] = 'TM';
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

        return [
            'data' => $data,
            'tanggalHari' => $tanggalHari
        ];
    }

    public function export(Request $request)
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $rekap = $this->getRekapData($month, $year);

        return Excel::download(
            new RekapKehadiranIIExport($rekap['data'], $rekap['tanggalHari'], $month, $year),
            'rekap_kehadiran_' . $month . '_' . $year . '.xlsx'
        );
    }

}
