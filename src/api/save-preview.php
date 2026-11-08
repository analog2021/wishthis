<?php

/**
 * Save preview
 *
 * Crawlers send a screenshot of the page they are on (see
 * `src/assets/js/html2canvas.js`), which is then used as the link preview
 * (`og:image`) of every link to that page (see `Page::__construct()`).
 *
 * Because one screenshot stands for every link to a page, only pages which
 * look the same to everybody who isn't logged in are accepted. A screenshot
 * of someone's wishlist would otherwise show up in the preview of everybody
 * else's wishlist links.
 *
 * @category API
 */

namespace wishthis;

global $page, $options;

if (!isset($page)) {
    \http_response_code(403);
    die('Direct access to this location is not allowed.');
}

/**
 * Don't save previews before wishthis is installed.
 */
if (!$options || !$options->getOption('isInstalled')) {
    return;
}

/**
 * The pages a screenshot may be saved for.
 */
$previewPages = [
    'home',
    'blog',
    'changelog',
    'login',
    'register',
];

/**
 * The largest screenshot accepted, in bytes and in pixels per side.
 */
$previewMaxBytes  = 4 * 1024 * 1024;
$previewMaxPixels = 4096;

$response['saved'] = false;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $previewPage = $_POST['page']    ?? '';
        $previewData = $_POST['preview'] ?? '';

        if (!\is_string($previewPage) || !\in_array($previewPage, $previewPages, true)) {
            break;
        }

        /** Only from crawlers, which see the page as somebody who isn't logged in. */
        $crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();

        if (!$crawlerDetect->isCrawler() || User::getCurrent()->isLoggedIn()) {
            break;
        }

        /** A PNG data URL of a sensible size. */
        $dataUrlPrefix = 'data:image/png;base64,';

        if (!\is_string($previewData) || !\str_starts_with($previewData, $dataUrlPrefix)) {
            break;
        }

        $preview = \base64_decode(\substr($previewData, \strlen($dataUrlPrefix)), true);

        if (false === $preview || '' === $preview || \strlen($preview) > $previewMaxBytes) {
            break;
        }

        $previewSize = \getimagesizefromstring($preview);

        if (
               false === $previewSize
            || \IMAGETYPE_PNG !== $previewSize[2]
            || $previewSize[0] < 1
            || $previewSize[1] < 1
            || $previewSize[0] > $previewMaxPixels
            || $previewSize[1] > $previewMaxPixels
        ) {
            break;
        }

        /**
         * Replace a screenshot at most once a month. They are kept apart from
         * the screenshots which come with wishthis, so updating wishthis
         * doesn't conflict with them.
         */
        $previewDirectory = ROOT . '/src/assets/img/screenshots/generated';
        $previewFilepath  = $previewDirectory . '/' . $previewPage . '.png';

        if (\file_exists($previewFilepath) && \time() - \filemtime($previewFilepath) <= Duration::MONTH) {
            break;
        }

        if (!\is_dir($previewDirectory) && !\mkdir($previewDirectory, 0755, true) && !\is_dir($previewDirectory)) {
            break;
        }

        $response['saved'] = false !== \file_put_contents($previewFilepath, $preview);
        break;
}
