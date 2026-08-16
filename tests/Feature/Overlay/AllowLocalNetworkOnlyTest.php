<?php

use App\Models\Arena;
use App\Models\Tournament;

beforeEach(function () {
    $this->arena = Arena::factory()->for(Tournament::factory())->create();
});

it('mengizinkan permintaan dari localhost', function () {
    $this->call('GET', route('overlay.state', $this->arena), server: ['REMOTE_ADDR' => '127.0.0.1'])
        ->assertOk();
});

it('mengizinkan permintaan dari rentang IP privat 192.168.0.0/16', function () {
    $this->call('GET', route('overlay.state', $this->arena), server: ['REMOTE_ADDR' => '192.168.1.50'])
        ->assertOk();
});

it('menolak permintaan dari IP publik di luar jaringan lokal', function () {
    $this->call('GET', route('overlay.state', $this->arena), server: ['REMOTE_ADDR' => '8.8.8.8'])
        ->assertForbidden();
});

it('menghormati OVERLAY_ALLOWED_CIDRS kustom', function () {
    config(['overlay.allowed_cidrs' => ['203.0.113.0/24']]);

    $this->call('GET', route('overlay.state', $this->arena), server: ['REMOTE_ADDR' => '203.0.113.7'])
        ->assertOk();

    $this->call('GET', route('overlay.state', $this->arena), server: ['REMOTE_ADDR' => '192.168.1.1'])
        ->assertForbidden();
});
