<?php

declare(strict_types=1);

namespace wishthis\Tests;

use PHPUnit\Framework\TestCase;
use wishthis\User;

/**
 * When fulfilled wishes leave the owners lists: two weeks after their last
 * birthday.
 */
final class ReceivedCutoffTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../src/classes/wishthis/User.php';
        require_once __DIR__ . '/../src/functions/getShortDateFormatter.php';
    }

    private function cutoff(string $birthdate, string $today): ?string
    {
        $cutoff = User::getReceivedCutoff($birthdate, new \DateTimeImmutable($today . ' 15:30'));

        return null === $cutoff ? null : $cutoff->format('Y-m-d H:i');
    }

    public function testCutoffThisYearOnceItHasPassed(): void
    {
        $this->assertSame('2026-10-24 00:00', $this->cutoff('1990-10-10', '2026-11-07'));
    }

    public function testCutoffIsTheDayItself(): void
    {
        $this->assertSame('2026-11-07 00:00', $this->cutoff('1990-10-24', '2026-11-07'));
    }

    public function testCutoffLastYearWhileThisYearsIsAhead(): void
    {
        $this->assertSame('2025-11-14 00:00', $this->cutoff('1990-10-31', '2026-11-07'));
        $this->assertSame('2025-12-28 00:00', $this->cutoff('1990-12-14', '2026-11-07'));
    }

    public function testCutoffAcrossTheNewYear(): void
    {
        $this->assertSame('2026-01-08 00:00', $this->cutoff('1990-12-25', '2026-01-10'));
        $this->assertSame('2025-01-08 00:00', $this->cutoff('1990-12-25', '2026-01-07'));
    }

    public function testLeapDayBirthday(): void
    {
        /** 2026 has no 29th of February, so the birthday is on the 1st of March. */
        $this->assertSame('2026-03-15 00:00', $this->cutoff('2000-02-29', '2026-11-07'));
        $this->assertSame('2028-03-14 00:00', $this->cutoff('2000-02-29', '2028-11-07'));
    }

    public function testInvalidBirthdate(): void
    {
        $this->assertNull($this->cutoff('', '2026-11-07'));
        $this->assertNull($this->cutoff('1990-02-30', '2026-11-07'));
        $this->assertNull($this->cutoff('not a date', '2026-11-07'));
    }

    public function testShortDateHasFourDigitYear(): void
    {
        $date = \mktime(12, 0, 0, 11, 7, 2026);

        $this->assertSame('11/07/2026', \getShortDateFormatter('en_US')->format($date));
        $this->assertSame('07.11.2026', \getShortDateFormatter('de_DE')->format($date));
        $this->assertSame('07/11/2026', \getShortDateFormatter('en_GB')->format($date));
    }
}
