@props([
    'judul' => 'Belum ada isinya',

    // WAJIB diisi: keadaan kosong harus menyebutkan APA YANG MEMBUKA ISINYA,
    // bukan sekadar "tidak ada data". Panitia yang membuka layar kosong perlu
    // tahu langkah apa yang membuatnya terisi.
    'syarat',
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-start gap-2 rounded-[var(--radius)] border border-dashed border-line-strong px-5 py-8']) }}>
    <p class="text-[16px] font-semibold text-ink">{{ $judul }}</p>
    <p class="max-w-[64ch] text-[14px] leading-relaxed text-ink-muted">{{ $syarat }}</p>
    @isset($aksi)
        <div class="mt-2">{{ $aksi }}</div>
    @endisset
</div>
