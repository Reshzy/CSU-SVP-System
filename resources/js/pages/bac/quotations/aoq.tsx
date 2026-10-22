import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    download as downloadGroupAoq,
    generate as generateGroupAoq,
} from '@/routes/bac/item-groups/aoq';
import { index, manage } from '@/routes/bac/quotations';
import {
    bacOverride,
    consolidated,
    download as downloadAoq,
    generate as generateAoq,
    resolveTie,
} from '@/routes/bac/quotations/aoq';

type QuotationItem = {
    id: number;
    unit_price: string;
    total_price: string;
    is_tied: boolean;
    is_winner: boolean;
    is_withdrawn: boolean;
    quotation?: {
        quotation_number: string;
        supplier?: { business_name: string } | null;
    } | null;
};

type QuotableItem = {
    id: number;
    item_name: string | null;
    estimated_unit_cost: string;
    quotation_items: QuotationItem[];
};

type ItemGroup = {
    id: number;
    group_code: string;
    group_name: string;
};

type GroupAoqState = {
    unresolved_ties: number[];
    can_create_aoq: boolean;
    can_regenerate_aoq: boolean;
    latest_aoq: { aoq_reference_number: string } | null;
};

type Props = {
    purchaseRequest: {
        id: number;
        pr_number: string | null;
        quotable_items: QuotableItem[];
        item_groups: ItemGroup[];
    };
    unresolvedTies: number[];
    latestAoq: { aoq_reference_number: string } | null;
    groupAoq: Record<string, GroupAoqState>;
};

