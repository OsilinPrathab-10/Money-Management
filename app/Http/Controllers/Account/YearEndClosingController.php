<?php

namespace App\Http\Controllers\Account;

use App\Services\Account\YearEndClosingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class YearEndClosingController extends Controller
{
    public function __construct(protected YearEndClosingService $service)
    {
    }

    /**
     * Show the year-end closing preview page.
     * Defaults to the previous calendar year so the admin reviews a full year.
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account'), 403);

        $currentYear = (int) date('Y');
        $year = (int) $request->get('year', $currentYear - 1);

        // Clamp to a sensible range
        $year = max(2000, min($year, $currentYear));

        $preview = $this->service->preview($year, creatorId());

        $availableYears = range($currentYear, 2000);

        return view('admin.account.year-end-closing.index', [
            'preview'        => $preview,
            'year'           => $year,
            'currentYear'    => $currentYear,
            'availableYears' => $availableYears,
        ]);
    }

    /**
     * Execute the year-end closing for the given year.
     */
    public function close(Request $request)
    {
        abort_unless(Auth::user()->can('manage-account'), 403);

        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:' . date('Y'),
        ]);

        $year = (int) $validated['year'];

        try {
            $journal = $this->service->close($year, creatorId());

            return redirect()
                ->route('account.year-end-closing.index', ['year' => $year])
                ->with('success', __("Year-end closing for FY :year completed successfully. Journal #:number has been posted.", [
                    'year'   => $year,
                    'number' => $journal->journal_number ?? $journal->id,
                ]));
        } catch (\Exception $e) {
            return redirect()
                ->route('account.year-end-closing.index', ['year' => $year])
                ->with('error', $e->getMessage());
        }
    }
}
