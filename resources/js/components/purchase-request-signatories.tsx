import { Link } from '@inertiajs/react';
import { CircleHelp, Pencil } from 'lucide-react';
import nameDesignationExample from '../../images/name-designation.png';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit } from '@/routes/profile';

export type Signatory = {
    name: string | null;
    designation: string | null;
};

export type Signatories = {
    requestedBy: Signatory;
    approvedBy: Signatory;
    isRequester: boolean;
};

function SignatoryBlock({
    label,
    signatory,
}: {
    label: string;
    signatory: Signatory;
}) {
    return (
        <div className="space-y-1">
            <p className="text-muted-foreground text-xs tracking-wide uppercase">
                {label}
            </p>
            <div className="border-foreground/15 mt-6 border-b" aria-hidden />
            <p className="pt-1 font-medium">{signatory.name ?? 'Not set'}</p>
            <p className="text-muted-foreground text-sm italic">
                {signatory.designation ?? 'No designation'}
            </p>
        </div>
    );
}

function NameDesignationHelp() {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground size-7"
                    aria-label="Where the printed name and designation appear"
                    data-testid="pr-signatories-help"
                >
                    <CircleHelp />
                </Button>
            </TooltipTrigger>
            <TooltipContent
                side="bottom"
                align="start"
                className="max-w-md rounded-md p-2 font-sans text-xs tracking-normal normal-case"
            >
                <img
                    src={nameDesignationExample}
                    alt="Exported purchase request showing the Printed Name and Designation lines under Requested by"
                    className="w-full rounded-sm border bg-white"
                />
                <p className="mt-2">
                    Your name prints on the <strong>Printed Name</strong> line
                    and your designation on the <strong>Designation</strong>{' '}
                    line of the exported PR.
                </p>
            </TooltipContent>
        </Tooltip>
    );
}

export function PurchaseRequestSignatories({
    signatories,
}: {
    signatories: Signatories;
}) {
    return (
        <section
            className="bg-card rounded-lg border p-5 text-sm"
            data-testid="pr-signatories"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-1">
                    <h2 className="font-medium">Signatories</h2>
                    <NameDesignationHelp />
                </div>
                {signatories.isRequester && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={edit()}>
                            <Pencil />
                            Edit your name or designation
                        </Link>
                    </Button>
                )}
            </div>
            <div className="mt-4 grid gap-8 sm:grid-cols-2">
                <SignatoryBlock
                    label="Requested by"
                    signatory={signatories.requestedBy}
                />
                <SignatoryBlock
                    label="Approved by"
                    signatory={signatories.approvedBy}
                />
            </div>
        </section>
    );
}
