@php($contingent ??= null)

<x-ui.card title="Identitas kontingen" class="max-w-3xl">
    <div class="grid gap-3.5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-ui.input name="name" label="Nama kontingen" :value="$contingent?->name" required
                        hint="Nama inilah yang dipanggil announcer dan tercetak di bagan." />
        </div>

        <x-ui.input name="region" label="Daerah" :value="$contingent?->region" />

        <x-ui.select name="user_id" label="Official pengelola" :options="$officials"
                     :selected="$contingent?->user_id"
                     hint="Official hanya bisa melihat dan mengelola kontingen yang ditugaskan kepadanya." />

        <x-ui.input name="contact_name" label="Nama kontak" :value="$contingent?->contact_name" />
        <x-ui.input name="contact_phone" label="Telepon kontak" :value="$contingent?->contact_phone" />
    </div>
</x-ui.card>
