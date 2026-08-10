<?php

namespace App\Http\Controllers\Admin\Access;

use App\Http\Controllers\Controller;
use App\Models\Access\Individual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessUserController extends Controller
{
    public function index(): View
    {
        return view('admin.access.users.index', [
            'accessUsers' => Individual::withCount('cards')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.access.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Individual::create($validated);

        return redirect()->route('admin.access-users.index')->with('status', 'Access user created successfully.');
    }

    public function edit(Individual $user): View
    {
        return view('admin.access.users.edit', [
            'accessUser' => $user,
        ]);
    }

    public function update(Request $request, Individual $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return redirect()->route('admin.access-users.index')->with('status', 'Access user updated successfully.');
    }

    public function destroy(Individual $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.access-users.index')->with('status', 'Access user deleted successfully.');
    }
}
