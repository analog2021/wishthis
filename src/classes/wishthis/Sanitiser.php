<?php

namespace wishthis;

class Sanitiser
{
    /**
     * @deprecated 1.1.0
     */
    public static function render(string $text): string
    {
        return $text;
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getNumber(mixed $valueToSanitise): float|int
    {
        $number = \preg_replace('/[^0-9\.]+/', '', $valueToSanitise);

        return $number;
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getPage(mixed $valueToSanitise): string
    {
        $valueToSanitise = \strtolower($valueToSanitise);

        return \preg_replace('/[^a-z\-_]+/', '', $valueToSanitise);
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getTable(mixed $valueToSanitise): string
    {
        $valueToSanitise = \strtolower($valueToSanitise);

        return \preg_replace('/[^a-z_]+/', '', $valueToSanitise);
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getTitle(mixed $valueToSanitise): string
    {
        $valueToSanitise = \trim($valueToSanitise);

        return \htmlentities($valueToSanitise, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_DISALLOWED | \ENT_HTML5);
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getText(mixed $valueToSanitise): string
    {
        $valueToSanitise = \trim($valueToSanitise);

        return \htmlentities($valueToSanitise, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_DISALLOWED | \ENT_HTML5);
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getURL(mixed $valueToSanitise): string
    {
        return \preg_replace('/[\s]+/', '', $valueToSanitise);
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getStatus(mixed $valueToSanitise): string
    {
        $valueToSanitise = \strtolower($valueToSanitise);

        return \preg_replace('/[^a-z\-_]+/', '', $valueToSanitise);
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getOption(mixed $valueToSanitise): string
    {
        return \preg_replace('/[^a-zA-Z\_]+/', '', $valueToSanitise);
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getEmail(mixed $valueToSanitise): string
    {
        $valueToSanitise = \strtolower($valueToSanitise);

        return \preg_replace('/[^a-z\-_0-9\.+@]+/', '', $valueToSanitise);
    }

    /**
     * @deprecated 1.1.0
     */
    public static function getSHA1(mixed $valueToSanitise): string
    {
        $valueToSanitise = \strtolower($valueToSanitise);

        return \preg_replace('/[^a-f0-9]+/', '', $valueToSanitise);
    }

    /**
     * Sanitize a single line of text.
     *
     * @param string $text
     *
     * @return string
     */
    public static function sanitiseText(string $text): string
    {
        $sanitisedText = \trim(\addslashes(\filter_var($text, \FILTER_SANITIZE_SPECIAL_CHARS)));

        return $sanitisedText;
    }

    /**
     * Sanitises a single line of text like `sanitiseText()`, shortened so it
     * fits a column of `$maxLength` characters as stored. Symbols such as `&`
     * take up several characters once encoded, so the text is shortened
     * before encoding and never ends in a cut off `&#38;`.
     *
     * The form fields keep within the limit already (`data-stored-max-length`
     * in `default.js`), so this only matters for requests made otherwise.
     *
     * @param string $text      Plain text, as typed by the user.
     * @param int    $maxLength The length of the database column.
     *
     * @return string
     */
    public static function sanitiseTextToFit(string $text, int $maxLength): string
    {
        /** Every character takes up at least one character as stored */
        $text = \mb_substr(\trim($text), 0, $maxLength);

        while ('' !== $text && \mb_strlen(self::sanitiseText($text)) > $maxLength) {
            $text = \mb_substr($text, 0, -1);
        }

        return self::sanitiseText($text);
    }

    /**
     * Sanitise an email address.
     *
     * @param string $email
     *
     * @return string
     */
    public static function sanitiseEmail(string $email): string
    {
        $sanitisedEmail = \trim(\addslashes(\filter_var($email, \FILTER_SANITIZE_EMAIL)));

        return $sanitisedEmail;
    }
}
