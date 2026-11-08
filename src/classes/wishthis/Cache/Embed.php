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
use wishthis\EmbedAdapters\Detectors\Price as PriceDetector;

class Embed extends Cache
{
    /**
     * Why a page could not be read, in `$info->problem`.
     */
    public const PROBLEM_BLOCKED     = 'blocked';
    public const PROBLEM_NOT_FOUND   = 'not_found';
    public const PROBLEM_UNREACHABLE = 'unreachable';
    public const PROBLEM_ERROR_PAGE  = 'error_page';

    /**
     * Parts of a page which only bot checks and block pages contain, even
     * when they are sent as "200 OK". Normal pages of shops using these
     * services don't contain them.
     */
    private const BLOCK_PAGE_MARKERS = [
        /** PerimeterX / HUMAN */
        'px-captcha',
        /** DataDome */
        'captcha-delivery.com',
        /** Imperva / Incapsula */
        'Incapsula incident ID',
        /** Cloudflare */
        '_cf_chl_opt',
    ];

    /**
     * Titles of bot checks and block pages. Only matched as the whole title,
     * optionally with the shop's name before or after it.
     */
    private const BLOCK_PAGE_TITLES = [
        'Just a moment',
        'Attention Required',
        'Access Denied',
        'Access to this page has been denied',
        'Pardon Our Interruption',
        'Robot Check',
        'Are you a robot',
        'Are you a human',
        'Bot verification',
        'Verify you are human',
        'Security check',
        'One more step',
        'Request unsuccessful',
    ];

    /**
     * Titles of error pages. Only matched as the whole title, optionally with
     * the shop's name before or after it.
     */
    private const ERROR_PAGE_TITLES = [
        'Error Page',
        'Error',
        'Error 404',
        '404',
        '404 Not Found',
        '404 Error',
        'Not Found',
        'Page Not Found',
        'Page cannot be found',
        'Page can\'t be found',
        'This page does not exist',
        'Page Unavailable',
        'Something went wrong',
        'Oops',
        'Sorry',
        'We\'re sorry',
        'Server Error',
        'Internal Server Error',
        'Service Unavailable',
        'Bad Request',
        'Site Maintenance',
        'Temporarily unavailable',
    ];

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
        $factory->addDetector('price', PriceDetector::class);

