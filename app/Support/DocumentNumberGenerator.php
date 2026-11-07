<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Generates human-readable, gapless document numbers (Q-2026-00042) inside the
 * caller's transaction. The SELECT ... FOR UPDATE lock prevents two concurrent
 * requests from receiving the same sequence value.
 */
class DocumentNumberGenerator
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function next(string $modelClass, string $column, string $prefix, bool $includeRevisionSuffix = false): string
    {
        $stem = sprintf('%s-%s-', $prefix, now()->format('Y'));
        $padding = (int) config('crm.documents.number_padding', 5);

        $query = $modelClass::query()
            ->where($column, 'like', $stem.'%')
            ->lockForUpdate();

        if (! $includeRevisionSuffix) {
            // Revisions carry an "-R2" suffix and must not be treated as the
            // latest value of the base sequence.
            $query->where($column, 'not like', '%-R%');
        }

        $latest = $query->orderByDesc($column)->value($column);

        $sequence = $latest
            ? ((int) substr((string) $latest, strlen($stem))) + 1
            : 1;

        return $stem.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }
}
