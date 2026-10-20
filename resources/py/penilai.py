"""
Penilai kode siswa — DIJALANKAN DI DALAM PYODIDE, DI BROWSER SISWA.

Berkas ini disalin ke public/vendor/teater/penilai.py oleh
scripts/vendor-pyodide.mjs, lalu dimuat Web Worker sebagai teks dan
dieksekusi sekali di namespace Pyodide. Worker memanggil `nilai_paket(json)`.

=== BATAS KEAMANAN (BACA INI) ===
Pemeriksaan AST di bawah adalah LAPISAN INTEGRITAS, BUKAN BATAS KEAMANAN.
Kode siswa berjalan di perangkat siswa sendiri, jadi siswa yang niat bisa
memodifikasi JS/Python di browsernya. Yang benar-benar melindungi penilaian:
  1. expected_value dan tolerance TIDAK PERNAH dikirim ke browser.
  2. Server MENGHITUNG SENDIRI lulus/gagal dari nilai mentah yang dilaporkan.
  3. Level sumatif wajib diverifikasi ulang oleh guru.
Tujuan pemeriksaan AST: mencegah kesalahan tak sengaja (mis. `import os`)
dan menjaga pengalaman belajar tetap di dalam materi.

=== KONTRAK INPUT (JSON) ===
{"kode": "...", "setup_code": "...", "target": [{"id": 12, "check_target": "status"}],
 "modul_diizinkan": ["math"], "maks_keluaran": 2000}
TIDAK ADA expected_value / tolerance di sini.

=== KONTRAK OUTPUT (JSON) ===
{"hasil": [{"id": 12, "tipe": "int|float|str|bool|none|lain|belum_dibuat",
            "nilai": <skalar atau repr terpotong>}],
 "keluaran": "<stdout kode siswa, dipotong>",
 "galat": null | {"jenis": "SyntaxError", "pesan": "...", "baris": 3}}
"""

import ast
import io
import json
import math
import sys
import traceback

# Nama berkas semu agar nomor baris galat menunjuk ke kode SISWA.
NAMA_FILE_SISWA = "<kode_siswa>"

# Modul yang tidak boleh diimpor walau ada di daftar izin (pertahanan berlapis).
MODUL_SELALU_TERLARANG = frozenset({
    "js", "pyodide", "pyodide_js", "micropip", "os", "sys", "subprocess",
    "ctypes", "importlib", "socket", "shutil", "pathlib", "builtins", "gc",
    "inspect", "threading", "multiprocessing", "pickle", "marshal", "shelve",
    "tempfile", "urllib", "http", "requests",
})

# Nama bawaan berbahaya yang tidak boleh dipakai sama sekali.
NAMA_TERLARANG = frozenset({
    "__import__", "eval", "exec", "compile", "open", "input", "globals",
    "locals", "vars", "getattr", "setattr", "delattr", "breakpoint", "exit",
    "quit", "help", "memoryview",
})


class PerintahTerlarang(Exception):
    """Dilempar saat AST memuat perintah yang tidak diizinkan."""


def _periksa_ast(pohon, modul_diizinkan):
    """
    Periksa AST kode siswa. LAPISAN INTEGRITAS, bukan batas keamanan.

    Menolak: import di luar daftar izin, modul berbahaya, nama bawaan
    berbahaya, dan atribut berawalan "__".
    """
    diizinkan = set(modul_diizinkan or [])

    for node in ast.walk(pohon):
        if isinstance(node, ast.Import):
            for alias in node.names:
                akar = alias.name.split(".")[0]
                if akar in MODUL_SELALU_TERLARANG or akar not in diizinkan:
                    raise PerintahTerlarang(
                        f"Kode memakai perintah yang tidak diizinkan: import {akar}"
                    )

        elif isinstance(node, ast.ImportFrom):
            if node.level and node.level > 0:
                raise PerintahTerlarang(
                    "Kode memakai perintah yang tidak diizinkan: import relatif"
                )

            akar = (node.module or "").split(".")[0]

            if akar in MODUL_SELALU_TERLARANG:
                raise PerintahTerlarang(
                    f"Kode memakai perintah yang tidak diizinkan: import {akar}"
                )
            if akar and akar not in diizinkan:
                raise PerintahTerlarang(
                    f"Kode memakai perintah yang tidak diizinkan: import {akar}"
                )

        elif isinstance(node, ast.Name):
            if node.id in NAMA_TERLARANG:
                raise PerintahTerlarang(
                    f"Kode memakai perintah yang tidak diizinkan: {node.id}"
                )

        elif isinstance(node, ast.Attribute):
            if node.attr.startswith("__"):
                raise PerintahTerlarang(
                    f"Kode memakai perintah yang tidak diizinkan: {node.attr}"
                )


