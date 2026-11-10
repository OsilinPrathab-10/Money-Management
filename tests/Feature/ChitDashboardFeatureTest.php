<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChitDashboardFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $scheme;
    protected $group;
    protected $client;
    protected $member;

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

        $this->scheme = ChitScheme::create([
            'name' => 'Scheme A',
            'scheme_code' => 'SCH0001',
            'chit_value' => 10000.00,
            'duration_months' => 2,
            'total_members' => 5,
            'installment_amount' => 2000.00,
            'commission_pct' => 5.00,
            'commission_amount' => 500.00,
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'active',
        ]);

        $this->group = ChitGroup::create([
            'group_code' => 'GRP0001',
            'scheme_id' => $this->scheme->id,
            'start_date' => '2026-07-01',
            'total_months' => 2,
            'chit_value' => 10000.00,
            'total_members' => 5,
            'installment_amount' => 2000.00,
            'commission_pct' => 5.00,
            'auction_type' => 'open',
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $this->client = Client::create([
            'client_name' => 'John Doe',
            'client_email' => 'john@doe.com',
            'client_phone' => '1234567890',
            'status' => 'active',
        ]);

        $this->member = GroupMember::create([
            'group_id'      => $this->group->id,
            'client_id'     => $this->client->id,
            'member_number' => 1,
            'status'        => 'active',
            'joined_date'   => '2026-07-01',
        ]);
    }

    /** @test */
    public function it_can_load_chit_dashboard_with_correct_financials()
    {
        $this->actingAs($this->admin);

        // 1. Create a paid installment collection
        Installment::create([
            'group_id' => $this->group->id,
            'member_id' => $this->member->id,
            'month_number' => 1,
            'due_date' => '2026-07-01',
            'amount' => 2000.00,
            'paid_amount' => 2000.00,
            'status' => 'paid',
            'paid_date' => '2026-07-05',
        ]);

        // 2. Create an auction (required FK for payout)
        $auction = Auction::create([
            'group_id'       => $this->group->id,
            'month_number'   => 1,
            'auction_date'   => '2026-07-10',
            'bid_type'       => 'open',
            'status'         => 'completed',
            'winner_member_id' => $this->member->id,
            'winning_bid'    => 1000.00,
        ]);

        // 3. Create a paid payout settlement
        Payout::create([
            'payout_code'      => 'PAY2026070001',
            'group_id'         => $this->group->id,
            'auction_id'       => $auction->id,
            'winner_member_id' => $this->member->id,
            'month_number'     => 1,
            'chit_value'       => 10000.00,
            'winning_bid'      => 1000.00,
            'commission_amount'=> 500.00,
            'payout_amount'    => 8500.00,
            'status'           => 'paid',
            'paid_date'        => '2026-07-10',
            'processed_by'     => $this->admin->id,
        ]);

        $response = $this->get(route('chit.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.chit.dashboard');

        // Verify key variables passed to view
        $response->assertViewHas('totalCollections', 2000.00);
        $response->assertViewHas('totalSettlements', 8500.00);
        $response->assertViewHas('groupBalance', -6500.00);
        $response->assertViewHas('activeGroupsCount', 1);
        $response->assertViewHas('totalSchemesCount', 1);
        $response->assertSee('Settlement to whom', false);
        $response->assertSee('John Doe', false);
        $response->assertViewHas('recentSettlements', function ($settlements) {
            return $settlements->count() === 1
                && $settlements->first()['settlement_to'] === 'John Doe'
                && (float) $settlements->first()['amount'] === 8500.00;
        });
        $response->assertViewHas('groupWiseSummary', function ($summary) {
            $row = $summary->firstWhere('group_code', 'GRP0001');

            return $row
                && ($row['settlement_to'] ?? null) === 'John Doe'
                && (int) ($row['settlement_month'] ?? 0) === 1;
        });

        // Verify group specific amounts passed on groups.show
        $showResponse = $this->get(route('chit.groups.show', $this->group));
        $showResponse->assertStatus(200);
        $showResponse->assertViewHas('groupCollections', 2000.00);
        $showResponse->assertViewHas('groupSettlements', 8500.00);
        $showResponse->assertViewHas('groupBalance', -6500.00);
        $showResponse->assertViewHas('availableGroupFunds', -6500.00);
    }
}
