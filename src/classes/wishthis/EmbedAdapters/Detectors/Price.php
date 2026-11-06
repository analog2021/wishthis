<?php

/**
 * Price detector for all websites.
 *
 * Reads the product price from linked data (`offers`), Open Graph product
 * metas or microdata, in this order.
 */

namespace wishthis\EmbedAdapters\Detectors;

use Embed\Detectors\Detector;

class Price extends Detector
{
    /**
     * @return array|null `['price' => float, 'currency' => ?string]`
     */
    public function detect(): ?array
    {
        return $this->detectFromLinkedData($this->extractor->getLinkedData()->getAll())
            ?: $this->detectFromMetas()
            ?: $this->detectFromMicrodata();
    }

    /**
     * Returns the first offer with a price found anywhere in the linked data.
     *
     * @param mixed $data
     *
     * @return array|null
     */
    private function detectFromLinkedData(mixed $data): ?array
    {
        if (!\is_array($data)) {
            return null;
        }

        if (isset($data['offers']) && \is_array($data['offers'])) {
            $offers = \array_is_list($data['offers']) ? $data['offers'] : [$data['offers']];

            foreach ($offers as $offer) {
                if (!\is_array($offer)) {
                    continue;
                }

                $price = self::parsePrice($offer['price'] ?? $offer['lowPrice'] ?? $offer['priceSpecification']['price'] ?? null);

                if (null !== $price) {
                    return [
                        'price'    => $price,
                        'currency' => self::parseCurrency($offer['priceCurrency'] ?? $offer['priceSpecification']['priceCurrency'] ?? null),
                    ];
                }
            }
        }

        foreach ($data as $value) {
            $price = $this->detectFromLinkedData($value);

            if ($price) {
                return $price;
            }
        }

        return null;
    }

    private function detectFromMetas(): ?array
    {
        $metas = $this->extractor->getMetas();
        $price = self::parsePrice($metas->str('product:price:amount', 'og:price:amount'));

        if (null === $price) {
            return null;
        }

        return [
            'price'    => $price,
            'currency' => self::parseCurrency($metas->str('product:price:currency', 'og:price:currency')),
        ];
    }

    private function detectFromMicrodata(): ?array
    {
        $document = $this->extractor->getDocument();
        $node     = $document->select('.//*[@itemprop="price"]')->node();

        if (!$node) {
            return null;
        }

        $price = self::parsePrice($node->getAttribute('content') ?: $node->textContent);

        if (null === $price) {
            return null;
        }

        $currency = $document->select('.//*[@itemprop="priceCurrency"]')->node();

        return [
            'price'    => $price,
            'currency' => $currency ? self::parseCurrency($currency->getAttribute('content') ?: $currency->textContent) : null,
        ];
    }

    /**
     * Understands `29.99`, `1,299.00` and `29,99`.
     *
     * @param mixed $price
     *
     * @return float|null
     */
    private static function parsePrice(mixed $price): ?float
    {
        if (\is_int($price) || \is_float($price)) {
            return $price > 0 ? (float) $price : null;
        }

        if (!\is_string($price)) {
            return null;
        }

        $price = \trim($price);

        if (\preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $price)) {
            $price = \str_replace(',', '', $price);
        } elseif (\preg_match('/^\d+,\d{1,2}$/', $price)) {
            $price = \str_replace(',', '.', $price);
        }

        return \is_numeric($price) && $price > 0 ? (float) $price : null;
    }

    private static function parseCurrency(mixed $currency): ?string
    {
        if (!\is_string($currency)) {
            return null;
        }

        $currency = \strtoupper(\trim($currency));

        return \preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
    }
}
