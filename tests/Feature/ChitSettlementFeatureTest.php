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

class ChitSettlementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $agent;
    protected ChitGroup $group;
    protected GroupMember $memberOne;
    protected GroupMember $memberTwo;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        Role::firstOrCreate(['name' => 'Agent']);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-settlement@test.com',
            'phone' => '1111111111',
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => 'admin',
        ]);
        $this->admin->assignRole('Admin');

        $this->agent = User::create([
            'name' => 'Agent User',
            'email' => 'agent-settlement@test.com',
            'phone' => '2222222222',
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => 'agent',
        ]);
        $this->agent->assignRole('Agent');

        $scheme = ChitScheme::create([
            'name' => 'Fixed Scheme',
            'scheme_code' => 'SCHSET01',
            'chit_value' => 100000.00,
            'duration_months' => 2,
            'total_members' => 2,
            'installment_amount' => 50000.00,
            'commission_pct' => 5.00,
            'commission_amount' => 5000.00,
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'active',
        ]);

        $this->group = ChitGroup::create([
            'group_code' => 'GRPSET01',
            'scheme_id' => $scheme->id,
            'start_date' => '2026-07-01',
            'total_months' => 2,
            'chit_value' => 100000.00,
            'total_members' => 2,
            'installment_amount' => 50000.00,
            'commission_pct' => 5.00,
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'status' => 'active',
            'current_month' => 0,
            'created_by' => $this->admin->id,
        ]);

        $clientOne = Client::create([
            'client_name' => 'Member One',
            'client_email' => 'one@test.com',
            'client_phone' => '3333333333',
            'status' => 'active',
        ]);

        $clientTwo = Client::create([
            'client_name' => 'Member Two',
            'client_email' => 'two@test.com',
            'client_phone' => '4444444444',
            'status' => 'active',
        ]);

        $this->memberOne = GroupMember::create([
            'group_id' => $this->group->id,
            'client_id' => $clientOne->id,
            'member_number' => 1,
            'status' => 'active',
            'joined_date' => '2026-07-01',
        ]);

        $this->memberTwo = GroupMember::create([
            'group_id' => $this->group->id,
            'client_id' => $clientTwo->id,
            'member_number' => 2,
            'status' => 'active',
            'joined_date' => '2026-07-01',
        ]);
    }

    /** @test */
    public function admin_can_view_settlement_management_page()
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('chit.settlements.manage'));

        $response->assertStatus(200);
        $response->assertSee('Settlement Management');
        $response->assertSee('GRPSET01');
        $response->assertSee('Settlement');
    }

    /** @test */
    public function admin_can_settle_and_pay_in_one_step()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('chit.settlements.process', [$this->group, $this->memberOne]), [
            'payment_mode' => 'bank_transfer',
            'paid_date' => '2026-07-10',
            'reference_no' => 'TXN001',
        ]);

        $response->assertRedirect(route('chit.settlements.history'));
        $this->assertDatabaseHas('payouts', [
            'group_id' => $this->group->id,
            'winner_member_id' => $this->memberOne->id,
            'month_number' => 1,
            'status' => 'paid',
            'reference_no' => 'TXN001',
            'processed_by' => $this->admin->id,
        ]);

        $this->group->refresh();
        $this->memberOne->refresh();
        $this->assertEquals(1, $this->group->current_month);
        $this->assertTrue($this->memberOne->has_won_auction);
    }

    /** @test */
    public function agent_cannot_access_settlement_module()
    {
        $this->actingAs($this->agent);

        $this->get(route('chit.settlements.manage'))->assertStatus(403);
    }

    /** @test */
    public function duplicate_monthly_settlement_is_prevented()
    {
        $this->actingAs($this->admin);

        $this->post(route('chit.payouts.store'), [
            'group_id' => $this->group->id,
            'member_id' => $this->memberOne->id,
        ]);

        $response = $this->post(route('chit.settlements.process', [$this->group, $this->memberTwo]), [
            'payment_mode' => 'cash',
            'paid_date' => '2026-07-11',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(1, Payout::where('group_id', $this->group->id)->count());
    }

    /** @test */
    public function duplicate_member_settlement_is_prevented()
    {
        $this->actingAs($this->admin);

        Payout::create([
            'payout_code' => 'PAYTEST0001',
            'group_id' => $this->group->id,
            'auction_id' => Auction::create([
                'group_id' => $this->group->id,
                'month_number' => 1,
                'auction_date' => '2026-07-01',
                'status' => 'completed',
            ])->id,
            'winner_member_id' => $this->memberOne->id,
            'month_number' => 1,
            'chit_value' => 100000,
            'winning_bid' => 100000,
            'commission_amount' => 5000,
            'payout_amount' => 95000,
            'status' => 'paid',
            'paid_date' => '2026-07-10',
            'processed_by' => $this->admin->id,
        ]);

        $this->group->update(['current_month' => 1]);

        $response = $this->post(route('chit.settlements.process', [$this->group, $this->memberOne]), [
            'payment_mode' => 'cash',
            'paid_date' => '2026-07-12',
        ]);

        $response->assertSessionHas('error');
    }

    /** @test */
    public function settlement_history_page_supports_filtering()
    {
        $this->actingAs($this->admin);

        Payout::create([
            'payout_code' => 'PAYTEST0002',
            'group_id' => $this->group->id,
            'auction_id' => Auction::create([
                'group_id' => $this->group->id,
                'month_number' => 1,
                'auction_date' => '2026-07-01',
                'status' => 'completed',
            ])->id,
            'winner_member_id' => $this->memberOne->id,
            'month_number' => 1,
            'chit_value' => 100000,
            'winning_bid' => 100000,
            'commission_amount' => 5000,
            'payout_amount' => 95000,
            'status' => 'paid',
            'paid_date' => '2026-07-10',
            'processed_by' => $this->admin->id,
        ]);

        $response = $this->get(route('chit.settlements.history', [
            'group_id' => $this->group->id,
            'status' => 'paid',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Member One');
        $response->assertSee('Export CSV');
    }

    /** @test */
    public function settlement_history_can_be_exported()
    {
        $this->actingAs($this->admin);

        Payout::create([
            'payout_code' => 'PAYTEST0003',
            'group_id' => $this->group->id,
            'auction_id' => Auction::create([
                'group_id' => $this->group->id,
                'month_number' => 1,
                'auction_date' => '2026-07-01',
                'status' => 'completed',
            ])->id,
            'winner_member_id' => $this->memberOne->id,
            'month_number' => 1,
            'chit_value' => 100000,
            'winning_bid' => 100000,
            'commission_amount' => 5000,
            'payout_amount' => 95000,
            'status' => 'paid',
            'paid_date' => '2026-07-10',
            'processed_by' => $this->admin->id,
        ]);

        $response = $this->get(route('chit.settlements.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    /** @test */
    public function settlement_uses_monthly_payout_amount_from_scheme_schedule()
    {
        $this->actingAs($this->admin);

        $this->group->scheme->update([
            'payout_schedule' => [
                ['installment_no' => 1, 'installment_amount' => 50000, 'payout_amount' => '90000'],
                ['installment_no' => 2, 'installment_amount' => 50000, 'payout_amount' => '85000'],
            ],
        ]);

        $this->post(route('chit.settlements.process', [$this->group, $this->memberOne]), [
            'payment_mode' => 'bank_transfer',
            'paid_date' => '2026-07-10',
        ]);

        $this->assertDatabaseHas('payouts', [
            'group_id' => $this->group->id,
            'month_number' => 1,
            'payout_amount' => 90000,
            'status' => 'paid',
        ]);

        $this->group->update(['current_month' => 1]);

        $this->post(route('chit.settlements.process', [$this->group, $this->memberTwo]), [
            'payment_mode' => 'bank_transfer',
            'paid_date' => '2026-08-10',
        ]);

        $this->assertDatabaseHas('payouts', [
            'group_id' => $this->group->id,
            'month_number' => 2,
            'payout_amount' => 85000,
            'status' => 'paid',
        ]);
    }

    /** @test */
    public function settlement_confirm_page_shows_member_and_amount()
    {
        $this->actingAs($this->admin);

        $this->group->scheme->update([
            'payout_schedule' => [
                ['installment_no' => 1, 'installment_amount' => 50000, 'payout_amount' => '95000'],
            ],
        ]);

        $response = $this->get(route('chit.settlements.confirm', [$this->group, $this->memberOne]));

        $response->assertStatus(200);
        $response->assertSee('Settlement Confirmation');
        $response->assertSee('Member One');
        $response->assertSee('95,000');
        $response->assertSee('Month 1');
    }
}