def _nama_didefinisikan(pohon):
    """Nama yang ditulis setup_code (dipakai untuk deteksi konflik)."""
    nama = set()

    for node in ast.walk(pohon):
        if isinstance(node, ast.Assign):
            for target in node.targets:
                if isinstance(target, ast.Name):
                    nama.add(target.id)
        elif isinstance(node, (ast.AnnAssign, ast.AugAssign)):
            if isinstance(node.target, ast.Name):
                nama.add(node.target.id)
        elif isinstance(node, ast.FunctionDef):
            nama.add(node.name)
        elif isinstance(node, ast.ClassDef):
            nama.add(node.name)

    return nama


def _assignment_modul(pohon):
    """
    Nama yang di-assign kode siswa di TINGKAT MODUL.

    Termasuk di dalam if/for/while/try (semuanya berjalan saat modul
    dieksekusi), tetapi TIDAK masuk ke dalam def/class (variabel lokal).
    """
    hasil = {}

    def kunjungi(daftar):
        for node in daftar:
            if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)):
                continue

            if isinstance(node, ast.Assign):
                for target in node.targets:
                    if isinstance(target, ast.Name):
                        hasil.setdefault(target.id, node.lineno)
            elif isinstance(node, (ast.AnnAssign, ast.AugAssign)):
                if isinstance(node.target, ast.Name):
                    hasil.setdefault(node.target.id, node.lineno)

            for atribut in ("body", "orelse", "finalbody"):
                isi = getattr(node, atribut, None)
                if isinstance(isi, list):
                    kunjungi(isi)

            if isinstance(node, ast.Try):
                for handler in node.handlers:
                    kunjungi(handler.body)

    kunjungi(pohon.body)

    return hasil


def _tipe_hasil(nilai):
    """Ubah nilai Python menjadi {tipe, nilai} sesuai kontrak JSON."""
    # bool HARUS diperiksa sebelum int (bool adalah subclass int di Python).
    if isinstance(nilai, bool):
        return {"tipe": "bool", "nilai": bool(nilai)}

    if isinstance(nilai, int):
        return {"tipe": "int", "nilai": int(nilai)}

    if isinstance(nilai, float):
        if math.isnan(nilai) or math.isinf(nilai):
            # NaN/inf tidak pernah lulus pembanding di server.
            return {"tipe": "lain", "nilai": repr(nilai)[:100]}

        return {"tipe": "float", "nilai": float(nilai)}

    if isinstance(nilai, str):
        return {"tipe": "str", "nilai": nilai[:200]}

    if nilai is None:
        return {"tipe": "none", "nilai": None}

    return {"tipe": "lain", "nilai": repr(nilai)[:100]}


def _galat_ringkas(exc, jenis=None):
    """Ambil {jenis, pesan, baris} dengan baris dari frame <kode_siswa>."""
    baris = None

    if isinstance(exc, SyntaxError):
        baris = exc.lineno
    else:
        for frame in reversed(traceback.extract_tb(exc.__traceback__)):
            if frame.filename == NAMA_FILE_SISWA:
                baris = frame.lineno
                break

    pesan = str(exc).strip() or type(exc).__name__

    return {
        "jenis": jenis or type(exc).__name__,
        "pesan": pesan[:300],
        "baris": baris,
    }


def _nama_variabel_saja(target):
    """True bila check_target hanya nama variabel (bukan pemanggilan fungsi)."""
    return target.replace("_", "a").isalnum() and not target[:1].isdigit()


