<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\CRM\Enums\CrmPipelineStage;
use App\Domains\CRM\Models\CrmLead;
use App\Domains\CRM\Models\CrmLeadActivity;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessCrmFollowUpsCommand extends Command
{
    protected $signature = 'crm:process-followups';
    protected $description = 'Process overdue and upcoming CRM lead follow-ups and notify assigned agents';

    public function handle(): int
    {
        $now = Carbon::now();

        // 1. Fetch leads with due follow-ups in non-terminal stages
        $dueLeads = CrmLead::query()
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', $now)
            ->whereNotIn('stage', [CrmPipelineStage::Closed->value, CrmPipelineStage::Lost->value])
            ->with(['agent'])
            ->get();

        $processedCount = 0;

        foreach ($dueLeads as $lead) {
            // 2. Prevent duplicate notifications for the same scheduled follow-up
            $alreadyReminded = CrmLeadActivity::query()
                ->where('crm_lead_id', $lead->id)
                ->where('activity_type', 'follow_up_reminder')
                ->where('created_at', '>=', $lead->next_follow_up_at)
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            // 3. Record reminder activity
            CrmLeadActivity::query()->create([
                'crm_lead_id' => $lead->id,
                'actor_user_id' => $lead->assigned_agent_id ?: $lead->agent_id,
                'activity_type' => 'follow_up_reminder',
                'summary' => "Follow-up reminder due for client {$lead->client_name} ({$lead->client_phone}). Notes: " . ($lead->notes ?: 'None'),
                'created_at' => $now,
            ]);

            $processedCount++;
        }

        $this->info("Processed {$processedCount} CRM follow-up reminders.");

        return Command::SUCCESS;
    }
}

