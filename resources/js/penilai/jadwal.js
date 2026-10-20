/**
 * Penjadwal "santai" untuk pekerjaan latar belakang (Fase 6).
 *
 * === KENAPA DIPISAH DARI index.js ===
 * Modul ini murni (tidak menyentuh DOM kecuali lewat `window` yang diinjeksi),
 * sehingga bisa diuji di Node tanpa browser — lihat `npm run uji:jadwal`.
 *
 * === BUG YANG DICEGAH ===
 * Pola lama di index.js:
 *
 *     (window.requestIdleCallback ?? window.setTimeout)(mulai, 2000)
 *
 * Terlihat ringkas, tetapi SALAH: kedua fungsi menerima parameter kedua yang
 * berbeda jenis.
 *
 *   requestIdleCallback(callback, options?)  → options berupa OBJEK
 *                                              ({ timeout: 2000 })
 *   setTimeout(callback, delay?)             → delay berupa ANGKA (ms)
 *
 * Saat browser punya `requestIdleCallback`, memanggilnya dengan angka memicu:
 *
 *   TypeError: Failed to execute 'requestIdleCallback' on 'Window':
 *              The provided value is not of type 'IdleRequestOptions'
 *
 * Akibatnya pemuatan awal Pyodide gagal total dan penilaian tidak berjalan.
 */

/** Waktu tunggu bawaan sebelum mulai memuat runtime (ms). */
export const JEDA_PRAMAAT = 2000;

/**
 * Jalankan `fn` saat browser menganggur, dengan batas tunggu `ms`.
 *
 * Selalu berhasil menjadwalkan: bila `requestIdleCallback` tidak ada ATAU
 * melempar galat (mis. diblokir kebijakan privasi), jatuh ke `setTimeout`.
 *
 * @param {() => void} fn
 * @param {number} [ms]
 * @param {{ requestIdleCallback?: Function, setTimeout?: Function }} [target]
 *        lingkungan yang dipakai (default: `window`); bisa diinjeksi untuk uji
 * @returns {'idle'|'timeout'} jalur yang akhirnya dipakai
 */
export function jadwalkanSantai(fn, ms = JEDA_PRAMAAT, target = globalThis.window ?? globalThis) {
    if (typeof fn !== 'function') {
        return 'timeout';
    }

    // Jalur utama: idle callback, dengan OBJEK opsi (bukan angka).
    try {
        if (typeof target?.requestIdleCallback === 'function') {
            target.requestIdleCallback(fn, { timeout: ms });

            return 'idle';
        }
    } catch {
        // Browser punya API-nya tetapi menolak; lanjut ke setTimeout.
    }

    // Jalur cadangan: setTimeout menerima ANGKA.
    if (typeof target?.setTimeout === 'function') {
        target.setTimeout(fn, ms);

        return 'timeout';
    }

    // Lingkungan tanpa keduanya: jalankan langsung agar pekerjaan tidak hilang.
    fn();

    return 'timeout';
}
