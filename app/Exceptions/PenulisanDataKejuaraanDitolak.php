<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Node gelanggang menolak menulis data kejuaraan -- dengan kalimat, bukan
 * dengan halaman galat.
 *
 * Penjagaannya lahir sebagai RuntimeException biasa, dan yang terlihat di
 * peramban adalah "Server Error" berlatar putih: penolakan yang benar,
 * disampaikan seolah aplikasinya rusak. Panitia yang membacanya akan mencoba
 * lagi, lalu menelepon.
 *
 * 422, bukan 403: yang salah bukan siapa yang menekan, melainkan MESIN tempat
 * ia menekannya -- dan kalimatnya menyebut ke mana harus pergi.
 */
class PenulisanDataKejuaraanDitolak extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->withErrors(['sinkron' => $this->getMessage()]);
    }
}
