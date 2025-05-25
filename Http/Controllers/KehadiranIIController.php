<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use App\Models\Core\User;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Cuti\Entities\Cuti;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranII;
use Modules\RekapKehadiran\Exports\RekapKehadiranIIExport;
use Modules\Setting\Entities\Jam;
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

        // Ambil data cuti pegawai yang disetujui di bulan & tahun yang dimaksud
        $cutiData = Cuti::where('status', 'Selesai')
            ->whereIn('pegawai_id', $pegawaiIDs)
            ->get()
            ->flatMap(function ($cuti) {
                $start = Carbon::parse($cuti->tanggal_mulai);
                $end = Carbon::parse($cuti->tanggal_selesai);
                $range = [];

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $range[] = $date->format('Y-m-d');
                }

                return collect($range)->map(function ($tgl) use ($cuti) {
                    return [
                        'pegawai_id' => $cuti->pegawai_id,
                        'tanggal' => $tgl,
                    ];
                });
            });

        // Group cuti berdasarkan pegawai_id
        $cutiByPegawai = $cutiData->groupBy('pegawai_id')->map(function ($items) {
            return $items->pluck('tanggal')->toArray();
        });

        // Proses presensi
        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $tanggalHari, $liburIndex, $cutiByPegawai) {
            $presensi = [];
            $total = ['D' => 0, 'T' => 0, 'TM' => 0, 'C' => 0, 'DL' => 0];

            $cutiTanggal = $cutiByPegawai->get($pegawai->id, []);

            foreach ($tanggalHari as $idx => $tanggal) {
                if ($liburIndex[$idx]) {
                    $presensi[] = 'L'; // Libur
                    continue;
                }

                if (in_array($tanggal, $cutiTanggal)) {
                    $presensi[] = 'C'; // Cuti
                    $total['C']++;
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;

                if (isset($kehadiran[$key])) {
                    $records = $kehadiran[$key];
                    $checkIn = $records->where('checktype', 'I')->sortBy('checktime')->first();
                    $checkOut = $records->where('checktype', 'O')->sortByDesc('checktime')->first();

                    $hasI = !is_null($checkIn);
                    $hasO = !is_null($checkOut);

                    if ($hasI && $hasO) {
                        $start = Carbon::parse($checkIn->checktime);
                        $end = Carbon::parse($checkOut->checktime);
                        $durationInHours = $end->floatDiffInHours($start);

                        // Ambil role asli dari user pegawai
                        $userPegawai = User::where('username', $pegawai->username)->first();
                        $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];

                        $jenis = in_array('dosen', $pegawaiRoles) ? 'dosen' : 'pegawai';
                        $minimalJamKerja = ($jenis === 'dosen') ? 4 : 8;

                        // Cek jam kerja kustom berdasarkan rentang tanggal
                        $jamKerjaCustom = Jam::where('jenis', $jenis)
                            ->whereDate('tanggal_mulai', '<=', $tanggal)
                            ->whereDate('tanggal_selesai', '>=', $tanggal)
                            ->first();

                        if ($jamKerjaCustom && !empty($jamKerjaCustom->jam_kerja)) {
                            $jamKerjaStr = strtolower(trim($jamKerjaCustom->jam_kerja));
                            $jamKerjaStr = preg_replace('/\s+/', ' ', $jamKerjaStr);

                            $pattern = '/(\d+)\s*jam\s*(\d+)\s*menit/';
                            if (preg_match($pattern, $jamKerjaStr, $matches)) {
                                $jamMinimal = (int)$matches[1];
                                $menitMinimal = (int)$matches[2];
                                $minimalJamKerja = $jamMinimal + ($menitMinimal / 60);
                            } else {
                                Log::warning("Format jam_kerja tidak sesuai: " . $jamKerjaCustom->jam_kerja);
                            }
                        }

                        if ($durationInHours < $minimalJamKerja) {
                            $presensi[] = 'T'; // Hadir tapi kurang jam
                            $total['T']++;
                        } else {
                            $presensi[] = 'D'; // Hadir penuh
                            $total['D']++;
                        }
                    } elseif ($hasI || $hasO) {
                        $presensi[] = 'T'; // Hadir sebagian
                        $total['T']++;
                    } else {
                        $presensi[] = 'TM'; // Tidak masuk (tidak valid)
                        $total['TM']++;
                    }
                } else {
                    $presensi[] = 'TM'; // Tidak masuk
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

    private function getRekapData(Request $request, $year)
    {
        $user = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        $pegawaiQuery = Pegawai::query();

        if (!in_array('admin', $roles) && !in_array('super', $roles)) {
            if (in_array('mahasiswa', $roles)) {
                $pegawaiQuery->whereNull('id');
            } elseif (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                $pegawaiQuery->where('username', $user->username);
            }
        }

        $pegawaiList = $pegawaiQuery->select('id', 'nama', 'nip', 'username')->get();
        $pegawaiIDs = $pegawaiList->pluck('id')->toArray();

        $kehadiran = KehadiranIII::query()
            ->whereYear('checktime', $year)
            ->when(!in_array('admin', $roles), function ($query) use ($pegawaiIDs) {
                return $query->whereIn('user_id', $pegawaiIDs);
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '|' . \Carbon\Carbon::parse($item->checktime)->format('Y-m-d');
            });

        $tanggalLibur = Libur::whereYear('tanggal', $year)
            ->pluck('tanggal')
            ->map(fn($tgl) => \Carbon\Carbon::parse($tgl)->format('Y-m-d'))
            ->toArray();

        $cutiData = Cuti::where('status', 'Selesai')
            ->whereIn('pegawai_id', $pegawaiIDs)
            ->get()
            ->flatMap(function ($cuti) {
                $start = \Carbon\Carbon::parse($cuti->tanggal_mulai);
                $end = \Carbon\Carbon::parse($cuti->tanggal_selesai);
                $range = [];

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $range[] = $date->format('Y-m-d');
                }

                return collect($range)->map(function ($tgl) use ($cuti) {
                    return [
                        'pegawai_id' => $cuti->pegawai_id,
                        'tanggal' => $tgl,
                    ];
                });
            });

        $cutiByPegawai = $cutiData->groupBy('pegawai_id')->map(function ($items) {
            return $items->pluck('tanggal')->toArray();
        });

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

        return $pegawaiList->map(function ($pegawai) use ($kehadiran, $hariKerja, $cutiByPegawai) {
            $total = ['D' => 0, 'T' => 0, 'TM' => 0, 'C' => 0, 'DL' => 0];
            $cutiTanggal = $cutiByPegawai->get($pegawai->id, []);

            $userPegawai = User::where('username', $pegawai->username)->first();
            $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];
            $jenis = in_array('dosen', $pegawaiRoles) ? 'dosen' : 'pegawai';

            foreach ($hariKerja as $tanggal) {
                if (in_array($tanggal, $cutiTanggal)) {
                    $total['C']++;
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;

                if (isset($kehadiran[$key])) {
                    $records = $kehadiran[$key];
                    $checkIn = $records->where('checktype', 'I')->sortBy('checktime')->first();
                    $checkOut = $records->where('checktype', 'O')->sortByDesc('checktime')->first();

                    $hasI = !is_null($checkIn);
                    $hasO = !is_null($checkOut);

                    if ($hasI && $hasO) {
                        $start = \Carbon\Carbon::parse($checkIn->checktime);
                        $end = \Carbon\Carbon::parse($checkOut->checktime);
                        $durationInHours = $end->floatDiffInHours($start);

                        // Cek jam kerja custom
                        $minimalJamKerja = ($jenis === 'dosen') ? 4 : 8;
                        $jamKerjaCustom = Jam::where('jenis', $jenis)
                            ->whereDate('tanggal_mulai', '<=', $tanggal)
                            ->whereDate('tanggal_selesai', '>=', $tanggal)
                            ->first();

                        if ($jamKerjaCustom && !empty($jamKerjaCustom->jam_kerja)) {
                            $jamKerjaStr = strtolower(trim($jamKerjaCustom->jam_kerja));
                            $jamKerjaStr = preg_replace('/\s+/', ' ', $jamKerjaStr);
                            $pattern = '/(\d+)\s*jam\s*(\d+)\s*menit/';

                            if (preg_match($pattern, $jamKerjaStr, $matches)) {
                                $jamMinimal = (int)$matches[1];
                                $menitMinimal = (int)$matches[2];
                                $minimalJamKerja = $jamMinimal + ($menitMinimal / 60);
                            } else {
                                \Log::warning("Format jam_kerja tidak sesuai: " . $jamKerjaCustom->jam_kerja);
                            }
                        }

                        if ($durationInHours < $minimalJamKerja) {
                            $total['T']++;
                        } else {
                            $total['D']++;
                        }
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
        })->toArray();
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
