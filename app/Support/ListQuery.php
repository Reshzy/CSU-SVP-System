<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ListQuery
{
    /**
     * @param  array{
     *     searchable?: list<string>,
     *     sortable?: array<string, string>,
     *     equals?: array<string, string>,
     *     dateColumn?: string|null,
     *     defaultSort?: string|null,
     *     defaultDirection?: 'asc'|'desc',
     *     defaultPerPage?: int,
     *     allowedPerPage?: list<int>
     * }  $config
     */
    public static function paginate(Builder $query, Request $request, array $config): LengthAwarePaginator
    {
        self::applySearch($query, $request, $config['searchable'] ?? []);
        self::applyEquals($query, $request, $config['equals'] ?? []);
        self::applyDateRange($query, $request, $config['dateColumn'] ?? null);
        self::applySort($query, $request, $config);

        $allowedPerPage = $config['allowedPerPage'] ?? [10, 15, 20, 50, 100];
        $defaultPerPage = $config['defaultPerPage'] ?? 15;
        $perPage = $request->integer('per_page') ?: $defaultPerPage;

        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = $defaultPerPage;
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function snapshot(Request $request, array $extra = []): array
    {
        return [
            ...$extra,
            'search' => $request->filled('search') ? $request->string('search')->trim()->value() : null,
            'sort' => $request->filled('sort') ? $request->string('sort')->value() : null,
            'direction' => $request->filled('direction') ? $request->string('direction')->value() : null,
            'per_page' => $request->filled('per_page') ? $request->integer('per_page') : null,
            'page' => $request->filled('page') ? $request->integer('page') : null,
            'date_from' => $request->filled('date_from') ? $request->string('date_from')->value() : null,
            'date_to' => $request->filled('date_to') ? $request->string('date_to')->value() : null,
        ];
    }

    /**
     * @param  list<string>  $searchable
     */
    private static function applySearch(Builder $query, Request $request, array $searchable): void
    {
        if ($searchable === [] || ! $request->filled('search')) {
            return;
        }

        $term = '%'.$request->string('search')->trim()->value().'%';

        $query->where(function (Builder $nested) use ($searchable, $term): void {
            foreach ($searchable as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if (! self::isSafeIdentifier($relation) || ! self::isSafeIdentifier($relatedColumn)) {
                        continue;
                    }

                    $nested->orWhereHas($relation, function (Builder $related) use ($relatedColumn, $term): void {
                        $related->where($relatedColumn, 'like', $term);
                    });

                    continue;
                }

                if (! self::isSafeIdentifier($column)) {
                    continue;
                }

                $nested->orWhere($column, 'like', $term);
            }
        });
    }

    /**
     * @param  array<string, string>  $equals
     */
    private static function applyEquals(Builder $query, Request $request, array $equals): void
    {
        foreach ($equals as $requestKey => $column) {
            if (! $request->filled($requestKey) || ! self::isSafeIdentifier($column)) {
                continue;
            }

            $query->where($column, $request->input($requestKey));
        }
    }

    private static function applyDateRange(Builder $query, Request $request, ?string $dateColumn): void
    {
        if ($dateColumn === null || ! self::isSafeIdentifier($dateColumn)) {
            return;
        }

        if ($request->filled('date_from')) {
            $query->whereDate($dateColumn, '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate($dateColumn, '<=', $request->date('date_to'));
        }
    }

    /**
     * @param  array{
     *     sortable?: array<string, string>,
     *     defaultSort?: string|null,
     *     defaultDirection?: 'asc'|'desc'
     * }  $config
     */
    private static function applySort(Builder $query, Request $request, array $config): void
    {
        $sortable = $config['sortable'] ?? [];
        $sortKey = $request->string('sort')->value();
        $column = $sortable[$sortKey] ?? null;
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';

        if (is_string($column) && self::isSafeIdentifier($column)) {
            $query->orderBy($column, $request->filled('direction') ? $direction : ($config['defaultDirection'] ?? 'desc'));

            return;
        }

        $defaultSort = $config['defaultSort'] ?? null;

        if (is_string($defaultSort) && self::isSafeIdentifier($defaultSort)) {
            $query->orderBy($defaultSort, $config['defaultDirection'] ?? 'desc');
        }
    }

    private static function isSafeIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $value) === 1;
    }
}
