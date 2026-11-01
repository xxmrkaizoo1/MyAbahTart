<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Signs shop administrators in and out of the admin mobile app.
 */
class AuthController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These details don’t match our records.']);
        }

        if (! $user->isAdmin()) {
            throw ValidationException::withMessages(['email' => 'Only shop administrators can use this app.']);
        }

        return response()->json([
            'token' => ApiToken::issue($user, $validated['device_name'] ?? 'Admin app'),
            'user' => $this->userData($user),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    public function destroy(Request $request): JsonResponse
    {
        ApiToken::findByPlainToken($request->bearerToken())?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /**
     * @return array{id: int, name: string, email: string, initials: string}
     */
    private function userData(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'initials' => $user->initials()];
    }
}
