<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Both documentation endpoints must enforce
     * the same authentication and authorization rules.
     */
    public function test_guests_cannot_access_api_documentation(): void
    {
        foreach ($this->documentationPaths() as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }

    public function test_drivers_cannot_access_api_documentation(): void
    {
        $this->assertAccessDeniedForRole(UserRole::DRIVER);
    }

    public function test_dispatchers_cannot_access_api_documentation(): void
    {
        $this->assertAccessDeniedForRole(UserRole::DISPATCHER);
    }

    public function test_inactive_administrators_cannot_access_api_documentation(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($user);

        foreach ($this->documentationPaths() as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    public function test_active_administrators_can_access_api_documentation(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        foreach ($this->documentationPaths() as $path) {
            $this->get($path)->assertOk();
        }
    }

    private function assertAccessDeniedForRole(UserRole $role): void
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        foreach ($this->documentationPaths() as $path) {
            $this->getJson($path)->assertForbidden();
        }
    }

    /**
     * The UI and its OpenAPI specification
     * must share identical access restrictions.
     *
     * @return array<int, string>
     */
    private function documentationPaths(): array
    {
        return [
            '/docs/api',
            '/docs/api.json',
        ];
    }
}
