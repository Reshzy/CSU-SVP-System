import { Form } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { type ComponentProps, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { CatalogItem, PlannedItem } from '@/types';

type Props = {
    /** Spread from a Wayfinder `.form()` helper. */
    action: ComponentProps<typeof Form>['action'];
    method: ComponentProps<typeof Form>['method'];
    fiscalYear: number;
    appItems: CatalogItem[];
    plannedItems: PlannedItem[];
    submitLabel: string;
};

const QUARTERS = [1, 2, 3, 4] as const;

export function PpmpForm({
    action,
    method,
    fiscalYear,
    appItems,
    plannedItems,
    submitLabel,
}: Props) {
    const [query, setQuery] = useState('');

    const planned = useMemo(
        () => new Map(plannedItems.map((item) => [item.app_item_id, item])),
        [plannedItems],
    );

    const needle = query.trim().toLowerCase();

    if (appItems.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                The PS-DBMS catalog for {fiscalYear} is empty. Import it before
                planning against it.
            </p>
        );
    }

    return (
        <Form
            action={action}
            method={method}
            disableWhileProcessing
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="fiscal_year"
                        value={fiscalYear}
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Filter by item name or code"
                                className="w-72 pl-8"
                            />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Leave an item at zero to keep it off the plan.
                        </p>
                    </div>

                    <InputError message={errors.items} />

                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Unit</TableHead>
                                    <TableHead className="text-right">
                                        Unit price
                                    </TableHead>
                                    {QUARTERS.map((quarter) => (
                                        <TableHead
                                            key={quarter}
                                            className="w-24 text-right"
                                        >
                                            Q{quarter}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {appItems.map((appItem, index) => {
                                    const line = planned.get(appItem.id);
                                    const priceError =
                                        errors[
                                            `items.${index}.custom_unit_price`
                                        ];
                                    const matches =
                                        needle === '' ||
                                        appItem.item_name
                                            .toLowerCase()
                                            .includes(needle) ||
                                        appItem.item_code
                                            .toLowerCase()
                                            .includes(needle);

                                    return (
                                        <TableRow
                                            key={appItem.id}
                                            className={cn(!matches && 'hidden')}
                                        >
                                            <TableCell>
                                                <input
                                                    type="hidden"
                                                    name={`items[${index}][app_item_id]`}
                                                    value={appItem.id}
                                                />
                                                <span className="font-medium">
                                                    {appItem.item_name}
                                                </span>
                                                <span className="text-muted-foreground block font-mono text-xs">
                                                    {appItem.item_code} ·{' '}
                                                    {appItem.category}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-xs">
                                                {appItem.unit_of_measure}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {appItem.unit_price !== null ? (
                                                    <span className="tabular-nums">
                                                        {appItem.unit_price}
                                                    </span>
                                                ) : (
                                                    <>
                                                        <Label
                                                            htmlFor={`custom-price-${appItem.id}`}
                                                            className="sr-only"
                                                        >
                                                            Unit price for{' '}
                                                            {appItem.item_name}
                                                        </Label>
                                                        <Input
                                                            id={`custom-price-${appItem.id}`}
                                                            name={`items[${index}][custom_unit_price]`}
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            placeholder="Set price"
                                                            defaultValue={
                                                                line?.estimated_unit_cost ??
                                                                ''
                                                            }
                                                            className="ml-auto w-28 text-right"
                                                        />
                                                        <InputError
                                                            message={priceError}
                                                        />
                                                    </>
                                                )}
                                            </TableCell>
                                            {QUARTERS.map((quarter) => (
                                                <TableCell key={quarter}>
                                                    <Label
                                                        htmlFor={`q${quarter}-${appItem.id}`}
                                                        className="sr-only"
                                                    >
                                                        Q{quarter} quantity for{' '}
                                                        {appItem.item_name}
                                                    </Label>
                                                    <Input
                                                        id={`q${quarter}-${appItem.id}`}
                                                        name={`items[${index}][q${quarter}_quantity]`}
                                                        type="number"
                                                        min={0}
                                                        defaultValue={
                                                            line?.[
                                                                `q${quarter}_quantity`
                                                            ] ?? 0
                                                        }
                                                        className="w-20 text-right"
                                                    />
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>

                    <Button type="submit" data-test="save-ppmp-button">
                        {processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
