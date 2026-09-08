<?php

namespace App\Http\Controllers\Ceo;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ceo\StoreDepartmentRequest;
use App\Http\Requests\Ceo\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Department CRUD for the Executive Officer. There is no destroy: departments
 * carry historical purchase requests, so they are archived instead.
 */
class DepartmentController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        $departments = Department::query()
            ->withCount('users')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ceo/departments/index', [
            'departments' => $departments,
            'filters' => ['search' => $request->string('search')->value()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ceo/departments/create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());

        $this->toast("Created {$department->name}.");

        return redirect()->route('ceo.departments.index');
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('ceo/departments/edit', [
            'department' => $department,
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        $this->toast("Updated {$department->name}.");

        return redirect()->route('ceo.departments.index');
    }
}
