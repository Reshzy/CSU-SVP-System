<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreDepartmentRequestRequest;
use App\Models\Department;
use App\Models\DepartmentRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a guest ask for a department that is missing from the register dropdown.
 * The Executive Officer reviews the submission before it becomes selectable.
 */
class DepartmentRequestController extends Controller
{
    use FlashesToasts;

    public function create(): Response
    {
        return Inertia::render('auth/request-department', [
            'departments' => Department::selectable()->orderBy('name')->get(['name', 'code']),
        ]);
    }

    public function store(StoreDepartmentRequestRequest $request): RedirectResponse
    {
        DepartmentRequest::create($request->validated());

        $this->toast(
            'Your department request was submitted for review. You will be able to select it once the Executive Officer approves it.',
            'info',
        );

        return redirect()->route('register');
    }
}
