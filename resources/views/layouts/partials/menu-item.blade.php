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
       // `shrink-0`: nav adalah kolom flex yang bisa digulir, dan flex-child
       // BAWAANNYA boleh menyusut di bawah tingginya sendiri begitu isinya
       // lebih panjang dari ruang yang ada. Di layar pendek -- HP tegak dengan
       // dua puluh menu, apalagi HP dipegang miring -- tinggi 38px terperas
       // sampai 20px, ikonnya menempel ke ikon baris berikutnya, dan sasaran
       // sentuhnya mengecil justru di perangkat yang paling butuh sasaran
       // besar. Yang benar: barisnya tetap setinggi 38px, navnya yang digulir.
       'flex h-9.5 shrink-0 items-center gap-2.5 overflow-hidden rounded-[var(--radius)] px-3 text-[13.5px] whitespace-nowrap',
       'focus-visible:ring-[3px] focus-visible:ring-ink/10 focus-visible:outline-none',

       // DESIGN-SYSTEM.md §6: yang aktif adalah kartu putih bertepi di atas
       // sidebar yang ber-latar #fafafa -- bukan bidang tinta penuh. Bedanya
       // permukaan, bukan warna, jadi menu tidak pernah bersaing dengan aksi
       // utama halaman yang justru bertinta.
       'border border-line bg-surface-raised font-medium text-ink' => $item['active'],
       'text-ink-secondary hover:bg-surface-inset hover:text-ink' => ! $item['active'],
   ])>
    @if ($item['icon'])
        <x-si.ikon :nama="$item['icon']" class="size-4 shrink-0" />
    @endif

    <span class="flex-1 truncate" data-rail="hide">{{ $item['label'] }}</span>

    @if ($item['badge'])
        <span @class([
            'shrink-0 rounded-[var(--radius-kecil)] px-1.5 py-px text-[12px] font-medium',
            'bg-surface-inset text-ink-secondary' => $item['active'],
            'bg-warning-soft text-warning' => ! $item['active'],
        ]) data-rail="hide">{{ $item['badge'] }}</span>
    @endif
</a>
