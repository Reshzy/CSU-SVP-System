<?php

namespace App\Http\Controllers\Ceo;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ceo\StoreDepartmentRequest;
use App\Http\Requests\Ceo\UpdateDepartmentRequest;
use App\Http\Requests\ListQueryRequest;
use App\Models\Department;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Department CRUD for the Executive Officer. There is no destroy: departments
 * carry historical purchase requests, so they are archived instead.
 */
class DepartmentController extends Controller
{
    use FlashesToasts;

    public function index(ListQueryRequest $request): Response
    {
        $departments = ListQuery::paginate(
            Department::query()->withCount('users'),
            $request,
            [
                'searchable' => ['name', 'code'],
                'sortable' => [
                    'code' => 'code',
                    'name' => 'name',
                    'head_name' => 'head_name',
                    'users_count' => 'users_count',
                    'is_active' => 'is_active',
                ],
                'equals' => ['is_active' => 'is_active'],
                'defaultSort' => 'name',
                'defaultDirection' => 'asc',
                'defaultPerPage' => 15,
            ],
        );

        return Inertia::render('ceo/departments/index', [
            'departments' => $departments,
            'filters' => ListQuery::snapshot($request, [
                'is_active' => $request->filled('is_active') ? $request->string('is_active')->value() : null,
            ]),
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
