import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, update } from '@/routes/supply/po-signatories';

type User = { id: number; name: string };
type Signatory = {
    id: number;
    user_id: number | null;
    manual_name: string | null;
    position: string;
    prefix: string | null;
    suffix: string | null;
    is_active: boolean;
};

type Props = { signatory: Signatory; users: User[] };

export default function SupplyPoSignatoriesEdit({ signatory, users }: Props) {
    const form = useForm({
        user_id: signatory.user_id ? String(signatory.user_id) : '',
        manual_name: signatory.manual_name ?? '',
        position: signatory.position,
        prefix: signatory.prefix ?? '',
        suffix: signatory.suffix ?? '',
        is_active: signatory.is_active,
    });

    return (
        <>
            <Head title="Edit PO signatory" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Edit PO signatory" />
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            user_id: data.user_id === '' ? null : Number(data.user_id),
                        }));
                        form.put(update.url(signatory.id));
                    }}
                >
                    <div className="space-y-2">
                        <Label>User</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.user_id}
                            onChange={(event) => form.setData('user_id', event.target.value)}
                        >
                            <option value="">Manual name instead</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label>Manual name</Label>
                        <Input
                            value={form.data.manual_name}
                            onChange={(event) => form.setData('manual_name', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Position</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.position}
                            onChange={(event) => form.setData('position', event.target.value)}
                        >
                            <option value="ceo">CEO</option>
                            <option value="chief_accountant">Chief accountant</option>
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label>Prefix</Label>
                        <Input value={form.data.prefix} onChange={(event) => form.setData('prefix', event.target.value)} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                </form>
            </div>
        </>
    );
}

SupplyPoSignatoriesEdit.layout = {
    breadcrumbs: [{ title: 'PO signatories', href: index() }],
};
