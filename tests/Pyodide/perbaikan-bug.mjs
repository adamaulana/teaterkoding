/**
 * Kode PERBAIKAN tiap kartu bug (bukan fix_explanation).
 *
 * === KENAPA TERPISAH DARI fix_explanation ===
 * Kolom `fix_explanation` di materi berisi PENJELASAN untuk siswa, mis.
 * "Data saldo berbentuk string. Perbaikan: saldo = 2500000 atau
 * saldo = int("2500000")." — bukan kode yang bisa dijalankan. Uji ini butuh
 * kode perbaikan yang benar-benar Python valid, jadi dipetakan di sini
 * berdasarkan judul kartu.
 *
 * Setiap entri diverifikasi lulus SEMUA kasus uji kartu tersebut
 * (lihat tests/Pyodide/uji-pyodide.mjs bagian b).
 */
export const KODE_PERBAIKAN_BUG = {
    'Saldo yang Berupa Teks': `saldo = 2500000
bunga = saldo * 0.005
print(bunga)
`,

    'Pecahan Tertukar': `penarikan = 1_250_000
lembar = penarikan // 100_000
sisa = penarikan % 100_000
print(lembar, sisa)
`,

    'Gold yang Tak Pernah Muncul': `def status(saldo):
    if saldo >= 10_000_000:
        return "Gold"
    elif saldo >= 5_000_000:
        return "Silver"
    else:
        return "Reguler"

print(status(12_000_000))
`,

    'Batas yang Meleset': `def status(saldo):
    if saldo >= 10_000_000:
        return "Gold"
    elif saldo >= 5_000_000:
        return "Silver"
    else:
        return "Reguler"

print(status(10_000_000), status(5_000_000))
`,

    'Total yang Selalu Ulang dari Nol': `daftar = [12_000_000, 6_500_000, 3_000_000]
total = 0
for saldo in daftar:
    total += saldo
print(total)
`,

    // Bug ini MACET (while tanpa increment). Perbaikan menambahkan bulan += 1,
    // sehingga penilai harus selesai (bukan WaktuHabis).
    'Bulan yang Tak Pernah Berganti': `saldo = 1_000_000
bulan = 1
while bulan <= 12:
    saldo += 300_000
    saldo += saldo * 0.005
    bulan += 1
print(saldo)
`,

    'Fungsi yang Hanya Bicara': `def hitung_bunga(saldo, persen):
    return saldo * persen / 100

saldo_akhir = 4_000_000 + hitung_bunga(4_000_000, 0.5)
print(saldo_akhir)
`,

    'Return yang Terlalu Cepat': `def jumlah_gold(daftar_saldo):
    n = 0
    for s in daftar_saldo:
        if s >= 10_000_000:
            n += 1
    return n

print(jumlah_gold([3_000_000, 12_000_000, 15_000_000]))
`,
};
