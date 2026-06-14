<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, AuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:20',
            'password' => 'required|string|max:255',
            'level'    => 'required_without:role|string|in:Kasir,Terapis,Gudang,Manajer',
            'role'     => 'required_without:level|string|in:Kasir,Terapis,Gudang,Manajer',
        ]);

        $level = $validated['level'] ?? $validated['role'];

        $user = User::where('username', $validated['username'])
            ->where('level', $level)
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Username, password, atau level salah.'], 401);
        }

        $token = $user->createToken('simkk-auth')->plainTextToken;

        $audit->log($user, 'login', 'user', (string) $user->id, null, ['ip' => $request->ip()]);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'           => $user->id,
                'username'     => $user->username,
                'nama_lengkap' => $user->nama_lengkap,
                'name'         => $user->nama_lengkap,
                'level'        => $user->level,
                'role'         => $user->level,
                'shift'        => $user->shift,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil logout.']);
    }
}
