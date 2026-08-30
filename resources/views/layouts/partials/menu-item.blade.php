{{--
    Satu baris menu di sidebar.

    Dipisah jadi berkas sendiri karena dipanggil dari dua tempat: item yang
    berdiri di pucuk tanpa seksi, dan item di dalam seksi. Menyalinnya dua kali
    berarti dua tempat yang harus dijaga tetap sama — dan yang pertama kali
    berbeda tidak akan ada yang menyadarinya.

    `title` diisi supaya di lebar rail, saat labelnya disembunyikan, kursor
    masih bisa menanyakan ini menu apa. Itu bukan pengganti label bagi yang
    memakai layar sentuh atau pembaca layar — labelnya tetap ada di DOM, hanya
    disembunyikan CSS, jadi pembaca layar tetap membacakannya.
--}}

<a href="{{ $item['url'] ?? '#' }}"
   title="{{ $item['label'] }}"
   data-rail="center"
   @if ($item['active']) aria-current="page" @endif
   @class([
       'flex min-h-10 items-center gap-2.5 overflow-hidden rounded-[var(--radius-kecil)] px-2.5 text-[14px] whitespace-nowrap',
       'focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none',

       // Yang sedang dibuka ditandai bidang tinta penuh, bukan rona lembut: di
       // layar terang, aksen lembut di atas kertas hampir tidak terbaca
       // sebagai "sedang di sini".
       'bg-accent font-semibold text-accent-on' => $item['active'],
       'text-ink-secondary hover:bg-surface-inset hover:text-ink' => ! $item['active'],
   ])>
    @if ($item['icon'])
        <x-si.ikon :nama="$item['icon']" class="size-[18px] shrink-0" />
    @endif

    <span class="flex-1 truncate" data-rail="hide">{{ $item['label'] }}</span>

    @if ($item['badge'])
        <span @class([
            'shrink-0 rounded-[var(--radius-kecil)] px-1.5 py-px text-[12px] font-semibold',
            'bg-accent-on/15 text-accent-on' => $item['active'],
            'bg-warning-soft text-warning' => ! $item['active'],
        ]) data-rail="hide">{{ $item['badge'] }}</span>
    @endif
</a>
