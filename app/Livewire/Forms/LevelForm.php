<?php

namespace App\Livewire\Forms;

use App\Models\Level;
use Livewire\Form;

/**
 * Form ubah level. Nomor level tidak dapat diubah setelah dibuat.
 */
class LevelForm extends Form
{
    public ?int $levelId = null;

    public string $level_name = '';

    public bool $is_summative = false;

    public function setLevel(Level $level): void
    {
        $this->levelId = $level->level_id;
        $this->level_name = $level->level_name;
        $this->is_summative = (bool) $level->is_summative;
    }

    public function resetForm(): void
    {
        $this->reset();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'level_name' => ['required', 'string', 'max:100'],
            'is_summative' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'level_name.required' => 'Nama level wajib diisi.',
            'level_name.max' => 'Nama level maksimal 100 karakter.',
        ];
    }
}
