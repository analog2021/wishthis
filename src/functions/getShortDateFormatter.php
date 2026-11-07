<?php

/**
 * Get short date formatter
 *
 * Returns a formatter for a date in the order of `$locale`, always with a
 * two digit day and month and a four digit year, e.g. `11/07/2026` (en_US) or
 * `07.11.2026` (de_DE).
 */
function getShortDateFormatter(string $locale): \IntlDateFormatter
{
    $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);
    $pattern   = \preg_replace(
        [
            '/y+/',
            '/(?<![M])M{1,2}(?![M])/',
            '/(?<![d])d{1,2}(?![d])/',
        ],
        [
            'yyyy',
            'MM',
            'dd',
        ],
        $formatter->getPattern()
    );

    $formatter->setPattern($pattern);

    return $formatter;
}
