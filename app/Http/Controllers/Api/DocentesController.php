<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DocentesController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Docentes endpoint is under construction'], 501);
    }

    public function show($id)
    {
        return response()->json(['message' => 'Docentes endpoint is under construction'], 501);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Docentes endpoint is under construction'], 501);
    }

    public function dashboard()
    {
        return response()->json(['message' => 'Docentes dashboard is under construction'], 501);
    }
}
