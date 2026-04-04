<?php

namespace Tests\Feature\Imports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportRoutesProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_access_admin_imports_index(): void
    {
        $this->withoutVite();

        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.imports.index'))
            ->assertForbidden();
    }
}
