<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\ChitGroup;
use App\Models\GroupMember;
use App\Models\Dividend;
use App\Models\DividendDistribution;
use App\Models\Payout;
use App\Services\ChitPayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChitAuctionController extends Controller
{
    public function __construct(
        protected ChitPayoutService $payoutService
    ) {}
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'winners');

        $winnersQuery = Payout::with(['group.scheme', 'winner.client', 'auction', 'processedBy'])
            ->whereHas('group')
            ->where('status', 'paid')
            ->latest('paid_date');

        if ($request->filled('group_id')) {
            $winnersQuery->where('group_id', $request->group_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $winnersQuery->where(function ($q) use ($search) {
                $q->where('payout_code', 'like', "%{$search}%")
                    ->orWhereHas('winner.client', fn ($c) => $c
                        ->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%"))
                    ->orWhereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"));
            });
        }

        $winners = $winnersQuery->paginate(15, ['*'], 'winners_page')->withQueryString();

        $query = Auction::with(['group.scheme', 'winner.client', 'payout'])
            ->whereHas('group')
            ->latest('auction_date');

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $auctions = $query->paginate(15, ['*'], 'auctions_page')->withQueryString();
        $groups = ChitGroup::whereIn('status', ['active', 'completed'])->orderBy('id', 'desc')->get();
        $mode = 'index';

        return view('admin.chit.auctions.index', compact('auctions', 'winners', 'groups', 'mode', 'tab'));
    }

    public function create()
    {
        $groups = ChitGroup::where('status', 'active')
                           ->whereIn('scheme_type', ['auction', 'flexible'])
                           ->orderBy('id', 'desc')
                           ->get();
        $mode   = 'create';
        return view('admin.chit.auctions.index', compact('groups', 'mode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'group_id'     => 'required|exists:chit_groups,id',
            'month_number' => 'required|integer|min:1',
            'auction_date' => 'required|date',
            'auction_time' => 'nullable',
            'location'     => 'nullable|string',
            'min_bid'      => 'nullable|numeric',
        ]);

        $group = ChitGroup::findOrFail($request->group_id);

        if (!$group->supportsAuction()) {
            return back()->with('error', 'This group type (' . $group->scheme_type_label . ') does not support auctions.');
        }

        if (Auction::where('group_id', $group->id)->where('month_number', $request->month_number)->exists()) {
            return back()->with('error', 'Auction already scheduled for this month.');
        }

        Auction::create([
            'group_id'          => $group->id,
            'month_number'      => $request->month_number,
            'auction_date'      => $request->auction_date,
            'auction_time'      => $request->auction_time,
            'duration_minutes'  => $request->duration_minutes ?? 30,
            'bid_type'          => $request->bid_type ?? 'open',
            'location'          => $request->location,
            'min_bid'           => $request->min_bid ?? $group->installment_amount,
            'max_bid'           => $group->chit_value,
            'status'            => 'scheduled',
            'conducted_by'      => Auth::id(),
        ]);

        return redirect()->route('chit.auctions.index')->with('success', 'Auction scheduled!');
    }

    public function show(Auction $auction)
    {
        $auction->load(['group.scheme', 'bids.member.client', 'winner.client', 'dividend', 'payout.winner.client']);
        $eligibleMembers = $auction->group->members()
            ->where('has_won_auction', false)
            ->whereIn('status', ['active'])
            ->with('client')
            ->get();
        $mode = 'show';

        return view('admin.chit.auctions.index', compact('auction', 'eligibleMembers', 'mode'));
    }

    public function addBid(Request $request, Auction $auction)
    {
        $request->validate([
            'member_id'  => 'required|exists:group_members,id',
            'bid_amount' => 'required|numeric|min:1',
        ]);

        if ($auction->status !== 'open') {
            return back()->with('error', 'Auction is not open for bidding.');
        }

        $member = GroupMember::findOrFail($request->member_id);
        if ($member->has_won_auction || Payout::where('group_id', $auction->group_id)->where('winner_member_id', $member->id)->exists()) {
            return back()->with('error', 'This member has already received a payout/settlement and is not eligible.');
        }

        AuctionBid::updateOrCreate(
            ['auction_id' => $auction->id, 'member_id' => $request->member_id],
            ['bid_amount' => $request->bid_amount]
        );

        return back()->with('success', 'Bid recorded!');
    }

    public function declareWinner(Auction $auction)
    {
        $winnerBid = $auction->bids()->orderBy('bid_amount')->first();

        if (!$winnerBid) {
            return back()->with('error', 'No bids placed for this auction.');
        }

        if ($winnerBid->member->has_won_auction || Payout::where('group_id', $auction->group_id)->where('winner_member_id', $winnerBid->member_id)->exists()) {
            return back()->with('error', 'The winning bidder has already won or received a payout, and is not eligible.');
        }

        DB::transaction(function () use ($auction, $winnerBid) {
            $group    = $auction->group;
            $discount = $group->chit_value - $winnerBid->bid_amount;
            $commission = round($group->chit_value * $group->commission_pct / 100, 2);
            $netDividend = $discount - $commission;
            $perMember   = $group->total_members > 0 ? round($netDividend / $group->total_members, 2) : 0;

            $auction->update([
                'winning_bid'      => $winnerBid->bid_amount,
                'discount'         => $discount,
                'winner_member_id' => $winnerBid->member_id,
                'status'           => 'completed',
            ]);

            $winnerBid->update(['is_winner' => true]);

            $dividend = Dividend::create([
                'group_id'          => $group->id,
                'auction_id'        => $auction->id,
                'month_number'      => $auction->month_number,
                'chit_value'        => $group->chit_value,
                'discount'          => $discount,
                'commission_amount' => $commission,
                'net_dividend'      => $netDividend,
                'per_member_dividend' => $perMember,
                'status'            => 'pending',
            ]);

            $members = $group->members()->whereIn('status', ['active'])->get();
            foreach ($members as $member) {
                DividendDistribution::create([
                    'dividend_id' => $dividend->id,
                    'member_id'   => $member->id,
                    'amount'      => $perMember,
                    'status'      => 'pending',
                ]);
            }

            $this->payoutService->createAuctionSettlement(
                $group,
                $auction,
                $winnerBid->member,
                (float) $winnerBid->bid_amount,
                Auth::id()
            );
        });

        return redirect()->route('chit.auctions.show', $auction)->with('success', 'Winner declared! Dividend & payout records created.');
    }
}
