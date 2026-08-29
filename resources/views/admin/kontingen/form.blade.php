@php($contingent ??= null)

<x-si.kartu judul="Identitas kontingen" class="max-w-3xl">
    <div class="grid gap-3.5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-si.isian name="name" label="Nama kontingen" :value="$contingent?->name" wajib
                        bantuan="Nama inilah yang dipanggil announcer dan tercetak di bagan." />
        </div>

        <x-si.isian name="region" label="Daerah" :value="$contingent?->region" />

        <x-si.pilihan name="user_id" label="Official pengelola" :options="$officials"
                      :selected="$contingent?->user_id"
                      bantuan="Official hanya bisa melihat dan mengelola kontingen yang ditugaskan kepadanya." />

        <x-si.isian name="contact_name" label="Nama kontak" :value="$contingent?->contact_name" />
        <x-si.isian name="contact_phone" label="Telepon kontak" :value="$contingent?->contact_phone" />
    </div>
</x-si.kartu>
