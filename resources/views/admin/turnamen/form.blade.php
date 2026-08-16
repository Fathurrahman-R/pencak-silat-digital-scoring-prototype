@php($tournament ??= null)

{{--
    Dua kartu, bukan tiga: jadwal dan jendela pendaftaran adalah empat kolom
    tanggal yang saling terkait, dan memisahkannya jadi dua kotak bertumpuk
    hanya menambah kepala kartu tanpa menambah arti.
--}}

{{-- items-start: kartu setinggi isinya sendiri. Tanpa itu kartu terpendek
     diregangkan mengikuti yang tertinggi dan menyisakan bidang kosong. --}}
<div class="grid items-start gap-4 lg:grid-cols-3">
    <x-ui.card title="Identitas kejuaraan" class="lg:col-span-2">
        <div class="space-y-3.5">
            <x-ui.input name="name" label="Nama kejuaraan" :value="$tournament?->name" required />

            <div class="grid gap-3.5 sm:grid-cols-2">
                <x-ui.input name="organizer" label="Penyelenggara" :value="$tournament?->organizer"
                            hint="Mis. Pengurus Cabang IPSI Semarang." />
                <x-ui.input name="venue" label="Tempat" :value="$tournament?->venue" />
            </div>

            <x-ui.textarea name="description" label="Keterangan" :value="$tournament?->description" rows="3" />
        </div>
    </x-ui.card>

    <x-ui.card title="Jadwal dan pendaftaran">
        <div class="space-y-3.5">
            <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-1">
                <x-ui.input type="date" name="starts_on" label="Tanggal mulai"
                            :value="$tournament?->starts_on?->format('Y-m-d')" />
                <x-ui.input type="date" name="ends_on" label="Tanggal selesai"
                            :value="$tournament?->ends_on?->format('Y-m-d')" />
            </div>

            <div class="grid gap-3.5 border-t border-line pt-3.5 sm:grid-cols-2 lg:grid-cols-1">
                <x-ui.input type="datetime-local" name="registration_opens_at" label="Pendaftaran dibuka"
                            :value="$tournament?->registration_opens_at?->format('Y-m-d\TH:i')" />
                <x-ui.input type="datetime-local" name="registration_closes_at" label="Pendaftaran ditutup"
                            :value="$tournament?->registration_closes_at?->format('Y-m-d\TH:i')"
                            hint="Paling lambat hari pertama bertanding — bagan disusun dari peserta yang sudah terkunci." />
            </div>
        </div>
    </x-ui.card>
</div>
