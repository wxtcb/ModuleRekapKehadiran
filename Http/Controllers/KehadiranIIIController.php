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
use Modules\RekapKehadiran\Entities\KehadiranIII;
use Modules\RekapKehadiran\Exports\RekapKehadiranIIIExport;
use Modules\Setting\Entities\Jam;
use Modules\Setting\Entities\Libur;
use Modules\SuratIjin\Entities\LupaAbsen;
use Modules\SuratIjin\Entities\Terlambat;
use Modules\SuratTugas\Entities\SuratTugas;

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
        $roles = $user->getRoleNames()->toArray(); // ✅ dijamin array

        $pegawaiList = Pegawai::query();

        if (!in_array('admin', $roles) && !in_array('super', $roles) && !in_array('direktur', $roles)) {
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

                $pegawaiList->whereIn('id', $pegawaiIds->unique());
            } else {
                $pegawaiList->where('username', $user->username);
            }
        }
        $pegawaiList = $pegawaiList->select('id', 'nama', 'nip', 'username')->get();
        $pegawaiIDs = $pegawaiList->pluck('id')->toArray();

        $kehadiran = KehadiranIII::query()
            ->whereYear('checktime', $year)
            ->when($user->role_aktif !== 'admin', function ($query) use ($user) {
                if ($user->role_aktif === 'pegawai' || $user->role_aktif === 'dosen') {
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

        $cuti = Cuti::where('status', 'Selesai')
            ->whereIn('pegawai_id', $pegawaiIDs)
            ->whereYear('tanggal_mulai', '<=', $year)
            ->get();

        $cutiByPegawai = [];
        foreach ($cuti as $item) {
            $start = \Carbon\Carbon::parse($item->tanggal_mulai);
            $end = \Carbon\Carbon::parse($item->tanggal_selesai);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($date->year == $year) {
                    $cutiByPegawai[$item->pegawai_id][] = $date->format('Y-m-d');
                }
            }
        }

        // DL (Dinas Luar)
        $suratTugas = SuratTugas::with(['detail', 'anggota'])
            ->whereHas('detail', function ($q) use ($year) {
                $q->whereYear('tanggal_mulai', '<=', $year)->orWhereYear('tanggal_selesai', '<=', $year);
            })->get();

        $dinasLuarByPegawai = [];
        foreach ($suratTugas as $surat) {
            if (!$surat->detail) continue;
            $start = \Carbon\Carbon::parse($surat->detail->tanggal_mulai);
            $end = \Carbon\Carbon::parse($surat->detail->tanggal_selesai);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $tanggal = $date->format('Y-m-d');
                if ($date->year != $year) continue;

                $penanggungJawabID = $surat->detail->pegawai_id;
                $dinasLuarByPegawai[$penanggungJawabID][] = $tanggal;

                foreach ($surat->anggota as $anggota) {
                    $dinasLuarByPegawai[$anggota->pegawai_id][] = $tanggal;
                }
            }
        }

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

        return $pegawaiList->map(function ($pegawai) use ($kehadiran, $hariKerja, $cutiByPegawai, $dinasLuarByPegawai) {
            $total = ['D' => 0, 'TM' => 0, 'C' => 0, 'T' => 0, 'DL' => 0];

            $userPegawai = User::where('username', $pegawai->username)->first();
            $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];

            $filteredRoles = collect($pegawaiRoles)->intersect(['dosen', 'pegawai'])->values();
            $jenis = $filteredRoles->first() ?? 'pegawai';

            foreach ($hariKerja as $tanggal) {
                if (in_array($tanggal, $dinasLuarByPegawai[$pegawai->id] ?? [])) {
                    $total['DL']++;
                    continue;
                }

                if (in_array($tanggal, $cutiByPegawai[$pegawai->id] ?? [])) {
                    $total['C']++;
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;

                if ($kehadiran->has($key)) {
                    $absenHariItu = $kehadiran->get($key);
                    $datang = $absenHariItu->where('checktype', 'I')->sortBy('checktime')->first();
                    $pulang = $absenHariItu->where('checktype', 'O')->sortByDesc('checktime')->first();

                    $hasI = $datang !== null;
                    $hasO = $pulang !== null;

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

                    $userPegawai = User::where('username', $pegawai->username)->first();
                    $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];

                    $jenis = in_array('dosen', $pegawaiRoles) ? 'dosen' : 'pegawai';
                    $minimalJam = ($jenis === 'dosen') ? 4 : 8;

                    $jamKerjaCustom = Jam::where('jenis', $jenis)
                        ->whereDate('tanggal_mulai', '<=', $tanggal)
                        ->whereDate('tanggal_selesai', '>=', $tanggal)
                        ->first();

                    if ($jamKerjaCustom && !empty($jamKerjaCustom->jam_kerja)) {
                        $jamKerjaStr = strtolower(trim($jamKerjaCustom->jam_kerja));
                        $jamKerjaStr = preg_replace('/\s+/', ' ', $jamKerjaStr);
                        if (preg_match('/(\d+)\s*jam\s*(\d+)\s*menit/', $jamKerjaStr, $matches)) {
                            $minimalJam = (int)$matches[1] + ((int)$matches[2] / 60);
                        }
                    }

                    if ($hasI && $hasO) {
                        $jamKerjaJam = strtotime($pulang->checktime) - strtotime($datang->checktime);
                        $jamKerjaJam /= 3600;

                        if ($jamKerjaJam >= $minimalJam || $izinMasukDisetujui || $izinPulangDisetujui) {
                            $total['D']++;
                        } else {
                            $total['T']++;
                        }
                    } elseif ($hasI || $hasO) {
                        if (($hasI && $izinPulangDisetujui) || ($hasO && $izinMasukDisetujui)) {
                            $total['D']++;
                        } else {
                            $total['T']++;
                        }
                    } else {
                        $izinLupaMasuk = LupaAbsen::where('pegawai_id', $pegawai->id)
                            ->where('status', 'Disetujui')
                            ->whereDate('tanggal', $tanggal)
                            ->where('jenis_ijin', 'Lupa Absen Masuk')
                            ->exists();

                        $izinLupaPulang = LupaAbsen::where('pegawai_id', $pegawai->id)
                            ->where('status', 'Disetujui')
                            ->whereDate('tanggal', $tanggal)
                            ->where('jenis_ijin', 'Lupa Absen Pulang')
                            ->exists();

                        if ($izinLupaMasuk && $izinLupaPulang) {
                            $total['D']++;
                        } else {
                            $total['TM']++;
                        }
                    }
                } else {
                    $total['TM']++;
                }
            }

            return [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'jenis' => $jenis,
                'total' => $total
            ];
        })->toArray();
    }

    public function export(Request $request)
    {
        $year = $request->input('year', now()->year);
        $data = $this->getRekapData($request, $year); // sudah array, aman

        return Excel::download(new RekapKehadiranIIIExport($data, $year), 'rekap_kehadiran_' . $year . '.xlsx');
    }
}
