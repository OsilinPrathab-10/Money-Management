<?php

namespace Tests\Feature;

use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChitAdvancePayoutFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ChitGroup $group;
    protected GroupMember $memberOne;
    protected GroupMember $memberTwo;
    protected GroupMember $memberThree;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Advance Admin',
            'email' => 'advance-admin@test.com',
            'phone' => '5555555555',
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => 'admin',
        ]);
        $this->admin->assignRole('Admin');

        $scheme = ChitScheme::create([
            'name' => 'Unregistered Scheme',
            'scheme_code' => 'SCHADV01',
            'chit_value' => 300000.00,
            'duration_months' => 3,
            'total_members' => 3,
            'installment_amount' => 100000.00,
            'commission_pct' => 5.00,
            'commission_amount' => 15000.00,
            'scheme_type' => 'fixed',
            'installment_frequency' => 'monthly',
            'registration_type' => 'non_registered',
            'status' => 'active',
        ]);

        $this->group = ChitGroup::create([
            'group_code' => 'GRPADV01',
            'scheme_id' => $scheme->id,
            'start_date' => '2026-07-01',
            'total_months' => 3,
            'chit_value' => 300000.00,
            'total_members' => 3,
            'installment_amount' => 100000.00,
            'commission_pct' => 5.00,
            'scheme_type' => 'fixed',
            'registration_type' => 'non_registered',
            'installment_frequency' => 'monthly',
            'status' => 'active',
            'current_month' => 0,
            'created_by' => $this->admin->id,
        ]);

        foreach ([1 => 'Member One', 2 => 'Member Two', 3 => 'Member Three'] as $number => $name) {
            $client = Client::create([
                'client_name' => $name,
                'client_email' => strtolower(str_replace(' ', '', $name)) . '@test.com',
                'client_phone' => '666666666' . $number,
                'status' => 'active',
            ]);

            $member = GroupMember::create([
                'group_id' => $this->group->id,
                'client_id' => $client->id,
                'member_number' => $number,
                'status' => 'active',
                'joined_date' => '2026-07-01',
            ]);

            if ($number === 1) {
                $this->memberOne = $member;
            } elseif ($number === 2) {
                $this->memberTwo = $member;
            } else {
                $this->memberThree = $member;
            }
        }
    }

    public function test_unregistered_chit_allows_advance_payouts_for_same_period_after_original(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('chit.settlements.process', [$this->group, $this->memberOne]), [
            'payment_mode' => 'bank_transfer',
            'paid_date' => '2026-07-10',
            'reference_no' => 'ORIG001',
            'payout_kind' => 'original',
        ])->assertRedirect(route('chit.settlements.history'));

        $original = Payout::where('group_id', $this->group->id)->where('payout_kind', 'original')->first();
        $this->assertNotNull($original);
        $this->assertEquals('paid', $original->status);

        $this->group->refresh();
        $this->assertEquals(1, $this->group->current_month);

        $this->post(route('chit.settlements.process', [$this->group, $this->memberTwo]), [
            'payment_mode' => 'cash',
            'paid_date' => '2026-07-10',
            'reference_no' => 'ADV001',
            'payout_kind' => 'advance',
        ])->assertRedirect(route('chit.settlements.history'));

        $this->post(route('chit.settlements.process', [$this->group, $this->memberThree]), [
            'payment_mode' => 'upi',
            'paid_date' => '2026-07-10',
            'reference_no' => 'ADV002',
            'payout_kind' => 'advance',
        ])->assertRedirect(route('chit.settlements.history'));

        $this->assertDatabaseHas('payouts', [
            'group_id' => $this->group->id,
            'winner_member_id' => $this->memberTwo->id,
            'month_number' => 1,
            'payout_kind' => 'advance',
            'original_payout_id' => $original->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payouts', [
            'group_id' => $this->group->id,
            'winner_member_id' => $this->memberThree->id,
            'month_number' => 1,
            'payout_kind' => 'advance',
            'original_payout_id' => $original->id,
            'status' => 'paid',
        ]);

        $this->group->refresh();
        $this->assertEquals(1, $this->group->current_month);
        $this->assertEquals(3, Payout::where('group_id', $this->group->id)->where('status', 'paid')->count());
    }

    public function test_registered_chit_blocks_second_payout_for_same_period(): void
    {
        $this->actingAs($this->admin);

        $this->group->update(['registration_type' => 'non_registered']);
        $this->group->scheme->update(['registration_type' => 'registered']);
        $this->group->update(['registration_type' => 'registered']);

        $this->post(route('chit.settlements.process', [$this->group, $this->memberOne]), [
            'payment_mode' => 'bank_transfer',
            'paid_date' => '2026-07-10',
            'payout_kind' => 'original',
        ])->assertRedirect(route('chit.settlements.history'));

        $response = $this->post(route('chit.settlements.process', [$this->group, $this->memberTwo]), [
            'payment_mode' => 'cash',
            'paid_date' => '2026-07-10',
            'payout_kind' => 'advance',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(1, Payout::where('group_id', $this->group->id)->count());
    }
}
