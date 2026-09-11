<?php

use App\Models\Arena;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SilatResourceSeeder;
use Database\Seeders\SilatRoleSeeder;

/**
 * Panitia menugaskan operator ke gelanggang, bukan ke partai. Tanpa halaman
 * ini penjagaan di panel gelanggang tidak bisa dipakai: tidak ada cara
 * memberi tahu sistem siapa operator gelanggang mana.
 */
beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->syncRoles([config('resources.super_admin_role')]);

    $this->tournament = Tournament::factory()->create(['starts_on' => '2026-09-01']);
    $this->gelanggang = Arena::factory()->for($this->tournament)->create(['name' => 'Gelanggang A']);

    $this->buatOperator = function (string $nama) {
        $user = User::factory()->create(['name' => $nama]);
        $user->syncRoles(['operator-it']);

        return $user;
    };
});

it('menugaskan operator ke gelanggang', function () {
    $operator = ($this->buatOperator)('Fajar');

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.gelanggang.operator', [$this->tournament, $this->gelanggang]), [
            'operator_id' => [$operator->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($this->gelanggang->operators()->pluck('users.id')->all())->toBe([$operator->id]);
});

it('mengganti penugasan lama saat disimpan ulang', function () {
    $lama = ($this->buatOperator)('Fajar');
    $baru = ($this->buatOperator)('Yudi');
    $this->gelanggang->operators()->attach($lama);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.gelanggang.operator', [$this->tournament, $this->gelanggang]), [
            'operator_id' => [$baru->id],
        ])
        ->assertRedirect();

    expect($this->gelanggang->operators()->pluck('users.id')->all())->toBe([$baru->id]);
});

it('mengosongkan penugasan bila tidak ada yang dipilih', function () {
    $this->gelanggang->operators()->attach(($this->buatOperator)('Fajar'));

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.gelanggang.operator', [$this->tournament, $this->gelanggang]), [])
        ->assertRedirect();

    expect($this->gelanggang->operators()->count())->toBe(0);
});

it('menolak pengguna yang bukan operator', function () {
    /*
     * Official kontingen, bukan sekadar "peran lain".
     *
     * Uji ini dulu memakai peran `sekretariat`, dan ketika peran itu lebur ke
     * `operator-it` (September 2026) ia diam-diam berubah arti: yang dikirim
     * justru operator sungguhan, jadi penolakannya tidak pernah terjadi dan
     * ujinya merah. Yang dibutuhkan peran yang memang tidak akan pernah
     * berdiri di meja operator.
     */
    $bukanOperator = User::factory()->create();
    $bukanOperator->syncRoles(['official-kontingen']);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.gelanggang.operator', [$this->tournament, $this->gelanggang]), [
            'operator_id' => [$bukanOperator->id],
        ])
        ->assertSessionHasErrors('operator_id.0');

    expect($this->gelanggang->operators()->count())->toBe(0);
});

it('menolak gelanggang milik kejuaraan lain', function () {
    $kejuaraanLain = Tournament::factory()->create(['starts_on' => '2026-10-01']);

    $this->actingAs($this->admin)
        ->post(route('admin.turnamen.gelanggang.operator', [$kejuaraanLain, $this->gelanggang]), [
            'operator_id' => [($this->buatOperator)('Fajar')->id],
        ])
        ->assertNotFound();
});
