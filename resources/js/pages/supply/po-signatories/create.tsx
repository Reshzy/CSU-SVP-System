import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/supply/po-signatories';

type User = { id: number; name: string };
type Props = { users: User[] };

export default function SupplyPoSignatoriesCreate({ users }: Props) {
    const form = useForm({
        user_id: users[0] ? String(users[0].id) : '',
        manual_name: '',
        position: 'ceo',
        prefix: '',
        suffix: '',
        is_active: true,
    });

    return (
        <>
            <Head title="Add PO signatory" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Add PO signatory" />
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            user_id: data.user_id === '' ? null : Number(data.user_id),
                        })).post(store.url());
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
                    <div className="space-y-2">
                        <Label>Suffix</Label>
                        <Input value={form.data.suffix} onChange={(event) => form.setData('suffix', event.target.value)} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                </form>
            </div>
        </>
    );
}

SupplyPoSignatoriesCreate.layout = {
    breadcrumbs: [{ title: 'PO signatories', href: index() }],
};
