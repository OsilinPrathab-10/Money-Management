<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChitGroupCloseFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ChitScheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-close@test.com',
            'phone' => '1234567899',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->admin->assignRole('Admin');

        $this->scheme = ChitScheme::create([
            'name' => 'Close Test Scheme',
            'scheme_code' => 'SCHCLOSE1',
            'chit_value' => 10000.00,
            'total_members' => 2,
            'duration_months' => 2,
            'installment_amount' => 5000.00,
            'commission_pct' => 5.00,
            'commission_amount' => 500.00,
            'auction_type' => 'open',
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
    }

    protected function makeActiveGroup(array $overrides = []): ChitGroup
    {
        return ChitGroup::create(array_merge([
            'group_code' => 'GRPCLOSE1',
            'scheme_id' => $this->scheme->id,
            'start_date' => '2026-01-01',
            'total_months' => 2,
            'chit_value' => 10000.00,
            'total_members' => 2,
            'installment_amount' => 5000.00,
            'commission_pct' => 5.00,
            'auction_type' => 'open',
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'active',
            'current_month' => 0,
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    /** @test */
    public function it_can_close_an_active_group_early_while_still_running()
    {
        $this->actingAs($this->admin);

        $group = $this->makeActiveGroup(['current_month' => 1]);

        $this->assertTrue($group->canBeClosed());
        $this->assertFalse($group->isFullySettled());

        $response = $this->post(route('chit.groups.close', $group));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $group->refresh();
        $this->assertEquals('terminated', $group->status);
        $this->assertNotNull($group->end_date);
    }

    /** @test */
    public function it_closes_a_fully_settled_active_group()
    {
        $this->actingAs($this->admin);

        $group = $this->makeActiveGroup(['current_month' => 2]);

        $client = Client::create([
            'client_name' => 'Close Member',
            'client_email' => 'close@member.com',
            'client_phone' => '9998887776',
            'status' => 'active',
        ]);

        $member = GroupMember::create([
            'group_id' => $group->id,
            'client_id' => $client->id,
            'member_number' => 1,
            'status' => 'active',
            'joined_date' => '2026-01-01',
        ]);

        $auction = Auction::create([
            'group_id' => $group->id,
            'month_number' => 2,
            'auction_date' => '2026-02-01',
            'bid_type' => 'open',
            'status' => 'completed',
            'winner_member_id' => $member->id,
            'winning_bid' => 0,
        ]);

        Payout::create([
            'payout_code' => 'PAYCLOSE0001',
            'group_id' => $group->id,
            'auction_id' => $auction->id,
            'winner_member_id' => $member->id,
            'month_number' => 2,
            'chit_value' => 10000.00,
            'winning_bid' => 0,
            'commission_amount' => 500.00,
            'payout_amount' => 9500.00,
            'status' => 'paid',
            'paid_date' => '2026-02-05',
            'processed_by' => $this->admin->id,
        ]);

        $this->assertTrue($group->fresh()->canBeClosed());

        $response = $this->post(route('chit.groups.close', $group));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $group->refresh();
        $member->refresh();

        $this->assertEquals('completed', $group->status);
        $this->assertNotNull($group->end_date);
        $this->assertEquals('completed', $member->status);
        $this->assertFalse($group->canBeClosed());
    }
}
