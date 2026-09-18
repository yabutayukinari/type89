<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Performance;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_many_performances(): void
    {
        $show = Show::factory()->create();
        $performance = Performance::factory()->create(['show_id' => $show->id]);

        $this->assertTrue($show->performances->contains($performance));
    }
}
