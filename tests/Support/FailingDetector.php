<?php

declare(strict_types=1);

namespace wishthis\Tests\Support;

use Embed\Detectors\Detector;

/**
 * A detector which always fails, to check that the other details of a page
 * are still read.
 */
final class FailingDetector extends Detector
{
    public function detect(): never
    {
        throw new \RuntimeException('Detector failed on purpose');
    }
}
