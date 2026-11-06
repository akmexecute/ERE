<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Company\Models\Company;
use App\Domains\Invoice\Actions\CreatePersistedInvoiceAction;
use App\Domains\Invoice\Models\Invoice;
use App\Domains\Location\Models\Location;
use App\Domains\Portfolio\Services\MultiTenantOrganizationGuard;
use App\Domains\User\Models\User;
use App\Support\Calendar\EthiopianCalendarConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        $query = Invoice::query()
            ->with(['company', 'customer'])
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at');

        if (! $isGlobalAdmin) {
            $actorCompanyId = MultiTenantOrganizationGuard::resolveActorCompanyId($user);

            if ($actorCompanyId) {
                MultiTenantOrganizationGuard::applyScope($query, $user);
            } else {
                // Customer / Tenant view (only their own invoices)
                $query->where('customer_user_id', $user->id);
            }
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->query('service_type'));
        }

        $invoices = $query->paginate(15)->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $actorCompanyId = MultiTenantOrganizationGuard::resolveActorCompanyId($user);

        $companies = $actorCompanyId
            ? Company::query()->where('id', $actorCompanyId)->get()
            : Company::query()->where('status', 'active')->limit(10)->get();

        if ($companies->isEmpty()) {
            $companies = Company::query()->limit(5)->get();
        }

        $customers = User::query()->where('status', 'active')->limit(30)->get();
        if ($customers->isEmpty()) {
            $customers = User::query()->limit(10)->get();
        }

        $locations = Location::query()->limit(20)->get();

        return view('invoices.create', [
            'companies' => $companies,
            'customers' => $customers,
            'locations' => $locations,
        ]);
    }

    public function store(
        Request $request,
        CreatePersistedInvoiceAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'nullable|exists:company_branches,id',
            'customer_user_id' => 'required|exists:users,id',
            'service_type' => 'required|string|in:rent,lease,maintenance,commission,short_stay,other',
            'location_id' => 'nullable|exists:locations,id',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'paid_amount_etb' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.unit_price_etb' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        if (! $isGlobalAdmin) {
            $userCompanyId = MultiTenantOrganizationGuard::resolveActorCompanyId($user);
            if (! $userCompanyId || (int) $validated['company_id'] !== $userCompanyId) {
                abort(403, 'You are not authorized to issue invoices for this company.');
            }
        }

        $invoice = $action->execute($request->user(), $validated);

        return redirect()->route('invoices.show', $invoice->uuid)
            ->with('status', "Invoice {$invoice->invoice_number} created and persisted successfully.");
    }

    public function show(string $uuid, Request $request): View
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()
            ->where('uuid', $uuid)
            ->with(['company', 'branch', 'customer', 'lineItems'])
            ->firstOrFail();

        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        $isAuthorized = $isGlobalAdmin
            || $invoice->customer_user_id === $user->id
            || MultiTenantOrganizationGuard::canAccess($user, $invoice);

        if (! $isAuthorized) {
            abort(403, 'You are not authorized to view this invoice.');
        }

        $ethDate = EthiopianCalendarConverter::fromGregorian($invoice->issue_date)->format('en');

        return view('invoices.show', [
            'invoice' => $invoice,
            'ethDate' => $ethDate,
        ]);
    }

    public function print(string $uuid, Request $request): View
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()
            ->where('uuid', $uuid)
            ->with(['company', 'branch', 'customer', 'lineItems'])
            ->firstOrFail();

        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        $isAuthorized = $isGlobalAdmin
            || $invoice->customer_user_id === $user->id
            || MultiTenantOrganizationGuard::canAccess($user, $invoice);

        if (! $isAuthorized) {
            abort(403, 'You are not authorized to print this invoice.');
        }

        $ethDateEn = EthiopianCalendarConverter::fromGregorian($invoice->issue_date)->format('en');
        $ethDateAm = EthiopianCalendarConverter::fromGregorian($invoice->issue_date)->format('am');

        return view('invoices.print', [
            'invoice' => $invoice,
            'ethDateEn' => $ethDateEn,
            'ethDateAm' => $ethDateAm,
        ]);
    }

    public function void(string $uuid, Request $request): RedirectResponse
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $user = $request->user();
        $isGlobalAdmin = $user->hasRole('super_admin') || $user->hasRole('platform_admin');

        if (! $isGlobalAdmin && ! MultiTenantOrganizationGuard::canAccess($user, $invoice)) {
            abort(403, 'You are not authorized to void this invoice.');
        }

        try {
            $invoice->voidInvoice($user, $validated['reason']);
        } catch (\DomainException $e) {
            return redirect()->route('invoices.show', $invoice->uuid)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice->uuid)
            ->with('status', "Invoice {$invoice->invoice_number} has been voided successfully.");
    }
}
