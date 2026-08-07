<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketUpdate;
use App\Models\User;
use App\Notifications\SystemEventNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TicketsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:tickets.index'])->only(['index', 'show']);
        $this->middleware(['permission:tickets.create'])->only(['create', 'store']);
        $this->middleware(['permission:tickets.update'])->only(['edit', 'update', 'addUpdate']);
        $this->middleware(['permission:tickets.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $tickets = Ticket::filter(request()->only('search', 'status', 'category'))
            ->with('assignee:id,name')
            ->latest()
            ->paginate()
            ->withQueryString();

        return Inertia::render('Tickets/Index', [
            'filters' => request()->all('search', 'status', 'category'),
            'tickets' => $tickets,
            'statuses' => Ticket::STATUSES,
            'categories' => Ticket::CATEGORIES,
            'priorities' => Ticket::PRIORITIES,
            'counts' => [
                'all' => Ticket::count(),
                'open' => Ticket::where('status', 'open')->count(),
                'in_progress' => Ticket::where('status', 'in_progress')->count(),
                'resolved' => Ticket::where('status', 'resolved')->count(),
                'closed' => Ticket::where('status', 'closed')->count(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Tickets/Create', [
            'nextReference' => Ticket::nextReference(),
            'statuses' => Ticket::STATUSES,
            'categories' => Ticket::CATEGORIES,
            'priorities' => Ticket::PRIORITIES,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateTicket($request);

        $ticket = new Ticket($data);
        $ticket->reference = Ticket::nextReference();
        $ticket->created_by = auth()->id();
        if (in_array($ticket->status, ['resolved', 'closed'])) {
            $ticket->resolved_at = now();
        }
        $ticket->save();

        $ticket->updates()->create([
            'user_id' => auth()->id(),
            'new_status' => $ticket->status,
            'is_system' => true,
            'body' => 'Ticket logged.',
        ]);

        activity()->performedOn($ticket)->log('Create Ticket ' . $ticket->reference_display);

        $this->notifyTicketParties($ticket, 'Ticket ' . $ticket->reference_display . ' created: ' . $ticket->title);

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', 'Ticket ' . $ticket->reference_display . ' created successfully.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load([
            'assignee:id,name',
            'creator:id,name',
            'updates.user:id,name',
        ]);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'statuses' => Ticket::STATUSES,
            'categories' => Ticket::CATEGORIES,
            'priorities' => Ticket::PRIORITIES,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $this->validateTicket($request);

        $oldStatus = $ticket->status;
        $ticket->fill($data);

        if ($oldStatus !== $ticket->status) {
            if (in_array($ticket->status, ['resolved', 'closed']) && ! $ticket->resolved_at) {
                $ticket->resolved_at = now();
            }
            if (! in_array($ticket->status, ['resolved', 'closed'])) {
                $ticket->resolved_at = null;
            }
        }

        $ticket->save();

        if ($oldStatus !== $ticket->status) {
            $ticket->updates()->create([
                'user_id' => auth()->id(),
                'old_status' => $oldStatus,
                'new_status' => $ticket->status,
                'is_system' => true,
                'body' => 'Status changed from ' . (Ticket::STATUSES[$oldStatus] ?? $oldStatus)
                    . ' to ' . $ticket->status_label . '.',
            ]);
        }

        activity()->performedOn($ticket)->log('Update Ticket ' . $ticket->reference_display);

        $this->notifyTicketParties($ticket, 'Ticket ' . $ticket->reference_display . ' updated (' . $ticket->status_label . '): ' . $ticket->title);

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', 'Ticket ' . $ticket->reference_display . ' updated.');
    }

    /**
     * Add a progress note to the ticket's activity trail, optionally moving the
     * status at the same time.
     */
    public function addUpdate(Request $request, Ticket $ticket)
    {
        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'new_status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
        ]);

        $oldStatus = $ticket->status;
        $newStatus = $request->input('new_status');
        $statusChanged = $newStatus && $newStatus !== $oldStatus;

        if ($statusChanged) {
            $ticket->status = $newStatus;
            if (in_array($newStatus, ['resolved', 'closed']) && ! $ticket->resolved_at) {
                $ticket->resolved_at = now();
            }
            if (! in_array($newStatus, ['resolved', 'closed'])) {
                $ticket->resolved_at = null;
            }
            $ticket->save();
        }

        $ticket->updates()->create([
            'user_id' => auth()->id(),
            'body' => $request->input('body'),
            'old_status' => $statusChanged ? $oldStatus : null,
            'new_status' => $statusChanged ? $newStatus : null,
            'is_system' => false,
        ]);

        activity()->performedOn($ticket)->log('Comment on Ticket ' . $ticket->reference_display);

        $this->notifyTicketParties($ticket, 'New update on ticket ' . $ticket->reference_display . ': ' . str($request->input('body'))->limit(80));

        return redirect()->route('tickets.show', $ticket->id)
            ->with('success', 'Update added to ' . $ticket->reference_display . '.');
    }

    public function destroy(Ticket $ticket)
    {
        $ref = $ticket->reference_display;
        $ticket->delete();

        activity()->log('Delete Ticket ' . $ref);

        return redirect()->route('tickets.index')->with('success', 'Ticket ' . $ref . ' deleted.');
    }

    /**
     * Bell notifications for the ticket's assignee and creator (excluding
     * whoever performed the action).
     */
    private function notifyTicketParties(Ticket $ticket, string $message): void
    {
        User::whereIn('id', array_filter([$ticket->assigned_to, $ticket->created_by]))
            ->where('id', '!=', auth()->id())
            ->get()
            ->each(fn ($user) => $user->notify(new SystemEventNotification(
                'ticket',
                $message,
                route('tickets.show', $ticket->id)
            )));
    }

    private function validateTicket(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(Ticket::STATUSES))],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:40'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'resolution' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);
    }
}
