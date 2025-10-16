<?php

namespace App\Http\Controllers;

use App\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ImportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
       // $this->middleware(['permission:imports'])->only(['index', 'show']);

    }

    public function index()
    {
        $results = Import::filter(\request()->only('search'))
            ->orderBy('id', 'desc')
            ->paginate();
        return Inertia::render('Imports/Imports', [
            'filters' => \request()->all('search', 'active'),
            'results' => $results,
        ]);
    }

    public function downloadFailedFile(Import $import)
    {
        $filePath = storage_path('app/public/' . $import->failed_file_path);

        if (!file_exists($filePath)) {
            abort(404, 'File not found.');
        }

        return response()->download($filePath);
    }
}
