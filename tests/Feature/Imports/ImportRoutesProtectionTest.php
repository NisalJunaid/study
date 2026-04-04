<?php

namespace Tests\Feature\Imports;

use App\Models\Import;
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

    public function test_guest_is_redirected_from_admin_imports_index(): void
    {
        $this->withoutVite();

        $this->get(route('admin.imports.index'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_confirm_import(): void
    {
        $this->withoutVite();

        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $import = Import::query()->create([
            'uploaded_by' => $admin->id,
            'file_name' => 'questions.csv',
            'file_path' => 'imports/questions/questions.csv',
            'status' => Import::STATUS_READY,
        ]);

        $this->actingAs($student)
            ->post(route('admin.imports.confirm', $import))
            ->assertForbidden();
    }

}
