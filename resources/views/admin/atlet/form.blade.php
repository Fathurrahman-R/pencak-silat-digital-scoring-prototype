@php
    use App\Enums\JenisKelamin;

    $athlete ??= null;
    $suffix ??= 'baru';
@endphp

<x-ui.input name="name" label="Nama atlet" :value="$athlete?->name" required :id="'nama-atlet-'.$suffix" />

<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.select name="jenis_kelamin" label="Jenis kelamin"
                 :id="'jk-atlet-'.$suffix"
                 :options="collect(JenisKelamin::cases())
                     ->mapWithKeys(fn (JenisKelamin $jk) => [$jk->value => $jk->labelOrang()])
                     ->all()"
                 :selected="$athlete?->jenis_kelamin?->value" required />

    <x-ui.input type="number" step="0.1" name="weight_claim" label="Berat badan (kg)"
                :id="'berat-atlet-'.$suffix"
                :value="$athlete?->weight_claim"
                hint="Berat yang diakui saat mendaftar. Yang menentukan tetap hasil timbang badan di venue." />
</div>

<x-ui.input type="date" name="birth_date" label="Tanggal lahir"
            :id="'lahir-atlet-'.$suffix"
            :value="$athlete?->birth_date?->format('Y-m-d')" required
            hint="Golongan usia dihitung dari umur pada bulan kejuaraan dimulai, bukan umur hari ini (Pasal 2)." />
