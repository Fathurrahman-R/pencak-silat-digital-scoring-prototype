@php($tournament ??= null)

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <x-ui.card title="Identitas kejuaraan">
            <div class="space-y-4">
                <x-ui.input name="name" label="Nama kejuaraan" :value="$tournament?->name" required />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="organizer" label="Penyelenggara" :value="$tournament?->organizer"
                                hint="Mis. Pengurus Cabang IPSI Semarang." />
                    <x-ui.input name="venue" label="Tempat" :value="$tournament?->venue" />
                </div>

                <x-ui.textarea name="description" label="Keterangan" :value="$tournament?->description" rows="3" />
            </div>
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card title="Jadwal">
            <div class="space-y-4">
                <x-ui.input type="date" name="starts_on" label="Tanggal mulai"
                            :value="$tournament?->starts_on?->format('Y-m-d')" />
                <x-ui.input type="date" name="ends_on" label="Tanggal selesai"
                            :value="$tournament?->ends_on?->format('Y-m-d')" />
            </div>
        </x-ui.card>

        <x-ui.card title="Pendaftaran">
            <div class="space-y-4">
                <x-ui.input type="datetime-local" name="registration_opens_at" label="Dibuka"
                            :value="$tournament?->registration_opens_at?->format('Y-m-d\TH:i')" />
                <x-ui.input type="datetime-local" name="registration_closes_at" label="Ditutup"
                            :value="$tournament?->registration_closes_at?->format('Y-m-d\TH:i')"
                            hint="Paling lambat hari pertama bertanding — bagan disusun dari peserta yang sudah terkunci." />
            </div>
        </x-ui.card>
    </div>
</div>
