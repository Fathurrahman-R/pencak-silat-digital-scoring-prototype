@php($contingent ??= null)

<x-ui.card title="Identitas kontingen">
    <div class="space-y-4">
        <x-ui.input name="name" label="Nama kontingen" :value="$contingent?->name" required
                    hint="Nama inilah yang dipanggil announcer dan tercetak di bagan." />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="region" label="Daerah" :value="$contingent?->region" />

            <x-ui.select name="user_id" label="Official pengelola" :options="$officials"
                         :selected="$contingent?->user_id"
                         hint="Official hanya bisa melihat dan mengelola kontingen yang ditugaskan kepadanya." />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="contact_name" label="Nama kontak" :value="$contingent?->contact_name" />
            <x-ui.input name="contact_phone" label="Telepon kontak" :value="$contingent?->contact_phone" />
        </div>
    </div>
</x-ui.card>
