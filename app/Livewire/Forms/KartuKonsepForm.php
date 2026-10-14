<?php

namespace App\Livewire\Forms;

use App\Models\ConceptCard;
use Livewire\Form;

/**
 * Form ubah isi kartu konsep (6 bidang isi).
 *
 * Jumlah kartu tetap 5 karena direferensikan mission_plans, sehingga tidak
 * ada operasi hapus pada form ini.
 */
class KartuKonsepForm extends Form
{
    public ?int $conceptId = null;

    public string $concept_name = '';

    public string $function_desc = '';

    public string $syntax_example = '';

    public string $when_to_use = '';

    public string $common_pitfalls = '';

    public string $trigger_question = '';

    public function setKartu(ConceptCard $kartu): void
    {
        $this->conceptId = $kartu->concept_id;
        $this->concept_name = $kartu->concept_name;
        $this->function_desc = (string) ($kartu->function_desc ?? '');
        $this->syntax_example = (string) ($kartu->syntax_example ?? '');
        $this->when_to_use = (string) ($kartu->when_to_use ?? '');
        $this->common_pitfalls = (string) ($kartu->common_pitfalls ?? '');
        $this->trigger_question = (string) ($kartu->trigger_question ?? '');
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
            'concept_name' => ['required', 'string', 'max:30'],
            'function_desc' => ['nullable', 'string', 'max:2000'],
            'syntax_example' => ['nullable', 'string', 'max:2000'],
            'when_to_use' => ['nullable', 'string', 'max:2000'],
            'common_pitfalls' => ['nullable', 'string', 'max:2000'],
            'trigger_question' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'concept_name.required' => 'Nama kartu wajib diisi.',
            'concept_name.max' => 'Nama kartu maksimal 30 karakter.',
            'function_desc.max' => 'Ringkasan fungsi maksimal 2000 karakter.',
            'syntax_example.max' => 'Contoh sintaks maksimal 2000 karakter.',
            'when_to_use.max' => 'Kapan dipakai maksimal 2000 karakter.',
            'common_pitfalls.max' => 'Jebakan umum maksimal 2000 karakter.',
            'trigger_question.max' => 'Pertanyaan pemicu maksimal 1000 karakter.',
        ];
    }
}
