<?php

namespace Tests\Unit;

use App\Services\ProjectTrackingService;
use PHPUnit\Framework\TestCase;

class TrackingAverageTest extends TestCase
{
    public function test_weighted_average_uses_only_graded_items(): void
    {
        $items = [
            ['calificacion' => 80, 'ponderacion' => 40],
            ['calificacion' => 100, 'ponderacion' => 60],
            ['calificacion' => null, 'ponderacion' => 100],
        ];
        $this->assertSame(92.0, ProjectTrackingService::average($items));
    }

    public function test_simple_average_without_weights_and_null_without_grades(): void
    {
        $this->assertSame(85.0, ProjectTrackingService::average([['calificacion' => 80, 'ponderacion' => null], ['calificacion' => 90, 'ponderacion' => null]]));
        $this->assertNull(ProjectTrackingService::average([['calificacion' => null, 'ponderacion' => 50]]));
    }
}
