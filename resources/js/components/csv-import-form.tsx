import { Form } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    /** Spread from a Wayfinder `.form()` helper. */
    action: ComponentProps<typeof Form>['action'];
    method: ComponentProps<typeof Form>['method'];
    fiscalYear: number;
    submitLabel: string;
    /** Shown above the fields to explain what this particular import does. */
    children?: ReactNode;
};

export function CsvImportForm({
    action,
    method,
    fiscalYear,
    submitLabel,
    children,
}: Props) {
    return (
        <Form
            action={action}
            method={method}
            disableWhileProcessing
            className="max-w-xl space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    {children}

                    <div className="grid gap-2">
                        <Label htmlFor="fiscal_year">Fiscal year</Label>
                        <Input
                            id="fiscal_year"
                            name="fiscal_year"
                            type="number"
                            required
                            min={2020}
                            max={2100}
                            defaultValue={fiscalYear}
                            className="w-40"
                        />
                        <InputError message={errors.fiscal_year} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="csv_file">APP-CSE worksheet</Label>
                        <Input
                            id="csv_file"
                            name="csv_file"
                            type="file"
                            required
                            accept=".csv,text/csv,text/plain"
                        />
                        <p className="text-muted-foreground text-xs">
                            CSV export of the APP-CSE form, up to 10 MB.
                        </p>
                        <InputError message={errors.csv_file} />
                    </div>

                    <Button type="submit" data-test="import-csv-button">
                        {processing ? (
                            <Spinner />
                        ) : (
                            <Upload className="size-4" />
                        )}
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
