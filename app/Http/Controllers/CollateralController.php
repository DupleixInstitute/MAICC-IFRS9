<?php
namespace App\Http\Controllers;

use App\Models\CollateralType;
use App\Models\CollateralRegister;
use App\Models\CollateralAllocation;
use App\Models\LoanBook;
use App\Imports\CollateralRegisterImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class CollateralController extends Controller
{
    /* ============================================================
       INERTIA PAGE ROUTES (UI Views)
    ============================================================ */

    /**
     * Render the Collateral Types management page
     */
    public function collateralType()
    {
        return Inertia::render('Collateral/Types', [
            'types' => CollateralType::paginate(15),
        ]);
    }
    

    /**
     * Render the Collateral Register Import page
     */
    public function importView()
    {
        return Inertia::render('Collateral/Import');
    }

    /**
     * Render the Auto Allocation page
     */
    public function allocateView()
    {
        return Inertia::render('Collateral/Components/Allocate');
    }

    /**
     * Render Collateral Allocations index page
     */
    public function indexAllocations()
    {
        $allocations = CollateralAllocation::with('collateral')->latest()->paginate(20);

        return Inertia::render('Collateral/Index', [
            'allocations' => $allocations,
        ]);
    }

    /* ============================================================
       LOGIC / API ENDPOINTS
    ============================================================ */

    public function store(Request $request)
    {
        $request->validate([
            'type_code' => 'required|unique:collateral_types,type_code',
            'type_name' => 'required|string|max:255',
            'standard_haircut' => 'required|numeric|min:0|max:100',
            'description' => 'nullable|string',
        ]);

        CollateralType::create($request->only(['type_code', 'type_name', 'standard_haircut', 'description']));

        return back()->with('success', 'Collateral type added successfully.');
    }

    public function importCollateralRegister(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:10240',
        ]);

        try {
            Excel::import(new CollateralRegisterImport, $request->file('file'));
            return back()->with('success', 'Collateral register imported successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function allocateAutomatically(Request $request)
    {
        $request->validate([
            'collateral_register_id' => 'required|exists:collateral_registers,id',
            'allocation_basis' => 'required|string|in:proportional,descending,ascending,equal',
        ]);

        $collateral = CollateralRegister::findOrFail($request->collateral_register_id);
        $haircut = optional(
            CollateralType::where('type_code', $collateral->collateral_type)->first()
        )->standard_haircut ?? 20;

        $haircutFactor = 1 - ($haircut / 100);
        $loans = LoanBook::query()->where('carrying_amount', '>', 0)->get();

        if ($loans->isEmpty()) {
            return response()->json(['message' => 'No loans found to allocate collateral.'], 404);
        }

       $totalExposure = $loans->sum('carrying_amount');
        $available = $collateral->execution_value;
        $remainingLoans = $loans->count();

        foreach ($loans as $loan) {
            if ($available <= 0) break;

            $share = match ($request->allocation_basis) {
                // split the remaining available equally across the remaining loans
                'equal' => $available / max(1, $remainingLoans),
                default => min(($loan->carrying_amount / $totalExposure) * $collateral->execution_value, $available),
            };

            $discounted = $share * $haircutFactor;
            $coverage = $loan->carrying_amount > 0 ? $discounted / $loan->carrying_amount : 0;

            CollateralAllocation::create([
                'reporting_year' => now()->year,
                'reporting_month' => now()->month,
                'collateral_register_id' => $collateral->id,
                'customer_id' => $loan->customer_id,
                'customer_name' => $loan->name,
                'contract_id' => $loan->id, // or your actual contract/loan ID field
                'total_customer_exposure' => $loan->carrying_amount,
                'allocated_collateral' => $share,
                'allocation_percentage' => round(($share / $collateral->execution_value) * 100, 2),
                'discounted_collateral' => $discounted,
                'coverage_ratio' => $coverage,
                'allocation_basis' => strtoupper($request->allocation_basis),
                'allocation_notes' => 'Auto allocated by system',
            ]);

            $available -= $share;
            $remainingLoans--;
        }

        return response()->json(['message' => 'Collateral automatically allocated successfully.']);
    }
}
