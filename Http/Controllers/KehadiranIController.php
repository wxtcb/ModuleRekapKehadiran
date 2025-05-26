<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use App\Models\Core\User;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Cuti\Entities\Cuti;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranI;
use Modules\RekapKehadiran\Exports\RekapKehadiranIExport;
use Modules\Setting\Entities\Jam;
use Modules\Setting\Entities\Libur;
use Modules\SuratIjin\Entities\LupaAbsen;
use Modules\SuratIjin\Entities\Terlambat;
use Modules\SuratTugas\Entities\SuratTugas;

class KehadiranIController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $tanggal = $request->input('tanggal', date("Y-m-d"));
        $namaQuery = $request->input('nama');

        $user = Auth::user();
        $isToday = $tanggal === date('Y-m-d');
        $roles = $user->getRoleNames()->toArray();

        $isWeekend = Carbon::parse($tanggal)->isWeekend();
        $isLibur = Libur::whereDate('tanggal', $tanggal)->exists();
        $statusLibur = ($isWeekend || $isLibur);

        $pegawaiQuery = Pegawai::query();

        if (!in_array('super', $roles) && !in_array('admin', $roles)) {
            if ($isToday) {
                // Bisa lihat semua pegawai
            } elseif (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                $pegawaiQuery->where('username', $user->username);
            } else {
                $pegawaiQuery->whereNull('id');
            }
        }

        $pegawaiList = $pegawaiQuery->select('id', 'nama', 'nip', 'username')->get();

        if ($namaQuery) {
            $pegawaiList = $pegawaiList->filter(function ($pegawai) use ($namaQuery) {
                return stripos($pegawai->nama, $namaQuery) !== false;
            });
        }

        $kehadiranQuery = KehadiranI::on('second_db')->whereDate('checktime', $tanggal);

        if (!in_array('super', $roles) && !in_array('admin', $roles)) {
            if (!$isToday && (in_array('pegawai', $roles) || in_array('dosen', $roles))) {
                $pegawai = Pegawai::where('username', $user->username)->first();
                if ($pegawai) {
                    $kehadiranQuery->where('user_id', $pegawai->id);
                } else {
                    $kehadiranQuery->whereNull('user_id');
                }
            }
        }

        $kehadiranList = $kehadiranQuery->get();
        $presensiByUser = $kehadiranList->groupBy('user_id');

        $rekapPresensi = $pegawaiList->map(function ($pegawai) use ($presensiByUser, $tanggal, $statusLibur) {
            $userPresensi = $presensiByUser->get($pegawai->id, collect());

            $datang = $userPresensi->where('checktype', 'I')->sortBy('checktime')->first();
            $pulang = $userPresensi->where('checktype', 'O')->sortBy('checktime')->last();

            $waktuDatang = $datang ? date('H:i', strtotime($datang->checktime)) : '-';
            $waktuPulang = $pulang ? date('H:i', strtotime($pulang->checktime)) : '-';

            $isCuti = Cuti::where('pegawai_id', $pegawai->id)
                ->where('status', 'Selesai')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->exists();

            // ✅ Tambahan: Cek apakah pegawai sedang Dinas Luar
            $isDinasLuar = SuratTugas::where(function ($query) use ($pegawai, $tanggal) {
                // Cek jika pegawai ada di detail surat tugas
                $query->whereHas('detail', function ($q) use ($pegawai, $tanggal) {
                    $q->where('pegawai_id', $pegawai->id)
                    ->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal);
                })
                // Atau pegawai ada di anggota surat tugas
                ->orWhereHas('anggota', function ($q) use ($pegawai, $tanggal) {
                    $q->where('pegawai_id', $pegawai->id)
                    ->whereHas('suratTugas.detail', function ($qd) use ($tanggal) {
                        $qd->whereDate('tanggal_mulai', '<=', $tanggal)
                            ->whereDate('tanggal_selesai', '>=', $tanggal);
                    });
                });
            })->exists();

            $durasi_jam = '-';
            $kurang_dari_jam_kerja = false;

            $userPegawai = User::where('username', $pegawai->username)->first();
            $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];

            if ($datang && $pulang) {
                $start = strtotime($datang->checktime);
                $end = strtotime($pulang->checktime);
                $diff = $end - $start;
                $jam = floor($diff / 3600);
                $menit = floor(($diff % 3600) / 60);
                $durasi_jam = "{$jam} jam {$menit} menit";

                $minimalJamKerja = 8;
                if (in_array('dosen', $pegawaiRoles)) {
                    $minimalJamKerja = 4;
                }

                $jenis = in_array('dosen', $pegawaiRoles) ? 'dosen' : 'pegawai';

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

                if ($jam + ($menit / 60) < $minimalJamKerja) {
                    $kurang_dari_jam_kerja = true;
                }
            }

            $izinMasukDisetujui =
                Terlambat::where('pegawai_id', $pegawai->id)
                ->where('status', 'Disetujui')
                ->whereDate('tanggal', $tanggal)
                ->whereIn('jenis_ijin', ['Terlambat'])
                ->exists() ||

                LupaAbsen::where('pegawai_id', $pegawai->id)
                ->where('status', 'Disetujui')
                ->whereDate('tanggal', $tanggal)
                ->whereIn('jenis_ijin', ['Lupa Absen Masuk'])
                ->exists();

            $izinPulangDisetujui =
                Terlambat::where('pegawai_id', $pegawai->id)
                ->where('status', 'Disetujui')
                ->whereDate('tanggal', $tanggal)
                ->whereIn('jenis_ijin', ['Pulang Cepat'])
                ->exists() ||

                LupaAbsen::where('pegawai_id', $pegawai->id)
                ->where('status', 'Disetujui')
                ->whereDate('tanggal', $tanggal)
                ->whereIn('jenis_ijin', ['Lupa Absen Pulang'])
                ->exists();

            // ✅ Penentuan Status dengan "Dinas Luar"
            if ($statusLibur) {
                $status = 'Libur';
            } elseif ($isCuti) {
                $status = 'Cuti';
            } elseif ($isDinasLuar) {
                $status = 'Dinas Luar';
            } elseif (!$datang && !$pulang && !$izinMasukDisetujui && !$izinPulangDisetujui) {
                $status = 'Alpha';
            } elseif (!$datang && !$izinMasukDisetujui) {
                $status = 'Hadir (Lupa presensi datang)';
            } elseif (!$pulang && !$izinPulangDisetujui) {
                $status = 'Hadir (Lupa presensi pulang)';
            } elseif ($kurang_dari_jam_kerja) {
                if (!$izinMasukDisetujui || !$izinPulangDisetujui) {
                    $status = 'Hadir';
                } else {
                    $status = 'Hadir (Tidak Mendapat Tunjangan)';
                }
            } else {
                $status = 'Hadir';
            }

            return (object)[
                'nama' => $pegawai->nama,
                'nip' => $pegawai->nip,
                'tanggal' => $tanggal,
                'waktu_datang' => $waktuDatang,
                'waktu_pulang' => $waktuPulang,
                'status' => $status,
                'durasi_jam' => $durasi_jam,
            ];
        });

        $isAdmin = in_array('admin', $roles) || in_array('super', $roles);
        $pegawaiId = null;

        if (!$isAdmin) {
            $pegawaiId = Pegawai::where('username', $user->username)->value('id');
        }

        return view('rekapkehadiran::kehadirani.index', compact('rekapPresensi', 'isAdmin', 'pegawaiId', 'tanggal'));
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

    public function export(Request $request)
    {
        $pegawaiId = $request->input('pegawai_id');
        $month = $request->input('month');
        $year = $request->input('year');

        return Excel::download(new RekapKehadiranIExport($pegawaiId, $month, $year), 'rekap-kehadiran.xlsx');
    }
}
