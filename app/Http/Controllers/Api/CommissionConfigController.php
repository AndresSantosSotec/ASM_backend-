<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionConfig;
use Illuminate\Http\Request;

class CommissionConfigController extends Controller
{
    public function index()
    {
        return CommissionConfig::latest()->firstOrFail();
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'base_rate'           => 'required|numeric|min:0',
            'bonus_threshold'     => 'required|integer|min:0',
            'bonus_rate'          => 'required|numeric|min:0',
            'period'              => 'required|in:monthly,quarterly',
            'respect_personalized' => 'required|boolean',
        ]);

        $config = CommissionConfig::create($data);

        return response()->json($config, 201);
    }

    public function show($id)
    {
        $config = CommissionConfig::findOrFail($id);
        return response()->json($config);
    }

    // CommissionConfigController.php
    public function update(Request $request)
    {
        $validated = $request->validate([
            'base_rate' => 'required|numeric',
            'bonus_threshold' => 'required|numeric',
            'bonus_rate' => 'required|numeric',
            'period' => 'required|in:monthly,quarterly',
        ]);

        // Assuming you have a CommissionConfig model to handle the settings
        $config = CommissionConfig::first(); // or create if doesn't exist
        $config->base_rate = $validated['base_rate'];
        $config->bonus_threshold = $validated['bonus_threshold'];
        $config->bonus_rate = $validated['bonus_rate'];
        $config->period = $validated['period'];
        $config->save();

        return response()->json($config);
    }


    public function destroy($id)
    {
        CommissionConfig::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
