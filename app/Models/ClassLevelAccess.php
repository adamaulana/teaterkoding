<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pengaturan buka/tutup satu level untuk satu kelas.
 *
 * Tanpa baris di sini, level dianggap TERTUTUP (lihat LevelAccessService).
 *
 * @property int $id
 * @property int $class_id
 * @property int $level_id
 * @property bool $is_open
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 */
#[Fillable(['class_id', 'level_id', 'is_open', 'opens_at', 'closes_at'])]
class ClassLevelAccess extends Model
{
    protected $table = 'class_level_access';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
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
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    /**
     * Apakah pengaturan ini sedang berada di dalam jendela waktunya.
     *
     * Dianggap terbuka bila opens_at belum lewat DAN closes_at belum lewat.
     */
    public function dalamJendelaWaktu(): bool
    {
        $sekarang = now();

        if ($this->opens_at !== null && $sekarang->lt($this->opens_at)) {
            return false;
        }

        if ($this->closes_at !== null && $sekarang->gte($this->closes_at)) {
            return false;
        }

        return true;
    }

    /**
     * Apakah level ini benar-benar bisa dibuka sekarang.
     */
    public function bolehDibuka(): bool
    {
        return $this->is_open && $this->dalamJendelaWaktu();
    }
}
