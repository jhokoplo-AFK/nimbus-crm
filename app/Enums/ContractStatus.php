<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'Draft';
    case Active = 'Active';
    case Expired = 'Expired';

    public function label(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Active => 'emerald',
            self::Expired => 'amber',
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
