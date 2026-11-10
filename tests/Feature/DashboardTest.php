<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'phone' => '1234567890',
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => 'admin',
        ]);
        $this->admin->assignRole('Admin');
    }

    /** @test */
    public function it_can_load_dashboard_successfully()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
        
        // Assert that upcomingLoans is not passed to the view
        $response->assertViewMissing('upcomingLoans');
        
        // Assert that the string "Upcoming" in the context of the statistic is not present, or the text matches the new layout
        $response->assertSee('Pending');
        $response->assertSee('Overdue');
        $response->assertDontSee('stat-upcomingLoans');
    }
}
