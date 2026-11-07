<?php

namespace App\Exceptions;

class LeadNotConvertibleException extends CrmException
{
    public static function alreadyConverted(): self
    {
        return new self('This lead has already been converted.');
    }

    public static function disqualified(): self
    {
        return new self('Unqualified leads must be re-qualified before they can be converted.');
    }
}
