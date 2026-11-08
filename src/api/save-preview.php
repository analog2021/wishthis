<?php

/**
 * Save preview
 *
 * Crawlers used to send a screenshot of the page they were on, which then
 * became the link preview (`og:image`) of every link to that page. Whether a
 * visitor is a crawler is only known from what it says about itself, so
 * anybody could replace the preview with any picture, for a month at a time.
 *
 * Link previews now only use the screenshots which come with wishthis (see
 * `Page::__construct()`), and nothing is saved here any more. The module is
 * kept so that crawlers which still have the old script cached get an answer
 * instead of an error.
 *
 * @category API
 */

namespace wishthis;

global $page;

if (!isset($page)) {
    \http_response_code(403);
    die('Direct access to this location is not allowed.');
}

$response['saved'] = false;
