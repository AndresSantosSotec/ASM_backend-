<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CollectionLog;
use Illuminate\Http\Request;

class CollectionLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CollectionLog::query();

        if ($request->filled('prospecto_id')) {
            $query->where('prospecto_id', $request->prospecto_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'prospecto_id'    => 'required|exists:prospectos,id',
            'date'            => 'required|date',
            'type'            => 'required|string',
            'notes'           => 'nullable|string',
            'agent'           => 'required|string',
            'next_contact_at' => 'nullable|date',
        ]);

        $log = CollectionLog::create($data);

        return response()->json(['data' => $log], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $log = CollectionLog::findOrFail($id);
        return response()->json(['data' => $log]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $log = CollectionLog::findOrFail($id);

        $data = $request->validate([
            'prospecto_id'    => 'sometimes|exists:prospectos,id',
            'date'            => 'sometimes|date',
            'type'            => 'sometimes|string',
            'notes'           => 'nullable|string',
            'agent'           => 'sometimes|string',
            'next_contact_at' => 'nullable|date',
        ]);

        $log->update($data);

        return response()->json(['data' => $log]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        CollectionLog::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
