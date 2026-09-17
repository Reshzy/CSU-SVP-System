import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type User = { id: number; name: string };
type Signatory = {
    id: number;
    user_id: number;
    position: string;
    prefix: string | null;
    suffix: string | null;
    is_active: boolean;
};

type Props = { signatory: Signatory; users: User[] };

export default function BacSignatoriesEdit({ signatory, users }: Props) {
    const form = useForm({
        user_id: String(signatory.user_id),
        position: signatory.position,
        prefix: signatory.prefix ?? '',
        suffix: signatory.suffix ?? '',
        is_active: signatory.is_active,
    });

    return (
        <>
            <Head title="Edit BAC signatory" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Edit BAC signatory" />
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({ ...data, user_id: Number(data.user_id) })).put(`/bac/signatories/${signatory.id}`);
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
