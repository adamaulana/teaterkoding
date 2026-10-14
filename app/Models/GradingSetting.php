<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bobot penilaian sumatif satu kelas. Satu baris per kelas.
 *
 * Total bobot harus tepat 100 — divalidasi di aplikasi.
 *
 * @property int $id
 * @property int $class_id
 * @property int $w_individual
 * @property int $w_team
 * @property int $w_bug_plan
 * @property int $w_reflection
 * @property int $w_observation
 */
#[Fillable(['class_id', 'w_individual', 'w_team', 'w_bug_plan', 'w_reflection', 'w_observation'])]
class GradingSetting extends Model
{
    protected $table = 'grading_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'w_individual' => 'integer',
            'w_team' => 'integer',
            'w_bug_plan' => 'integer',
            'w_reflection' => 'integer',
            'w_observation' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Bobot dalam bentuk peta kunci komponen => nilai.
     *
     * @return array<string, int>
     */
    public function bobot(): array
    {
        return [
            'w_individual' => (int) $this->w_individual,
            'w_team' => (int) $this->w_team,
            'w_bug_plan' => (int) $this->w_bug_plan,
            'w_reflection' => (int) $this->w_reflection,
            'w_observation' => (int) $this->w_observation,
        ];
    }

    /**
     * Bobot default (50/20/10/10/10) sebagai peta kunci => nilai.
     *
     * @return array<string, int>
     */
    public static function bobotDefault(): array
    {
        /** @var array<string, int> $default */
        $default = config('teater.default_weights', [
            'w_individual' => 50,
            'w_team' => 20,
            'w_bug_plan' => 10,
            'w_reflection' => 10,
            'w_observation' => 10,
        ]);

        return $default;
    }

    /**
     * Total bobot (harus 100).
     */
    public function total(): int
    {
        return array_sum($this->bobot());
    }

    /**
     * Label Indonesia untuk tiap kolom bobot.
     *
     * Dipakai tampilan agar nama kolom mentah (w_individual, dst) tidak
     * pernah terlihat guru. Contoh keluaran:
     *   ['w_individual' => 'Fungsi Individu', ...]
     *
     * @return array<string, string>
     */
    public static function labelPerBobot(): array
    {
        /** @var array<string, string> $perBobot */
        $perBobot = config('teater.komponen_per_bobot', []);

        /** @var array<string, string> $label */
        $label = config('teater.komponen_labels', []);

        $hasil = [];

        foreach ($perBobot as $kolom => $kunciKomponen) {
            $hasil[$kolom] = $label[$kunciKomponen] ?? $kunciKomponen;
        }

        return $hasil;
    }
}
