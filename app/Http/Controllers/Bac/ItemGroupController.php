<?php

namespace App\Http\Controllers\Bac;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bac\StorePrItemGroupsRequest;
use App\Http\Requests\Bac\UpdatePrItemGroupsRequest;
use App\Models\PrItemGroup;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ItemGroupController extends Controller
{
    use FlashesToasts;

    public function create(PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($purchaseRequest->canManageGroups(), 403, 'PR must be in BAC evaluation or partial PO generation stage to split items.');

        $purchaseRequest->load(['quotableItems', 'itemGroups.items']);

        return Inertia::render('bac/item-groups/create', [
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function store(StorePrItemGroupsRequest $request, PurchaseRequest $purchaseRequest, PurchaseRequestActivityLogger $activityLogger): RedirectResponse
    {
        $groupsInfo = $this->persistGroups($purchaseRequest, $request->validated('groups'));
        $activityLogger->logItemGroupsCreated($purchaseRequest, $groupsInfo);
        $this->toast('Items have been successfully grouped.');

        return redirect()->route('bac.quotations.manage', $purchaseRequest);
    }

    public function edit(PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($purchaseRequest->canManageGroups(), 403, 'PR must be in BAC evaluation or partial PO generation stage to edit groups.');
        abort_unless(! $purchaseRequest->groupsAreLocked(), 403, 'Groups cannot be changed after RFQ, quotations, or AOQ.');

        $purchaseRequest->load(['quotableItems', 'itemGroups.items']);

        return Inertia::render('bac/item-groups/edit', [
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function update(UpdatePrItemGroupsRequest $request, PurchaseRequest $purchaseRequest, PurchaseRequestActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless(! $purchaseRequest->groupsAreLocked(), 403, 'Groups cannot be changed after RFQ, quotations, or AOQ.');

        $groupsInfo = $this->persistGroups($purchaseRequest, $request->validated('groups'));
        $activityLogger->logItemGroupsUpdated($purchaseRequest, $groupsInfo);
        $this->toast('Item groups have been updated.');

        return redirect()->route('bac.quotations.manage', $purchaseRequest);
    }

    public function destroy(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        abort_unless($purchaseRequest->canManageGroups(), 403, 'PR must be in BAC evaluation or partial PO generation stage.');
        abort_unless(! $purchaseRequest->groupsAreLocked(), 403, 'Groups cannot be changed after RFQ, quotations, or AOQ.');

        DB::transaction(function () use ($purchaseRequest): void {
            $purchaseRequest->items()->update(['pr_item_group_id' => null]);
            $purchaseRequest->itemGroups()->delete();
        });

        $this->toast('All groups have been removed.');

        return redirect()->route('bac.quotations.manage', $purchaseRequest);
    }

    /**
     * @param  list<array{name: string, items: list<int|string>}>  $groups
     * @return list<array{group_code: string, group_name: string, item_count: int}>
     */
    private function persistGroups(PurchaseRequest $purchaseRequest, array $groups): array
    {
        $groupsInfo = [];

        DB::transaction(function () use ($purchaseRequest, $groups, &$groupsInfo): void {
            abort_unless(! $purchaseRequest->groupsAreLocked(), 403, 'Groups cannot be changed after RFQ, quotations, or AOQ.');

            $purchaseRequest->items()->update(['pr_item_group_id' => null]);
            $purchaseRequest->itemGroups()->delete();

            foreach ($groups as $index => $groupData) {
                $groupCode = PrItemGroup::generateNextGroupCode($purchaseRequest);
                $group = PrItemGroup::query()->create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'group_name' => $groupData['name'],
                    'group_code' => $groupCode,
                    'display_order' => $index + 1,
                    'status' => 'pending',
                ]);

                $purchaseRequest->items()
                    ->whereIn('id', $groupData['items'])
                    ->update(['pr_item_group_id' => $group->id]);

                $groupsInfo[] = [
                    'group_code' => $groupCode,
                    'group_name' => $groupData['name'],
                    'item_count' => count($groupData['items']),
                ];
            }
        });

        return $groupsInfo;
    }
}
