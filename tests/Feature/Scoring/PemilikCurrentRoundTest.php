<?php

/*
 * `current_round` hanya boleh ditulis MatchTimer.
 *
 * Invariant ini sudah lama didokumentasikan di docblock MatchTimer, tapi tidak
 * pernah ditegakkan apa pun. Ia jadi genting sejak babak susulan lahir: seluruh
 * rancangan susulan berdiri di atas janji bahwa `current_round` tidak pernah
 * bergerak mundur, dan satu `update(['current_round' => ...])` yang diselipkan
 * di tempat lain membatalkan janji itu tanpa satu pun uji yang gagal.
 *
 * Uji ini membaca berkas, bukan menjalankan kode. Ia sengaja kasar: yang
 * dijaga adalah kebiasaan, bukan sebuah jalur eksekusi.
 */
it('hanya membiarkan MatchTimer menulis current_round', function () {
    $penulis = [];

    $berkas = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($berkas as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $isi = file_get_contents($file->getPathname());

        foreach (preg_split('/\R/', $isi) as $baris) {
            /*
             * Yang dicari PENULISAN, bukan pembacaan.
             *
             * Baris muatan siaran berbentuk `'current_round' => $match->current_round`
             * -- menyebutnya dua kali, dan yang kedua jelas pembacaan. Baris
             * penulisan menyebutnya sekali: `'current_round' => $babak`.
             * Deklarasi cast dan fillable disaring dengan cara yang sama.
             */
            $tulisArray = preg_match("/'current_round'\s*=>/", $baris)
                && substr_count($baris, 'current_round') === 1
                && ! str_contains($baris, "=> 'integer'");

            $tulisProperti = (bool) preg_match('/->current_round\s*=[^=>]/', $baris);

            if ($tulisArray || $tulisProperti) {
                $penulis[] = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                break;
            }
        }
    }

    sort($penulis);

    expect($penulis)->toBe(['Support'.DIRECTORY_SEPARATOR.'Scoring'.DIRECTORY_SEPARATOR.'MatchTimer.php']);
});
