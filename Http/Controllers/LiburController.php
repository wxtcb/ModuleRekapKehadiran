<?php

namespace Modules\RekapKehadiran\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\RekapKehadiran\Entities\Libur;

class LiburController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $year = $request->input('year', now()->year);
    
        $hariLibur = Libur::whereYear('tanggal', $year)
            ->orderBy('tanggal', 'desc')
            ->get();
    
        // Ambil semua tahun yang ada di tabel libur
        $tahunTersedia = Libur::selectRaw('YEAR(tanggal) as tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun');
    
        return view('rekapkehadiran::libur.index', compact('hariLibur', 'tahunTersedia', 'year'));
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
            'tanggal.*' => 'required|date|distinct',
            'keterangan.*' => 'required|string|max:255',
        ]);

        $tanggal = $request->input('tanggal');
        $keterangan = $request->input('keterangan');

        $data = [];
        foreach ($tanggal as $index => $tgl) {
            // Skip jika tanggal sudah ada di database
            if (Libur::whereDate('tanggal', $tgl)->exists()) continue;

            $data[] = [
                'tanggal' => $tgl,
                'keterangan' => $keterangan[$index],
            ];
        }

        Libur::insert($data);

        return redirect()->back()->with('success', 'Hari libur berhasil ditambahkan.');
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