        return $embed;
    }

    /**
     * Whether the shop served a bot check or refused the request, instead of
     * the page itself.
     *
     * @param \Embed\Extractor $embedInfo
     *
     * @return bool
     */
    private static function isBlocked(\Embed\Extractor $embedInfo): bool
    {
        if ($embedInfo instanceof AmazonExtractor && $embedInfo->isBlocked()) {
            return true;
        }

        $response = $embedInfo->getResponse();

        /** @see https://developers.cloudflare.com/cloudflare-challenges/challenge-types/challenge-pages/detect-response/ */
        if ('challenge' === \strtolower($response->getHeaderLine('cf-mitigated'))) {
            return true;
        }

        return \in_array($response->getStatusCode(), [403, 429], true);
    }

    /**
     * Whether the title is one of the phrases, optionally with the shop's
     * name before or after it (e.g. "Page not found | Shop").
     *
     * @param string   $title
     * @param string[] $phrases
     *
     * @return bool
     */
    private static function titleIsOneOf(string $title, array $phrases): bool
    {
        $title = \trim(\html_entity_decode($title, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

        if ('' === $title) {
            return false;
        }

        $quoted    = \array_map(fn (string $phrase): string => \preg_quote($phrase, '/'), $phrases);
        $separator = '\s+[|\-–—:·•]\s+';
        $pattern   = '/^(?:.+' . $separator . ')?(?:' . \implode('|', $quoted) . ')[\s.!?…]*(?:' . $separator . '.+)?$/iu';

        return 1 === \preg_match($pattern, \str_replace('’', '\'', $title));
    }

    /**
     * Why a page which was sent as "200 OK" still doesn't show the product:
     * a bot check or block page, or an error page.
     *
     * @param \Embed\Extractor $embedInfo
     * @param string           $title     The detected title.
     *
     * @return string|null One of the `PROBLEM_*` constants or `null`.
     */
    private static function getDisguisedProblem(\Embed\Extractor $embedInfo, string $title): ?string
    {
        $body = (string) $embedInfo->getResponse()->getBody();

        foreach (self::BLOCK_PAGE_MARKERS as $marker) {
            if (false !== \stripos($body, $marker)) {
                return self::PROBLEM_BLOCKED;
            }
        }

        /** The page's own title, in case the detected one comes from elsewhere (e.g. og:title) */
        $titles = [$title];

        try {
            $titles[] = (string) $embedInfo->getDocument()->select('.//head/title')->str();
        } catch (\Throwable $ex) {
            /** No usable document, the detected title is all there is */
        }

        foreach ($titles as $pageTitle) {
            if (self::titleIsOneOf($pageTitle, self::BLOCK_PAGE_TITLES)) {
                return self::PROBLEM_BLOCKED;
            }

            if (self::titleIsOneOf($pageTitle, self::ERROR_PAGE_TITLES)) {
                return self::PROBLEM_ERROR_PAGE;
            }
        }

        return null;
    }

    /**
     * Reads one detail of the page. If that fails, the detail is left empty
     * and the others are still read.
     *
     * @param \Embed\Extractor $embedInfo
     * @param string           $name      The detail, e.g. `title`.
     *
     * @return mixed The detail, or `null` if it could not be read.
     */
    private function read(\Embed\Extractor $embedInfo, string $name): mixed
    {
        try {
            return $embedInfo->$name;
        } catch (\Throwable $ex) {
            \error_log('Unable to read the ' . $name . ' of ' . $this->url . ': ' . $ex->getMessage());

            return null;
        }
    }

    /**
     * Returns the cached info if there is one, since it is still better than
     * nothing. Otherwise only says why the page could not be read.
     *
     * @param \stdClass $info    The cached info (or an empty one).
     * @param string    $problem One of the `PROBLEM_*` constants.
     *
     * @return \stdClass
     */
    private function getWithProblem(\stdClass $info, string $problem): \stdClass
    {
        if (!$this->exists()) {
            $info->url     = $this->url;
            $info->blocked = self::PROBLEM_BLOCKED === $problem;
            $info->problem = $problem;
        }

        return $info;
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
                if (self::isBlocked($embedInfo)) {
                    return $this->getWithProblem($info, self::PROBLEM_BLOCKED);
                }

                /** An error page doesn't describe the wish either */
                if ($codePage < 200 || $codePage >= 400) {
                    return $this->getWithProblem(
                        $info,
                        \in_array($codePage, [404, 410], true) ? self::PROBLEM_NOT_FOUND : self::PROBLEM_UNREACHABLE
                    );
                }
            } catch (\Throwable $ex) {
                \error_log('Unable to read ' . $this->url . ': ' . $ex->getMessage());

                return $this->getWithProblem($info, self::PROBLEM_UNREACHABLE);
            }

            /**
             * Each detail is read on its own, so one that fails doesn't stop
             * the others from being found.
             */
            $title = (string) $this->read($embedInfo, 'title');

            /** A bot check or an error page, sent as if it was the product */
            $problem = self::getDisguisedProblem($embedInfo, $title);

            if (null !== $problem) {
                return $this->getWithProblem($info, $problem);
            }

            $publishedTime = $this->read($embedInfo, 'publishedTime');

            /** Convert embed info to a saveable format (JSON) stdClass */
            $info->authorName    = (string) $this->read($embedInfo, 'authorName');
            $info->authorUrl     = (string) $this->read($embedInfo, 'authorUrl');
            $info->cms           = (string) $this->read($embedInfo, 'cms');
            $info->code          = (string) $this->read($embedInfo, 'code');
            $info->description   = (string) $this->read($embedInfo, 'description');
            $info->favicon       = (string) $this->read($embedInfo, 'favicon');
            $info->feeds         = (array)  $this->read($embedInfo, 'feeds');
            $info->icon          = (string) $this->read($embedInfo, 'icon');
            $info->image         = (string) $this->read($embedInfo, 'image');
            $info->keywords      = (array)  $this->read($embedInfo, 'keywords');
            $info->language      = (string) $this->read($embedInfo, 'language');
            $info->languages     = (array)  $this->read($embedInfo, 'languages');
            $info->license       = (string) $this->read($embedInfo, 'license');
            $info->providerName  = (string) $this->read($embedInfo, 'providerName');
            $info->providerUrl   = (string) $this->read($embedInfo, 'providerUrl');
            $info->publishedTime = $publishedTime instanceof \DateTimeInterface ? $publishedTime->format('d.m.Y') : '';
            $info->redirect      = (string) $this->read($embedInfo, 'redirect');
            $info->title         = $title;
            $info->url           = (string) ($this->read($embedInfo, 'url') ?: $this->url);
            $info->price         = null;
            $info->currency      = null;
            $info->blocked       = false;
            $info->problem       = null;

            if ($embedInfo instanceof AmazonExtractor) {
                try {
                    $info->price    = $embedInfo->getPrice();
                    $info->currency = $embedInfo->getCurrency();
                } catch (\Throwable $ex) {
                    \error_log('Unable to read the price of ' . $this->url . ': ' . $ex->getMessage());
                }
            }

            $price = null === $info->price ? $this->read($embedInfo, 'price') : null;

            if (\is_array($price) && isset($price['price'])) {
                $info->price    = $price['price'];
                $info->currency = $price['currency'] ?? null;
            }

            /** Some websites repeat the title as description */
            if ($info->description === $info->title) {
                $info->description = '';
            }

            /** Don't keep a result without any useful information */
            if (!$info->title && !$info->description && !$info->image) {
                $generateCache = false;
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
