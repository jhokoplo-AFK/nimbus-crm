<?php

namespace App\Data;

final readonly class PriceApprovalDecision
{
    /**
     * @param  string  $reason  discount|amount|discount_and_amount|requires_higher_authority|within_limit|fallback_policy
     */
    public function __construct(
        public bool $requiresApproval,
        public ?int $requiredLevel = null,
        public ?string $requiredRole = null,
        public ?int $submitterLevel = null,
        public string $reason = 'within_limit',
    ) {}

    /**
     * @return array{requires_approval: bool, required_level: int|null, required_role: string|null, submitter_level: int|null, reason: string}
     */
    public function toArray(): array
    {
        return [
            'requires_approval' => $this->requiresApproval,
            'required_level' => $this->requiredLevel,
            'required_role' => $this->requiredRole,
            'submitter_level' => $this->submitterLevel,
            'reason' => $this->reason,
        ];
    }
}
