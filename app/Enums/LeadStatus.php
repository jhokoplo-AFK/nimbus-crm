<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'New';
    case Contacted = 'Contacted';
    case Qualified = 'Qualified';
    case Unqualified = 'Unqualified';
    case Converted = 'Converted';

    public function label(): string
    {
        return $this->value;
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'slate',
            self::Contacted => 'sky',
            self::Qualified => 'emerald',
            self::Unqualified => 'rose',
            self::Converted => 'violet',
        };
    }

    /**
     * A lead can only be converted once and must have shown buying intent.
     */
    public function isConvertible(): bool
    {
        return $this !== self::Converted && $this !== self::Unqualified;
    }

    /**
     * Converted leads are permanently locked for editing.
     */
    public function isEditable(): bool
    {
        return $this !== self::Converted;
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
