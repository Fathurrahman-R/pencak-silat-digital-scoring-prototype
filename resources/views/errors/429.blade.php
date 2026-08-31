@include('errors.partials.layout', [
    'code' => '429',
    'title' => 'Terlalu banyak percobaan',
    'message' => 'Percobaan masuk dibatasi supaya kata sandi tidak bisa ditebak berulang-ulang. '
        .'Tunggu sekitar satu menit, lalu coba lagi.',
])
