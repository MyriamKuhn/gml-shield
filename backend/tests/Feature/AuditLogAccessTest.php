<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\AuditLog;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_audit_logs(): void
    {
        $this->getJson('/api/audit-logs')
            ->assertUnauthorized();
    }

    public function test_active_admin_can_access_audit_logs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/audit-logs')
            ->assertOk();
    }

    public function test_dispatcher_cannot_access_audit_logs(): void
    {
        $dispatcher = User::factory()->create([
            'role' => UserRole::DISPATCHER,
            'is_active' => true,
        ]);

        $this->actingAs($dispatcher)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_driver_cannot_access_audit_logs(): void
    {
        $driver = User::factory()->create([
            'role' => UserRole::DRIVER,
            'is_active' => true,
        ]);

        $this->actingAs($driver)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_access_audit_logs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_admin_receives_paginated_audit_logs_ordered_newest_first(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        // Create 25 events to verify the 20-item page limit.
        for ($i = 1; $i <= 25; $i++) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => "PAGINATION_TEST_{$i}",
                'ip_address' => '192.0.2.10',
                'timestamp' => now()->subMinutes(26 - $i),
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson('/api/audit-logs');

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('data.0.action', 'PAGINATION_TEST_25')
            ->assertJsonPath('data.19.action', 'PAGINATION_TEST_6');
    }

    public function test_admin_can_access_second_page_of_audit_logs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => "SECOND_PAGE_TEST_{$i}",
                'ip_address' => '192.0.2.10',
                'timestamp' => now()->subMinutes(26 - $i),
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson('/api/audit-logs?page=2');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('data.0.action', 'SECOND_PAGE_TEST_5')
            ->assertJsonPath('data.4.action', 'SECOND_PAGE_TEST_1');
    }

    public function test_admin_cannot_use_invalid_audit_log_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/audit-logs?page=abc')
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->getJson('/api/audit-logs?page=-1')
            ->assertUnprocessable();
    }
}
