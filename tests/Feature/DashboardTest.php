<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_without_tenant_redirect_to_workspaces()
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/dashboard')->assertRedirect(route('workspaces.index'));
    }
}
