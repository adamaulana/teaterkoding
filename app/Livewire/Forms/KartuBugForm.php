<?php

namespace App\Livewire\Forms;

use App\Models\BugCard;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Form tambah/ubah kartu bug.
 *
 * buggy_code disimpan apa adanya dan SELALU ditampilkan ter-escape di view
 * (jangan pernah dirender sebagai HTML).
 */
class KartuBugForm extends Form
{
    public ?int $bugId = null;

    public ?int $level_id = null;

    public string $bug_title = '';

    public string $buggy_code = '';

    public string $hint_text = '';

    public string $fix_explanation = '';

    public int $points = 10;

    public function setKartu(BugCard $kartu): void
    {
        $this->bugId = $kartu->bug_id;
        $this->level_id = $kartu->level_id;
        $this->bug_title = $kartu->bug_title;
        $this->buggy_code = $kartu->buggy_code;
        $this->hint_text = (string) ($kartu->hint_text ?? '');
        $this->fix_explanation = $kartu->fix_explanation;
        $this->points = (int) $kartu->points;
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->points = 10;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'level_id' => ['required', 'integer', Rule::exists('levels', 'level_id')],
            'bug_title' => ['required', 'string', 'max:100'],
            'buggy_code' => ['required', 'string', 'max:5000'],
            'hint_text' => ['nullable', 'string', 'max:2000'],
            'fix_explanation' => ['required', 'string', 'max:5000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'level_id.required' => 'Level wajib dipilih.',
            'level_id.exists' => 'Level yang dipilih tidak ditemukan.',
            'bug_title.required' => 'Judul bug wajib diisi.',
            'bug_title.max' => 'Judul bug maksimal 100 karakter.',
            'buggy_code.required' => 'Kode bermasalah wajib diisi.',
            'buggy_code.max' => 'Kode bermasalah maksimal 5000 karakter.',
            'hint_text.max' => 'Teks petunjuk maksimal 2000 karakter.',
            'fix_explanation.required' => 'Penjelasan perbaikan wajib diisi.',
            'fix_explanation.max' => 'Penjelasan perbaikan maksimal 5000 karakter.',
            'points.required' => 'Poin wajib diisi.',
            'points.integer' => 'Poin harus berupa angka bulat.',
            'points.min' => 'Poin minimal 1.',
            'points.max' => 'Poin maksimal 100.',
        ];
    }
}
