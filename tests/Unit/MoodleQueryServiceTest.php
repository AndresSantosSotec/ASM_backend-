<?php

namespace Tests\Unit;

use App\Services\MoodleQueryService;
use PHPUnit\Framework\TestCase;

class MoodleQueryServiceTest extends TestCase
{
    public function test_course_name_is_cleaned(): void
    {
        $service = new class extends MoodleQueryService {
            public function __construct() {}
        };

        $method = new \ReflectionMethod(MoodleQueryService::class, 'cleanCourseName');
        $method->setAccessible(true);

        $original = 'Febrero Jueves 2025 MDGP Gestión del tiempo, presupuestos y costos en los proyectos';
        $cleaned  = $method->invoke($service, $original);

        $this->assertSame('Gestión del tiempo, presupuestos y costos en los proyectos', $cleaned);
    }
}
