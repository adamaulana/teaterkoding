<?php

use App\Services\AccessCodeService;

test('generate menghasilkan 8 karakter tanpa karakter membingungkan', function () {
    $servis = app(AccessCodeService::class);

    foreach (range(1, 200) as $i) {
        $kode = $servis->generate();

        expect($kode)
            ->toHaveLength(8)
            ->toMatch('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$/');
    }
});

test('generate menghasilkan kode acak yang umumnya berbeda', function () {
    $servis = app(AccessCodeService::class);

    expect($servis->generate())->not->toBe($servis->generate());
});

test('normalisasi mengubah huruf kecil dan membuang spasi serta tanda hubung', function () {
    $servis = app(AccessCodeService::class);

    expect($servis->normalisasi(' ab-cd - 23 45 '))->toBe('ABCD2345');
});

test('display memformat kode menjadi XXXX-XXXX', function () {
    $servis = app(AccessCodeService::class);

    expect($servis->display('ABCD2345'))->toBe('ABCD-2345');
    expect($servis->display('abcd-2345'))->toBe('ABCD-2345');
});

test('panjang standar kode adalah 8', function () {
    expect(app(AccessCodeService::class)->panjang())->toBe(8);
});