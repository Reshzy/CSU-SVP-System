<?php

namespace App\Http\Controllers\Bac;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\BacMeeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        return Inertia::render('bac/meetings/index', [
            'meetings' => BacMeeting::query()->latest('meeting_datetime')->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('bac/meetings/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meeting_datetime' => ['required', 'date'],
            'location' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:scheduled,completed,cancelled'],
            'agenda' => ['nullable', 'string'],
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
        ]);

        BacMeeting::query()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $this->toast('Meeting scheduled.');

        return redirect()->route('bac.meetings.index');
    }

    public function show(BacMeeting $meeting): Response
    {
        return Inertia::render('bac/meetings/show', [
            'meeting' => $meeting->load('attendees'),
        ]);
    }
}
