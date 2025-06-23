<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use App\Models\Core\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DateTime;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Cuti\Entities\Cuti;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\Jam;
use Modules\RekapKehadiran\Entities\KehadiranII;
use Modules\RekapKehadiran\Entities\Libur;
use Modules\RekapKehadiran\Exports\RekapKehadiranIIExport;
use Modules\SuratIjin\Entities\LupaAbsen;
use Modules\SuratIjin\Entities\Terlambat;
use Modules\SuratTugas\Entities\AnggotaSuratTugas;
use Modules\SuratTugas\Entities\DetailSuratTugas;
use Modules\SuratTugas\Entities\SuratTugas;

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

        if (!in_array('admin', $roles) && !in_array('super', $roles) && !in_array('direktur', $roles)) {
            if (in_array('mahasiswa', $roles) && (now()->month != $month || now()->year != $year)) {
                $pegawaiQuery->whereNull('id'); // tidak tampilkan apa-apa
            } else {
                $pegawai = Pegawai::with('pejabat.timKerja.anggota', 'timKerjaKetua.anggota')
                    ->where('username', $user->username)
                    ->first();

                if ($pegawai) {
                    $pegawaiIds = collect([$pegawai->id]);

                    if ($pegawai->pejabat && $pegawai->pejabat->timKerja) {
                        foreach ($pegawai->pejabat->timKerja as $tim) {
                            foreach ($tim->anggota as $anggota) {
                                $pegawaiIds->push($anggota->id);
                            }
                        }
                    }

                    foreach ($pegawai->timKerjaKetua as $tim) {
                        foreach ($tim->anggota as $anggota) {
                            $pegawaiIds->push($anggota->id);
                        }
                    }

                    $pegawaiQuery->whereIn('id', $pegawaiIds->unique());
                } else {
                    $pegawaiQuery->where('username', $user->username);
                }
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
            ->groupBy(fn($item) => $item->user_id . '|' . Carbon::parse($item->checktime)->format('Y-m-d'));

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

                return collect($range)->map(fn($tgl) => [
                    'pegawai_id' => $cuti->pegawai_id,
                    'tanggal' => $tgl,
                ]);
            });

        $cutiByPegawai = $cutiData->groupBy('pegawai_id')->map(fn($items) => $items->pluck('tanggal')->toArray());

        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $tanggalHari, $liburIndex, $cutiByPegawai) {
            $presensi = [];
            $total = ['D' => 0, 'T' => 0, 'TM' => 0, 'C' => 0, 'DL' => 0];
            $jamMasukArray = [];
            $jamPulangArray = [];

            $cutiTanggal = $cutiByPegawai->get($pegawai->id, []);

            $tanggalHariSort = collect($tanggalHari)->sort();
            $tanggalAwal = $tanggalHariSort->first();
            $tanggalAkhir = $tanggalHariSort->last();

            $dinasLuarTanggal = SuratTugas::with(['detail', 'anggota'])
                ->where(function ($query) use ($tanggalAwal, $tanggalAkhir) {
                    $query->whereHas('detail', function ($q) use ($tanggalAwal, $tanggalAkhir) {
                        $q->whereDate('tanggal_mulai', '<=', $tanggalAkhir)
                            ->whereDate('tanggal_selesai', '>=', $tanggalAwal);
                    });
                })
                ->get()
                ->flatMap(function ($surat) use ($pegawai, $tanggalHari) {
                    $range = [];

                    if ($surat->detail && $surat->detail->pegawai_id == $pegawai->id) {
                        $start = Carbon::parse($surat->detail->tanggal_mulai);
                        $end = Carbon::parse($surat->detail->tanggal_selesai);
                        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                            $range[] = $date->format('Y-m-d');
                        }
                    }

                    foreach ($surat->anggota as $anggota) {
                        if ($anggota->pegawai_id == $pegawai->id && $surat->detail) {
                            $start = Carbon::parse($surat->detail->tanggal_mulai);
                            $end = Carbon::parse($surat->detail->tanggal_selesai);
                            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                                $range[] = $date->format('Y-m-d');
                            }
                        }
                    }

                    return $range;
                })
                ->unique()
                ->toArray();

            foreach ($tanggalHari as $idx => $tanggal) {
                if ($liburIndex[$idx]) {
                    $presensi[] = 'L';
                    $jamMasukArray[] = null;
                    $jamPulangArray[] = null;

                    continue;
                }

                if (in_array($tanggal, $dinasLuarTanggal)) {
                    $presensi[] = 'DL';
                    $total['DL']++;
                    $jamMasukArray[] = null;
                    $jamPulangArray[] = null;

                    continue;
                }

                if (in_array($tanggal, $cutiTanggal)) {
                    $presensi[] = 'C';
                    $total['C']++;
                    $jamMasukArray[] = null;
                    $jamPulangArray[] = null;

                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;
                $records = $kehadiran[$key] ?? collect();

                $checkIn = $records->where('checktype', 'I')->sortBy('checktime')->first();
                $checkOut = $records->where('checktype', 'O')->sortByDesc('checktime')->first();
                $jamMasukArray[] = $checkIn ? \Carbon\Carbon::parse($checkIn->checktime)->format('H:i') : null;
                $jamPulangArray[] = $checkOut ? \Carbon\Carbon::parse($checkOut->checktime)->format('H:i') : null;

                $hasI = !is_null($checkIn);
                $hasO = !is_null($checkOut);

                $userPegawai = User::where('username', $pegawai->username)->first();
                $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];

                $jenis = in_array('dosen', $pegawaiRoles) ? 'dosen' : 'pegawai';
                $minimalJamKerja = ($jenis === 'dosen') ? 4 : 8;

                $jamKerjaCustom = Jam::where('jenis', $jenis)
                    ->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal)
                    ->first();

                if ($jamKerjaCustom && !empty($jamKerjaCustom->jam_kerja)) {
                    $jamKerjaStr = strtolower(trim($jamKerjaCustom->jam_kerja));
                    $jamKerjaStr = preg_replace('/\s+/', ' ', $jamKerjaStr);
                    if (preg_match('/(\d+)\s*jam\s*(\d+)\s*menit/', $jamKerjaStr, $matches)) {
                        $minimalJamKerja = (int)$matches[1] + ((int)$matches[2] / 60);
                    }
                }

                $izinMasukDisetujui = Terlambat::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->where('jenis_ijin', 'Terlambat')
                    ->exists() ||
                    LupaAbsen::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->where('jenis_ijin', 'Lupa Absen Masuk')
                    ->exists();

                $izinPulangDisetujui = Terlambat::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->where('jenis_ijin', 'Pulang Cepat')
                    ->exists() ||
                    LupaAbsen::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->where('jenis_ijin', 'Lupa Absen Pulang')
                    ->exists();

                if ($hasI && $hasO) {
                    $start = Carbon::parse($checkIn->checktime);
                    $end = Carbon::parse($checkOut->checktime);
                    $durationInHours = $end->floatDiffInHours($start);

                    if ($durationInHours < $minimalJamKerja) {
                        if ($izinMasukDisetujui || $izinPulangDisetujui) {
                            $presensi[] = 'D';
                            $total['D']++;
                        } else {
                            $presensi[] = 'T';
                            $total['T']++;
                        }
                    } else {
                        $presensi[] = 'D';
                        $total['D']++;
                    }
                } elseif ($hasI || $hasO) {
                    if (($hasI && $izinPulangDisetujui) || ($hasO && $izinMasukDisetujui)) {
                        $presensi[] = 'D';
                        $total['D']++;
                    } else {
                        $presensi[] = 'T';
                        $total['T']++;
                    }
                } else {
                    $izinLupaAbsenMasuk = LupaAbsen::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->where('jenis_ijin', 'Lupa Absen Masuk')
                        ->exists();

                    $izinLupaAbsenPulang = LupaAbsen::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->where('jenis_ijin', 'Lupa Absen Pulang')
                        ->exists();

                    if ($izinLupaAbsenMasuk && $izinLupaAbsenPulang) {
                        $presensi[] = 'D';
                        $total['D']++;
                    } else {
                        $presensi[] = 'TM';
                        $total['TM']++;
                    }
                }
            }

            $filteredRoles = collect($pegawaiRoles)->intersect(['dosen', 'pegawai'])->values();

            return [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'presensi' => $presensi,
                'jam_masuk' => $jamMasukArray,
                'jam_pulang' => $jamPulangArray,
                'total' => $total,
                'keterangan' => $filteredRoles->isNotEmpty() ? implode(', ', $filteredRoles->toArray()) : '-',
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

        if (!in_array('admin', $roles) && !in_array('super', $roles) && !in_array('direktur', $roles)) {
            if (in_array('mahasiswa', $roles) && (now()->month != $month || now()->year != $year)) {
                $pegawaiQuery->whereNull('id'); // tidak tampilkan apa-apa
            } else {
                $pegawai = Pegawai::with('pejabat.timKerja.anggota', 'timKerjaKetua.anggota')
                    ->where('username', $user->username)
                    ->first();

                if ($pegawai) {
                    $pegawaiIds = collect([$pegawai->id]);

                    if ($pegawai->pejabat && $pegawai->pejabat->timKerja) {
                        foreach ($pegawai->pejabat->timKerja as $tim) {
                            foreach ($tim->anggota as $anggota) {
                                $pegawaiIds->push($anggota->id);
                            }
                        }
                    }

                    foreach ($pegawai->timKerjaKetua as $tim) {
                        foreach ($tim->anggota as $anggota) {
                            $pegawaiIds->push($anggota->id);
                        }
                    }

                    $pegawaiQuery->whereIn('id', $pegawaiIds->unique());
                } else {
                    $pegawaiQuery->where('username', $user->username);
                }
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

        $cutiByPegawai = $cutiData->groupBy('pegawai_id')->map(fn($items) => $items->pluck('tanggal')->toArray());

        $data = $pegawaiList->map(function ($pegawai) use ($kehadiran, $tanggalHari, $liburIndex, $cutiByPegawai) {
            $presensi = [];
            $total = ['D' => 0, 'T' => 0, 'TM' => 0, 'C' => 0, 'DL' => 0];
            $jamMasukArray = []; // Menambahkan array untuk jam masuk
            $jamPulangArray = []; // Menambahkan array untuk jam pulang
            $cutiTanggal = $cutiByPegawai->get($pegawai->id, []);

            $tanggalHariSort = collect($tanggalHari)->sort();
            $tanggalAwal  = $tanggalHariSort->first();
            $tanggalAkhir = $tanggalHariSort->last();

            $dinasLuarTanggal = SuratTugas::with(['detail', 'anggota'])
                ->where(function ($query) use ($tanggalAwal, $tanggalAkhir) {
                    $query->whereHas('detail', function ($q) use ($tanggalAwal, $tanggalAkhir) {
                        $q->whereDate('tanggal_mulai', '<=', $tanggalAkhir)
                            ->whereDate('tanggal_selesai', '>=', $tanggalAwal);
                    });
                })
                ->get()
                ->flatMap(function ($surat) use ($pegawai, $tanggalHari) {
                    $range = [];

                    // Cek apakah pegawai adalah penanggung jawab (detail)
                    if ($surat->detail && $surat->detail->pegawai_id == $pegawai->id) {
                        $start = Carbon::parse($surat->detail->tanggal_mulai);
                        $end = Carbon::parse($surat->detail->tanggal_selesai);
                        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                            $range[] = $date->format('Y-m-d');
                        }
                    }

                    // Cek apakah pegawai adalah anggota
                    foreach ($surat->anggota as $anggota) {
                        if ($anggota->pegawai_id == $pegawai->id && $surat->detail) {
                            $start = Carbon::parse($surat->detail->tanggal_mulai);
                            $end = Carbon::parse($surat->detail->tanggal_selesai);
                            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                                $range[] = $date->format('Y-m-d');
                            }
                        }
                    }

                    return $range;
                })
                ->unique()
                ->toArray();

            foreach ($tanggalHari as $idx => $tanggal) {
                if ($liburIndex[$idx]) {
                    $presensi[] = 'L';
                    $jamMasukArray[] = null; // Tambahkan null untuk hari libur
                    $jamPulangArray[] = null; // Tambahkan null untuk hari libur
                    continue;
                }

                // ⬅️ Cek DL lebih dulu dari cuti dan kehadiran lainnya
                if (in_array($tanggal, $dinasLuarTanggal)) {
                    $presensi[] = 'DL';
                    $total['DL']++;
                    $jamMasukArray[] = null; // Tambahkan null untuk dinas luar
                    $jamPulangArray[] = null; // Tambahkan null untuk dinas luar
                    continue;
                }

                if (in_array($tanggal, $cutiTanggal)) {
                    $presensi[] = 'C';
                    $total['C']++;
                    $jamMasukArray[] = null; // Tambahkan null untuk cuti
                    $jamPulangArray[] = null; // Tambahkan null untuk cuti
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;
                $records = $kehadiran[$key] ?? collect();

                $checkIn = $records->where('checktype', 'I')->sortBy('checktime')->first();
                $checkOut = $records->where('checktype', 'O')->sortByDesc('checktime')->first();

                // Tambahkan jam masuk dan jam pulang ke array
                $jamMasukArray[] = $checkIn ? \Carbon\Carbon::parse($checkIn->checktime)->format('H:i') : null;
                $jamPulangArray[] = $checkOut ? \Carbon\Carbon::parse($checkOut->checktime)->format('H:i') : null;

                $hasI = !is_null($checkIn);
                $hasO = !is_null($checkOut);

                $userPegawai = User::where('username', $pegawai->username)->first();
                $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];

                $jenis = in_array('dosen', $pegawaiRoles) ? 'dosen' : 'pegawai';
                $minimalJamKerja = ($jenis === 'dosen') ? 4 : 8;

                $jamKerjaCustom = Jam::where('jenis', $jenis)
                    ->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal)
                    ->first();

                if ($jamKerjaCustom && !empty($jamKerjaCustom->jam_kerja)) {
                    $jamKerjaStr = strtolower(trim($jamKerjaCustom->jam_kerja));
                    $jamKerjaStr = preg_replace('/\s+/', ' ', $jamKerjaStr);
                    if (preg_match('/(\d+)\s*jam\s*(\d+)\s*menit/', $jamKerjaStr, $matches)) {
                        $minimalJamKerja = (int)$matches[1] + ((int)$matches[2] / 60);
                    }
                }

                $izinMasukDisetujui = Terlambat::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->where('jenis_ijin', 'Terlambat')
                    ->exists() ||
                    LupaAbsen::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->where('jenis_ijin', 'Lupa Absen Masuk')
                    ->exists();

                $izinPulangDisetujui = Terlambat::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->where('jenis_ijin', 'Pulang Cepat')
                    ->exists() ||
                    LupaAbsen::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->where('jenis_ijin', 'Lupa Absen Pulang')
                    ->exists();

                if ($hasI && $hasO) {
                    $start = Carbon::parse($checkIn->checktime);
                    $end = Carbon::parse($checkOut->checktime);
                    $durationInHours = $end->floatDiffInHours($start);

                    if ($durationInHours < $minimalJamKerja) {
                        if ($izinMasukDisetujui || $izinPulangDisetujui) {
                            $presensi[] = 'D';
                            $total['D']++;
                        } else {
                            $presensi[] = 'T';
                            $total['T']++;
                        }
                    } else {
                        $presensi[] = 'D';
                        $total['D']++;
                    }
                } elseif ($hasI || $hasO) {
                    if (($hasI && $izinPulangDisetujui) || ($hasO && $izinMasukDisetujui)) {
                        $presensi[] = 'D';
                        $total['D']++;
                    } else {
                        $presensi[] = 'T';
                        $total['T']++;
                    }
                } else {
                    // Tambahan logika: jika tidak absen sama sekali, cek apakah ada izin lengkap
                    $izinLupaAbsenMasuk = LupaAbsen::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->where('jenis_ijin', 'Lupa Absen Masuk')
                        ->exists();

                    $izinLupaAbsenPulang = LupaAbsen::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->where('jenis_ijin', 'Lupa Absen Pulang')
                        ->exists();

                    if ($izinLupaAbsenMasuk && $izinLupaAbsenPulang) {
                        $presensi[] = 'D';
                        $total['D']++;
                    } else {
                        $presensi[] = 'TM';
                        $total['TM']++;
                    }
                }
            }

            // Tambahkan keterangan seperti di function index
            $filteredRoles = collect($pegawaiRoles)->intersect(['dosen', 'pegawai'])->values();

            return [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'presensi' => $presensi,
                'jam_masuk' => $jamMasukArray, // Tambahkan jam masuk
                'jam_pulang' => $jamPulangArray, // Tambahkan jam pulang
                'total' => $total,
                'keterangan' => $filteredRoles->isNotEmpty() ? implode(', ', $filteredRoles->toArray()) : '-', // Tambahkan keterangan
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
        $format = $request->input('format', 'excel');

        $rekap = $this->getRekapData($month, $year);

        if ($format === 'pdf') {
            $monthName = \DateTime::createFromFormat('!m', $month)->format('F');
            $title = "Rekap Kehadiran $monthName $year";

            $pdf = \PDF::loadView('rekapkehadiran::pdf.rekapbulanan', [
                'data' => $rekap['data'],
                'tanggalHari' => $rekap['tanggalHari'],
                'month' => $month,
                'year' => $year,
                'title' => $title
            ])
                ->setPaper('a4', 'landscape')
                ->setOption('dpi', 96)
                ->setOption('margin-top', 5)
                ->setOption('margin-right', 5)
                ->setOption('margin-bottom', 5)
                ->setOption('margin-left', 5);

            return $pdf->download('rekap_kehadiran_' . $month . '_' . $year . '.pdf');
        }

        // Default to Excel export
        return Excel::download(
            new RekapKehadiranIIExport($rekap['data'], $rekap['tanggalHari'], $month, $year),
            'rekap_kehadiran_' . $month . '_' . $year . '.xlsx'
        );
    }
}
