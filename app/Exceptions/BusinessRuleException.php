<?php

namespace App\Exceptions;

use DomainException;

/**
 * Base for business rule violations whose message is safe to show to the
 * user. Rendered as an error toast (or 422 JSON) in bootstrap/app.php.
 */
abstract class BusinessRuleException extends DomainException {}
