import { Form, Head } from '@inertiajs/react';
import { ApprovalStatusBadge } from '@/components/approval-status-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { approve, index, reject } from '@/routes/ceo/department-requests';
import type { ApprovalStatus } from '@/types';

type DepartmentRequestDetail = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    head_name: string | null;
    contact_person: string | null;
    contact_email: string | null;
    contact_number: string | null;
    requester_email: string;
    status: ApprovalStatus;
    rejection_reason: string | null;
    reviewed_at: string | null;
    reviewer?: { id: number; name: string } | null;
};

type Props = {
    departmentRequest: DepartmentRequestDetail;
};

export default function CeoDepartmentRequestsShow({
    departmentRequest,
}: Props) {
    const isPending = departmentRequest.status === 'pending';

    const details: [string, string | null][] = [
        ['Code', departmentRequest.code],
        ['Description', departmentRequest.description],
        ['Department head', departmentRequest.head_name],
        ['Contact person', departmentRequest.contact_person],
        ['Contact email', departmentRequest.contact_email],
        ['Contact number', departmentRequest.contact_number],
        ['Requested by', departmentRequest.requester_email],
    ];

    return (
        <>
            <Head title={departmentRequest.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={departmentRequest.name}
                        description="Approving creates the department immediately."
                    />
                    <ApprovalStatusBadge status={departmentRequest.status} />
                </div>

                <Card className="max-w-xl">
                    <CardHeader>
                        <CardTitle>Requested details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        {details.map(([label, value]) => (
                            <div
                                key={label}
                                className="flex justify-between gap-4"
                            >
                                <span className="text-muted-foreground">
                                    {label}
                                </span>
                                <span>{value ?? '—'}</span>
                            </div>
                        ))}

                        {departmentRequest.rejection_reason && (
                            <>
                                <Separator />
                                <div className="space-y-1">
                                    <span className="text-muted-foreground">
                                        Rejection reason
                                    </span>
                                    <p>{departmentRequest.rejection_reason}</p>
                                </div>
                            </>
                        )}

                        {departmentRequest.reviewer && (
                            <>
                                <Separator />
                                <div className="flex justify-between gap-4">
                                    <span className="text-muted-foreground">
                                        Reviewed by
                                    </span>
                                    <span>
                                        {departmentRequest.reviewer.name}
                                    </span>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                {isPending && (
                    <div className="grid max-w-4xl gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Approve</CardTitle>
                                <CardDescription>
                                    Creates {departmentRequest.code} and makes
                                    it selectable at registration.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...approve.form(departmentRequest.id)}
                                    disableWhileProcessing
                                    className="space-y-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <InputError
                                                message={
                                                    errors.name ?? errors.code
                                                }
                                            />
                                            <Button
                                                type="submit"
                                                data-test="approve-department-request-button"
                                            >
                                                {processing && <Spinner />}
                                                Create department
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Reject</CardTitle>
                                <CardDescription>
                                    The reason is stored on the request.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...reject.form(departmentRequest.id)}
                                    disableWhileProcessing
                                    className="space-y-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="rejection_reason">
                                                    Reason
                                                </Label>
                                                <Input
                                                    id="rejection_reason"
                                                    name="rejection_reason"
                                                    required
                                                    placeholder="Duplicate of an existing college"
                                                />
                                                <InputError
                                                    message={
                                                        errors.rejection_reason
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                data-test="reject-department-request-button"
                                            >
                                                {processing && <Spinner />}
                                                Reject
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </>
    );
}

CeoDepartmentRequestsShow.layout = {
    breadcrumbs: [{ title: 'Department requests', href: index() }],
};
