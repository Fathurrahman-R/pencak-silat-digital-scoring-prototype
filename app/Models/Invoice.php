<?php

namespace App\Models;

use App\Enums\StatusInvoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'contingent_id',
        'number',
        'status',
        'total_amount',
        'locked_at',
        'paid_at',
        'paid_via',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusInvoice::class,
            'total_amount' => 'integer',
            'locked_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function contingent(): BelongsTo
    {
        return $this->belongsTo(Contingent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function lunas(): bool
    {
        return $this->status === StatusInvoice::Lunas;
    }

    public function rupiah(): string
    {
        return 'Rp '.number_format($this->total_amount, 0, ',', '.');
    }

    /**
     * Nomor tagihan berikutnya.
     *
     * Berurutan lintas kejuaraan, karena dipakai sebagai dasar `order_id` di
     * gerbang pembayaran yang menolak nomor pesanan berulang.
     */
    public static function nomorBerikutnya(): string
    {
        $terakhir = self::query()->orderByDesc('id')->value('number');
        $urut = $terakhir ? ((int) substr($terakhir, -5)) + 1 : 1;

        return 'INV-'.now()->format('Ym').'-'.str_pad((string) $urut, 5, '0', STR_PAD_LEFT);
    }
}
