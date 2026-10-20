<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga rute /siswa/*.
 *
 * - Siswa yang sudah login (guard `siswa`) → diteruskan.
 * - Guru/admin yang sedang login (guard `web`) → 403, karena guard siswa dan
 *   web sengaja TIDAK saling memakai sesi (isolasi antar guard).
 * - Tamu → diarahkan ke halaman masuk siswa (bukan login guru).
 */
class AutentikasiSiswa
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('siswa')->check()) {
            return $next($request);
        }

        if (Auth::guard('web')->check()) {
            abort(403, 'Sesi guru tidak bisa dipakai untuk membuka halaman siswa.');
        }

        return redirect()->route('siswa.masuk');
    }
}
