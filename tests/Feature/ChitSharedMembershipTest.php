<?php

namespace Tests\Feature;

use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\GroupMemberShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChitSharedMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ChitScheme $scheme;
    protected ChitGroup $group;
    protected Client $clientA;
    protected Client $clientB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-shared@test.com',
            'phone' => '9999999999',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->admin->assignRole('Admin');

        $this->scheme = ChitScheme::create([
            'name' => 'Shared Test Scheme',
            'scheme_code' => 'SCHSHARE',
            'chit_value' => 100000.00,
            'total_members' => 10,
            'duration_months' => 10,
            'installment_amount' => 10000.00,
            'commission_pct' => 5.00,
            'commission_amount' => 5000.00,
            'auction_type' => 'open',
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $this->group = ChitGroup::create([
            'group_code' => 'GRPSHARE',
            'scheme_id' => $this->scheme->id,
            'start_date' => now()->format('Y-m-d'),
            'total_months' => 10,
            'chit_value' => 100000.00,
            'total_members' => 10,
            'installment_amount' => 10000.00,
            'commission_pct' => 5.00,
            'auction_type' => 'open',
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'forming',
            'created_by' => $this->admin->id,
        ]);

        $this->clientA = Client::create([
            'client_name' => 'Share Owner A',
            'client_phone' => '9000000001',
            'status' => 'active',
        ]);
        $this->clientB = Client::create([
            'client_name' => 'Share Owner B',
            'client_phone' => '9000000002',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_enrolls_a_shared_membership_with_50_50_ownership(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('chit.members.store'), [
            'group_id' => $this->group->id,
            'is_shared' => 1,
            'joined_date' => now()->format('Y-m-d'),
            'shares' => [
                ['client_id' => $this->clientA->id, 'ownership_percentage' => 50],
                ['client_id' => $this->clientB->id, 'ownership_percentage' => 50],
            ],
        ]);

        $response->assertRedirect(route('chit.groups.show', $this->group));

        $member = GroupMember::where('group_id', $this->group->id)->first();
        $this->assertNotNull($member);
        $this->assertTrue((bool) $member->is_shared);
        $this->assertEquals($this->clientA->id, $member->client_id);
        $this->assertEquals(2, $member->shares()->count());

        $shareA = $member->shares()->where('client_id', $this->clientA->id)->first();
        $shareB = $member->shares()->where('client_id', $this->clientB->id)->first();
        $this->assertEquals(50, (float) $shareA->ownership_percentage);
        $this->assertEquals(50000, (float) $shareA->share_amount);
        $this->assertEquals(50, (float) $shareB->ownership_percentage);
        $this->assertEquals(50000, (float) $shareB->share_amount);
        $this->assertTrue($shareA->is_primary);
    }

    /** @test */
    public function it_rejects_shared_membership_when_percentages_do_not_total_100(): void
    {
        $this->actingAs($this->admin);

        $response = $this->from(route('chit.members.create'))->post(route('chit.members.store'), [
            'group_id' => $this->group->id,
            'is_shared' => 1,
            'joined_date' => now()->format('Y-m-d'),
            'shares' => [
                ['client_id' => $this->clientA->id, 'ownership_percentage' => 40],
                ['client_id' => $this->clientB->id, 'ownership_percentage' => 40],
            ],
        ]);

        $response->assertSessionHasErrors('shares');
        $this->assertEquals(0, GroupMember::count());
    }

    /** @test */
    public function it_rejects_duplicate_customers_in_shared_membership(): void
    {
        $this->actingAs($this->admin);

        $response = $this->from(route('chit.members.create'))->post(route('chit.members.store'), [
            'group_id' => $this->group->id,
            'is_shared' => 1,
            'joined_date' => now()->format('Y-m-d'),
            'shares' => [
                ['client_id' => $this->clientA->id, 'ownership_percentage' => 50],
                ['client_id' => $this->clientA->id, 'ownership_percentage' => 50],
            ],
        ]);

        $response->assertSessionHasErrors('shares');
        $this->assertEquals(0, GroupMember::count());
    }

    /** @test */
    public function existing_single_membership_allocates_100_percent_without_share_rows(): void
    {
        $member = GroupMember::create([
            'group_id' => $this->group->id,
            'client_id' => $this->clientA->id,
            'is_shared' => false,
            'member_number' => 1,
            'status' => 'active',
            'joined_date' => now()->format('Y-m-d'),
        ]);
        $member->setRelation('group', $this->group);

        $this->assertEquals(100, $member->ownershipPercentageFor($this->clientA->id));
        $this->assertEquals(0, $member->ownershipPercentageFor($this->clientB->id));
        $this->assertEquals(10000, $member->amountForClient(10000, $this->clientA->id));
        $this->assertEquals(0, GroupMemberShare::count());
    }

    /** @test */
    public function allocate_amount_splits_by_ownership_percentage(): void
    {
        $member = GroupMember::create([
            'group_id' => $this->group->id,
            'client_id' => $this->clientA->id,
            'is_shared' => true,
            'member_number' => 1,
            'status' => 'active',
            'joined_date' => now()->format('Y-m-d'),
        ]);
        $member->syncShares(true, [
            ['client_id' => $this->clientA->id, 'ownership_percentage' => 60],
            ['client_id' => $this->clientB->id, 'ownership_percentage' => 40],
        ], 100000);

        $allocated = $member->fresh('shares')->allocateAmount(1000);
        $this->assertEquals(600, $allocated[$this->clientA->id]);
        $this->assertEquals(400, $allocated[$this->clientB->id]);
    }
}
