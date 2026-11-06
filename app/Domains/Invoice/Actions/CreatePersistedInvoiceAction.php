<?php

declare(strict_types=1);

namespace App\Domains\Invoice\Actions;

use App\Domains\Company\Models\Company;
use App\Domains\Invoice\Models\Invoice;
use App\Domains\Invoice\Models\InvoiceLineItem;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreatePersistedInvoiceAction
{
    public function __construct(
        private GenerateInvoiceNumberAction $numberGenerator,
        private ResolveApplicableTaxRulesAction $taxResolver
    ) {}

    /**
     * Persists an official itemized ETB invoice with deterministic tax resolution and sequential numbering.
     *
     * @param array{
     *     company_id: int,
     *     branch_id?: int|null,
     *     customer_user_id: int,
     *     customer_name_snapshot?: string|null,
     *     customer_tin_snapshot?: string|null,
     *     service_type: string,
     *     reference_type?: string|null,
     *     reference_id?: int|null,
     *     location_id?: int|null,
     *     issue_date?: string|\DateTimeInterface|null,
     *     due_date?: string|\DateTimeInterface|null,
     *     items: array<int, array{
     *         description: string,
     *         quantity?: float|int|string|null,
     *         unit_price_etb: float|int|string,
     *     }>,
     *     paid_amount_etb?: float|int|string|null,
     *     notes?: string|null,
     * } $data
     */
    public function execute(User $actor, array $data): Invoice
    {
        $company = Company::query()->findOrFail($data['company_id']);
        $customer = User::query()->findOrFail($data['customer_user_id']);

        if (empty($data['items'])) {
            throw ValidationException::withMessages([
                'items' => ['An invoice must contain at least one line item.'],
            ]);
        }

        $issueDate = ! empty($data['issue_date'])
            ? Carbon::parse($data['issue_date'])->startOfDay()
            : Carbon::today();

        $dueDate = ! empty($data['due_date'])
            ? Carbon::parse($data['due_date'])->startOfDay()
            : $issueDate->copy()->addDays(30);

        // 1. Calculate line subtotals with decimal-safe precision
        $lineItemsData = [];
        $invoiceSubtotal = 0.00;

        foreach ($data['items'] as $item) {
            $qty = isset($item['quantity']) ? max(0.01, (float) $item['quantity']) : 1.00;
            $unitPrice = round((float) $item['unit_price_etb'], 2);

            if ($unitPrice < 0.00) {
                throw ValidationException::withMessages([
                    'items' => ['Line item unit price cannot be negative.'],
                ]);
            }

            $lineSubtotal = round($qty * $unitPrice, 2);
            $invoiceSubtotal = round($invoiceSubtotal + $lineSubtotal, 2);

            $lineItemsData[] = [
                'description' => trim($item['description']),
                'quantity' => $qty,
                'unit_price_etb' => $unitPrice,
                'line_subtotal_etb' => $lineSubtotal,
            ];
        }

        // 2. Resolve database-driven tax rules deterministically
        $taxResolution = $this->taxResolver->execute(
            serviceType: $data['service_type'],
            date: $issueDate,
            locationId: $data['location_id'] ?? null,
            subtotalEtb: $invoiceSubtotal
        );

        $taxAmount = $taxResolution['tax_amount_etb'];
        $totalAmount = $taxResolution['total_amount_etb'];

        $paidAmount = isset($data['paid_amount_etb'])
            ? min($totalAmount, max(0.00, round((float) $data['paid_amount_etb'], 2)))
            : 0.00;

        $outstandingBalance = max(0.00, round($totalAmount - $paidAmount, 2));

        // 3. Derive payment status from authoritative financial figures
        $paymentStatus = match (true) {
            $paidAmount >= $totalAmount => 'paid',
            $paidAmount > 0.00 => 'partially_paid',
            default => 'unpaid',
        };

        return DB::transaction(function () use (
            $company,
            $customer,
            $data,
            $issueDate,
            $dueDate,
            $invoiceSubtotal,
            $taxAmount,
            $totalAmount,
            $paidAmount,
            $outstandingBalance,
            $paymentStatus,
            $taxResolution,
            $lineItemsData
        ): Invoice {
            $invoiceNumber = $this->numberGenerator->execute();

            $primaryTaxRule = $taxResolution['applied_rules'][0] ?? null;

            $invoice = Invoice::query()->create([
                'uuid' => (string) Str::uuid(),
                'invoice_number' => $invoiceNumber,
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'customer_user_id' => $customer->id,
                'customer_name_snapshot' => $data['customer_name_snapshot'] ?? $customer->name,
                'customer_tin_snapshot' => $data['customer_tin_snapshot'] ?? $customer->tin_number ?? null,
                'service_type' => $data['service_type'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'currency' => 'ETB',
                'subtotal_amount_etb' => $invoiceSubtotal,
                'tax_amount_etb' => $taxAmount,
                'total_amount_etb' => $totalAmount,
                'paid_amount_etb' => $paidAmount,
                'outstanding_balance_etb' => $outstandingBalance,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'payment_status' => $paymentStatus,
                'tax_snapshots_json' => $taxResolution['applied_rules'],
                'notes' => $data['notes'] ?? null,
            ]);

            // Persist itemized line items with applied tax snapshots
            foreach ($lineItemsData as $line) {
                $lineTaxRate = $primaryTaxRule ? (float) $primaryTaxRule['rate_pct'] : 0.00;
                $lineTaxAmount = round($line['line_subtotal_etb'] * ($lineTaxRate / 100.0), 2);
                $lineTotal = round($line['line_subtotal_etb'] + $lineTaxAmount, 2);

                InvoiceLineItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price_etb' => $line['unit_price_etb'],
                    'line_subtotal_etb' => $line['line_subtotal_etb'],
                    'tax_code' => $primaryTaxRule['tax_code'] ?? null,
                    'tax_name_snapshot' => $primaryTaxRule['name_en'] ?? null,
                    'tax_rate_pct' => $lineTaxRate,
                    'tax_amount_etb' => $lineTaxAmount,
                    'line_total_etb' => $lineTotal,
                    'created_at' => now(),
                ]);
            }

            return $invoice->fresh(['company', 'branch', 'customer', 'lineItems']);
        });
    }
}
