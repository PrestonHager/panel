<?php

namespace Pterodactyl\Tests\Integration\Panel;

use Pterodactyl\Models\User;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PanelUpdatesAdminTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    public function testNonAdminCannotAccessUpdatesSettings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/settings/updates')
            ->assertForbidden();
    }

    public function testAdminCanViewUpdatesSettingsPage(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings/updates')
            ->assertOk()
            ->assertSee('Upstream Configuration')
            ->assertSee('Run Safe Upgrade');
    }

    public function testAdminCanPollUpgradeStatus(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/admin/settings/updates/status')
            ->assertOk()
            ->assertJsonStructure(['state', 'step', 'progress', 'message']);
    }
}
