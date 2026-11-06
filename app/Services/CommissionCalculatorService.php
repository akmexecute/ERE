<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Commission\Actions\CalculateAgentCommissionAction;

class CommissionCalculatorService
{
    public function __construct(
        protected ?CalculateAgentCommissionAction $calculator = null
    ) {
        $this->calculator ??= new CalculateAgentCommissionAction();
    }

    /**
     * @return array{total_commission: float, company_share: float, agent_share: float}
     */
    public function calculate(float $salePrice, int $companyId): array
    {
        $result = $this->calculator->execute(
            transactionAmountEtb: $salePrice,
            transactionType: 'sale',
            companyId: $companyId
        );

        return [
            'total_commission' => $result['gross_commission_etb'],
            'company_share' => $result['company_share_etb'],
            'agent_share' => $result['agent_share_etb'],
        ];
    }
}
