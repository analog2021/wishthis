<?php

/**
 * Amazon product image, in the highest resolution available.
 */

namespace wishthis\EmbedAdapters\Amazon\Detectors;

use Psr\Http\Message\UriInterface;
use wishthis\EmbedAdapters\Detectors\Image as Detector;

class Image extends Detector
{
    public function detect(): ?UriInterface
    {
        $document = $this->extractor->getDocument();

        /** Main product image */
        $image = $document
        ->select('.//img[@id="landingImage"]')
        ->url('data-old-hires');

        if ($image) {
            return $image;
        }

        /** Largest of the image variants (also used for book covers) */
        $variants = $document
        ->select('.//img[@id="landingImage" or @id="imgBlkFront" or @id="ebooksImgBlkFront"]')
        ->str('data-a-dynamic-image');

        if ($variants) {
            $variants = \json_decode(\html_entity_decode($variants), true);

            if (\is_array($variants) && !empty($variants)) {
                \uasort(
                    $variants,
                    function ($a, $b) {
                        return ($b[0] ?? 0) * ($b[1] ?? 0) <=> ($a[0] ?? 0) * ($a[1] ?? 0);
                    }
                );

                return $this->extractor->resolveUri(\array_key_first($variants));
            }
        }

        /** High resolution image from the image gallery data */
        if (\preg_match('/"hiRes":"(https:[^"]+)"/', (string) $this->extractor->getResponse()->getBody(), $matches)) {
            return $this->extractor->resolveUri(\stripslashes($matches[1]));
        }

        return parent::detect();
    }
}
