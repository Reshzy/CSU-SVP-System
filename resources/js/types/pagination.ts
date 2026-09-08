export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Shape of Laravel's `LengthAwarePaginator` once serialized to JSON. */
export type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
    links: PaginationLink[];
};
