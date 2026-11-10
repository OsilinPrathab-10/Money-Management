<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\ChitScheme;
use App\Models\ChitGroup;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Auction;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChitGroupActivationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $scheme;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin role and admin user
        Role::firstOrCreate(['name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'phone' => '1234567890',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->admin->assignRole('Admin');

        // Create a Chit Scheme
        $this->scheme = ChitScheme::create([
            'name' => 'Test Monthly 50k',
            'scheme_code' => 'SCH0001',
            'chit_value' => 50000.00,
            'total_members' => 10,
            'duration_months' => 10,
            'installment_amount' => 5000.00,
            'commission_pct' => 5.00,
            'commission_amount' => 2500.00,
            'auction_type' => 'open',
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
    }

    /** @test */
    public function it_cannot_activate_a_group_with_zero_members()
    {
        $this->actingAs($this->admin);

        $group = ChitGroup::create([
            'group_code' => 'GRP00001',
            'scheme_id' => $this->scheme->id,
            'start_date' => now()->format('Y-m-d'),
            'total_months' => 10,
            'chit_value' => 50000.00,
            'total_members' => 10,
            'installment_amount' => 5000.00,
            'commission_pct' => 5.00,
            'auction_type' => 'open',
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'forming',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->post(route('chit.groups.activate', $group));

        $response->assertSessionHas('error');
        $group->refresh();
        $this->assertEquals('forming', $group->status);
    }

    /** @test */
    public function it_can_activate_a_group_with_only_one_or_two_members()
    {
        $this->actingAs($this->admin);

        $group = ChitGroup::create([
            'group_code' => 'GRP00002',
            'scheme_id' => $this->scheme->id,
            'start_date' => now()->format('Y-m-d'),
            'total_months' => 10,
            'chit_value' => 50000.00,
            'total_members' => 10,
            'installment_amount' => 5000.00,
            'commission_pct' => 5.00,
            'auction_type' => 'open',
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'forming',
            'created_by' => $this->admin->id,
        ]);

        // Add 1 client and enroll them
        $client = Client::create([
            'client_name' => 'John Doe',
            'client_email' => 'john@doe.com',
            'client_phone' => '9876543210',
            'status' => 'active',
        ]);

        $member = GroupMember::create([
            'group_id' => $group->id,
            'client_id' => $client->id,
            'member_number' => 1,
            'status' => 'approved',
            'joined_date' => today(),
        ]);

        $response = $this->post(route('chit.groups.activate', $group));

        $response->assertSessionHas('success');
        $group->refresh();
        $this->assertEquals('active', $group->status);

        $member->refresh();
        $this->assertEquals('active', $member->status);

        // Verify installments were generated for the member
        $installmentsCount = Installment::where('member_id', $member->id)->count();
        $this->assertEquals(10, $installmentsCount);

        // Verify auctions schedule generated
        $auctionsCount = Auction::where('group_id', $group->id)->count();
        $this->assertEquals(10, $auctionsCount);
    }

    /** @test */
    public function late_joining_member_in_active_private_group_is_automatically_activated_with_installments()
    {
        $this->actingAs($this->admin);

        $group = ChitGroup::create([
            'group_code' => 'GRP00003',
            'scheme_id' => $this->scheme->id,
            'start_date' => now()->format('Y-m-d'),
            'total_months' => 10,
            'chit_value' => 50000.00,
            'total_members' => 10,
            'installment_amount' => 5000.00,
            'commission_pct' => 5.00,
            'auction_type' => 'open',
            'scheme_type' => 'group_based', // private scheme
            'installment_frequency' => 'monthly',
            'is_private' => true,
            'status' => 'active', // already active
            'created_by' => $this->admin->id,
        ]);

        // Add client
        $client = Client::create([
            'client_name' => 'Jane Doe',
            'client_email' => 'jane@doe.com',
            'client_phone' => '9876543211',
            'status' => 'active',
        ]);

        // Enroll via store route
        $response = $this->post(route('chit.members.store'), [
            'group_id' => $group->id,
            'client_id' => $client->id,
            'member_number' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify member status is active immediately and installments are generated
        $member = GroupMember::where('group_id', $group->id)->where('client_id', $client->id)->first();
        $this->assertNotNull($member);
        $this->assertEquals('active', $member->status);

        $installmentsCount = Installment::where('member_id', $member->id)->count();
        $this->assertEquals(10, $installmentsCount);
    }

    /** @test */
    public function late_joining_member_in_active_public_group_is_activated_and_gets_installments_upon_approval()
    {
        $this->actingAs($this->admin);

        $group = ChitGroup::create([
            'group_code' => 'GRP00004',
            'scheme_id' => $this->scheme->id,
            'start_date' => now()->format('Y-m-d'),
            'total_months' => 10,
            'chit_value' => 50000.00,
            'total_members' => 10,
            'installment_amount' => 5000.00,
            'commission_pct' => 5.00,
            'auction_type' => 'open',
            'scheme_type' => 'auction', // public scheme
            'installment_frequency' => 'monthly',
            'is_private' => false,
            'status' => 'active', // already active
            'created_by' => $this->admin->id,
        ]);

        // Add client
        $client = Client::create([
            'client_name' => 'Alice Smith',
            'client_email' => 'alice@smith.com',
            'client_phone' => '9876543212',
            'status' => 'active',
        ]);

        // Enroll via store route (status should default to applied)
        $this->post(route('chit.members.store'), [
            'group_id' => $group->id,
            'client_id' => $client->id,
            'member_number' => 1,
        ]);

        $member = GroupMember::where('group_id', $group->id)->where('client_id', $client->id)->first();
        $this->assertNotNull($member);
        $this->assertEquals('applied', $member->status);

        // Verify NO installments generated yet
        $this->assertEquals(0, Installment::where('member_id', $member->id)->count());

        // Approve the member
        $response = $this->post(route('chit.members.approve', $member));
        $response->assertRedirect();

        // Verify status changes to active and installments are generated
        $member->refresh();
        $this->assertEquals('active', $member->status);

        $this->assertEquals(10, Installment::where('member_id', $member->id)->count());
    }

    /** @test */
    public function it_bases_due_dates_on_member_joining_date_and_stores_chit_need_date()
    {
        $this->actingAs($this->admin);

        $group = ChitGroup::create([
            'group_code' => 'GRP00005',
            'scheme_id' => $this->scheme->id,
            'start_date' => '2026-07-01',
            'total_months' => 3,
            'chit_value' => 50000.00,
            'total_members' => 10,
            'installment_amount' => 5000.00,
            'commission_pct' => 5.00,
            'auction_type' => 'open',
            'scheme_type' => 'group_based',
            'installment_frequency' => 'monthly',
            'is_private' => true,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $client = Client::create([
            'client_name' => 'Bob Test',
            'client_email' => 'bob@test.com',
            'client_phone' => '9876543233',
            'status' => 'active',
        ]);

        // Submit custom joined_date and chit_need_date
        $joinedDate = '2026-07-15';
        $chitNeedDate = '2026-07-20';

        $response = $this->post(route('chit.members.store'), [
            'group_id' => $group->id,
            'client_id' => $client->id,
            'joined_date' => $joinedDate,
            'chit_need_date' => $chitNeedDate,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $member = GroupMember::where('group_id', $group->id)->where('client_id', $client->id)->first();
        $this->assertNotNull($member);
        $this->assertEquals('active', $member->status);
        $this->assertEquals('2026-07-15', $member->joined_date->format('Y-m-d'));
        $this->assertEquals('2026-07-20', $member->chit_need_date->format('Y-m-d'));

        // Verify installments were generated based on joined_date
        $installments = Installment::where('member_id', $member->id)->orderBy('month_number')->get();
        $this->assertCount(3, $installments);

        $this->assertEquals('2026-07-15', $installments[0]->due_date->format('Y-m-d'));
        $this->assertEquals('2026-08-15', $installments[1]->due_date->format('Y-m-d'));
        $this->assertEquals('2026-09-15', $installments[2]->due_date->format('Y-m-d'));
    }
}
