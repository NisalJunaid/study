<?php

namespace Tests\Feature\Student;

use Tests\TestCase;

class ApplicationBootTest extends TestCase
{
    public function test_homepage_loads_successfully(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk();
    }
}
