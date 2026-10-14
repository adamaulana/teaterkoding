<?php

namespace App\Concerns;

/**
 * State tabel yang dipakai bersama oleh x-ui.data-table
 * (pencarian, pengurutan, paginasi) di halaman Livewire.
 *
 * @phpstan-ignore trait.unused
 */
trait InteractsWithTable
{
    public string $search = '';

    public string $sortBy = '';

    public string $sortDir = 'asc';

    public int $perPage = 10;

    public int $page = 1;

    /**
     * Ganti kolom urut; arah dibalik bila kolom yang sama diklik lagi.
     */
    public function sortTable(string $key): void
    {
        if ($this->sortBy === $key) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $key;
            $this->sortDir = 'asc';
        }

        $this->page = 1;
    }

    /**
     * Kembali ke halaman pertama saat pencarian berubah.
     */
    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    /**
     * Kembali ke halaman pertama saat jumlah data per halaman berubah.
     */
    public function updatedPerPage(): void
    {
        $this->page = 1;
    }
}
