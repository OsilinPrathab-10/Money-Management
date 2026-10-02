<?php

namespace App\Http\Controllers;

use App\Models\ChitFamily;
use App\Models\ChitFamilyMember;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Account\BankAccount;
use App\Services\ChitPaymentService;
use App\Support\BulkPaymentGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChitFamilyController extends Controller
{
    public function __construct(
        protected ChitPaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        $query = ChitFamily::withCount('familyMembers')
            ->with(['primaryClient', 'members'])
            ->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhereHas('members', fn ($mq) => $mq->where('client_name', 'like', "%{$s}%")
                        ->orWhere('client_phone', 'like', "%{$s}%"));
            });
        }

        $families = $query->paginate(15)->withQueryString();
        $families->getCollection()->load('familyMembers');
        $clients  = Client::where('status', 'active')->orderBy('client_name')->get();
        $unassignedClients = Client::where('status', 'active')
            ->whereDoesntHave('chitFamilyMember')
            ->withCount(['groupMembers' => fn ($q) => $q->whereIn('status', ['active', 'approved', 'applied'])])
            ->orderBy('client_name')
            ->get();

        return view('admin.chit.families.index', compact('families', 'clients', 'unassignedClients'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'               => 'required|string|max:150',
            'notes'              => 'nullable|string|max:500',
            'primary_client_id'  => 'nullable|exists:clients,id',
            'client_ids'         => 'nullable|array',
            'client_ids.*'       => 'exists:clients,id',
            'relationships'      => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $family = ChitFamily::create([
                'name'              => $request->name,
                'primary_client_id' => $request->primary_client_id,
                'notes'             => $request->notes,
                'created_by'        => Auth::id(),
            ]);

            $clientIds = array_unique(array_filter($request->input('client_ids', [])));
            if ($request->primary_client_id && !in_array($request->primary_client_id, $clientIds)) {
                $clientIds[] = $request->primary_client_id;
            }

            foreach ($clientIds as $clientId) {
                if (ChitFamilyMember::where('client_id', $clientId)->exists()) {
                    $client = Client::find($clientId);
                    throw ValidationException::withMessages([
                        'client_ids' => ($client->client_name ?? 'Client') . ' is already assigned to another family.',
                    ]);
                }

                ChitFamilyMember::create([
                    'family_id'    => $family->id,
                    'client_id'    => $clientId,
                    'relationship' => $request->input("relationships.{$clientId}") ?: null,
                    'is_primary'   => (int) $clientId === (int) $request->primary_client_id,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()->route('chit.families.show', $family)
            ->with('success', 'Family created successfully!');
    }

    public function show(ChitFamily $family)
    {
        $family->load(['familyMembers.client', 'primaryClient', 'members']);

        Installment::applyAutomatedPenalties();

        $currentDues = $family->getCurrentDues();
        $bankAccounts = $this->getBankAccounts();

        $memberGroups = GroupMember::with(['group.scheme'])
            ->whereIn('client_id', $family->familyMembers->pluck('client_id'))
            ->whereIn('status', ['active', 'approved'])
            ->get()
            ->groupBy('client_id');

        return view('admin.chit.families.show', compact('family', 'currentDues', 'bankAccounts', 'memberGroups'));
    }

    public function update(Request $request, ChitFamily $family)
    {
        $request->validate([
            'name'              => 'required|string|max:150',
            'notes'             => 'nullable|string|max:500',
            'primary_client_id' => 'nullable|exists:clients,id',
        ]);

        $family->update($request->only(['name', 'notes', 'primary_client_id']));

        if ($request->primary_client_id) {
            $family->familyMembers()->update(['is_primary' => false]);
            $family->familyMembers()
                ->where('client_id', $request->primary_client_id)
                ->update(['is_primary' => true]);
        }

        return back()->with('success', 'Family updated successfully!');
    }

    public function destroy(ChitFamily $family)
    {
        $family->delete();
        return redirect()->route('chit.families.index')->with('success', 'Family deleted successfully!');
    }

    public function addMember(Request $request, ChitFamily $family)
    {
        $request->validate([
            'client_id'    => 'required|exists:clients,id',
            'relationship' => 'nullable|string|max:50',
        ]);

        if (ChitFamilyMember::where('client_id', $request->client_id)->exists()) {
            return back()->with('error', 'This client is already assigned to a family.');
        }

        ChitFamilyMember::create([
            'family_id'    => $family->id,
            'client_id'    => $request->client_id,
            'relationship' => $request->relationship ?: null,
            'is_primary'   => false,
        ]);

        return back()->with('success', 'Family member added successfully!');
    }

    public function removeMember(ChitFamily $family, ChitFamilyMember $member)
    {
        if ($member->family_id !== $family->id) {
            abort(404);
        }

        if ($family->primary_client_id === $member->client_id) {
            $family->update(['primary_client_id' => null]);
        }

        $member->delete();

        return back()->with('success', 'Member removed from family.');
    }

    public function bulkCollect(Request $request, ChitFamily $family)
    {
        Installment::applyAutomatedPenalties();

        $request->validate([
            'installment_ids'     => 'required|array|min:1',
            'installment_ids.*'   => 'exists:installments,id',
            'payment_mode'        => 'required|in:cash,in_hand,bank_transfer,upi,wallet',
            'paid_date'           => 'required|date|before_or_equal:today',
            'reference_no'        => 'nullable|string|max:100',
            'remarks'             => 'nullable|string|max:500',
            'collection_type'     => 'required|in:full,partial',
            'total_amount_paid'   => 'required_if:collection_type,partial|nullable|numeric|min:0.01',
            'internal_bank_account_id' => 'required_if:payment_mode,upi,bank_transfer|nullable|exists:bank_accounts,id',
        ]);

        if (in_array($request->payment_mode, ['upi', 'bank_transfer'], true) && ! $request->filled('internal_bank_account_id')) {
            throw ValidationException::withMessages([
                'internal_bank_account_id' => ['Please select a collection bank account for UPI / Bank Transfer payments.'],
            ]);
        }

        $familyClientIds = $family->familyMembers()->pluck('client_id');
        $installments = Installment::with('member.client')
            ->whereIn('id', $request->installment_ids)
            ->get();

        if ($installments->isEmpty()) {
            throw ValidationException::withMessages([
                'installment_ids' => 'No valid installments selected.',
            ]);
        }

        foreach ($installments as $installment) {
            $clientId = $installment->member?->client_id;
            if (!$clientId || !$familyClientIds->contains($clientId)) {
                throw ValidationException::withMessages([
                    'installment_ids' => 'One or more installments do not belong to this family.',
                ]);
            }
        }

        $batchKey = BulkPaymentGroup::generateKey();
        $reference = $request->reference_no ?: $batchKey;
        $remarks = BulkPaymentGroup::appendRemarks(
            $request->remarks,
            $batchKey,
            BulkPaymentGroup::FAMILY_MARKER . ': ' . $family->name . ']'
        );
        $collected = 0;
        $totalAmount = 0;
        $errors = [];
        $collectedPayments = [];
        $cashbookLines = [];

        DB::beginTransaction();
        try {
            if ($request->collection_type === 'full') {
                foreach ($installments as $installment) {
                    $balance = round((float) $installment->balance, 2);
                    if ($balance <= 0) {
                        continue;
                    }

                    try {
                        $result = $this->paymentService->collectInstallment($installment, [
                            'paid_amount'   => $balance,
                            'payment_mode'  => $request->payment_mode,
                            'payment_type'  => 'full',
                            'paid_date'     => $request->paid_date,
                            'reference_no'  => $reference,
                            'remarks'       => $remarks,
                            'internal_bank_account_id' => $request->internal_bank_account_id,
                            'skip_cashbook' => true,
                        ]);
                        $collected++;
                        $totalAmount += $balance;
                        $collectedPayments[] = [
                            'client_id'      => $installment->member?->client_id,
                            'client_name'    => $installment->member?->client?->client_name ?? 'Member',
                            'client_phone'   => $installment->member?->client?->client_phone ?? '',
                            'group_code'     => $installment->group?->group_code ?? '',
                            'month_number'   => $installment->month_number,
                            'amount_paid'    => $balance,
                            'remaining_bal'  => (float) ($installment->fresh()->balance ?? 0),
                        ];
                        $lineKey = ($result['group_code'] ?? 'GRP') . '|' . ($result['client_name'] ?? 'Member');
                        if (! isset($cashbookLines[$lineKey])) {
                            $cashbookLines[$lineKey] = [
                                'group_code' => $result['group_code'] ?? ($installment->group?->group_code ?? 'GRP'),
                                'client_name' => $result['client_name'] ?? ($installment->member?->client?->client_name ?? 'Member'),
                                'month' => [],
                            ];
                        }
                        foreach (($result['months'] ?? [(int) $installment->month_number]) as $monthNo) {
                            $cashbookLines[$lineKey]['month'][] = (int) $monthNo;
                        }
                    } catch (ValidationException $e) {
                        $clientName = $installment->member?->client?->client_name ?? 'Member';
                        $errors[] = $clientName . ': ' . collect($e->errors())->flatten()->first();
                    }
                }
            } else {
                $remainingPaid = round((float) $request->total_amount_paid, 2);
                $sortedInstallments = $installments->sortBy('due_date');

                foreach ($sortedInstallments as $installment) {
                    if ($remainingPaid <= 0.009) {
                        break;
                    }

                    $balance = round((float) $installment->balance, 2);
                    if ($balance <= 0) {
                        continue;
                    }

                    $amountToPay = min($remainingPaid, $balance);
                    $paymentType = abs($amountToPay - $balance) < 0.01 ? 'full' : 'partial';

                    try {
                        $result = $this->paymentService->collectInstallment($installment, [
                            'paid_amount'           => $amountToPay,
                            'payment_mode'          => $request->payment_mode,
                            'payment_type'          => $paymentType,
                            'paid_date'             => $request->paid_date,
                            'reference_no'          => $reference,
                            'remarks'               => $remarks,
                            'bypass_min_validation' => true,
                            'internal_bank_account_id' => $request->internal_bank_account_id,
                            'skip_cashbook' => true,
                        ]);
                        $collected++;
                        $totalAmount += $amountToPay;
                        $remainingPaid -= $amountToPay;
                        $collectedPayments[] = [
                            'client_id'      => $installment->member?->client_id,
                            'client_name'    => $installment->member?->client?->client_name ?? 'Member',
                            'client_phone'   => $installment->member?->client?->client_phone ?? '',
                            'group_code'     => $installment->group?->group_code ?? '',
                            'month_number'   => $installment->month_number,
                            'amount_paid'    => $amountToPay,
                            'remaining_bal'  => (float) ($installment->fresh()->balance ?? 0),
                        ];
                        $lineKey = ($result['group_code'] ?? 'GRP') . '|' . ($result['client_name'] ?? 'Member');
                        if (! isset($cashbookLines[$lineKey])) {
                            $cashbookLines[$lineKey] = [
                                'group_code' => $result['group_code'] ?? ($installment->group?->group_code ?? 'GRP'),
                                'client_name' => $result['client_name'] ?? ($installment->member?->client?->client_name ?? 'Member'),
                                'month' => [],
                            ];
                        }
                        foreach (($result['months'] ?? [(int) $installment->month_number]) as $monthNo) {
                            $cashbookLines[$lineKey]['month'][] = (int) $monthNo;
                        }
                    } catch (ValidationException $e) {
                        $clientName = $installment->member?->client?->client_name ?? 'Member';
                        $errors[] = $clientName . ': ' . collect($e->errors())->flatten()->first();
                    }
                }
            }

            if ($collected === 0) {
                DB::rollBack();
                throw ValidationException::withMessages([
                    'installment_ids' => $errors[0] ?? 'No installments could be collected.',
                ]);
            }

            if ($totalAmount > 0.009 && ! in_array($request->payment_mode, ['wallet'], true)) {
                foreach ($cashbookLines as &$line) {
                    $line['month'] = array_values(array_unique($line['month']));
                }
                unset($line);

                app(\App\Services\Account\ChitAccountingService::class)->recordBulkInstallmentCollection(
                    (float) $totalAmount,
                    [
                        'payment_mode' => $request->payment_mode,
                        'paid_date' => $request->paid_date,
                        'reference_no' => $reference,
                        'internal_bank_account_id' => $request->internal_bank_account_id,
                        'lines' => array_values($cashbookLines),
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $newOutstandingBalance = $family->total_current_due;
        $message = "Collected {$collected} installment(s) totalling ₹" . number_format($totalAmount, 2) . ". Outstanding balance: ₹" . number_format($newOutstandingBalance, 2);
        if (!empty($errors)) {
            $message .= '. Skipped: ' . implode('; ', $errors);
        }

        $family->loadMissing(['primaryClient', 'members']);
        $primaryClient = $family->primaryClient ?: $family->members->first();
        $primaryPhone = $primaryClient?->client_phone ?? '';
        $cleanPhone = preg_replace('/\D/', '', $primaryPhone);
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '91' . $cleanPhone;
        }

        $whatsappUrl = null;
        $smsUrl = null;
        if ($cleanPhone) {
            $msgData = \App\Helpers\NotificationTemplateHelper::getFamilyBulkPaymentMessages([
                'client_name'       => $primaryClient?->client_name ?? $family->name,
                'family_name'       => $family->name,
                'collected_count'   => $collected,
                'amount_paid'       => $totalAmount,
                'reference_no'      => $reference,
                'remaining_balance' => $newOutstandingBalance,
            ]);
            $whatsappUrl = 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($msgData['whatsapp_message']);
            $smsUrl = 'sms:+' . $cleanPhone . '?body=' . rawurlencode($msgData['sms_message']);
        }

        $memberMessages = [];
        $groupedByClient = collect($collectedPayments)->groupBy('client_id');

        foreach ($groupedByClient as $clientId => $items) {
            $cName = $items->first()['client_name'];
            $cPhone = $items->first()['client_phone'];
            $paidSum = $items->sum('amount_paid');
            $cleanMemberPhone = preg_replace('/\D/', '', $cPhone);
            if (strlen($cleanMemberPhone) === 10) {
                $cleanMemberPhone = '91' . $cleanMemberPhone;
            }

            $pubToken = $clientId ? \App\Support\HashId::encode($clientId) : null;
            $pubLink = $pubToken ? route('public.view-chit-schedule', $pubToken) : '';

            $groupNameStr = $items->pluck('group_code')->unique()->filter()->implode(', ');
            $monthsStr = $items->pluck('month_number')->unique()->filter()->implode(', ');
            $latestRemaining = $items->last()['remaining_bal'];

            $memberWaUrl = null;
            $memberSmsUrl = null;

            if ($cleanMemberPhone) {
                $msgRes = \App\Helpers\NotificationTemplateHelper::getChitRepaymentMessages([
                    'client_name'       => $cName,
                    'mobile_no'         => $cleanMemberPhone,
                    'group_name'        => $groupNameStr,
                    'month_number'      => $monthsStr,
                    'amount_paid'       => $paidSum,
                    'remaining_balance' => $latestRemaining,
                    'client_id'         => $clientId,
                    'public_link'       => $pubLink,
                ]);

                $memberWaUrl = 'https://wa.me/' . $cleanMemberPhone . '?text=' . rawurlencode($msgRes['whatsapp_message']);
                $memberSmsUrl = 'sms:+' . $cleanMemberPhone . '?body=' . rawurlencode($msgRes['sms_message']);
            }

            $memberMessages[] = [
                'client_id'    => $clientId,
                'client_name'  => $cName,
                'client_phone' => $cPhone,
                'amount_paid'  => $paidSum,
                'group_name'   => $groupNameStr,
                'month_number' => $monthsStr,
                'whatsapp_url' => $memberWaUrl,
                'sms_url'      => $memberSmsUrl,
            ];
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'             => true,
                'message'             => $message,
                'collected'           => $collected,
                'total_amount'        => $totalAmount,
                'reference_no'        => $reference,
                'outstanding_balance' => $newOutstandingBalance,
                'primary_phone'       => $primaryPhone,
                'primary_name'        => $primaryClient?->client_name ?? '',
                'whatsapp_url'        => $whatsappUrl,
                'sms_url'             => $smsUrl,
                'collected_members'   => $memberMessages,
            ]);
        }

        return back()->with('success', $message);
    }

    protected function getBankAccounts()
    {
        return BankAccount::where('is_active', true)
            ->orderBy('account_name')
            ->get();
    }
}
