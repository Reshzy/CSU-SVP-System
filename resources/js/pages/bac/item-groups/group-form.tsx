import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type QuotableItem = {
    id: number;
    item_name: string | null;
    lot_name: string | null;
    is_lot: boolean;
};

export type ExistingGroup = {
    id: number;
    group_name: string;
    group_code: string;
    items: { id: number }[];
};

type GroupDraft = {
    name: string;
    items: number[];
};

type Props = {
    quotableItems: QuotableItem[];
    initialGroups: GroupDraft[];
    actionUrl: string;
    method: 'post' | 'put';
};

export function emptyGroup(): GroupDraft {
    return { name: '', items: [] };
}

export function draftsFromExisting(groups: ExistingGroup[]): GroupDraft[] {
    if (groups.length === 0) {
        return [emptyGroup(), emptyGroup()];
    }

    return groups.map((group) => ({
        name: group.group_name,
        items: group.items.map((item) => item.id),
    }));
}

export function ItemGroupForm({
    quotableItems,
    initialGroups,
    actionUrl,
    method,
}: Props) {
    const form = useForm({
        groups: initialGroups,
    });

    const assignedTo = (itemId: number, exceptIndex: number): number | null => {
        const index = form.data.groups.findIndex(
            (group, groupIndex) =>
                groupIndex !== exceptIndex && group.items.includes(itemId),
        );

        return index === -1 ? null : index;
    };

    const toggleItem = (
        groupIndex: number,
        itemId: number,
        checked: boolean,
    ) => {
        const groups = form.data.groups.map((group, index) => {
            if (index !== groupIndex) {
                return group;
            }

            const items = checked
                ? [...group.items, itemId]
                : group.items.filter((id) => id !== itemId);

            return { ...group, items };
        });

        form.setData('groups', groups);
    };

    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();

                const assigned = new Set(
                    form.data.groups.flatMap((group) => group.items),
                );
                const unassigned = quotableItems.filter(
                    (item) => !assigned.has(item.id),
                );

                if (unassigned.length > 0) {
                    form.setError(
                        'groups',
                        `${unassigned.length} item(s) are not assigned to a group.`,
                    );
                    return;
                }

                form.clearErrors('groups');

                if (method === 'put') {
                    form.put(actionUrl);
                } else {
                    form.post(actionUrl);
                }
            }}
        >
            {form.data.groups.map((group, groupIndex) => (
                <div
                    key={groupIndex}
                    className="space-y-3 rounded-lg border p-4"
                >
                    <div className="flex items-end justify-between gap-3">
                        <div className="flex-1 space-y-2">
                            <Label>Group {groupIndex + 1} name</Label>
                            <Input
                                value={group.name}
                                onChange={(event) => {
                                    const groups = [...form.data.groups];
                                    groups[groupIndex] = {
                                        ...groups[groupIndex],
                                        name: event.target.value,
                                    };
                                    form.setData('groups', groups);
                                }}
                            />
                        </div>
                        {form.data.groups.length > 1 && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    form.setData(
                                        'groups',
                                        form.data.groups.filter(
                                            (_, index) => index !== groupIndex,
                                        ),
                                    )
                                }
                            >
                                Remove
                            </Button>
                        )}
                    </div>
                    <div className="space-y-2">
                        {quotableItems.map((item) => {
                            const otherGroup = assignedTo(item.id, groupIndex);
                            const label = item.is_lot
                                ? (item.lot_name ?? `Lot ${item.id}`)
                                : (item.item_name ?? `Item ${item.id}`);

                            return (
                                <label
                                    key={item.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={group.items.includes(item.id)}
                                        disabled={otherGroup !== null}
                                        onCheckedChange={(checked) =>
                                            toggleItem(
                                                groupIndex,
                                                item.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <span>
                                        {label}
                                        {otherGroup !== null
                                            ? ` (already in group ${otherGroup + 1})`
                                            : ''}
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                </div>
            ))}

            <InputError message={form.errors.groups} />

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        form.setData('groups', [
                            ...form.data.groups,
                            emptyGroup(),
                        ])
                    }
                >
                    Add group
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Save groups
                </Button>
            </div>
        </form>
    );
}
