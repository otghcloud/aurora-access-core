<?php

namespace OTGH\AccessControl\Core\Http\Controllers\Admin\Management;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use OTGH\AccessControl\Core\Http\Controllers\Controller;
use OTGH\AccessControl\Core\Models\User;

class SystemUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('name')->paginate(20);

        if ($this->sanctumTokensTableExists()) {
            $users = User::query()
                ->withCount('tokens')
                ->orderBy('name')
                ->paginate(20);
        } else {
            $users->getCollection()->transform(function (User $user): User {
                $user->setAttribute('tokens_count', 0);

                return $user;
            });
        }

        return view('admin.management.users.index', [
            'users' => $users,
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
            'tokens' => $this->sanctumTokensTableExists()
                ? $user->tokens()->latest('id')->get()
                : collect(),
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

        if ($this->sanctumTokensTableExists()) {
            $user->tokens()->delete();
        }

        $user->delete();

        return redirect()->route('admin.system-users.index')
            ->with('status', 'System user deleted successfully.');
    }

    public function storeToken(Request $request, User $user): RedirectResponse
    {
        if (! $this->sanctumTokensTableExists()) {
            return redirect()->route('admin.system-users.edit', $user)
                ->withErrors(['Sanctum token storage is unavailable. Run database migrations and try again.']);
        }

        $validated = $request->validate([
            'token_name' => ['required', 'string', 'max:255'],
        ]);

        $token = $user->createToken($validated['token_name']);

        return redirect()->route('admin.system-users.edit', $user)
            ->with('status', 'API token created successfully. Save it now; it will not be shown again.')
            ->with('new_api_token', $token->plainTextToken);
    }

    public function updateToken(Request $request, User $user, PersonalAccessToken $token): RedirectResponse
    {
        if (! $this->sanctumTokensTableExists()) {
            return redirect()->route('admin.system-users.edit', $user)
                ->withErrors(['Sanctum token storage is unavailable. Run database migrations and try again.']);
        }

        if ((int) $token->tokenable_id !== (int) $user->id || $token->tokenable_type !== User::class) {
            abort(404);
        }

        $validated = $request->validate([
            'token_name' => ['required', 'string', 'max:255'],
        ]);

        $token->update(['name' => $validated['token_name']]);

        return redirect()->route('admin.system-users.edit', $user)
            ->with('status', 'API token updated successfully.');
    }

    public function destroyToken(User $user, PersonalAccessToken $token): RedirectResponse
    {
        if (! $this->sanctumTokensTableExists()) {
            return redirect()->route('admin.system-users.edit', $user)
                ->withErrors(['Sanctum token storage is unavailable. Run database migrations and try again.']);
        }

        if ((int) $token->tokenable_id !== (int) $user->id || $token->tokenable_type !== User::class) {
            abort(404);
        }

        $token->delete();

        return redirect()->route('admin.system-users.edit', $user)
            ->with('status', 'API token revoked successfully.');
    }

    private function sanctumTokensTableExists(): bool
    {
        return Schema::hasTable('personal_access_tokens');
    }
}
