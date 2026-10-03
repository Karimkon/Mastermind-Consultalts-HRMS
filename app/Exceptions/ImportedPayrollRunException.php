<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when something tries to re-process a payroll run that was imported.
 *
 * Imported runs record payments that were made outside this system. The
 * calculation engine has no way to arrive at the same figures, so processing
 * one would not correct it — it would destroy the record of what was paid.
 */
class ImportedPayrollRunException extends RuntimeException
{
}
