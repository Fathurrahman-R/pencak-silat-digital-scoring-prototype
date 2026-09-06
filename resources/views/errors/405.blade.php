{{--
    Method Not Allowed.

    Tanpa berkas ini, alamat yang hanya menerima POST -- endpoint sinkron antar
    laptop gelanggang, misalnya -- membalas halaman galat Symfony polos
    "An Error Occurred: Method Not Allowed" saat dibuka dari bilah alamat.
    Halaman itu tidak menyebut nama aplikasi, tidak punya jalan kembali, dan
    membuat petugas mengira servernya rusak.
--}}
@include('errors.partials.layout', [
    'code' => '405',
    'title' => 'Alamat ini tidak dibuka lewat peramban',
    'message' => 'Alamat ini hanya melayani permintaan dari sistem, bukan dari bilah alamat. Kalau Anda sedang memeriksa sinkronisasi antar gelanggang, buka menu Sinkron Gelanggang.',
])
