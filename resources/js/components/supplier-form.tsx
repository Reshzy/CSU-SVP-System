import { Form } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type SupplierFormValues = {
    business_name: string;
    contact_person: string;
    email: string;
    phone: string;
    address: string;
    tin?: string | null;
    business_type: string;
    status?: string;
};

const businessTypes = [
    { value: 'sole_proprietorship', label: 'Sole proprietorship' },
    { value: 'partnership', label: 'Partnership' },
    { value: 'corporation', label: 'Corporation' },
    { value: 'cooperative', label: 'Cooperative' },
];

const statuses = [
    { value: 'pending_verification', label: 'Pending verification' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'blacklisted', label: 'Blacklisted' },
];

export function SupplierForm({
    action,
    method,
    supplier,
    submitLabel,
    includeStatus = false,
}: {
    action: ComponentProps<typeof Form>['action'];
    method: ComponentProps<typeof Form>['method'];
    supplier?: SupplierFormValues;
    submitLabel: string;
    includeStatus?: boolean;
}) {
    return (
        <Form
            action={action}
            method={method}
            disableWhileProcessing
            className="grid max-w-xl gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <div className="space-y-2">
                        <Label htmlFor="business_name">Business name</Label>
                        <Input
                            id="business_name"
                            name="business_name"
                            defaultValue={supplier?.business_name}
                            required
                        />
                        <InputError message={errors.business_name} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="contact_person">Contact person</Label>
                        <Input
                            id="contact_person"
                            name="contact_person"
                            defaultValue={supplier?.contact_person ?? ''}
                            required
                        />
                        <InputError message={errors.contact_person} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            defaultValue={supplier?.email ?? ''}
                            required
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="phone">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            defaultValue={supplier?.phone ?? ''}
                            required
                        />
                        <InputError message={errors.phone} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="address">Address</Label>
                        <Textarea
                            id="address"
                            name="address"
                            defaultValue={supplier?.address ?? ''}
                            required
                        />
                        <InputError message={errors.address} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="tin">TIN</Label>
                        <Input id="tin" name="tin" defaultValue={supplier?.tin ?? ''} />
                        <InputError message={errors.tin} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="business_type">Business type</Label>
                        <select
                            id="business_type"
                            name="business_type"
                            defaultValue={supplier?.business_type ?? 'corporation'}
                            className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                        >
                            {businessTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.business_type} />
                    </div>
                    {includeStatus && (
                        <div className="space-y-2">
                            <Label htmlFor="status">Status</Label>
                            <select
                                id="status"
                                name="status"
                                defaultValue={supplier?.status ?? 'pending_verification'}
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.status} />
                        </div>
                    )}
                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
