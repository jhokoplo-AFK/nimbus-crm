<?php

namespace App\Exceptions;

class QuotationStateException extends CrmException
{
    public static function notEditable(): self
    {
        return new self('Only draft or in-review quotations can be edited.');
    }

    public static function empty(): self
    {
        return new self('A quotation requires at least one line item.');
    }

    public static function notAwaitingApproval(): self
    {
        return new self('Only quotations in review can be approved or rejected.');
    }

    public static function notApproved(): self
    {
        return new self('The quotation must be approved before it can be sent.');
    }

    public static function notRevisable(): self
    {
        return new self('Approved quotations cannot be revised. Create a new quotation instead.');
    }
}
