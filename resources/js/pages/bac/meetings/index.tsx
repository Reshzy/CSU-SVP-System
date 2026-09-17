import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { Paginated } from '@/types';

type Meeting = { id: number; title: string; status: string; meeting_datetime: string };

type Props = { meetings: Paginated<Meeting> };

export default function BacMeetingsIndex({ meetings }: Props) {
    return (
        <>
            <Head title="BAC meetings" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="BAC meetings" description="Thin schedule of BAC meetings." />
                <Link href="/bac/meetings/create" className="text-sm text-primary underline">
                    Schedule meeting
                </Link>
                <ul className="space-y-2 text-sm">
                    {meetings.data.map((meeting) => (
                        <li key={meeting.id} className="rounded-lg border p-3">
                            <Link href={`/bac/meetings/${meeting.id}`} className="font-medium underline">
                                {meeting.title}
                            </Link>
                            <p className="text-muted-foreground">
                                {meeting.status} · {new Date(meeting.meeting_datetime).toLocaleString()}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
