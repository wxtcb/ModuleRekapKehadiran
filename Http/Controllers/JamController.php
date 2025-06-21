<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\RekapKehadiran\Entities\Jam;

class JamController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $jamKerjas = Jam::all();
        return view('rekapkehadiran::jam.index', compact('jamKerjas'));
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
        $request->validate([
            'nama' => 'required|string|max:255',
            'tanggal_mulai' => 'required',
            'tanggal_selesai' => 'required',
            'jenis' => 'required|in:pegawai,dosen',
            'jam_masuk' => 'nullable|required_if:jenis,pegawai|date_format:H:i',
            'jam_pulang' => 'nullable|required_if:jenis,pegawai|date_format:H:i',
            'jam_kerja' => 'nullable|required_if:jenis,dosen|string'
        ]);

        $jamKerja = new Jam();
        $jamKerja->nama = $request->nama;
        $jamKerja->tanggal_mulai = $request->tanggal_mulai;
        $jamKerja->tanggal_selesai = $request->tanggal_selesai;
        $jamKerja->jenis = $request->jenis;
        $jamKerja->jam_masuk = $request->jam_masuk;
        $jamKerja->jam_pulang = $request->jam_pulang;

        if ($request->jenis === 'pegawai') {
            $start = strtotime($request->jam_masuk);
            $end = strtotime($request->jam_pulang);
            $diff = $end - $start;
            $jam = floor($diff / 3600);
            $menit = floor(($diff % 3600) / 60);
            $jamKerja->jam_kerja = "$jam jam $menit menit";
        } else {
            $jamKerja->jam_kerja = $request->jam_kerja;
        }

        $jamKerja->save();

        return redirect()->back()->with('success', 'Jam kerja berhasil disimpan.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('setting::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('setting::edit');
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
