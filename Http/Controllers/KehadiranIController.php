<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\RekapKehadiran\Entities\KehadiranI;

class KehadiranIController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $kehadiran = KehadiranI::all();
        $pegawai = Pegawai::all()->keyBy('user_id');
        
        // Kelompokkan berdasarkan user_id dan tanggal
        $rekap = $kehadiran->groupBy(function($item) {
            return $item->user_id . '|' . date('Y-m-d', strtotime($item->checktime));
        })->map(function ($items, $key) use ($pegawai) {
            list($user_id, $tanggal) = explode('|', $key);
        
            // Ambil waktu datang (checktype = i)
            $datangItem = $items->where('checktype', 'I')->sortBy('checktime')->first();
            $waktuDatang = $datangItem ? explode(' ', $datangItem->checktime)[1] : '-';
            // dd($datangItem);
        
            // Ambil waktu pulang (checktype = o)
            $pulangItem = $items->where('checktype', 'O')->sortBy('checktime')->last();
            $waktuPulang = $pulangItem ? explode(' ', $pulangItem->checktime)[1] : '-';
        
            $pegawaiData = $pegawai[$user_id] ?? null;
        
            return [
                'nama' => $pegawaiData->nama ?? 'Tidak Diketahui',
                'nip' => $pegawaiData->nip ?? '-',
                'tanggal' => $tanggal,
                'waktu_datang' => $waktuDatang,
                'waktu_pulang' => $waktuPulang,
            ];
        })->values();
        
        return view('rekapkehadiran::kehadirani.index', ['rekapPresensi' => $rekap]);
        
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
