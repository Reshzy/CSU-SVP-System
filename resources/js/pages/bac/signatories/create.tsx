import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type User = { id: number; name: string };

type Props = { users: User[] };

export default function BacSignatoriesCreate({ users }: Props) {
    const form = useForm({
        user_id: String(users[0]?.id ?? ''),
        position: 'bac_chairman',
        prefix: '',
        suffix: '',
        is_active: true,
    });

    return (
        <>
            <Head title="Add BAC signatory" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Add BAC signatory" />
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({ ...data, user_id: Number(data.user_id) })).post('/bac/signatories');
                    }}
                >
                    <div className="space-y-2">
                        <Label>User</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.user_id}
                            onChange={(event) => form.setData('user_id', event.target.value)}
                        >
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label>Position</Label>
                        <Input value={form.data.position} onChange={(event) => form.setData('position', event.target.value)} />
                    </div>
                    <Button type="submit">Save</Button>
                </form>
            </div>
        </>
    );
}
