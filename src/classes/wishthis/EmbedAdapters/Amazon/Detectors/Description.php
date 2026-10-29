<?php

/**
 * Amazon product description.
 *
 * The meta description of Amazon pages is a generic category text, so it is
 * never used.
 */

namespace wishthis\EmbedAdapters\Amazon\Detectors;

use Embed\Detectors\Detector;

class Description extends Detector
{
    private const MAX_BULLETS = 5;

    public function detect(): ?string
    {
        $document = $this->extractor->getDocument();

        $bullets = $document
        ->select('.//*[@id="feature-bullets"]//li//span[contains(concat(" ", normalize-space(@class), " "), " a-list-item ")]')
        ->strAll();

        if (!empty($bullets)) {
            return \implode("\n", \array_slice(\array_values($bullets), 0, self::MAX_BULLETS));
        }

        return $document
        ->select('.//*[@id="productDescription"]')
        ->str();
    }
}
