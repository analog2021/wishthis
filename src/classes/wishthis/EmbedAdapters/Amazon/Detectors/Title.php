<?php

/**
 * Amazon product title.
 */

namespace wishthis\EmbedAdapters\Amazon\Detectors;

use Embed\Detectors\Title as Detector;

class Title extends Detector
{
    public function detect(): ?string
    {
        $title = $this->extractor->getDocument()
        ->select('.//*[@id="productTitle"]')
        ->str();

        if ($title) {
            return $title;
        }

        /**
         * The page title looks like "Amazon.com: Product name: Category".
         */
        $title = parent::detect();

        if ($title && \preg_match('/^Amazon\.[a-z.]+\s*:\s*/i', $title, $prefix)) {
            $title = \substr($title, \strlen($prefix[0]));
            $title = \preg_replace('/\s*:\s*[^:]+$/', '', $title);
        }

        return $title ?: null;
    }
}
