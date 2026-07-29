<?php

declare(strict_types=1);

namespace App\Services\Erp\Numbering;

use RuntimeException;

/**
 * Thrown when two allocations create the same counter row simultaneously. The
 * caller retries; the second attempt finds the row and takes the lock path.
 */
final class SequenceContendedException extends RuntimeException {}
