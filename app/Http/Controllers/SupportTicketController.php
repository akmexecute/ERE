<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Notification\Services\MultiChannelNotificationService;
use App\Domains\Support\Actions\GenerateTicketCodeAction;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SupportTicketController extends Controller
{
    public const array STAFF_ROLES = ['super_admin', 'platform_admin', 'moderator', 'customer_support'];

    public const array VALID_CATEGORIES = [
        'account',
        'property',
        'booking',
        'payment',
        'verification',
        'fraud',
        'technical',
        'subscription',
    ];

    public const array VALID_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const array VALID_STATUSES = ['open', 'in_progress', 'waiting_user', 'resolved', 'closed'];

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(self::STAFF_ROLES);

        $query = SupportTicket::query()
            ->with(['user', 'assignedStaff'])
            ->orderByDesc('created_at');

        if (! $isStaff) {
            $query->where('user_id', $user->id);
        }

        $tickets = $query->paginate(15);

        if ($request->wantsJson()) {
            return response()->json($tickets);
        }

        return view('support.index', [
            'tickets' => $tickets,
            'isStaff' => $isStaff,
        ]);
    }

    public function create(): View
    {
        return view('support.create', [
            'categories' => self::VALID_CATEGORIES,
            'priorities' => self::VALID_PRIORITIES,
        ]);
    }

    public function store(Request $request, GenerateTicketCodeAction $codeGenerator): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', self::VALID_CATEGORIES)],
            'priority' => ['nullable', 'string', 'in:'.implode(',', self::VALID_PRIORITIES)],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = DB::transaction(function () use ($request, $validated, $codeGenerator) {
            $code = $codeGenerator->execute();

            $created = SupportTicket::query()->create([
                'uuid' => (string) Str::uuid(),
                'ticket_code' => $code,
                'user_id' => $request->user()->id,
                'category' => $validated['category'],
                'priority' => $validated['priority'] ?? 'normal',
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'status' => 'open',
            ]);

            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $request->user()->id,
                    'action' => 'support.ticket_created',
                    'auditable_type' => SupportTicket::class,
                    'auditable_id' => (string) $created->id,
                    'new_values' => json_encode(['ticket_code' => $code, 'category' => $validated['category']]),
                    'reason' => 'Customer support ticket submitted',
                    'created_at' => now(),
                ]);
            }

            return $created;
        });

        // Notify user of ticket registration (SRS Sections 76, 214)
        app(MultiChannelNotificationService::class)->send(
            recipient: $request->user(),
            eventKey: 'ticket.created',
            data: [
                'ticket_code' => $ticket->ticket_code,
                'subject' => $ticket->subject,
            ],
            isMarketing: false
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Support ticket created successfully.',
                'ticket' => $ticket,
            ], 201);
        }

        return redirect()->route('support.tickets.show', ['uuid' => $ticket->uuid])
            ->with('success', "Ticket {$ticket->ticket_code} created successfully.");
    }

    public function show(string $uuid, Request $request): View|JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(self::STAFF_ROLES);

        $ticket = SupportTicket::query()
            ->where('uuid', $uuid)
            ->with(['user', 'assignedStaff'])
            ->firstOrFail();

        if (! $isStaff && $ticket->user_id !== $user->id) {
            throw new AccessDeniedHttpException('You are not authorized to view this support ticket.');
        }

        if ($request->wantsJson()) {
            return response()->json($ticket);
        }

        return view('support.show', [
            'ticket' => $ticket,
            'isStaff' => $isStaff,
            'validStatuses' => self::VALID_STATUSES,
        ]);
    }

    public function updateStatus(string $uuid, Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(self::STAFF_ROLES);

        if (! $isStaff) {
            throw new AccessDeniedHttpException('Only staff members can update ticket status or assignment.');
        }

        $ticket = SupportTicket::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', self::VALID_STATUSES)],
            'assigned_staff_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $oldStatus = $ticket->status;
        $ticket->update([
            'status' => $validated['status'],
            'assigned_staff_id' => $validated['assigned_staff_id'] ?? $ticket->assigned_staff_id,
        ]);

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'action' => 'support.ticket_status_updated',
                'auditable_type' => SupportTicket::class,
                'auditable_id' => (string) $ticket->id,
                'old_values' => json_encode(['status' => $oldStatus]),
                'new_values' => json_encode(['status' => $validated['status']]),
                'reason' => 'Staff status transition',
                'created_at' => now(),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Ticket status updated.',
                'ticket' => $ticket->fresh(['assignedStaff']),
            ]);
        }

        return back()->with('success', 'Ticket updated successfully.');
    }
}
