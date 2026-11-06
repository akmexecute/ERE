@extends('layouts.app', ['title' => 'Developer Management — ' . $project->title_en])

@section('content')
<div class="bg-slate-900 text-slate-100 py-10 border-b border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-2.5 py-0.5 rounded text-xs font-semibold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        Developer Operations
                    </span>
                    <span class="text-xs text-slate-400 font-mono">{{ $project->project_code }}</span>
                </div>
                <h1 class="text-3xl font-bold text-white">{{ $project->title_en }}</h1>
                <p class="text-sm text-slate-400 mt-1">
                    {{ $project->developer->company->legal_name ?? 'Developer' }} · {{ $project->address_line ?? 'Addis Ababa' }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('projects.show', $project->slug) }}" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    Public Showcase & Matrix
                </a>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10"
     x-data="developerProjectManager({
        projectId: {{ $project->id }},
        initialMilestones: {{ json_encode($project->milestones->map(fn($m) => [
            'id' => $m->id,
            'stage_code' => $m->stage_code,
            'name_en' => $m->name_en,
            'name_am' => $m->name_am,
            'progress_weight' => (float) $m->progress_weight,
            'completion_percentage' => (float) $m->completion_percentage,
            'is_verified' => (bool) $m->is_verified,
        ])->values()) }},
        initialOverallProgress: {{ (float) $project->overall_progress_pct }}
     })">

    @if(session('success'))
        <div class="mb-6 p-4 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    <!-- Flash feedback messages -->
    <div x-show="feedbackMessage" x-transition class="mb-6 p-4 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-800 text-sm font-medium flex items-center justify-between">
        <span x-text="feedbackMessage"></span>
        <button type="button" @click="feedbackMessage = null" class="text-emerald-600 hover:text-emerald-900">&times;</button>
    </div>
    <div x-show="errorMessage" x-transition class="mb-6 p-4 rounded-xl border bg-red-50 border-red-200 text-red-800 text-sm font-medium flex items-center justify-between">
        <span x-text="errorMessage"></span>
        <button type="button" @click="errorMessage = null" class="text-red-600 hover:text-red-900">&times;</button>
    </div>

    <!-- Top Management Tabs -->
    <div class="flex border-b border-slate-200 mb-8 space-x-8">
        <button type="button" @click="activeTab = 'stages'"
                :class="activeTab === 'stages' ? 'border-amber-600 text-amber-800 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 font-medium'"
                class="pb-4 text-sm border-b-2 transition">
            1. Construction Stages & Progress
        </button>
        <button type="button" @click="activeTab = 'plans'"
                :class="activeTab === 'plans' ? 'border-amber-600 text-amber-800 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 font-medium'"
                class="pb-4 text-sm border-b-2 transition">
            2. Milestone Payment Plans
        </button>
        <button type="button" @click="activeTab = 'accounts'"
                :class="activeTab === 'accounts' ? 'border-amber-600 text-amber-800 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 font-medium'"
                class="pb-4 text-sm border-b-2 transition">
            3. Developer Payment Accounts
        </button>
        <button type="button" @click="activeTab = 'contracts'"
                :class="activeTab === 'contracts' ? 'border-amber-600 text-amber-800 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 font-medium'"
                class="pb-4 text-sm border-b-2 transition">
            4. Sales Contracts & Amendments
        </button>
        <button type="button" @click="activeTab = 'units'"
                :class="activeTab === 'units' ? 'border-amber-600 text-amber-800 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 font-medium'"
                class="pb-4 text-sm border-b-2 transition">
            5. Unit Inventory Management
        </button>
    </div>

    <!-- TAB 1: Construction Stages & Progress Engine -->
    <div x-show="activeTab === 'stages'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Configurable Construction Stages</h2>
                    <p class="text-xs text-slate-500">Custom stages, progress weights, and weighted-completion recalculation</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <span class="text-[11px] text-slate-400 uppercase font-semibold">Total Configured Weight</span>
                        <div class="text-base font-mono font-bold"
                             :class="isTotalWeightValid ? 'text-emerald-600' : 'text-red-600'"
                             x-text="totalWeight.toFixed(2) + '%' "></div>
                    </div>
                    <div class="text-right pl-4 border-l border-slate-200">
                        <span class="text-[11px] text-slate-400 uppercase font-semibold">Weighted Progress</span>
                        <div class="text-xl font-bold text-slate-900" x-text="calculatedProgress.toFixed(2) + '%'"></div>
                    </div>
                </div>
            </div>

            <!-- Stage List Table -->
            <div class="overflow-x-auto mt-6">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 uppercase font-semibold text-[10px]">
                            <th class="py-2.5">Code</th>
                            <th class="py-2.5">Stage Name (English)</th>
                            <th class="py-2.5">Stage Name (Amharic)</th>
                            <th class="py-2.5 w-28">Weight (%)</th>
                            <th class="py-2.5 w-32">Completion (%)</th>
                            <th class="py-2.5 text-center">Audited</th>
                            <th class="py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(stage, idx) in stages" :key="stage.stage_code">
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="py-3 font-mono font-semibold text-slate-700" x-text="stage.stage_code"></td>
                                <td class="py-3">
                                    <input type="text" x-model="stage.name_en" class="w-full text-xs rounded border-slate-200 py-1 px-2 focus:ring-amber-500">
                                </td>
                                <td class="py-3">
                                    <input type="text" x-model="stage.name_am" class="w-full text-xs rounded border-slate-200 py-1 px-2 focus:ring-amber-500 font-['Noto_Sans_Ethiopic']">
                                </td>
                                <td class="py-3">
                                    <input type="number" step="0.5" min="0" max="100" @input="recomputeProgress()" x-model.number="stage.progress_weight" class="w-24 text-xs rounded border-slate-200 py-1 px-2 font-mono">
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <input type="number" step="1" min="0" max="100" @input="recomputeProgress()" x-model.number="stage.completion_percentage" class="w-20 text-xs rounded border-slate-200 py-1 px-2 font-mono font-bold text-slate-900">
                                        <span class="text-slate-400">%</span>
                                    </div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                          :class="stage.is_verified ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                                          x-text="stage.is_verified ? 'Verified' : 'Reported'"></span>
                                </td>
                                <td class="py-3 text-right">
                                    <button type="button" @click="removeStage(idx)" class="text-red-500 hover:text-red-700 text-xs font-semibold">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Add Custom Stage Inline Form -->
            <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-dashed border-slate-200">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">+ Add New Construction Stage</h4>
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                    <input type="text" x-model="newStage.stage_code" placeholder="Code (e.g. excavation)" class="text-xs rounded-lg border-slate-300 py-1.5 px-3">
                    <input type="text" x-model="newStage.name_en" placeholder="English Name" class="text-xs rounded-lg border-slate-300 py-1.5 px-3">
                    <input type="text" x-model="newStage.name_am" placeholder="Amharic Name" class="text-xs rounded-lg border-slate-300 py-1.5 px-3 font-['Noto_Sans_Ethiopic']">
                    <input type="number" step="0.5" x-model.number="newStage.progress_weight" placeholder="Weight %" class="text-xs rounded-lg border-slate-300 py-1.5 px-3">
                    <button type="button" @click="addStage()" class="py-1.5 px-4 rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs shadow-xs transition">
                        Add Stage
                    </button>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" @click="saveStages()" :disabled="savingStages || !isTotalWeightValid"
                        :class="savingStages || !isTotalWeightValid ? 'opacity-50 cursor-not-allowed' : 'hover:bg-amber-700'"
                        class="py-2.5 px-6 rounded-xl bg-amber-600 text-white font-bold text-xs shadow-sm transition flex items-center gap-2">
                    <span x-text="savingStages ? 'Recalculating...' : 'Save Stages & Recalculate Progress'"></span>
                </button>
            </div>
        </div>

        <!-- Audit History of Progress Logs -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-bold text-slate-900 mb-4">Construction Progress Audit Logs</h3>
            <div class="space-y-2">
                @forelse($project->milestones->flatMap->logs->sortByDesc('created_at')->take(6) as $log)
                    <div class="flex items-center justify-between text-xs py-2 border-b border-slate-100 last:border-0">
                        <div>
                            <span class="font-bold text-slate-900">{{ $log->milestone->name_en ?? 'Milestone' }}</span>
                            <span class="text-slate-500">· {{ $log->notes }}</span>
                        </div>
                        <div class="text-right text-slate-400 text-[11px]">
                            <span>{{ $log->created_at?->diffForHumans() }} by {{ $log->updater->name ?? 'Engineer' }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500">No milestone progress updates recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- TAB 2: Milestone Payment Plans -->
    <div x-show="activeTab === 'plans'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Create Custom Payment Plan (100% Total)</h2>
            <p class="text-xs text-slate-500 mb-6">Configure custom milestone sequences linked to construction progress triggers</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Plan Name (English)</label>
                    <input type="text" x-model="newPlan.name_en" placeholder="e.g. Standard 6-Stage Construction Plan" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Plan Name (Amharic)</label>
                    <input type="text" x-model="newPlan.name_am" placeholder="የክፍያ እቅድ ስም" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3 font-['Noto_Sans_Ethiopic']">
                </div>
            </div>

            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Milestone Tranches</h4>
                <div class="text-xs font-mono font-bold"
                     :class="newPlanTotalPct === 100 ? 'text-emerald-600' : 'text-red-600'"
                     x-text="'Total: ' + newPlanTotalPct.toFixed(2) + '% (Must be exactly 100%)'"></div>
            </div>

            <div class="space-y-2 mb-6">
                <template x-for="(inst, idx) in newPlan.installments" :key="idx">
                    <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-center p-2 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                        <span class="font-bold text-slate-600 pl-1" x-text="'#' + (idx + 1)"></span>
                        <input type="text" x-model="inst.milestone_label_en" placeholder="Milestone label" class="sm:col-span-2 text-xs rounded border-slate-300 py-1 px-2">
                        <div class="flex items-center gap-1">
                            <input type="number" step="0.5" min="1" max="100" x-model.number="inst.percentage" placeholder="%" class="w-full text-xs rounded border-slate-300 py-1 px-2 font-bold">
                            <span class="text-slate-400">%</span>
                        </div>
                        <input type="text" x-model="inst.trigger_milestone_code" placeholder="Stage code trigger" class="text-xs rounded border-slate-300 py-1 px-2">
                        <button type="button" @click="removePlanInstallment(idx)" class="text-red-500 hover:text-red-700 font-semibold text-right pr-2">
                            Delete
                        </button>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-slate-100">
                <button type="button" @click="addPlanInstallment()" class="py-1.5 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
                    + Add Installment
                </button>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" x-model="newPlan.is_default" class="rounded border-slate-300 text-amber-600">
                        <span>Set as default plan for new buyers</span>
                    </label>
                    <button type="button" @click="savePlan()" :disabled="savingPlan || newPlanTotalPct !== 100"
                            :class="savingPlan || newPlanTotalPct !== 100 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-amber-700'"
                            class="py-2 px-5 rounded-xl bg-amber-600 text-white font-bold text-xs shadow-xs transition">
                        Save Payment Plan
                    </button>
                </div>
            </div>
        </div>

        <!-- Existing Payment Plans List -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-bold text-slate-900 mb-4">Configured Project Plans</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($project->paymentPlans as $plan)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-bold text-slate-900 text-sm">{{ $plan->name_en }}</h4>
                            @if($plan->is_default)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Default</span>
                            @endif
                        </div>
                        <div class="space-y-1 text-xs text-slate-600">
                            @foreach($plan->installments as $inst)
                                <div class="flex justify-between py-0.5 border-b border-slate-100 last:border-0">
                                    <span>{{ $inst->sequence_number }}. {{ $inst->milestone_label_en }}</span>
                                    <strong class="font-mono text-slate-800">{{ $inst->percentage }}%</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- TAB 3: Developer Payment Accounts -->
    <div x-show="activeTab === 'accounts'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Developer Manual Payment Accounts</h2>
            <p class="text-xs text-slate-500 mb-6">Enter official bank accounts for direct manual transfer settlements (Option B)</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Account Label</label>
                    <input type="text" x-model="newAccount.account_label" placeholder="e.g. CBE Primary Operations" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Bank / Institution</label>
                    <input type="text" x-model="newAccount.institution_name" placeholder="e.g. Commercial Bank of Ethiopia" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Account Holder Name</label>
                    <input type="text" x-model="newAccount.account_holder_name" placeholder="Legal registered company name" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Account Number</label>
                    <input type="text" x-model="newAccount.account_number" placeholder="e.g. 1000123456789" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Branch Name</label>
                    <input type="text" x-model="newAccount.branch_name" placeholder="e.g. Bole Medhanialem Branch" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Currency</label>
                    <select x-model="newAccount.currency" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3">
                        <option value="ETB">ETB (Ethiopian Birr)</option>
                        <option value="USD">USD (US Dollar)</option>
                    </select>
                </div>
            </div>

            <div class="mb-5">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Transfer Instructions for Buyer</label>
                <textarea x-model="newAccount.transfer_instructions" rows="2" placeholder="e.g. Specify Unit Number and Reservation Code in the transaction narrative." class="w-full text-xs rounded-lg border-slate-300 py-2 px-3"></textarea>
            </div>

            <button type="button" @click="saveAccount()" :disabled="savingAccount"
                    class="py-2.5 px-6 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition">
                <span x-text="savingAccount ? 'Saving...' : 'Register Payment Destination Account'"></span>
            </button>
        </div>

        <!-- Active Accounts Display -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-bold text-slate-900 mb-4">Registered Destination Accounts</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($paymentAccounts as $acc)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50">
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-bold text-slate-900 text-sm">{{ $acc->account_label }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">{{ $acc->currency }}</span>
                        </div>
                        <div class="text-xs text-slate-700 font-medium">{{ $acc->institution_name }}</div>
                        <div class="text-xs font-mono font-bold text-slate-900 mt-1">Account: {{ $acc->account_number }}</div>
                        <div class="text-[11px] text-slate-500 mt-1">Holder: {{ $acc->account_holder_name }}</div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500">No destination bank accounts registered yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- TAB 4: Contracts, Amendments & Payments -->
    <div x-show="activeTab === 'contracts'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-lg font-bold text-slate-900 mb-1">Active Developer Sales Contracts</h2>
            <p class="text-xs text-slate-500 mb-6">Manage contracts, propose authorized price amendments, and verify buyer transfer claims</p>

            <div class="space-y-4">
                @forelse($contracts as $contract)
                    <div class="p-5 rounded-xl border border-slate-200 bg-slate-50/60 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
                            <div>
                                <span class="font-bold text-slate-900 text-sm">{{ $contract->contract_code }}</span>
                                <span class="text-slate-500 ml-2">Buyer: <strong>{{ $contract->buyer->name ?? 'Buyer' }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-100 text-blue-800">{{ $contract->status->value }}</span>
                                <span class="font-mono font-bold text-slate-900">{{ number_format((float) $contract->contract_price_etb, 2) }} ETB</span>
                            </div>
                        </div>

                        <!-- Installments & Verification Strip -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-3">
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase">Paid to Date</span>
                                <div class="font-bold text-emerald-700">{{ number_format((float) $contract->deposit_paid_etb, 2) }} ETB</div>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase">Balance Payable</span>
                                <div class="font-bold text-slate-800">{{ number_format((float) $contract->balance_payable_etb, 2) }} ETB</div>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase">Statutory Disclosure</span>
                                <div class="font-bold text-slate-800">{{ $contract->disclosure_accepted_by_buyer ? 'Consent Recorded' : 'Pending' }}</div>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] uppercase">Amendments</span>
                                <div class="font-bold text-slate-800">{{ $contract->amendments->count() }} Amendments</div>
                            </div>
                        </div>

                        <!-- Pending Transactions to Verify -->
                        @if($contract->transactions->where('status', 'pending_verification')->isNotEmpty())
                            <div class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-950">
                                <span class="font-bold">Pending Manual Transfer Claims to Verify:</span>
                                @foreach($contract->transactions->where('status', 'pending_verification') as $txn)
                                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-amber-200">
                                        <div>
                                            <span>Ref: <strong>{{ $txn->transfer_reference }}</strong> ({{ number_format((float) $txn->amount_etb, 2) }} ETB)</span>
                                            <span class="text-[10px] text-slate-500 block">Bank: {{ $txn->paymentAccount->institution_name ?? 'Bank' }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="button" @click="verifyClaim({{ $txn->id }}, true)" class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-[11px]">
                                                Approve
                                            </button>
                                            <button type="button" @click="verifyClaim({{ $txn->id }}, false)" class="px-2.5 py-1 rounded bg-red-600 hover:bg-red-700 text-white font-semibold text-[11px]">
                                                Reject
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Amendments History & Audit Trail -->
                        @if($contract->amendments->isNotEmpty())
                            <div class="mt-4 p-3 rounded-lg bg-slate-100/70 border border-slate-200 text-xs">
                                <span class="font-bold text-slate-800">Contract Amendments History ({{ $contract->amendments->count() }}):</span>
                                <div class="mt-2 space-y-2">
                                    @foreach($contract->amendments as $amendment)
                                        <div class="p-2 rounded bg-white border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div>
                                                <span class="font-mono font-semibold text-slate-900">{{ $amendment->amendment_code }}</span>:
                                                <span class="text-slate-500 line-through">{{ number_format((float) $amendment->previous_price_etb, 2) }} ETB</span>
                                                <span class="mx-1 font-bold text-slate-700">&rarr;</span>
                                                <span class="font-bold text-blue-700">{{ number_format((float) $amendment->revised_price_etb, 2) }} ETB</span>
                                                <span class="text-slate-500 block text-[11px] mt-0.5">{{ $amendment->reason }}</span>
                                                @if($amendment->status === 'rejected' && $amendment->rejection_reason)
                                                    <span class="text-rose-600 block text-[11px] font-medium">Rejection Reason: {{ $amendment->rejection_reason }}</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if($amendment->status === 'accepted' || $amendment->accepted_by_buyer)
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">Accepted</span>
                                                @elseif($amendment->status === 'rejected')
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-100 text-rose-800">Rejected</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800">Pending Buyer Consent</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Propose Contract Amendment -->
                        <div class="mt-4 pt-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                            <span class="text-slate-500 text-[11px]">Propose agreed contractual price revision:</span>
                            <div class="flex items-center gap-2">
                                <input type="number" step="1000" x-model.number="amendmentPrices[{{ $contract->id }}]" placeholder="Revised Price (ETB)" class="w-36 text-xs rounded border-slate-300 py-1 px-2 font-mono">
                                <input type="text" x-model="amendmentReasons[{{ $contract->id }}]" placeholder="Reason (e.g. upgraded finishing)" class="w-48 text-xs rounded border-slate-300 py-1 px-2">
                                <button type="button" @click="proposeAmendment({{ $contract->id }})" class="py-1 px-3 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs">
                                    Propose Amendment
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500">No active contracts for this project.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- TAB 5: Unit Inventory Management -->
    <div x-show="activeTab === 'units'" class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Project Unit Inventory</h2>
                    <p class="text-xs text-slate-500">Manage individual apartment and commercial units, prices, and availability</p>
                </div>
            </div>

            <!-- Unit List Table -->
            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-xs text-slate-600 border border-slate-200 rounded-xl overflow-hidden">
                    <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                        <tr>
                            <th class="p-3">Unit Number</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Floor</th>
                            <th class="p-3">Area (m²)</th>
                            <th class="p-3">Beds / Baths</th>
                            <th class="p-3">Price (ETB)</th>
                            <th class="p-3">Price / m²</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $allUnits = $project->buildings->flatMap->floors->flatMap->units;
                        @endphp
                        @forelse($allUnits as $unit)
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-3 font-mono font-bold text-slate-900">{{ $unit->unit_number }}</td>
                                <td class="p-3 uppercase font-medium">{{ $unit->unit_type }}</td>
                                <td class="p-3">Floor {{ $unit->floor_number }}</td>
                                <td class="p-3">{{ number_format((float) $unit->area_sqm, 1) }} m²</td>
                                <td class="p-3">{{ $unit->bedrooms }} Bed / {{ $unit->bathrooms }} Bath</td>
                                <td class="p-3 font-bold text-slate-900">{{ number_format((float) $unit->price_etb, 2) }} ETB</td>
                                <td class="p-3 text-slate-500">{{ number_format((float) $unit->price_per_sqm_etb, 2) }} ETB</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                        {{ $unit->status->value === 'available' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                        {{ $unit->status->value === 'reserved' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ $unit->status->value === 'contracted' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $unit->status->value === 'sold' ? 'bg-slate-100 text-slate-800' : '' }}
                                    ">
                                        {{ $unit->status->value }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-6 text-center text-slate-400">No units registered for this development project yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Add Unit Form -->
            <div class="mt-8 pt-6 border-t border-slate-100">
                <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <span>+</span> Add New Unit to Development
                </h3>

                <form method="POST" action="{{ route('projects.units.store', $project->slug) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Unit Number <span class="text-rose-500">*</span></label>
                        <input type="text" name="unit_number" placeholder="e.g. B-103 or A-201" required class="w-full rounded-xl border-slate-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Unit Type <span class="text-rose-500">*</span></label>
                        <select name="unit_type" class="w-full rounded-xl border-slate-300 text-xs" required>
                            <option value="studio">Studio</option>
                            <option value="1br">1 Bedroom</option>
                            <option value="2br" selected>2 Bedrooms</option>
                            <option value="3br">3 Bedrooms</option>
                            <option value="4br">4 Bedrooms</option>
                            <option value="penthouse">Penthouse</option>
                            <option value="commercial">Commercial / Retail</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Floor Number <span class="text-rose-500">*</span></label>
                        <input type="number" name="floor_number" value="1" min="0" max="100" required class="w-full rounded-xl border-slate-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Unit Area (m²) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.1" name="area_sqm" value="110" min="10" required class="w-full rounded-xl border-slate-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Bedrooms</label>
                        <input type="number" name="bedrooms" value="2" min="0" max="10" required class="w-full rounded-xl border-slate-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Bathrooms</label>
                        <input type="number" name="bathrooms" value="2" min="0" max="10" required class="w-full rounded-xl border-slate-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Price in ETB (ብር) <span class="text-rose-500">*</span></label>
                        <input type="number" step="0.01" name="price_etb" value="8800000" min="1" required class="w-full rounded-xl border-slate-300 text-xs font-bold text-teal-900">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Orientation</label>
                        <select name="orientation" class="w-full rounded-xl border-slate-300 text-xs">
                            <option value="north_east">North-East</option>
                            <option value="south_west">South-West</option>
                            <option value="north_west">North-West</option>
                            <option value="south_east">South-East</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Availability Status <span class="text-rose-500">*</span></label>
                        <select name="status" class="w-full rounded-xl border-slate-300 text-xs" required>
                            <option value="available" selected>Available for Reservation</option>
                            <option value="reserved">Reserved (Temporary Hold)</option>
                            <option value="contracted">Contract Signed</option>
                            <option value="sold">Sold</option>
                        </select>
                    </div>
                    <div class="sm:col-span-3 flex justify-end pt-2">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-teal-800 hover:bg-teal-900 text-white font-bold text-xs shadow-sm transition">
                            + Add Unit to Inventory
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function developerProjectManager(config) {
    return {
        projectId: config.projectId,
        activeTab: (new URLSearchParams(window.location.search)).get('tab') || 'stages',
        stages: config.initialMilestones || [],
        calculatedProgress: config.initialOverallProgress || 0.0,
        savingStages: false,
        savingPlan: false,
        savingAccount: false,
        feedbackMessage: null,
        errorMessage: null,
        amendmentPrices: {},
        amendmentReasons: {},

        newStage: {
            stage_code: '',
            name_en: '',
            name_am: '',
            progress_weight: 10.0,
            completion_percentage: 0.0
        },

        newPlan: {
            name_en: '',
            name_am: '',
            is_default: true,
            installments: [
                { sequence_number: 1, milestone_label_en: 'Reservation Deposit', percentage: 10.0, trigger_milestone_code: '' },
                { sequence_number: 2, milestone_label_en: 'Foundation', percentage: 30.0, trigger_milestone_code: 'foundation' },
                { sequence_number: 3, milestone_label_en: 'Structure', percentage: 30.0, trigger_milestone_code: 'structure' },
                { sequence_number: 4, milestone_label_en: 'Handover', percentage: 30.0, trigger_milestone_code: 'handover' }
            ]
        },

        newAccount: {
            account_label: '',
            institution_name: '',
            account_holder_name: '',
            account_number: '',
            branch_name: '',
            currency: 'ETB',
            transfer_instructions: ''
        },

        get totalWeight() {
            return this.stages.reduce((acc, s) => acc + (parseFloat(s.progress_weight) || 0), 0);
        },

        get isTotalWeightValid() {
            return Math.abs(this.totalWeight - 100.0) <= 0.01;
        },

        get newPlanTotalPct() {
            return this.newPlan.installments.reduce((acc, i) => acc + (parseFloat(i.percentage) || 0), 0);
        },

        addStage() {
            if (!this.newStage.stage_code || !this.newStage.name_en) {
                this.errorMessage = 'Stage code and English name are required.';
                return;
            }
            this.stages.push({
                stage_code: this.newStage.stage_code.toLowerCase().trim().replace(/\\s+/g, '_'),
                name_en: this.newStage.name_en,
                name_am: this.newStage.name_am || null,
                progress_weight: parseFloat(this.newStage.progress_weight) || 10.0,
                completion_percentage: 0.0,
                is_verified: false
            });
            this.newStage.stage_code = '';
            this.newStage.name_en = '';
            this.newStage.name_am = '';
            this.recomputeProgress();
        },

        removeStage(idx) {
            this.stages.splice(idx, 1);
            this.recomputeProgress();
        },

        recomputeProgress() {
            const sumWeights = this.totalWeight;
            if (sumWeights <= 0) {
                this.calculatedProgress = 0.0;
                return;
            }
            const weightedSum = this.stages.reduce((acc, s) => acc + ((parseFloat(s.completion_percentage) || 0) * (parseFloat(s.progress_weight) || 0)), 0);
            this.calculatedProgress = Math.min(100, Math.max(0, weightedSum / sumWeights));
        },

        async saveStages() {
            if (!this.isTotalWeightValid) {
                this.errorMessage = `Milestone weights must total 100.00%. Current sum: ${this.totalWeight.toFixed(2)}%`;
                return;
            }
            this.savingStages = true;
            this.errorMessage = null;

            try {
                const response = await fetch(`/api/v1/projects/${this.projectId}/milestones`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        milestones: this.stages,
                        notes: 'Updated via Developer Project Management interface.'
                    })
                });

                const data = await response.json();
                if (!response.ok) {
                    this.errorMessage = data.message || 'Failed to update construction stages.';
                    return;
                }

                this.calculatedProgress = data.overall_progress_pct;
                this.feedbackMessage = 'Construction stages saved and weighted progress recalculated.';
            } catch (err) {
                this.errorMessage = 'Network error while saving construction stages.';
            } finally {
                this.savingStages = false;
            }
        },

        addPlanInstallment() {
            this.newPlan.installments.push({
                sequence_number: this.newPlan.installments.length + 1,
                milestone_label_en: '',
                percentage: 10.0,
                trigger_milestone_code: ''
            });
        },

        removePlanInstallment(idx) {
            this.newPlan.installments.splice(idx, 1);
            this.newPlan.installments.forEach((inst, i) => inst.sequence_number = i + 1);
        },

        async savePlan() {
            if (this.newPlanTotalPct !== 100.0) {
                this.errorMessage = `Payment plan percentages must total exactly 100.00%. Current: ${this.newPlanTotalPct.toFixed(2)}%`;
                return;
            }
            this.savingPlan = true;
            this.errorMessage = null;

            try {
                const response = await fetch(`/api/v1/projects/${this.projectId}/payment-plans`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify(this.newPlan)
                });

                const data = await response.json();
                if (!response.ok) {
                    this.errorMessage = data.message || 'Failed to create payment plan.';
                    return;
                }

                this.feedbackMessage = 'Custom 100% payment plan saved successfully.';
                setTimeout(() => window.location.reload(), 1000);
            } catch (err) {
                this.errorMessage = 'Network error while saving payment plan.';
            } finally {
                this.savingPlan = false;
            }
        },

        async saveAccount() {
            if (!this.newAccount.institution_name || !this.newAccount.account_number || !this.newAccount.account_holder_name) {
                this.errorMessage = 'Institution name, account holder, and account number are required.';
                return;
            }
            this.savingAccount = true;
            this.errorMessage = null;

            try {
                const response = await fetch('/api/v1/developer/payment-accounts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        developer_id: {{ $project->developer_id }},
                        project_id: this.projectId,
                        ...this.newAccount
                    })
                });

                const data = await response.json();
                if (!response.ok) {
                    this.errorMessage = data.message || 'Failed to save payment account.';
                    return;
                }

                this.feedbackMessage = 'Developer manual payment account registered successfully.';
                setTimeout(() => window.location.reload(), 1000);
            } catch (err) {
                this.errorMessage = 'Network error while saving payment account.';
            } finally {
                this.savingAccount = false;
            }
        },

        async proposeAmendment(contractId) {
            const price = this.amendmentPrices[contractId];
            const reason = this.amendmentReasons[contractId] || 'Agreed specification revision';

            if (!price || price <= 0) {
                this.errorMessage = 'Please enter a valid revised contract price in ETB.';
                return;
            }

            try {
                const response = await fetch(`/api/v1/contracts/${contractId}/amend`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        revised_price_etb: price,
                        reason: reason
                    })
                });

                const data = await response.json();
                if (!response.ok) {
                    this.errorMessage = data.message || 'Failed to amend contract price.';
                    return;
                }

                this.feedbackMessage = `Contract price amendment proposed (${data.contract_code}).`;
                setTimeout(() => window.location.reload(), 1000);
            } catch (err) {
                this.errorMessage = 'Network error while proposing amendment.';
            }
        },

        async verifyClaim(paymentId, approved) {
            let reason = null;
            if (!approved) {
                reason = prompt('Please enter the rejection reason:');
                if (!reason) return;
            }

            try {
                const response = await fetch(`/api/v1/payments/${paymentId}/verify`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        approved: approved,
                        decision_notes: approved ? 'Verified via developer bank account ledger.' : null,
                        rejection_reason: reason
                    })
                });

                const data = await response.json();
                if (!response.ok) {
                    this.errorMessage = data.message || 'Failed to verify payment claim.';
                    return;
                }

                this.feedbackMessage = approved ? 'Payment verified and installment settled.' : 'Payment claim rejected.';
                setTimeout(() => window.location.reload(), 1000);
            } catch (err) {
                this.errorMessage = 'Network error while verifying payment.';
            }
        }
    };
}
</script>
@endsection

