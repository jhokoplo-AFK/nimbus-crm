<?php

namespace App\Data;

final readonly class CreditDecision
{
    /**
     * @param  string  $reason  within_limit|credit_limit|overdue|credit_limit_and_overdue
     */
    public function __construct(
        public bool $requiresApproval,
        public ?int $requiredLevel = null,
        public string $reason = 'within_limit',
        public float $exposure = 0.0,
        public ?float $creditLimit = null,
        public float $balanceDue = 0.0,
        public float $overdueBalance = 0.0,
    ) {}

    public function availableCredit(): ?float
    {
        return $this->creditLimit === null
            ? null
            : round($this->creditLimit - $this->balanceDue, 2);
    }

    /**
     * @return array<string, float|int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'requires_approval' => $this->requiresApproval,
            'required_level' => $this->requiredLevel,
            'reason' => $this->reason,
            'exposure' => $this->exposure,
            'credit_limit' => $this->creditLimit,
            'balance_due' => $this->balanceDue,
            'overdue_balance' => $this->overdueBalance,
            'available_credit' => $this->availableCredit(),
        ];
    }
}
