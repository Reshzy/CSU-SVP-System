import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

type Props = {
    meeting: {
        id: number;
        title: string;
        status: string;
        meeting_datetime: string;
        location: string | null;
        agenda: string | null;
        minutes: string | null;
    };
};

export default function BacMeetingsShow({ meeting }: Props) {
    return (
        <>
            <Head title={meeting.title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title={meeting.title} description={`${meeting.status} · ${new Date(meeting.meeting_datetime).toLocaleString()}`} />
                <p className="text-sm">Location: {meeting.location ?? '—'}</p>
                <p className="text-sm whitespace-pre-wrap">{meeting.agenda}</p>
            </div>
        </>
    );
}
