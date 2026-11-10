<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Staff;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin role and admin user
        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'Staff']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /** @test */
    public function it_can_create_a_staff_member_with_a_specified_password()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.staff.store'), [
            'name' => 'John Doe',
            'phone' => '9876543210',
            'email' => 'john@example.com',
            'salary_amount' => 50000,
            'role' => 'Staff',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Assert staff record created
        $staff = Staff::where('phone', '9876543210')->first();
        $this->assertNotNull($staff);
        $this->assertEquals('John Doe', $staff->name);

        // Assert user record created with the correct password
        $user = User::where('phone', '9876543210')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    /** @test */
    public function it_defaults_to_phone_number_password_if_left_empty_during_creation()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.staff.store'), [
            'name' => 'John Doe',
            'phone' => '9876543210',
            'email' => 'john@example.com',
            'salary_amount' => 50000,
            'role' => 'Staff',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('phone', '9876543210')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('9876543210', $user->password));
    }

    /** @test */
    public function it_validates_password_confirmation_on_creation()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.staff.store'), [
            'name' => 'John Doe',
            'phone' => '9876543210',
            'email' => 'john@example.com',
            'salary_amount' => 50000,
            'role' => 'Staff',
            'password' => 'secret123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertNull(Staff::where('phone', '9876543210')->first());
    }

    /** @test */
    public function it_can_update_a_staff_members_password()
    {
        $this->actingAs($this->admin);

        // Create the staff member
        $staff = Staff::create([
            'name' => 'Jane Doe',
            'phone' => '9876543211',
            'email' => 'jane@example.com',
            'salary_amount' => 45000,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Jane Doe',
            'phone' => '9876543211',
            'email' => 'jane@example.com',
            'password' => Hash::make('oldpassword'),
            'status' => 'active',
        ]);
        $user->assignRole('Staff');
        $staff->update(['user_id' => $user->id]);

        // Update the password
        $response = $this->post(url("staff/{$staff->id}/update"), [
            'name' => 'Jane Doe',
            'phone' => '9876543211',
            'email' => 'jane@example.com',
            'salary_amount' => 45000,
            'status' => 'active',
            'role' => 'Staff',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Refresh user and assert password changed
        $user->refresh();
        $this->assertTrue(Hash::check('newsecret123', $user->password));
    }

    /** @test */
    public function it_does_not_change_password_if_left_empty_during_update()
    {
        $this->actingAs($this->admin);

        // Create the staff member
        $staff = Staff::create([
            'name' => 'Jane Doe',
            'phone' => '9876543211',
            'email' => 'jane@example.com',
            'salary_amount' => 45000,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Jane Doe',
            'phone' => '9876543211',
            'email' => 'jane@example.com',
            'password' => Hash::make('oldpassword'),
            'status' => 'active',
        ]);
        $user->assignRole('Staff');
        $staff->update(['user_id' => $user->id]);

        // Update with empty password
        $response = $this->post(url("staff/{$staff->id}/update"), [
            'name' => 'Jane Doe',
            'phone' => '9876543211',
            'email' => 'jane@example.com',
            'salary_amount' => 45000,
            'status' => 'active',
            'role' => 'Staff',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasNoErrors();

        // Refresh user and assert password remained unchanged
        $user->refresh();
        $this->assertTrue(Hash::check('oldpassword', $user->password));
        $this->assertFalse(Hash::check('newsecret123', $user->password));
    }
}
