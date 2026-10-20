/**
 * Jawaban acuan tiap misi (diverifikasi manual) + kode kasus khusus.
 *
 * Sumber: instruksi Bagian E. Dipakai APA ADANYA — jangan diubah agar uji
 * benar-benar mengukur kecocokan materi dengan kasus uji nyata.
 *
 * Catatan Level 2: `saldo` DISEDIAKAN sistem, jadi jawaban acuan sengaja
 * tidak mendefinisikan `saldo` (kalau mendefinisikan, penilai akan menolak
 * dengan KonflikSetup — itu justru diuji terpisah).
 */
export const JAWABAN_ACUAN = {
    'KOP-L1-BUDI': `saldo_awal = 2_500_000
setoran_1 = 500_000
setoran_2 = 750_000
setoran_3 = 250_000
penarikan = 1_250_000
total_setoran = setoran_1 + setoran_2 + setoran_3
bunga = (saldo_awal + total_setoran) * 0.5 / 100
saldo_akhir = saldo_awal + total_setoran + bunga
lembar = penarikan // 100_000
sisa = penarikan % 100_000
`,

    'KOP-L2-STATUS': `if saldo < 0:
    status = "Data tidak valid"
    pinjaman = "Tidak"
elif saldo >= 10_000_000:
    status = "Gold"
    pinjaman = "Boleh"
elif saldo >= 5_000_000:
    status = "Silver"
    pinjaman = "Boleh"
else:
    status = "Reguler"
    pinjaman = "Tidak"
`,

    'KOP-L3-LOOP': `daftar_saldo = [12_000_000, 6_500_000, 3_000_000, 9_999_999, 5_000_000]
saldo_tabungan = 1_000_000
for bulan in range(12):
    saldo_tabungan += 300_000
    saldo_tabungan += saldo_tabungan * 0.5 / 100
total_saldo = 0
jumlah_gold = 0
jumlah_silver = 0
jumlah_reguler = 0
for s in daftar_saldo:
    total_saldo += s
    if s >= 10_000_000:
        jumlah_gold += 1
    elif s >= 5_000_000:
        jumlah_silver += 1
    else:
        jumlah_reguler += 1
`,

    'KOP-L4-KASIR': `def hitung_bunga(saldo, persen):
    return saldo * persen / 100

def cek_status(saldo):
    if saldo < 0:
        return "Data tidak valid"
    elif saldo >= 10_000_000:
        return "Gold"
    elif saldo >= 5_000_000:
        return "Silver"
    else:
        return "Reguler"

def boleh_pinjam(saldo):
    return cek_status(saldo) in ("Gold", "Silver")

def hitung_jumlah(daftar_saldo, status):
    n = 0
    for s in daftar_saldo:
        if cek_status(s) == status:
            n += 1
    return n

def simulasi_tabungan(saldo_awal, setoran_bulanan, bulan, persen_bunga):
    saldo = saldo_awal
    for _ in range(bulan):
        saldo += setoran_bulanan
        saldo += hitung_bunga(saldo, persen_bunga)
    return saldo
`,
};

/** Kode yang menulis ulang variabel yang disediakan sistem (Level 2). */
export const KODE_SALDO = `saldo = 12_000_000
status = "Gold"
pinjaman = "Boleh"
`;

/** Kode terlarang — harus ditolak AST, bukan dieksekusi. */
export const KODE_TERLARANG = {
    'import os': 'import os\nx = 1\n',
    '__import__': '__import__("os").getcwd()\n',
    'import js': 'import js\nx = 1\n',
    'eval': 'eval("1 + 1")\n',
    'dunder': 'x = 1\nprint(x.__class__)\n',
};

/**
 * Kode yang MENCETAK teks menyerupai laporan hasil, tetapi variabelnya salah.
 *
 * Penilai harus tetap melaporkan nilai variabel yang sebenarnya (gagal),
 * bukan tertipu oleh print. stdout tetap dilaporkan terpisah.
 */
export const KODE_PRINT_PALSU = `print("hasil: 5/5 lulus")
print('{"is_passed": true}')
total_setoran = 1
bunga = 2
saldo_akhir = 3
lembar = 4
sisa = 5
`;
