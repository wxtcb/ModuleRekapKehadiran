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
    $roles = $user->getRoleNames()->toArray();

    $pegawaiList = Pegawai::query();

    if (!in_array('admin', $roles) && !in_array('super', $roles)) {
        if (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
            $pegawaiList->where('username', $user->username);
        } else {
            $pegawaiList->whereNull('id');
        }
    }

    $pegawaiList = $pegawaiList->select('id', 'nama', 'nip', 'username')->get();
    $pegawaiIDs = $pegawaiList->pluck('id')->toArray();

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
        $total = ['D' => 0, 'TM' => 0, 'C' => 0, 'T' => 0, 'DL' => 0];

        foreach ($hariKerja as $tanggal) {
            if (in_array($tanggal, $cutiByPegawai[$pegawai->id] ?? [])) {
                $total['C']++;
                continue;
            }

            $key = $pegawai->id . '|' . $tanggal;

            if ($kehadiran->has($key)) {
                $absenHariItu = $kehadiran->get($key);

                $datang = $absenHariItu->where('checktype', 'I')->sortBy('checktime')->first();
                $pulang = $absenHariItu->where('checktype', 'O')->sortBy('checktime')->last();

                $hasI = $datang !== null;
                $hasO = $pulang !== null;

                if ($hasI && $hasO) {
                    $jamKerjaDetik = strtotime($pulang->checktime) - strtotime($datang->checktime);
                    $jamKerjaJam = $jamKerjaDetik / 3600;

                    // Ambil role pegawai dari user yang sesuai dengan username pegawai
                    $userPegawai = User::where('username', $pegawai->username)->first();
                    $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];

                    $jenis = in_array('dosen', $pegawaiRoles) ? 'dosen' : 'pegawai';

                    $jamKerjaCustom = Jam::where('jenis', $jenis)
                        ->whereDate('tanggal_mulai', '<=', $tanggal)
                        ->whereDate('tanggal_selesai', '>=', $tanggal)
                        ->first();

                    $minimalJam = ($jenis === 'dosen') ? 4 : 8;

                    if ($jamKerjaCustom && !empty($jamKerjaCustom->jam_kerja)) {
                        $jamKerjaStr = strtolower(trim($jamKerjaCustom->jam_kerja));
                        $jamKerjaStr = preg_replace('/\s+/', ' ', $jamKerjaStr);

                        $pattern = '/(\d+)\s*jam\s*(\d+)\s*menit/';
                        if (preg_match($pattern, $jamKerjaStr, $matches)) {
                            $jamMinimal = (int)$matches[1];
                            $menitMinimal = (int)$matches[2];
                            $minimalJam = $jamMinimal + ($menitMinimal / 60);
                        } else {
                            Log::warning("Format jam_kerja tidak sesuai: " . $jamKerjaCustom->jam_kerja);
                        }
                    }

                    if ($jamKerjaJam >= $minimalJam) {
                        $total['D']++;
                    } else {
                        $total['T']++;
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
        $year = $request->input('year', now()->year);
        $data = $this->getRekapData($request, $year); // sudah array, aman

        return Excel::download(new RekapKehadiranIIIExport($data, $year), 'rekap_kehadiran_' . $year . '.xlsx');
    }

}
