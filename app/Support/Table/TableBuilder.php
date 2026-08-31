<?php

namespace App\Support\Table;

use App\Support\Ekspor\TulisCsv;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pembungkus tipis di atas query Eloquent untuk kebutuhan tabel admin:
 * pencarian, pengurutan, penyaringan, paginasi, dan ekspor CSV.
 *
 * Dipakai dari controller:
 *
 *     $table = TableBuilder::for(User::query())
 *         ->searchable(['name', 'email'])
 *         ->sortable(['name', 'email', 'created_at'], default: 'created_at', direction: 'desc')
 *         ->filter('role', fn ($query, $value) => $query->whereHas('roles',
 *             fn ($q) => $q->where('name', $value)))
 *         ->perPage(15);
 *
 *     return view('admin.users.index', ['users' => $table->paginate(), 'table' => $table]);
 *
 * Kolom yang boleh diurutkan wajib didaftarkan lewat sortable(). Nilai ?sort=
 * di luar daftar itu diabaikan, bukan diteruskan ke query — kalau diteruskan,
 * parameter URL berubah jadi jalan masuk SQL injection.
 */
class TableBuilder
{
    /** @var array<int, string> */
    private array $searchable = [];

    /** @var array<int, string> */
    private array $sortable = [];

    private ?string $defaultSort = null;

    private string $defaultDirection = 'asc';

    /** @var array<string, Closure> */
    private array $filters = [];

    private int $perPage = 15;

    private string $searchParameter = 'q';

    private function __construct(
        private readonly Builder $query,
        private readonly Request $request,
    ) {}

    public static function for(Builder $query, ?Request $request = null): self
    {
        return new self($query, $request ?? request());
    }

    /**
     * Kolom yang ikut dicari. Boleh menyeberang relasi dengan notasi titik,
     * misalnya 'roles.name'.
     *
     * @param  array<int, string>  $columns
     */
    public function searchable(array $columns, string $parameter = 'q'): self
    {
        $this->searchable = $columns;
        $this->searchParameter = $parameter;

        return $this;
    }

    /** @param  array<int, string>  $columns */
    public function sortable(array $columns, ?string $default = null, string $direction = 'asc'): self
    {
        $this->sortable = $columns;
        $this->defaultSort = $default;
        $this->defaultDirection = $direction === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    /**
     * Filter dijalankan hanya kalau parameter dengan nama yang sama ada di URL
     * dan tidak kosong.
     */
    public function filter(string $key, Closure $callback): self
    {
        $this->filters[$key] = $callback;

        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function query(): Builder
    {
        $query = clone $this->query;

        $this->applySearch($query);
        $this->applyFilters($query);
        $this->applySort($query);

        return $query;
    }

    public function paginate(): LengthAwarePaginator
    {
        $perPage = (int) $this->request->integer('per_page', $this->perPage);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : $this->perPage;

        return $this->query()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Mengunduh seluruh hasil (tanpa paginasi) sebagai CSV.
     *
     * @param  Closure(mixed): array<string, mixed>  $row  memetakan satu model jadi satu baris
     */
    public function download(Closure $row, string $filename): StreamedResponse
    {
        $query = $this->query();

        return response()->streamDownload(function () use ($query, $row) {
            $handle = fopen('php://output', 'w');

            // BOM supaya Excel di Windows membaca UTF-8 dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");

            $headerWritten = false;

            $query->chunk(500, function ($records) use ($handle, $row, &$headerWritten) {
                foreach ($records as $record) {
                    $line = $row($record);

                    if (! $headerWritten) {
                        TulisCsv::tulis($handle, array_keys($line));
                        $headerWritten = true;
                    }

                    TulisCsv::tulis($handle, array_values($line));
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function search(): ?string
    {
        $term = $this->request->string($this->searchParameter)->trim()->value();

        return $term === '' ? null : $term;
    }

    public function searchParameter(): string
    {
        return $this->searchParameter;
    }

    public function sortColumn(): ?string
    {
        $requested = $this->request->string('sort')->value();

        if ($requested !== '' && in_array($requested, $this->sortable, true)) {
            return $requested;
        }

        return $this->defaultSort;
    }

    public function sortDirection(): string
    {
        $requested = strtolower($this->request->string('direction')->value());

        if (in_array($requested, ['asc', 'desc'], true)) {
            return $requested;
        }

        return $this->defaultDirection;
    }

    public function isSortable(string $column): bool
    {
        return in_array($column, $this->sortable, true);
    }

    /** URL untuk mengurutkan kolom ini; membalik arah kalau sudah aktif. */
    public function sortUrl(string $column): string
    {
        $direction = $this->sortColumn() === $column && $this->sortDirection() === 'asc' ? 'desc' : 'asc';

        return $this->request->fullUrlWithQuery([
            'sort' => $column,
            'direction' => $direction,
            'page' => null,
        ]);
    }

    public function hasActiveFilters(): bool
    {
        if ($this->search() !== null) {
            return true;
        }

        foreach (array_keys($this->filters) as $key) {
            if (filled($this->request->input($key))) {
                return true;
            }
        }

        return false;
    }

    public function resetUrl(): string
    {
        return $this->request->url();
    }

    private function applySearch(Builder $query): void
    {
        $term = $this->search();

        if ($term === null || $this->searchable === []) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            foreach ($this->searchable as $column) {
                if (! Str::contains($column, '.')) {
                    $query->orWhere($column, 'like', "%{$term}%");

                    continue;
                }

                [$relation, $relationColumn] = explode('.', $column, 2);

                $query->orWhereHas(
                    $relation,
                    fn (Builder $relationQuery) => $relationQuery->where($relationColumn, 'like', "%{$term}%")
                );
            }
        });
    }

    private function applyFilters(Builder $query): void
    {
        foreach ($this->filters as $key => $callback) {
            $value = $this->request->input($key);

            if (filled($value)) {
                $callback($query, $value);
            }
        }
    }

    private function applySort(Builder $query): void
    {
        $column = $this->sortColumn();

        if ($column === null) {
            return;
        }

        $query->orderBy($column, $this->sortDirection());
    }
}
