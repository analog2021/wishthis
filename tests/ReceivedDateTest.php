<?php

declare(strict_types=1);

namespace wishthis\Tests;

use PHPUnit\Framework\TestCase;
use wishthis\User;

/**
 * When a fulfilled wish leaves its owners lists: once the next birthday or
 * Christmas after it was fulfilled is over (two weeks after the birthday, end
 * of January after Christmas), waiting for the next one too if it is less
 * than two months away by then.
 */
final class ReceivedDateTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../src/classes/wishthis/User.php';
        require_once __DIR__ . '/../src/functions/getShortDateFormatter.php';
    }

    private function received(string $fulfilled, string $birthdate): ?string
    {
        $date = User::getReceivedDate(new \DateTimeImmutable($fulfilled . ' 15:30'), $birthdate);

        return null === $date ? null : $date->format('Y-m-d H:i');
    }

    public function testBirthdayFarFromChristmas(): void
    {
        /** Two weeks after the birthday. */
        $this->assertSame('2026-06-29 00:00', $this->received('2026-05-01', '1990-06-15'));
        $this->assertSame('2026-06-29 00:00', $this->received('2026-06-15', '1990-06-15'));
    }

    public function testBlackFridayBeforeADecemberBirthday(): void
    {
        /** Might be for the birthday or for Christmas, so it waits for both. */
        $this->assertSame('2027-01-31 00:00', $this->received('2026-11-27', '1990-12-10'));
    }

    public function testBirthdayTooCloseToChristmas(): void
    {
        /** Two weeks after the birthday is the 7th of November, less than two months before Christmas. */
        $this->assertSame('2027-01-31 00:00', $this->received('2026-10-01', '1990-10-24'));
    }

    public function testBirthdayJustFarEnoughFromChristmas(): void
    {
        /** Two weeks after the birthday is the 24th of October, Christmas is more than two months away. */
        $this->assertSame('2026-10-24 00:00', $this->received('2026-09-01', '1990-10-10'));
    }

    public function testBirthdayInJanuary(): void
    {
        /** The birthday is over before the end of January, which is when Christmas is over. */
        $this->assertSame('2027-01-31 00:00', $this->received('2026-11-27', '1990-01-10'));
    }

    public function testBirthdaySoonAfterChristmas(): void
    {
        /** The end of January is less than two months before the birthday, so it waits for it. */
        $this->assertSame('2027-03-01 00:00', $this->received('2026-12-01', '1990-02-15'));
    }

    public function testFulfilledAfterTheBirthday(): void
    {
        /** The next occasion is Christmas. */
        $this->assertSame('2027-01-31 00:00', $this->received('2026-07-01', '1990-06-15'));
    }

    public function testFulfilledAfterChristmas(): void
    {
        /** The next occasion is next year's birthday. */
        $this->assertSame('2027-06-29 00:00', $this->received('2026-12-28', '1990-06-15'));
    }

    public function testLeapDayBirthday(): void
    {
        /** 2027 has no 29th of February, so the birthday is on the 1st of March. */
        $this->assertSame('2027-03-15 00:00', $this->received('2027-02-01', '2000-02-29'));
        $this->assertSame('2028-03-14 00:00', $this->received('2028-02-01', '2000-02-29'));
    }

    public function testInvalidBirthdate(): void
    {
        $this->assertNull($this->received('2026-11-07', ''));
        $this->assertNull($this->received('2026-11-07', '1990-02-30'));
        $this->assertNull($this->received('2026-11-07', 'not a date'));
    }

    public function testShortDateHasFourDigitYear(): void
    {
        $date = \mktime(12, 0, 0, 11, 7, 2026);

        $this->assertSame('11/07/2026', \getShortDateFormatter('en_US')->format($date));
        $this->assertSame('07.11.2026', \getShortDateFormatter('de_DE')->format($date));
        $this->assertSame('07/11/2026', \getShortDateFormatter('en_GB')->format($date));
    }
}