export default function BacAoqPage({
    purchaseRequest,
    unresolvedTies,
    latestAoq,
    groupAoq = {},
}: Props) {
    const groups = purchaseRequest.item_groups ?? [];
    const hasGroups = groups.length > 0;
    const groupedTieIds = hasGroups
        ? Object.values(groupAoq).flatMap((group) => group.unresolved_ties)
        : unresolvedTies;

    const tiedItems = useMemo(
        () =>
            purchaseRequest.quotable_items.filter((item) =>
                groupedTieIds.includes(item.id),
            ),
        [purchaseRequest.quotable_items, groupedTieIds],
    );

    const resolveForm = useForm({
        purchase_request_item_id: String(tiedItems[0]?.id ?? ''),
        winning_quotation_item_id: String(
            tiedItems[0]?.quotation_items.find((candidate) => candidate.is_tied)
                ?.id ?? '',
        ),
        justification: '',
    });

    const overrideForm = useForm({
        purchase_request_item_id: String(
            purchaseRequest.quotable_items[0]?.id ?? '',
        ),
        winning_quotation_item_id: String(
            purchaseRequest.quotable_items[0]?.quotation_items[0]?.id ?? '',
        ),
        justification: '',
    });

    const selectedTie = tiedItems.find(
        (item) => String(item.id) === resolveForm.data.purchase_request_item_id,
    );

    const selectedOverrideItem = purchaseRequest.quotable_items.find(
        (item) =>
            String(item.id) === overrideForm.data.purchase_request_item_id,
    );

    return (
        <>
            <Head title={`AOQ ${purchaseRequest.pr_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`AOQ · ${purchaseRequest.pr_number}`}
                    description="Abstract of Quotations"
                />

                <Button variant="outline" asChild>
                    <Link href={manage.url(purchaseRequest.id)}>
                        Back to quotations
                    </Link>
                </Button>

                {hasGroups && (
                    <div className="space-y-3 rounded-lg border p-4">
                        <h2 className="font-medium">Per-group AOQ</h2>
                        {groups.map((group) => {
                            const state = groupAoq[String(group.id)];
                            const canGenerate = state?.can_create_aoq ?? true;
                            const canRegenerate =
                                state?.can_regenerate_aoq ?? false;

                            return (
                                <div
                                    key={group.id}
                                    className="flex flex-wrap items-center gap-2"
                                >
                                    <span className="text-sm font-medium">
                                        {group.group_code} {group.group_name}
                                    </span>
                                    {(canGenerate || canRegenerate) && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            data-testid={`generate-aoq-${group.group_code}`}
                                            onClick={() =>
                                                router.post(
                                                    generateGroupAoq.url(
                                                        group.id,
                                                    ),
                                                )
                                            }
                                        >
                                            {canRegenerate
                                                ? 'Regenerate AOQ'
                                                : 'Generate AOQ'}
                                        </Button>
                                    )}
                                    {state?.latest_aoq && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={downloadGroupAoq.url(
                                                    group.id,
                                                )}
                                            >
                                                Download{' '}
                                                {
                                                    state.latest_aoq
                                                        .aoq_reference_number
                                                }
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(consolidated.url(purchaseRequest.id))
                            }
                        >
                            Generate consolidated AOQ
                        </Button>
                    </div>
                )}

                {tiedItems.length > 0 ? (
                    <form
                        className="grid max-w-2xl gap-3 rounded-lg border border-destructive/40 bg-destructive/5 p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            resolveForm.transform((data) => ({
                                purchase_request_item_id: Number(
                                    data.purchase_request_item_id,
                                ),
                                winning_quotation_item_id: Number(
                                    data.winning_quotation_item_id,
                                ),
                                justification: data.justification,
                            }));
                            resolveForm.post(resolveTie.url(purchaseRequest.id));
                        }}
                    >
                        <p className="text-sm">
                            Unresolved ties must be decided before generating
                            AOQ. Justification must be at least 10 characters.
                        </p>
                        <div className="space-y-2">
                            <Label>Tied item</Label>
                            <select
                                className="border-input h-9 w-full rounded-md border px-3 text-sm"
                                value={resolveForm.data.purchase_request_item_id}
                                onChange={(event) => {
                                    const nextItem = tiedItems.find(
                                        (item) =>
                                            String(item.id) ===
                                            event.target.value,
                                    );
                                    const firstTied =
                                        nextItem?.quotation_items.find(
                                            (candidate) => candidate.is_tied,
                                        );

                                    resolveForm.setData({
                                        ...resolveForm.data,
                                        purchase_request_item_id:
                                            event.target.value,
                                        winning_quotation_item_id: String(
                                            firstTied?.id ?? '',
                                        ),
                                    });
                                }}
                            >
                                {tiedItems.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.item_name ?? `Item ${item.id}`}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-2">
                            <Label>Winning quote</Label>
                            <select
                                className="border-input h-9 w-full rounded-md border px-3 text-sm"
                                value={
                                    resolveForm.data.winning_quotation_item_id
                                }
                                onChange={(event) =>
                                    resolveForm.setData(
                                        'winning_quotation_item_id',
                                        event.target.value,
                                    )
                                }
                            >
                                {(
                                    selectedTie?.quotation_items.filter(
                                        (candidate) => candidate.is_tied,
                                    ) ?? []
                                ).map((candidate) => (
                                    <option
                                        key={candidate.id}
                                        value={candidate.id}
                                    >
                                        {candidate.quotation?.supplier
                                            ?.business_name ??
                                            candidate.quotation
                                                ?.quotation_number}{' '}
                                        — {candidate.total_price}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-2">
                            <Label>Justification</Label>
                            <Textarea
                                value={resolveForm.data.justification}
                                onChange={(event) =>
                                    resolveForm.setData(
                                        'justification',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={resolveForm.errors.justification}
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={resolveForm.processing}
                        >
                            Resolve tie
                        </Button>
                    </form>
                ) : (
                    !hasGroups && (
                        <Button
                            onClick={() =>
                                router.post(generateAoq.url(purchaseRequest.id))
                            }
                        >
                            Generate AOQ
                        </Button>
                    )
                )}

                <form
                    className="grid max-w-2xl gap-3 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        overrideForm.transform((data) => ({
                            purchase_request_item_id: Number(
                                data.purchase_request_item_id,
                            ),
                            winning_quotation_item_id: Number(
                                data.winning_quotation_item_id,
                            ),
                            justification: data.justification,
                        }));
                        overrideForm.post(bacOverride.url(purchaseRequest.id));
                    }}
                >
                    <h2 className="font-medium">BAC override</h2>
                    <p className="text-sm text-muted-foreground">
                        Justification must be at least 20 characters.
                    </p>
                    <div className="space-y-2">
                        <Label>Item</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={overrideForm.data.purchase_request_item_id}
                            onChange={(event) => {
                                const nextItem =
                                    purchaseRequest.quotable_items.find(
                                        (item) =>
                                            String(item.id) ===
                                            event.target.value,
                                    );

                                overrideForm.setData({
                                    ...overrideForm.data,
                                    purchase_request_item_id:
                                        event.target.value,
                                    winning_quotation_item_id: String(
                                        nextItem?.quotation_items[0]?.id ?? '',
                                    ),
                                });
                            }}
                        >
                            {purchaseRequest.quotable_items.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.item_name ?? `Item ${item.id}`}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label>Winning quote</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={overrideForm.data.winning_quotation_item_id}
                            onChange={(event) =>
                                overrideForm.setData(
                                    'winning_quotation_item_id',
                                    event.target.value,
                                )
                            }
                        >
                            {(selectedOverrideItem?.quotation_items ?? []).map(
                                (candidate) => (
                                    <option
                                        key={candidate.id}
                                        value={candidate.id}
                                    >
                                        {candidate.quotation?.supplier
                                            ?.business_name ??
                                            candidate.quotation
                                                ?.quotation_number}{' '}
                                        — {candidate.total_price}
                                    </option>
                                ),
                            )}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label>Justification</Label>
                        <Textarea
                            value={overrideForm.data.justification}
                            onChange={(event) =>
                                overrideForm.setData(
                                    'justification',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError
                            message={overrideForm.errors.justification}
                        />
                    </div>
                    <Button type="submit" disabled={overrideForm.processing}>
                        Apply override
                    </Button>
                </form>

                {latestAoq && (
                    <p className="text-sm">
                        Latest AOQ: {latestAoq.aoq_reference_number}{' '}
                        <a
                            className="underline"
                            href={downloadAoq.url(purchaseRequest.id)}
                        >
                            Download
                        </a>
                    </p>
                )}
            </div>
        </>
    );
}

BacAoqPage.layout = {
    breadcrumbs: [{ title: 'BAC quotations', href: index() }],
};