def _bungkus(hasil=None, keluaran="", galat=None):
    return json.dumps({"hasil": hasil or [], "keluaran": keluaran, "galat": galat})


def nilai_paket(payload_json):
    """
    Titik masuk yang dipanggil Web Worker.

    Menerima JSON (string) dan mengembalikan JSON (string).
    """
    try:
        payload = json.loads(payload_json)
    except Exception as exc:
        return _bungkus(galat={"jenis": "PayloadError", "pesan": str(exc)[:300], "baris": None})

    kode = payload.get("kode") or ""
    setup = payload.get("setup_code") or ""
    target = payload.get("target") or []
    modul_diizinkan = payload.get("modul_diizinkan") or []
    maks_keluaran = int(payload.get("maks_keluaran") or 2000)

    # (a) Periksa AST kode siswa.
    try:
        pohon_siswa = ast.parse(kode, filename=NAMA_FILE_SISWA)
    except SyntaxError as exc:
        return _bungkus(galat=_galat_ringkas(exc, "SyntaxError"))

    try:
        _periksa_ast(pohon_siswa, modul_diizinkan)
    except PerintahTerlarang as exc:
        return _bungkus(galat={"jenis": "PerintahTerlarang", "pesan": str(exc)[:300], "baris": None})

    # (b) Periksa konflik dengan setup_code (mis. Level 2: `saldo`).
    if setup.strip():
        try:
            pohon_setup = ast.parse(setup, filename="<setup_code>")
        except SyntaxError as exc:
            return _bungkus(galat={"jenis": "SetupError", "pesan": str(exc)[:300], "baris": None})

        disediakan = _nama_didefinisikan(pohon_setup)
        ditimpa = _assignment_modul(pohon_siswa)

        for nama in sorted(disediakan & set(ditimpa)):
            return _bungkus(
                galat={
                    "jenis": "KonflikSetup",
                    "pesan": (
                        f"Variabel `{nama}` disediakan sistem saat pengujian. "
                        f"Hapus baris yang menulis ulang `{nama} = ...`."
                    ),
                    "baris": ditimpa[nama],
                }
            )

    # (c) Namespace baru untuk setiap kelompok setup.
    namespace = {"__name__": "__main__"}
    penyangga = io.StringIO()
    keluaran_asli = sys.stdout

    try:
        sys.stdout = penyangga

        try:
            # (d) Jalankan setup_code lebih dulu.
            if setup.strip():
                exec(compile(setup, "<setup_code>", "exec"), namespace)

            # (e) Jalankan kode siswa (nama berkas semu agar baris galat tepat).
            exec(compile(pohon_siswa, NAMA_FILE_SISWA, "exec"), namespace)
        except Exception as exc:
            return _bungkus(
                keluaran=penyangga.getvalue()[:maks_keluaran],
                galat=_galat_ringkas(exc),
            )

        # (f) Evaluasi tiap check_target di namespace yang sama.
        hasil = []

        for satu in target:
            case_id = satu.get("id")
            check = (satu.get("check_target") or "").strip()

            try:
                nilai = eval(compile(check, "<cek>", "eval"), namespace)  # noqa: S307
                hasil.append({"id": case_id, **_tipe_hasil(nilai)})
            except NameError as exc:
                if _nama_variabel_saja(check):
                    # Variabel memang belum dibuat siswa.
                    hasil.append({"id": case_id, "tipe": "belum_dibuat", "nilai": None})
                else:
                    hasil.append(
                        {"id": case_id, "tipe": "lain", "nilai": str(exc)[:100], "galat": str(exc)[:100]}
                    )
            except Exception as exc:
                hasil.append(
                    {"id": case_id, "tipe": "lain", "nilai": repr(exc)[:100], "galat": str(exc)[:100]}
                )

        return _bungkus(hasil=hasil, keluaran=penyangga.getvalue()[:maks_keluaran])
    finally:
        sys.stdout = keluaran_asli


def versi_penilai():
    """Penanda versi skrip; dikirim worker ke main thread."""
    return "1"
