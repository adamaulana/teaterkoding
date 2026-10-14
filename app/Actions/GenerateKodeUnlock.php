<?php

namespace App\Actions;

use App\Models\Mission;

/**
 * Generator kode unlock misi.
 *
 * Format: {PREFIX}-L{n}-{KATA} — mis. KOP-L2-STATUS.
 * Prefix & daftar kata diambil dari config/materi.php agar mudah diganti.
 */
class GenerateKodeUnlock
{
    public function prefix(): string
    {
        return strtoupper((string) config('materi.unlock_prefix', 'KOP'));
    }

    /**
     * @return array<int, string>
     */
    public function kataTersedia(): array
    {
        /** @var array<int, string> $kata */
        $kata = config('materi.unlock_words', ['BUDI', 'STATUS', 'LOOP', 'KASIR']);

        return array_values(array_filter(array_map(
            fn (mixed $k): string => strtoupper(trim((string) $k)),
            $kata,
        )));
    }

    /**
     * Buat kode unlock yang dijamin unik (belum dipakai misi mana pun).
     *
     * @param  iterable<int, string>  $kodeTerpakai  Kode yang harus dihindari.
     */
    public function buat(int $levelNumber, iterable $kodeTerpakai = []): string
    {
        $terpakai = [];

        foreach ($kodeTerpakai as $kode) {
            $terpakai[mb_strtoupper((string) $kode)] = true;
        }

        $prefix = $this->prefix();
        $kata = $this->kataTersedia();
        $jumlahKata = count($kata);

        if ($jumlahKata === 0) {
            $kata = ['MISI'];
            $jumlahKata = 1;
        }

        // Dua putaran: kombinasi langsung, lalu akhiran angka bila semua habis.
        for ($putaran = 1; $putaran <= 2; $putaran++) {
            foreach ($kata as $indeks => $satu) {
                $kandidat = $putaran === 1
                    ? "{$prefix}-L{$levelNumber}-{$satu}"
                    : "{$prefix}-L{$levelNumber}-{$satu}".($indeks + 1);

                if (! isset($terpakai[$kandidat])) {
                    return $kandidat;
                }
            }
        }

        // Fallback terakhir: akhiran angka naik sampai tidak bentrok.
        $nomor = $jumlahKata + 1;

        do {
            $kandidat = "{$prefix}-L{$levelNumber}-KATA{$nomor}";
            $nomor++;
        } while (isset($terpakai[$kandidat]));

        return $kandidat;
    }

    /**
     * Kode unlock yang sudah dipakai di database.
     *
     * @return array<int, string>
     */
    public function kodeTerpakai(?int $kecualiMissionId = null): array
    {
        return Mission::query()
            ->when($kecualiMissionId !== null, fn ($q) => $q->where('mission_id', '!=', $kecualiMissionId))
            ->pluck('unlock_code')
            ->all();
    }

    /**
     * Buat kode otomatis berdasarkan level misi, menghindari kode yang sudah ada.
     */
    public function buatUntukLevel(int $levelNumber): string
    {
        return $this->buat($levelNumber, $this->kodeTerpakai());
    }

    /**
     * Normalisasi kode unlock: huruf besar, hanya A-Z 0-9 dan tanda hubung.
     */
    public function normalisasi(string $kode): string
    {
        $kode = mb_strtoupper(trim($kode));
        $kode = (string) preg_replace('/[^A-Z0-9-]/', '', $kode);

        return mb_substr($kode, 0, 30);
    }
}
