<?php 

namespace App\Http\Controllers;

use App\Models\Manuals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ManualsController extends Controller
{
    /**
     * Display a listing of all manuals.
     * Enhances each manual by attaching a human-readable route title from the menu config.
     */
    public function index()
    {
        $manuals = Manuals::all();

        // Load menu config for route mapping
        $menu = config('menu.admin');

        // Flatten nested routes and map each route to its display name
        $routeMap = collect($menu)->flatMap(function ($item) {
            $map = [$item['route'] => $item['name']];
            foreach ($item['children'] ?? [] as $child) {
                $map[$child['route']] = $child['name'];
            }
            return $map;
        });

        // Append route title to each manual for UI display
        $manuals->transform(function ($manual) use ($routeMap) {
            $manual->route_title = $routeMap[$manual->route] ?? $manual->route;
            return $manual;
        });

        // Render the manuals list view
        return Inertia::render('Manual/ManualList', [
            'manuals' => $manuals
        ]);
    }

    /**
     * Fetch a manual by its route name.
     * Returns the manual as JSON (used in modal or dynamic fetching).
     */
    public function showByRoute($route)
    {
        $manual = Manuals::where('route', $route)->first();

        if (!$manual) {
            return response()->json(['message' => 'Manual not found'], 404);
        }

        return response()->json($manual);
    }

    /**
     * Render the form for creating a new manual.
     */
    public function create(Request $request)
    {
        return Inertia::render('Manual/CreateManual');
    }

    /**
     * Show an individual manual (used in a modal).
     * Enhances it with route title from the config.
     */
    public function show(Manuals $manual)
    {
        $menu = config('menu.admin');

        // Map route => title
        $routeMap = collect($menu)->flatMap(function ($item) {
            $map = [$item['route'] => $item['name']];
            foreach ($item['children'] ?? [] as $child) {
                $map[$child['route']] = $child['name'];
            }
            return $map;
        });

        // Add route title to this manual
        $manual->route_title = $routeMap[$manual->route] ?? $manual->route;

        // Render modal view
        return Inertia::render('Manuals/ViewManualModal', [
            'manual' => $manual,
        ]);
    }

    /**
     * Store a new manual.
     * Validates route against routes defined in menu config to ensure consistency.
     */
    public function store(Request $request)
    {
        // Extract all defined routes from config/menu.php
        $menu = config('menu');
        $allRoutes = [];

        // Recursively collect route names
        $collectRoutes = function ($items) use (&$collectRoutes, &$allRoutes) {
            foreach ($items as $item) {
                if (!empty($item['route'])) {
                    $allRoutes[] = $item['route'];
                }

                if (!empty($item['children'])) {
                    $collectRoutes($item['children']);
                }
            }
        };

        if (isset($menu['admin'])) {
            $collectRoutes($menu['admin']);
        }

        // Validate request
        $request->validate([
            'title' => 'required',
            'route' => ['required', Rule::in($allRoutes)],
            'content' => 'required',
        ]);

        // Save manual
        Manuals::create($request->all());

        return back()->with('success', 'Instructions added successfully');
    }

    /**
     * Retrieve available routes from menu config for dropdown or selection.
     * Returns routes in [title, route] format as JSON.
     */
    public function getAvailableRoutes()
    {
        try {
            $menu = Config::get('menu');
            $routes = [];

            // Recursively extract all routes
            $collectRoutes = function ($items) use (&$collectRoutes, &$routes) {
                foreach ($items as $item) {
                    if (!empty($item['route'])) {
                        $routes[] = [
                            'title' => $item['name'],
                            'route' => $item['route'],
                        ];
                    }

                    if (!empty($item['children'])) {
                        $collectRoutes($item['children']);
                    }
                }
            };

            if (isset($menu['admin'])) {
                $collectRoutes($menu['admin']);
            }

            return response()->json($routes);
        } catch (\Throwable $e) {
            \Log::error('Error fetching routes: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get routes'], 500);
        }
    }

    /**
     * Show the form to edit an existing manual.
     */
    public function edit(Manuals $manual)
    {
        return Inertia::render('Manual/CreateManual', [
            'manual' => $manual,
        ]);
    }

    /**
     * Update a manual with new data.
     */
    public function update(Request $request, Manuals $manual)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'route' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $manual->update($validated);

        return redirect()->route('manuals.index')->with('success', 'Instructions updated successfully');
    }

    /**
     * Delete a manual from storage.
     */
    public function destroy(Manuals $manual)
    {
        if (!$manual) {
            return back()->withErrors('Manual Instructions not found.');
        }

        $manual->delete();

        return back()->with('success', 'Instructions deleted successfully.');
    }
}
