<?php

namespace App\Http\Requests\Admin;

class UpdateTournamentRequest extends StoreTournamentRequest
{
    // Aturannya sama persis. Yang membatasi penyuntingan bukan validasi
    // melainkan status kejuaraan, dan itu ditegakkan di controller.
}
