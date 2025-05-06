<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvisorCommissionRate;
use Illuminate\Http\Request;

class AdvisorCommissionRateController extends Controller
{
    // Fetch the current commission rate for a given advisor
    public function show($userId)
    {
        // Retrieve the commission rate for a specific advisor
        $rate = AdvisorCommissionRate::firstOrNew(['user_id' => $userId]);
        return response()->json($rate);
    }

    // Store a new commission rate for an advisor
    public function store(Request $request)
    {
        // Validate the incoming request
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'rate'    => 'required|numeric|min:0',
        ]);

        // Create a new commission rate
        $rate = AdvisorCommissionRate::create($data);

        return response()->json($rate, 201);
    }

    // Update the commission rate for an advisor
    public function update(Request $request, $userId)
    {
        // Find the commission rate entry for the given advisor
        $rate = AdvisorCommissionRate::findOrFail($userId);

        // Validate the updated commission data
        $data = $request->validate([
            'rate' => 'required|numeric|min:0',
        ]);

        // Update the rate
        $rate->update($data);

        return response()->json($rate);
    }

    // Delete the commission rate for an advisor
    public function destroy($userId)
    {
        // Find and delete the commission rate entry
        AdvisorCommissionRate::findOrFail($userId)->delete();
        return response()->json(null, 204);
    }
}
