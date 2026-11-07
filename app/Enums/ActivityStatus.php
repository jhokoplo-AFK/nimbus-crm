<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Open = 'Open';
    case Completed = 'Completed';

    public function label(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Completed => 'emerald',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'tone' => $case->tone(),
        ], self::cases());
    }
}
