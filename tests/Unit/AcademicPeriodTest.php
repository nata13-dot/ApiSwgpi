<?php

namespace Tests\Unit;

use App\Support\AcademicPeriod;
use PHPUnit\Framework\TestCase;

class AcademicPeriodTest extends TestCase
{
    public function test_first_half_contains_sixth_and_eighth_semesters(): void
    {
        $this->assertSame([6, 8], AcademicPeriod::semesters('2026-1'));
        $this->assertSame('2026-2', AcademicPeriod::next('2026-1'));
    }

    public function test_second_half_contains_fifth_seventh_and_ninth_semesters(): void
    {
        $this->assertSame([5, 7, 9], AcademicPeriod::semesters('2026-2'));
        $this->assertSame('2027-1', AcademicPeriod::next('2026-2'));
    }
}
