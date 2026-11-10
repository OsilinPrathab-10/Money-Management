<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\ChitScheme;
use App\Models\ChitGroup;
use App\Models\GroupMember;
use App\Models\ChitFamily;
use App\Models\ChitFamilyMember;
use App\Models\Installment;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChitFamilyPartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $scheme;
    protected $group;
    protected $family;
    protected $client1;
    protected $client2;
    protected $member1;
    protected $member2;

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
            'name' => 'Test Scheme',
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

        // Create a Chit Group already in active status
        $this->group = ChitGroup::create([
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
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // Create Clients
        $this->client1 = Client::create([
            'client_name' => 'John Family1',
            'client_email' => 'john@family.com',
            'client_phone' => '9876543210',
            'status' => 'active',
        ]);

        $this->client2 = Client::create([
            'client_name' => 'Jane Family2',
            'client_email' => 'jane@family.com',
            'client_phone' => '9876543211',
            'status' => 'active',
        ]);

        // Create a Chit Family
        $this->family = ChitFamily::create([
            'name' => 'The Test Family',
            'primary_client_id' => $this->client1->id,
            'created_by' => $this->admin->id,
        ]);

        ChitFamilyMember::create([
            'family_id' => $this->family->id,
            'client_id' => $this->client1->id,
            'relationship' => 'Self',
            'is_primary' => true,
        ]);

        ChitFamilyMember::create([
            'family_id' => $this->family->id,
            'client_id' => $this->client2->id,
            'relationship' => 'Spouse',
            'is_primary' => false,
        ]);

        // Enroll members in the group and generate installments
        $this->member1 = GroupMember::create([
            'group_id' => $this->group->id,
            'client_id' => $this->client1->id,
            'member_number' => 1,
            'status' => 'active',
            'joined_date' => today(),
        ]);
        $this->group->generateInstallmentsForMember($this->member1, today());

        $this->member2 = GroupMember::create([
            'group_id' => $this->group->id,
            'client_id' => $this->client2->id,
            'member_number' => 2,
            'status' => 'active',
            'joined_date' => today(),
        ]);
        $this->group->generateInstallmentsForMember($this->member2, today());
    }

    /** @test */
    public function it_can_collect_full_bulk_payment_for_family_members()
    {
        $this->actingAs($this->admin);

        // Get the first installment for each member
        $inst1 = Installment::where('member_id', $this->member1->id)->where('month_number', 1)->first();
        $inst2 = Installment::where('member_id', $this->member2->id)->where('month_number', 1)->first();

        $response = $this->postJson(route('chit.families.bulk-collect', $this->family), [
            'installment_ids' => [$inst1->id, $inst2->id],
            'payment_mode' => 'cash',
            'paid_date' => today()->format('Y-m-d'),
            'collection_type' => 'full',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message', 'collected', 'total_amount', 'outstanding_balance']);

        $inst1->refresh();
        $inst2->refresh();

        $this->assertEquals('paid', $inst1->status);
        $this->assertEquals('paid', $inst2->status);
        $this->assertEquals(5000.00, $inst1->paid_amount);
        $this->assertEquals(5000.00, $inst2->paid_amount);
    }

    /** @test */
    public function it_can_collect_partial_payment_sequentially_closing_emi_one_by_one()
    {
        $this->actingAs($this->admin);

        // Get the first installment for each member (each is ₹5,000)
        $inst1 = Installment::where('member_id', $this->member1->id)->where('month_number', 1)->first();
        $inst2 = Installment::where('member_id', $this->member2->id)->where('month_number', 1)->first();

        // Check original due dates are set
        $inst1->update(['due_date' => today()->subDays(5)]);
        $inst2->update(['due_date' => today()->addDays(5)]);

        // Pay ₹8,000 total (₹5,000 goes to inst1, ₹3,000 goes to inst2)
        $response = $this->postJson(route('chit.families.bulk-collect', $this->family), [
            'installment_ids' => [$inst1->id, $inst2->id],
            'payment_mode' => 'cash',
            'paid_date' => today()->format('Y-m-d'),
            'collection_type' => 'partial',
            'total_amount_paid' => 8000.00,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'collected' => 2,
            'total_amount' => 8000.00,
        ]);

        $inst1->refresh();
        $inst2->refresh();

        // inst1 (oldest due_date) should be fully paid
        $this->assertEquals('paid', $inst1->status);
        $this->assertEquals(5000.00, $inst1->paid_amount);

        // inst2 should be partially paid
        $this->assertEquals('partial', $inst2->status);
        $this->assertEquals(3000.00, $inst2->paid_amount);
        $this->assertEquals(2000.00, $inst2->balance);

        // Assert response message includes the new outstanding balance
        // The remaining balance on inst2 is ₹2,000, and member1's month 2 (₹5,000) is now collectible, so outstanding balance is ₹7,000.
        $response->assertJsonFragment([
            'outstanding_balance' => 7000.00,
        ]);
        $this->assertStringContainsString('Outstanding balance: ₹7,000.00', $response->json('message'));
    }

    /** @test */
    public function it_calculates_and_allocates_percentage_based_overdue_penalties_sequentially()
    {
        $this->actingAs($this->admin);

        // Configure penalty: 10% percentage-based penalty with 2 grace days
        \App\Models\ChitConfiguration::set('penalty_type', 'percentage');
        \App\Models\ChitConfiguration::set('penalty_value', '10.00');
        \App\Models\ChitConfiguration::set('penalty_grace_days', '2');

        $inst1 = Installment::where('member_id', $this->member1->id)->where('month_number', 1)->first();
        $inst2 = Installment::where('member_id', $this->member2->id)->where('month_number', 1)->first();

        // inst1 is 5 days overdue (past grace period of 2 days)
        $inst1->update(['due_date' => today()->subDays(5)]);
        // inst2 is 1 day overdue (within grace period of 2 days)
        $inst2->update(['due_date' => today()->subDays(1)]);

        // Call the automated penalty logic
        Installment::applyAutomatedPenalties();

        $inst1->refresh();
        $inst2->refresh();

        // inst1 is past grace days -> should have 10% of 5000 = 500 penalty, status overdue, total due 5500
        $this->assertEquals(500.00, (float) $inst1->penalty_amount);
        $this->assertEquals('overdue', $inst1->status);
        $this->assertEquals(5500.00, (float) $inst1->total_due);
        $this->assertEquals(5500.00, (float) $inst1->balance);

        // inst2 is within grace days -> should have 0 penalty, status overdue
        $this->assertEquals(0.00, (float) $inst2->penalty_amount);
        $this->assertEquals('overdue', $inst2->status);
        $this->assertEquals(5000.00, (float) $inst2->total_due);

        // Pay ₹6,000 sequentially:
        // - ₹5,500 fully pays inst1 (including penalty)
        // - ₹500 goes to inst2 as partial payment
        $response = $this->postJson(route('chit.families.bulk-collect', $this->family), [
            'installment_ids' => [$inst1->id, $inst2->id],
            'payment_mode' => 'cash',
            'paid_date' => today()->format('Y-m-d'),
            'collection_type' => 'partial',
            'total_amount_paid' => 6000.00,
        ]);

        $response->assertStatus(200);

        $inst1->refresh();
        $inst2->refresh();

        // inst1 should be fully paid (including penalty)
        $this->assertEquals('paid', $inst1->status);
        $this->assertEquals(5500.00, (float) $inst1->paid_amount);

        // inst2 should have ₹500 paid
        $this->assertEquals('partial', $inst2->status);
        $this->assertEquals(500.00, (float) $inst2->paid_amount);
        $this->assertEquals(4500.00, (float) $inst2->balance);
    }
}
