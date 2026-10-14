<?php

namespace App\Actions;

/**
 * Pengurai CSV siswa (murni — tanpa DB, mudah diuji).
 *
 * Format yang didukung: dua kolom `nama,nis` (NIS opsional), pemisah
 * koma atau titik-koma (dideteksi otomatis), BOM UTF-8 ditoleransi,
 * dan baris header (`nama,nis`) dilewati.
 */
class ParserCsvSiswa
{
    public const MAX_ROWS = 500;

    /**
     * Uraikan isi CSV menjadi baris valid & baris bermasalah.
     *
     * @param  array<int, string|int>  $existingNis  NIS yang sudah ada di kelas tujuan.
     * @return array{
     *     valid: array<int, array{row: int, name: string, nis: string|null}>,
     *     invalid: array<int, array{row: int, name: string, nis: string|null, reason: string}>,
     *     total: int,
     *     truncated: bool,
     * }
     */
    public function parse(string $content, array $existingNis = [], int $maxRows = self::MAX_ROWS): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $this->stripBom($content)) ?: [];

        /** @var array<int, array{line: int, raw: string}> $rows */
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = ['line' => $index + 1, 'raw' => $line];
        }

        $delimiter = $this->detectDelimiter($rows[0]['raw'] ?? '');

        $existing = [];
        foreach ($existingNis as $nis) {
            $existing[(string) $nis] = true;
        }

        $valid = [];
        $invalid = [];
        $seenInFile = [];
        $total = 0;
        $truncated = false;
        $barisPertama = true;

        foreach ($rows as $row) {
            $cells = str_getcsv($row['raw'], $delimiter);
            $name = trim((string) ($cells[0] ?? ''));
            $nis = trim((string) ($cells[1] ?? ''));

            if ($barisPertama) {
                $barisPertama = false;

                if ($this->looksLikeHeader($name, $nis)) {
                    continue;
                }
            }

            if ($total >= $maxRows) {
                $truncated = true;

                break;
            }

            $total++;

            if ($name === '') {
                $invalid[] = ['row' => $row['line'], 'name' => '', 'nis' => $this->nullIfEmpty($nis), 'reason' => 'Nama kosong.'];

                continue;
            }

            if ($nis !== '') {
                if (isset($seenInFile[$nis])) {
                    $invalid[] = ['row' => $row['line'], 'name' => $name, 'nis' => $nis, 'reason' => 'NIS ganda di dalam berkas.'];

                    continue;
                }

                if (isset($existing[$nis])) {
                    $invalid[] = ['row' => $row['line'], 'name' => $name, 'nis' => $nis, 'reason' => 'NIS sudah ada di kelas ini.'];

                    continue;
                }

                $seenInFile[$nis] = true;
            }

            $valid[] = ['row' => $row['line'], 'name' => $name, 'nis' => $this->nullIfEmpty($nis)];
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'total' => $total,
            'truncated' => $truncated,
        ];
    }

    private function stripBom(string $content): string
    {
        return str_starts_with($content, "\xEF\xBB\xBF")
            ? substr($content, 3)
            : $content;
    }

    private function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private function looksLikeHeader(string $name, string $nis): bool
    {
        $name = mb_strtolower($name);
        $nis = mb_strtolower($nis);

        return in_array($name, ['nama', 'name', 'full_name', 'nama lengkap'], true)
            || in_array($nis, ['nis', 'nisn', 'student_no', 'nomor induk'], true);
    }

    private function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
