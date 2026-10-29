<?php

/**
 * Cache embed requests.
 *
 * @see https://github.com/oscarotero/Embed/issues/471
 *
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis\Cache;

use Psr\Http\Client\ClientInterface;
use wishthis\EmbedAdapters\Amazon\Extractor as AmazonExtractor;
use wishthis\EmbedAdapters\Detectors\Image as ImageDetector;

class Embed extends Cache
{
    /**
     * Returns an Embed instance with wishthis' own adapters and detectors.
     *
     * @param ClientInterface|null $client The HTTP client (for testing).
     *
     * @return \Embed\Embed
     */
    public static function createEmbed(?ClientInterface $client = null): \Embed\Embed
    {
        $embed   = new \Embed\Embed($client ? new \Embed\Http\Crawler($client) : null);
        $factory = $embed->getExtractorFactory();

        foreach (\array_keys(AmazonExtractor::DOMAINS) as $domain) {
            $factory->addAdapter($domain, AmazonExtractor::class);
        }

        $factory->addDetector('image', ImageDetector::class);

        return $embed;
    }

    /**
     * Private
     */
    protected function getFilepath(): string
    {
        return parent::getFilepath() . '.json';
    }

    /**
     * Public
     */
    public function __construct($url)
    {
        parent::__construct($url, \wishthis\Duration::MONTH);

        $this->directory .= '/embed';
    }

    public function get(bool $generateCache = false): \stdClass
    {
        $filepath = $this->getFilepath();

        /** Get existing info */
        $info = $this->exists() ? \json_decode(\file_get_contents($filepath)) : new \stdClass();

        if (true === $generateCache) {
            try {
                /**
                 * Fetch embed info
                 *
                 * @link https://github.com/oscarotero/Embed
                 */
                $embedInfo = static::createEmbed()->get($this->url);
                $codePage  = $embedInfo->getResponse()->getStatusCode();

                /**
                 * The shop refused to show the page. Nothing is cached, and a
                 * previously cached result is still better than nothing.
                 */
                if ($embedInfo instanceof AmazonExtractor && $embedInfo->isBlocked()) {
                    if (!$this->exists()) {
                        $info->url     = $this->url;
                        $info->blocked = true;
                    }

                    return $info;
                }

                if ($codePage < 200 || $codePage >= 400) {
                    $generateCache = false;
                }

                /** Convert embed info to a saveable format (JSON) stdClass */
                $info->authorName    = (string) $embedInfo->authorName;
                $info->authorUrl     = (string) $embedInfo->authorUrl;
                $info->cms           = (string) $embedInfo->cms;
                $info->code          = (string) $embedInfo->code;
                $info->description   = (string) $embedInfo->description;
                $info->favicon       = (string) $embedInfo->favicon;
                $info->feeds         = (array)  $embedInfo->feeds;
                $info->icon          = (string) $embedInfo->icon;
                $info->image         = (string) $embedInfo->image;
                $info->keywords      = (array)  $embedInfo->keywords;
                $info->language      = (string) $embedInfo->language;
                $info->languages     = (array)  $embedInfo->languages;
                $info->license       = (string) $embedInfo->license;
                $info->providerName  = (string) $embedInfo->providerName;
                $info->providerUrl   = (string) $embedInfo->providerUrl;
                $info->publishedTime = $embedInfo->publishedTime ? $embedInfo->publishedTime->format('d.m.Y') : '';
                $info->redirect      = (string) $embedInfo->redirect;
                $info->title         = (string) $embedInfo->title;
                $info->url           = (string) $embedInfo->url;
                $info->price         = null;
                $info->currency      = null;
                $info->blocked       = false;

                if ($embedInfo instanceof AmazonExtractor) {
                    $info->price    = $embedInfo->getPrice();
                    $info->currency = $embedInfo->getCurrency();
                }

                /** Some websites repeat the title as description */
                if ($info->description === $info->title) {
                    $info->description = '';
                }

                /** Don't keep a result without any useful information */
                if (!$info->title && !$info->description && !$info->image) {
                    $generateCache = false;
                }

                /**
                 * The detected (canonical) URL may differ from the page which
                 * was fetched. Only then does it need to be checked separately.
                 */
                if ($info->url && $info->url !== (string) $embedInfo->getUri()) {
                    $codeURL = \wishthis\URL::getResponseCode($info->url);

                    if ($codeURL < 200 || $codeURL >= 400) {
                        $info->url = $this->url;
                    }
                }
            } catch (\Throwable $ex) {
                $generateCache = false;

                echo $ex->getMessage();
            }

            if ($generateCache) {
                $ch_options = [
                    \CURLOPT_AUTOREFERER    => true,
                    \CURLOPT_CONNECTTIMEOUT => 30,
                    \CURLOPT_FOLLOWLOCATION => true,
                    \CURLOPT_HEADER         => false,
                    \CURLOPT_MAXREDIRS      => 10,
                    \CURLOPT_RETURNTRANSFER => true,
                    \CURLOPT_SSL_VERIFYPEER => false,
                    \CURLOPT_TIMEOUT        => 30,
                    \CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:99.0) Gecko/20100101 Firefox/99.0',
                ];

                /** Favicon */
                if (\str_contains(\pathinfo($info->favicon, \PATHINFO_EXTENSION), 'ico')) {
                    $ch = \curl_init($info->favicon);
                    \curl_setopt_array($ch, $ch_options);

                    $favicon = \curl_exec($ch);
                    $code    = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);

                    $info->favicon = $favicon && ($code >= 200 && $code < 400) ? 'data:image/x-icon;base64,' . \base64_encode($favicon) : '';
                }
            }

            if ($generateCache) {
                $this->write($info);
            }
        }

        return $info;
    }
}
