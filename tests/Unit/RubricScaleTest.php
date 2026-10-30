<?php

namespace Tests\Unit;

use App\Support\RubricScale;
use PHPUnit\Framework\TestCase;

class RubricScaleTest extends TestCase
{
    public function test_every_scale_goes_from_zero_to_one_hundred_percent(): void
    {
        $this->assertSame([0.0, 25.0, 50.0, 75.0, 100.0], array_map(
            fn ($option) => RubricScale::percentage(RubricScale::AGREEMENT, $option['points']),
            RubricScale::options(RubricScale::AGREEMENT)
        ));
        $this->assertSame(0.0, RubricScale::percentage(RubricScale::POINTS_5, 1));
        $this->assertSame(100.0, RubricScale::percentage(RubricScale::POINTS_5, 5));
        $this->assertSame(0.0, RubricScale::percentage(RubricScale::POINTS_10, 1));
        $this->assertSame(100.0, RubricScale::percentage(RubricScale::POINTS_10, 10));
        $this->assertSame(55.56, RubricScale::percentage(RubricScale::POINTS_10, 6));
    }

    public function test_values_outside_the_scale_are_rejected_and_old_names_still_work(): void
    {
        $this->assertNull(RubricScale::points(RubricScale::POINTS_5, '6'));
        $this->assertNull(RubricScale::points(RubricScale::POINTS_10, '0'));
        $this->assertNull(RubricScale::points(RubricScale::AGREEMENT, '4'));
        $this->assertSame(5, RubricScale::points(RubricScale::AGREEMENT, 'totalmente_de_acuerdo'));
        $this->assertSame(3, RubricScale::points(RubricScale::AGREEMENT, 'nulo'));
        $this->assertSame('Nulo', RubricScale::label(RubricScale::AGREEMENT, 'neutral'));
        $this->assertSame('7 de 10', RubricScale::label(RubricScale::POINTS_10, '7'));
    }

    public function test_verdict_thresholds(): void
    {
        $this->assertSame('aprobado', RubricScale::verdict(80)['key']);
        $this->assertSame('success', RubricScale::verdict(100)['tone']);
        $this->assertSame('apenas_cumple', RubricScale::verdict(79.99)['key']);
        $this->assertSame('warning', RubricScale::verdict(70)['tone']);
        $this->assertSame('reprobado', RubricScale::verdict(69.99)['key']);
        $this->assertSame('danger', RubricScale::verdict(0)['tone']);
        $this->assertSame('sin_resultado', RubricScale::verdict(null)['key']);
    }
}
