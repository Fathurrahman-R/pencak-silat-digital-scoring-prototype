{{--
    Method Not Allowed.

    Tanpa berkas ini, alamat yang hanya menerima POST -- endpoint sinkron antar
    laptop gelanggang, unggahan berkas atlet -- membalas halaman galat Symfony
    polos "An Error Occurred: Method Not Allowed" saat dibuka dari bilah
    alamat. Halaman itu tidak menyebut nama aplikasi, tidak punya jalan
    kembali, dan membuat petugas mengira servernya rusak.

    Kalimatnya sengaja tidak menebak-nebak alamat mana yang sedang dibuka:
    sebutan yang keliru ("buka menu Sinkron Gelanggang" pada halaman unggah
    berkas) menyesatkan lebih jauh daripada tidak menyebut apa pun.
--}}
@include('errors.partials.layout', [
    'code' => '405',
    'title' => 'Alamat ini tidak dibuka lewat peramban',
    'message' => 'Alamat ini hanya melayani permintaan yang dikirim aplikasi, bukan yang diketik di bilah alamat. Kembali ke halaman sebelumnya, lalu pakai tombol yang tersedia di sana.',
])
