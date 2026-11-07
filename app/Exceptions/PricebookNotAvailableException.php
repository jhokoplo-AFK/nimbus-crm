<?php

namespace App\Exceptions;

class PricebookNotAvailableException extends CrmException
{
    public static function inactive(): self
    {
        return new self('The selected pricebook is inactive and cannot be used for new quotations.');
    }

    public static function missingPrice(int $productId): self
    {
        return new self("Product #{$productId} is not priced in the selected pricebook.");
    }
}
