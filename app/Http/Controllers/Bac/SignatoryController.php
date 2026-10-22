<?php

namespace App\Http\Controllers\Bac;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\BacSignatory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SignatoryController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        return Inertia::render('bac/signatories/index', [
            'signatories' => BacSignatory::query()->with('user:id,name,email')->orderBy('position')->get(),
            'users' => User::role(['BAC Chair', 'BAC Members', 'BAC Secretariat', 'Executive Officer'])->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('bac/signatories/create', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        BacSignatory::query()->create($validated);
        $this->toast('Signatory added.');

        return redirect()->route('bac.signatories.index');
    }

    public function edit(BacSignatory $signatory): Response
    {
        return Inertia::render('bac/signatories/edit', [
            'signatory' => $signatory->load('user:id,name,email'),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, BacSignatory $signatory): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['required', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $signatory->update($validated);
        $this->toast('Signatory updated.');

        return redirect()->route('bac.signatories.index');
    }

    public function destroy(BacSignatory $signatory): RedirectResponse
    {
        $signatory->delete();
        $this->toast('Signatory removed.');

        return redirect()->route('bac.signatories.index');
    }
}
