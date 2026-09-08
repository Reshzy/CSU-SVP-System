import { Form, Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { ApprovalStatusBadge } from '@/components/approval-status-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { approve, idProof, index, reject } from '@/routes/ceo/users';
import type { SvpRole, User } from '@/types';

type IdProofFile = {
    id: number;
    file_name: string;
    mime_type: string | null;
    file_size: number | null;
    created_at: string;
};

type Props = {
    user: User;
    roles: SvpRole[];
    mappedRole: SvpRole;
    idProofs: IdProofFile[];
};

function formatBytes(bytes: number | null): string {
    if (!bytes) {
        return '—';
    }

    const units = ['B', 'KB', 'MB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

export default function CeoUsersShow({
    user,
    roles,
    mappedRole,
    idProofs,
}: Props) {
    const isPending = user.approval_status === 'pending';

    return (
        <>
            <Head title={user.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading title={user.name} description={user.email} />
                    <ApprovalStatusBadge status={user.approval_status} />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Registration details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Department
                                </span>
                                <span>
                                    {user.department
                                        ? `${user.department.code} — ${user.department.name}`
                                        : '—'}
                                </span>
                            </div>
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Position
                                </span>
                                <span>{user.position?.name ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Employee ID
                                </span>
                                <span>{user.employee_id ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Phone
                                </span>
                                <span>{user.phone ?? '—'}</span>
                            </div>

                            <Separator />

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-muted-foreground">
                                    {roles.length > 0
                                        ? 'Current role'
                                        : 'Role on approval'}
                                </span>
                                <span className="flex flex-wrap gap-1">
                                    {roles.length > 0 ? (
                                        roles.map((role) => (
                                            <Badge
                                                key={role}
                                                variant="secondary"
                                            >
                                                {role}
                                            </Badge>
                                        ))
                                    ) : (
                                        <Badge variant="outline">
                                            {mappedRole}
                                        </Badge>
                                    )}
                                </span>
                            </div>

                            {user.rejection_reason && (
                                <>
                                    <Separator />
                                    <div className="space-y-1">
                                        <span className="text-muted-foreground">
                                            Rejection reason
                                        </span>
                                        <p>{user.rejection_reason}</p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Government-issued ID</CardTitle>
                            <CardDescription>
                                Confirm the identity before approving.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {idProofs.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    No ID was uploaded with this registration.
                                </p>
                            )}

                            {idProofs.map((proof) => (
                                <a
                                    key={proof.id}
                                    href={idProof([user.id, proof.id]).url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="hover:bg-accent flex items-center gap-3 rounded-lg border p-3 text-sm"
                                >
                                    <FileText className="text-muted-foreground size-4 shrink-0" />
                                    <span className="min-w-0 flex-1 truncate">
                                        {proof.file_name}
                                    </span>
                                    <span className="text-muted-foreground shrink-0 text-xs">
                                        {formatBytes(proof.file_size)}
                                    </span>
                                </a>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                {isPending && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Approve</CardTitle>
                                <CardDescription>
                                    Activates the account and assigns the{' '}
                                    {mappedRole} role.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...approve.form(user.id)}
                                    disableWhileProcessing
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            data-test="approve-user-button"
                                        >
                                            {processing && <Spinner />}
                                            Approve {user.name}
                                        </Button>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Reject</CardTitle>
                                <CardDescription>
                                    The reason is stored on the account.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...reject.form(user.id)}
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
                                                    placeholder="ID does not match the submitted name"
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
                                                data-test="reject-user-button"
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

CeoUsersShow.layout = {
    breadcrumbs: [{ title: 'User approvals', href: index() }],
};
