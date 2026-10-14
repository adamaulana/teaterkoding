<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan & refleksi siswa per misi, dibedakan menurut jenis/peran.
 *
 * @property int $id
 * @property int $team_id
 * @property int $mission_id
 * @property int $student_id
 * @property int|null $role_id
 * @property string $type
 * @property string $body
 */
#[Fillable(['team_id', 'mission_id', 'student_id', 'role_id', 'type', 'body'])]
class Reflection extends Model
{
    public const TYPE_PENJELASAN = 'penjelasan';

    public const TYPE_CATATAN_UJI = 'catatan_uji';

    public const TYPE_TINJAUAN = 'tinjauan';

    public const TYPE_REFLEKSI = 'refleksi';

    protected $table = 'reflections';

    /**
     * Semua jenis catatan yang dikenal, dengan label & peran pengampunya.
     *
     * @return array<string, array{label: string, peran: string|null, pertanyaan: string}>
     */
    public static function jenis(): array
    {
        return [
            self::TYPE_PENJELASAN => [
                'label' => 'Penjelasan Kode',
                'peran' => 'Analyst',
                'pertanyaan' => 'Jelaskan cara kerja kode kalian.',
            ],
            self::TYPE_CATATAN_UJI => [
                'label' => 'Catatan Pengujian',
                'peran' => 'Tester',
                'pertanyaan' => 'Input apa saja yang kamu coba dan hasilnya?',
            ],
            self::TYPE_TINJAUAN => [
                'label' => 'Tinjauan Kode',
                'peran' => 'Reviewer',
                'pertanyaan' => 'Apa yang kamu periksa dan apa temuanmu?',
            ],
            self::TYPE_REFLEKSI => [
                'label' => 'Refleksi Pribadi',
                'peran' => null,
                'pertanyaan' => 'Kesalahan apa yang kamu temukan dan bagaimana memperbaikinya?',
            ],
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
