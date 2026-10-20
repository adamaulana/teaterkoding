<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Keluar dari sesi siswa (guard `siswa`) tanpa menyentuh sesi guru.
 */
class SiswaLogoutController extends Controller
{
    /**
     * Hapus sesi siswa lalu kembali ke halaman masuk.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('siswa')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('siswa.masuk');
    }
}
