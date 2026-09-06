<?php

use App\Support\Live\SaluranArena;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;

function namaSaluran(array $saluran): array
{
    return array_map(fn (Channel $c) => (string) $c, $saluran);
}

it('tidak memberi channel apa pun untuk partai tanpa gelanggang', function () {
    config(['overlay.enabled' => true, 'live.enabled' => true]);

    expect(SaluranArena::untuk(null))->toBe([]);
});

it('menyertakan channel publik saat overlay menyala', function () {
    config(['overlay.enabled' => true, 'live.enabled' => false]);

    expect(namaSaluran(SaluranArena::untuk(7)))
        ->toBe(['presence-arena.7', 'public-live.7']);
});

it('menyertakan channel publik saat live score menyala', function () {
    config(['overlay.enabled' => false, 'live.enabled' => true]);

    expect(namaSaluran(SaluranArena::untuk(7)))
        ->toBe(['presence-arena.7', 'public-live.7']);
});

it('menyisakan hanya channel presence saat kedua siaran mati', function () {
    config(['overlay.enabled' => false, 'live.enabled' => false]);

    expect(namaSaluran(SaluranArena::untuk(7)))->toBe(['presence-arena.7']);
});

/**
 * Penjaga terpenting di berkas ini. Panel juri, wasit, operator, dan ketua
 * pertandingan semuanya mendengarkan channel presence -- kalau ia ikut lepas
 * saat siaran dimatikan, yang mati bukan siarannya melainkan pertandingannya.
 */
it('tidak pernah menghilangkan channel presence', function (bool $overlay, bool $live) {
    config(['overlay.enabled' => $overlay, 'live.enabled' => $live]);

    $saluran = SaluranArena::untuk(3);

    expect($saluran[0])->toBeInstanceOf(PresenceChannel::class)
        ->and((string) $saluran[0])->toBe('presence-arena.3');
})->with([
    [true, true],
    [true, false],
    [false, true],
    [false, false],
]);
