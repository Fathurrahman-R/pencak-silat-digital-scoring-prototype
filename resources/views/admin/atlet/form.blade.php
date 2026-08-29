@php
    use App\Enums\JenisKelamin;

    $athlete ??= null;
    $suffix ??= 'baru';
@endphp

<x-si.isian name="name" label="Nama atlet" :value="$athlete?->name" wajib :id="'nama-atlet-'.$suffix" />

<div class="grid gap-4 sm:grid-cols-2">
    <x-si.pilihan name="jenis_kelamin" label="Jenis kelamin"
                 :id="'jk-atlet-'.$suffix"
                 :options="collect(JenisKelamin::cases())
                     ->mapWithKeys(fn (JenisKelamin $jk) => [$jk->value => $jk->labelOrang()])
                     ->all()"
                 :selected="$athlete?->jenis_kelamin?->value" wajib />

    <x-si.isian tipe="number" step="0.1" name="weight_claim" label="Berat badan (kg)"
                :id="'berat-atlet-'.$suffix"
                :value="$athlete?->weight_claim"
                bantuan="Berat yang diakui saat mendaftar. Yang menentukan tetap hasil timbang badan di venue." />
</div>

<x-si.isian tipe="date" name="birth_date" label="Tanggal lahir"
            :id="'lahir-atlet-'.$suffix"
            :value="$athlete?->birth_date?->format('Y-m-d')" wajib
            bantuan="Golongan usia dihitung dari umur pada bulan kejuaraan dimulai, bukan umur hari ini (Pasal 2)." />
