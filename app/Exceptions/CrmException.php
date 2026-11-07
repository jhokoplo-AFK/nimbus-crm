<?php

namespace App\Exceptions;

use DomainException;

/**
 * Base class for every business rule violation raised by the CRM domain.
 * Because it extends DomainException, bootstrap/app.php turns it into a
 * validation error payload for Inertia forms instead of a 500 response.
 */
class CrmException extends DomainException {}
