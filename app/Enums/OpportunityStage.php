<?php

namespace App\Enums;

enum OpportunityStage: string
{
    case Prospecting = 'Prospecting';
    case Qualification = 'Qualification';
    case Proposal = 'Proposal';
    case Negotiation = 'Negotiation';
    case ClosedWon = 'Closed Won';
    case ClosedLost = 'Closed Lost';

    public function label(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Prospecting => 'slate',
            self::Qualification => 'sky',
            self::Proposal => 'indigo',
            self::Negotiation => 'amber',
            self::ClosedWon => 'emerald',
            self::ClosedLost => 'rose',
        };
    }

    public function defaultProbability(): int
    {
        return match ($this) {
            self::Prospecting => 10,
            self::Qualification => 25,
            self::Proposal => 50,
            self::Negotiation => 75,
            self::ClosedWon => 100,
            self::ClosedLost => 0,
        };
    }

    public function isWon(): bool
    {
        return $this === self::ClosedWon;
    }

    public function isLost(): bool
    {
        return $this === self::ClosedLost;
    }

    public function isClosed(): bool
    {
        return $this->isWon() || $this->isLost();
    }

    /**
     * @return array<int, array{value: string, label: string, tone: string, probability: int}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'tone' => $case->tone(),
            'probability' => $case->defaultProbability(),
        ], self::cases());
    }

    /**
     * Stages shown on the Kanban board, in pipeline order.
     *
     * @return array<int, self>
     */
    public static function board(): array
    {
        return self::cases();
    }
}
