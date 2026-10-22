<?php

namespace App\Http\Controllers\Supply;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\PoSignatory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PoSignatoryController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        return Inertia::render('supply/po-signatories/index', [
            'signatories' => PoSignatory::query()->with('user:id,name,email')->orderBy('position')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('supply/po-signatories/create', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        if (($validated['is_active'] ?? true) && $this->hasActivePosition($validated['position'])) {
            return back()->withErrors(['position' => 'An active signatory already exists for this position.']);
        }

        PoSignatory::query()->create($validated);
        $this->toast('PO signatory added.');

        return redirect()->route('supply.po-signatories.index');
    }

    public function edit(PoSignatory $poSignatory): Response
    {
        return Inertia::render('supply/po-signatories/edit', [
            'signatory' => $poSignatory->load('user:id,name,email'),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, PoSignatory $poSignatory): RedirectResponse
    {
        $validated = $this->validated($request);

        if (($validated['is_active'] ?? true) && $this->hasActivePosition($validated['position'], $poSignatory->id)) {
            return back()->withErrors(['position' => 'An active signatory already exists for this position.']);
        }

        $poSignatory->update($validated);
        $this->toast('PO signatory updated.');

        return redirect()->route('supply.po-signatories.index');
    }

    public function destroy(PoSignatory $poSignatory): RedirectResponse
    {
        $poSignatory->delete();
        $this->toast('PO signatory removed.');

        return redirect()->route('supply.po-signatories.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'manual_name' => ['nullable', 'string', 'max:255'],
            'position' => ['required', Rule::in(['ceo', 'chief_accountant'])],
            'prefix' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function hasActivePosition(string $position, ?int $ignoreId = null): bool
    {
        return PoSignatory::query()
            ->active()
            ->position($position)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
