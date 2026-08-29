@php($tournament ??= null)

{{--
    Dua kartu, bukan tiga: jadwal dan jendela pendaftaran adalah empat kolom
    tanggal yang saling terkait, dan memisahkannya jadi dua kotak bertumpuk
    hanya menambah kepala kartu tanpa menambah arti.
--}}

{{-- items-start: kartu setinggi isinya sendiri. Tanpa itu kartu terpendek
     diregangkan mengikuti yang tertinggi dan menyisakan bidang kosong. --}}
<div class="grid items-start gap-4 lg:grid-cols-3">
    <x-si.kartu judul="Identitas kejuaraan" class="lg:col-span-2">
        <div class="space-y-3.5">
            <x-si.isian name="name" label="Nama kejuaraan" :value="$tournament?->name" wajib />

            <div class="grid gap-3.5 sm:grid-cols-2">
                <x-si.isian name="organizer" label="Penyelenggara" :value="$tournament?->organizer"
                            bantuan="Mis. Pengurus Cabang IPSI Semarang." />
                <x-si.isian name="venue" label="Tempat" :value="$tournament?->venue" />
            </div>

            <x-si.isian-panjang name="description" label="Keterangan" :value="$tournament?->description" baris="3" />
        </div>
    </x-si.kartu>

    <x-si.kartu judul="Jadwal dan pendaftaran">
        <div class="space-y-3.5">
            <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-1">
                <x-si.isian tipe="date" name="starts_on" label="Tanggal mulai"
                            :value="$tournament?->starts_on?->format('Y-m-d')" />
                <x-si.isian tipe="date" name="ends_on" label="Tanggal selesai"
                            :value="$tournament?->ends_on?->format('Y-m-d')" />
            </div>

            <div class="grid gap-3.5 border-t border-line pt-3.5 sm:grid-cols-2 lg:grid-cols-1">
                <x-si.isian tipe="datetime-local" name="registration_opens_at" label="Pendaftaran dibuka"
                            :value="$tournament?->registration_opens_at?->format('Y-m-d\TH:i')" />
                <x-si.isian tipe="datetime-local" name="registration_closes_at" label="Pendaftaran ditutup"
                            :value="$tournament?->registration_closes_at?->format('Y-m-d\TH:i')"
                            bantuan="Paling lambat hari pertama bertanding — bagan disusun dari peserta yang sudah terkunci." />
            </div>
        </div>
    </x-si.kartu>
</div>
