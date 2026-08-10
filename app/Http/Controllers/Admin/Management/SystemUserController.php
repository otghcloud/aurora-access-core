<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class SystemUserController extends Controller
{
    public function index(): View
    {
        return view('admin.management.users.index', [
            'users' => User::query()
                ->withCount('tokens')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.management.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create($validated);

        return redirect()->route('admin.system-users.index')
            ->with('status', 'System user created successfully.');
    }

    public function edit(User $user): View
    {
        return view('admin.management.users.edit', [
            'systemUser' => $user,
            'tokens' => $user->tokens()->latest('id')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (($validated['password'] ?? null) === null || $validated['password'] === '') {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('admin.system-users.edit', $user)
            ->with('status', 'System user updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ((int) Auth::id() === (int) $user->id) {
            return redirect()->route('admin.system-users.index')
                ->withErrors(['You cannot delete your own account while logged in.']);
        }

        $user->delete();

        return redirect()->route('admin.system-users.index')
            ->with('status', 'System user deleted successfully.');
    }

    public function storeToken(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'token_name' => ['required', 'string', 'max:255'],
        ]);

        $token = $user->createToken($validated['token_name']);

        return redirect()->route('admin.system-users.edit', $user)
            ->with('status', 'API token created successfully. Save it now; it will not be shown again.')
            ->with('new_api_token', $token->plainTextToken);
    }

    public function destroyToken(User $user, PersonalAccessToken $token): RedirectResponse
    {
        if ((int) $token->tokenable_id !== (int) $user->id || $token->tokenable_type !== User::class) {
            abort(404);
        }

        $token->delete();

        return redirect()->route('admin.system-users.edit', $user)
            ->with('status', 'API token revoked successfully.');
    }
}
