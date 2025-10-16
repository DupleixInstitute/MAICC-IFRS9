<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExcelExportController extends Controller
{
    public function export(Request $request)
    {
        return 'response()->json($request->all());';
        dd($request->all());
        $applications = json_decode($request->get('applications'), true);
        dd($applications);
        // Handle export logic using $applications data
    }
}
