<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ChitConfiguration;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChitConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'phone' => '1234567890',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->admin->assignRole('Admin');
    }

    public function test_it_loads_the_configuration_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('chit.configuration.index'));

        $response->assertStatus(200);
        $response->assertSee('Chit configuration');
        $response->assertSee('Late Penalty Configuration');
    }

    public function test_it_updates_referral_configuration_only_successfully()
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('chit.configuration.update'), [
                'referral_commission_percentage' => '15.50',
                'referral_calculation_base' => 'chit_value',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Referral & Commission configuration saved successfully.'
        ]);

        $this->assertEquals('15.50', ChitConfiguration::get('referral_commission_percentage'));
        $this->assertEquals('chit_value', ChitConfiguration::get('referral_calculation_base'));
        // Penalty settings should remain defaults
        $this->assertEquals('fixed', ChitConfiguration::get('penalty_type', 'fixed'));
    }

    public function test_it_updates_penalty_configuration_only_successfully()
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('chit.configuration.update'), [
                'penalty_type' => 'percentage',
                'penalty_value' => '5.00',
                'penalty_grace_days' => '3',
                'penalty_enabled' => '1',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Late Penalty configuration saved successfully.'
        ]);

        // Referral settings should remain default / unchanged
        $this->assertEquals('10.00', ChitConfiguration::get('referral_commission_percentage', '10.00'));
        
        // Penalty settings should be updated
        $this->assertEquals('percentage', ChitConfiguration::get('penalty_type'));
        $this->assertEquals('5.00', ChitConfiguration::get('penalty_value'));
        $this->assertEquals('3', ChitConfiguration::get('penalty_grace_days'));
        $this->assertEquals('1', ChitConfiguration::get('penalty_enabled'));
    }

    public function test_it_validates_required_fields_conditionally()
    {
        // Try submitting partial referral config with missing base
        $response = $this->actingAs($this->admin)
            ->postJson(route('chit.configuration.update'), [
                'referral_commission_percentage' => '15.50',
                'referral_calculation_base' => '',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'referral_calculation_base',
        ]);

        // Try submitting partial penalty config with missing value
        $response2 = $this->actingAs($this->admin)
            ->postJson(route('chit.configuration.update'), [
                'penalty_type' => 'percentage',
                'penalty_value' => '',
                'penalty_grace_days' => '3',
            ]);

        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors([
            'penalty_value',
        ]);
    }
}
