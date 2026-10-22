import { Button } from '@/components/ui/button';
import type { DataTableBulkAction } from './types';

type Props<TData> = {
    selected: TData[];
    actions: DataTableBulkAction<TData>[];
    onClear: () => void;
};

export function DataTableBulkBar<TData>({
    selected,
    actions,
    onClear,
}: Props<TData>) {
    if (selected.length === 0) {
        return null;
    }

    return (
        <div className="bg-muted/60 flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm">
            <span className="font-medium">{selected.length} selected</span>
            {actions.map((action) => (
                <Button
                    key={action.label}
                    type="button"
                    size="sm"
                    variant={action.variant === 'destructive' ? 'destructive' : 'outline'}
                    onClick={() => action.onAction(selected)}
                >
                    {action.label}
                </Button>
            ))}
            <Button type="button" size="sm" variant="ghost" onClick={onClear}>
                Clear
            </Button>
        </div>
    );
}
