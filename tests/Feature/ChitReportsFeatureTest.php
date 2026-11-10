<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChitReportsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin']);
        $this->admin = User::create([
            'name' => 'Reports Admin',
            'email' => 'reports-admin@example.com',
            'phone' => '9000000011',
            'password' => bcrypt('password'),
            'status' => 'active',
            'role' => 'admin',
        ]);
        $this->admin->assignRole('Admin');
    }

    public function test_admin_can_open_chit_reports_hub_and_each_report(): void
    {
        $this->actingAs($this->admin)
            ->get(route('chit.reports.index'))
            ->assertOk()
            ->assertSee('Chit Reports')
            ->assertSee('Group Performance')
            ->assertSee('Collection Register');

        foreach (['groups', 'members', 'installments', 'collections', 'auctions', 'settlements'] as $report) {
            $this->get(route('chit.reports.show', $report))->assertOk();
        }
    }

    public function test_chit_reports_support_csv_excel_and_pdf_exports(): void
    {
        $this->actingAs($this->admin)
            ->get(route('chit.reports.export', ['report' => 'collections', 'format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->get(route('chit.reports.export', ['report' => 'collections', 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->get(route('chit.reports.export', ['report' => 'collections', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invalid_report_returns_not_found(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('chit.reports.show', 'unknown'))->assertNotFound();
    }
}
