<?php

/*
|--------------------------------------------------------------------------
| Struktur menu sidebar panel admin/guru
|--------------------------------------------------------------------------
| route = null  -> menu tampil non-aktif dengan badge "Segera"
|                  (diisi otomatis pada fase berikutnya).
| admin_only    -> hanya tampil untuk pengguna dengan peran admin.
*/

return [
    'groups' => [
        [
            'label' => null,
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'admin.dashboard'],
            ],
        ],
        [
            'label' => 'Akademik',
            'items' => [
                ['label' => 'Kelas', 'icon' => 'school', 'route' => 'admin.kelas'],
                ['label' => 'Siswa', 'icon' => 'users', 'route' => 'admin.siswa'],
                ['label' => 'Kelompok', 'icon' => 'users-group', 'route' => 'admin.kelompok'],
                ['label' => 'Penugasan Peran', 'icon' => 'id-badge-2', 'route' => 'admin.penugasan-peran'],
            ],
        ],
        [
            'label' => 'Materi Game',
            'items' => [
                ['label' => 'Level', 'icon' => 'stairs', 'route' => 'admin.level'],
                ['label' => 'Misi & Kasus Uji', 'icon' => 'target', 'route' => 'admin.misi'],
                ['label' => 'Kartu Konsep', 'icon' => 'layout-grid', 'route' => 'admin.kartu-konsep'],
                ['label' => 'Kartu Bug', 'icon' => 'bug', 'route' => 'admin.kartu-bug'],
            ],
        ],
        [
            'label' => 'Pemantauan',
            'items' => [
                ['label' => 'Pantau Live', 'icon' => 'broadcast', 'route' => 'admin.pantau-live'],
                ['label' => 'Riwayat Submission', 'icon' => 'history', 'route' => 'admin.riwayat-submission'],
                ['label' => 'Kontrol Kelas', 'icon' => 'lock', 'route' => 'admin.kontrol-kelas'],
            ],
        ],
        [
            'label' => 'Penilaian',
            'items' => [
                ['label' => 'Skor & Peringkat', 'icon' => 'trophy', 'route' => 'admin.skor-peringkat'],
                ['label' => 'Rekap Nilai Sumatif', 'icon' => 'clipboard-list', 'route' => 'admin.rekap-nilai'],
                ['label' => 'Verifikasi Penilaian', 'icon' => 'shield-check', 'route' => 'admin.verifikasi-penilaian', 'badge' => 'verifikasi'],
            ],
        ],
        [
            'label' => 'Pengaturan',
            'items' => [
                ['label' => 'Profil', 'icon' => 'user-circle', 'route' => 'admin.profil'],
                ['label' => 'Ganti Password', 'icon' => 'key', 'route' => 'admin.ganti-password'],
                ['label' => 'Pengaturan Penilaian', 'icon' => 'settings', 'route' => 'admin.pengaturan-penilaian'],
                ['label' => 'Pengguna', 'icon' => 'user-cog', 'route' => 'admin.pengguna', 'admin_only' => true],
            ],
        ],
    ],
];
