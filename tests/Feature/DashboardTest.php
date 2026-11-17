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
        $this->get('/generations')->assertRedirect('/login');
    }

    public function test_dashboard_redirects_to_the_studio()
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/generations');
    }

    public function test_authenticated_users_can_visit_the_studio()
    {
        $this->actingAs(User::factory()->create())->get('/generations')->assertOk();
    }

    public function test_only_admins_can_see_the_report()
    {
        $this->actingAs(User::factory()->create())->get('/admin/report')->assertForbidden();
    }
}
