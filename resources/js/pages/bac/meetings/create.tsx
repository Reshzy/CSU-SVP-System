import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function BacMeetingsCreate() {
    const form = useForm({
        title: '',
        meeting_datetime: '',
        location: '',
        status: 'scheduled',
        agenda: '',
    });

    return (
        <>
            <Head title="Schedule BAC meeting" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Schedule BAC meeting" />
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/bac/meetings');
                    }}
                >
                    <div className="space-y-2">
                        <Label>Title</Label>
                        <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label>Date / time</Label>
                        <Input
                            type="datetime-local"
                            value={form.data.meeting_datetime}
                            onChange={(event) => form.setData('meeting_datetime', event.target.value)}
                        />
                    </div>
                    <Button type="submit">Save</Button>
                </form>
            </div>
        </>
    );
}
