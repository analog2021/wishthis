<?php

/**
 * Embed adapter for Amazon product pages.
 *
 * Amazon pages have no Open Graph or linked data, so the product details are
 * read from the page markup instead.
 */

namespace wishthis\EmbedAdapters\Amazon;

use Embed\Extractor as Base;

class Extractor extends Base
{
    /**
     * Amazon domains and the currency their prices are shown in.
     */
    public const DOMAINS = [
        'amazon.com'     => 'USD',
        'amazon.ca'      => 'CAD',
        'amazon.com.mx'  => 'MXN',
        'amazon.com.br'  => 'BRL',
        'amazon.co.uk'   => 'GBP',
        'amazon.de'      => 'EUR',
        'amazon.fr'      => 'EUR',
        'amazon.it'      => 'EUR',
        'amazon.es'      => 'EUR',
        'amazon.nl'      => 'EUR',
        'amazon.com.be'  => 'EUR',
        'amazon.ie'      => 'EUR',
        'amazon.se'      => 'SEK',
        'amazon.pl'      => 'PLN',
        'amazon.com.tr'  => 'TRY',
        'amazon.ae'      => 'AED',
        'amazon.sa'      => 'SAR',
        'amazon.eg'      => 'EGP',
        'amazon.in'      => 'INR',
        'amazon.co.jp'   => 'JPY',
        'amazon.sg'      => 'SGD',
        'amazon.com.au'  => 'AUD',
    ];

    public function createCustomDetectors(): array
    {
        return [
            'title'       => new Detectors\Title($this),
            'description' => new Detectors\Description($this),
            'image'       => new Detectors\Image($this),
        ];
    }

    /**
     * Whether Amazon served its bot check instead of the product page.
     *
     * @return bool
     */
    public function isBlocked(): bool
    {
        return \str_contains((string) $this->getResponse()->getBody(), '/errors_page/validateCaptcha');
    }

    /**
     * Returns the displayed product price.
     *
     * @return float|null
     */
    public function getPrice(): ?float
    {
        $price = $this->getDocument()
        ->select('.//input[@id="twister-plus-price-data-price"]')
        ->str('value');

        return \is_numeric($price) && $price > 0 ? (float) $price : null;
    }

    /**
     * Returns the ISO currency code prices are shown in on this Amazon domain.
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        $host = $this->getUri()->getHost();

        foreach (self::DOMAINS as $domain => $currency) {
            if ($host === $domain || \str_ends_with($host, '.' . $domain)) {
                return $currency;
            }
        }

        return null;
    }
}
