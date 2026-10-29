<?php

/**
 * Image detector for all websites.
 *
 * Extends the default detector, which only understands linked data images in
 * the form of `"image": {"url": "..."}`, with the common `"image": "..."` and
 * `"image": ["...", ...]` forms as well as microdata.
 */

namespace wishthis\EmbedAdapters\Detectors;

use Embed\Detectors\Image as Detector;
use Psr\Http\Message\UriInterface;

class Image extends Detector
{
    public function detect(): ?UriInterface
    {
        $image = parent::detect()
              ?: $this->detectFromLinkedData($this->extractor->getLinkedData()->getAll());

        if ($image) {
            return $image;
        }

        $microdata = $this->extractor->getDocument()
        ->select('.//*[@itemprop="image"]')
        ->node();

        if ($microdata) {
            $url = $microdata->getAttribute('content') ?: $microdata->getAttribute('src') ?: $microdata->getAttribute('href');

            return $url ? $this->resolve($url) : null;
        }

        return null;
    }

    /**
     * Returns the first `image` found anywhere in the linked data.
     *
     * @param mixed $data
     *
     * @return UriInterface|null
     */
    private function detectFromLinkedData(mixed $data): ?UriInterface
    {
        if (!\is_array($data)) {
            return null;
        }

        if (isset($data['image'])) {
            $image = $data['image'];

            if (\is_array($image)) {
                $image = $image['url'] ?? $image['contentUrl'] ?? $image[0] ?? null;

                if (\is_array($image)) {
                    $image = $image['url'] ?? $image['contentUrl'] ?? null;
                }
            }

            if (\is_string($image) && $image) {
                return $this->resolve($image);
            }
        }

        foreach ($data as $value) {
            $image = $this->detectFromLinkedData($value);

            if ($image) {
                return $image;
            }
        }

        return null;
    }

    private function resolve(string $url): ?UriInterface
    {
        try {
            return $this->extractor->resolveUri($url);
        } catch (\Throwable $error) {
            return null;
        }
    }
}
