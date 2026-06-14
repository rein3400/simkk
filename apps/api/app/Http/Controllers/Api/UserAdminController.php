<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    private const LEVELS = ['Kasir', 'Terapis', 'Gudang', 'Manajer'];

    public function index(Request $request): JsonResponse
    {
        $q     = (string) $request->query('q', '');
        $level = $request->query('level');

        $query = User::query()->orderBy('nama_lengkap');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nama_lengkap', 'like', "%{$q}%")
                  ->orWhere('username', 'like', "%{$q}%");
            });
        }
        if (is_string($level) && in_array($level, self::LEVELS, true)) {
            $query->where('level', $level);
        }

        return response()->json($query->get()->map(fn (User $u) => $this->serialize($u)));
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate($this->baseRules(true));

        $user = User::create([
            'username'     => $data['username'],
            'password'     => $data['password'], // 'hashed' cast on the model
            'nama_lengkap' => $data['nama_lengkap'],
            'level'        => $data['level'],
            'shift'        => $data['shift'] ?? null,
        ]);

        $audit->log($request->user(), 'user.create', 'user', (string) $user->id, null, [
            'username'     => $user->username,
            'nama_lengkap' => $user->nama_lengkap,
            'level'        => $user->level,
            'shift'        => $user->shift,
        ]);

        return response()->json($this->serialize($user), 201);
    }

    public function show(int $user): JsonResponse
    {
        $row = User::findOrFail($user);
        return response()->json($this->serialize($row));
    }

    public function update(Request $request, int $user, AuditService $audit): JsonResponse
    {
        $row  = User::findOrFail($user);
        $old  = [
            'username'     => $row->username,
            'nama_lengkap' => $row->nama_lengkap,
            'level'        => $row->level,
            'shift'        => $row->shift,
        ];
        $data = $request->validate($this->baseRules(false, $row));

        $update = [];
        foreach (['username', 'nama_lengkap', 'level', 'shift'] as $f) {
            if (array_key_exists($f, $data)) {
                $update[$f] = $data[$f];
            }
        }
        if (!empty($data['password'])) {
            $update['password'] = $data['password']; // 'hashed' cast
            // P2 #5: password change invalidates any previously issued Sanctum tokens.
            // Otherwise the user could remain logged in on a stolen device after admin
            // resets the password.
            $row->tokens()->delete();
        }
        if (!empty($update)) {
            $row->update($update);
        }

        $audit->log($request->user(), 'user.update', 'user', (string) $row->id, $old, $data);

        return response()->json($this->serialize($row));
    }

    public function destroy(Request $request, int $user, AuditService $audit): JsonResponse
    {
        $row = User::findOrFail($user);

        // `transaksi` has no user_id column. The link from a user to a transaksi is
        // the idempotency_keys table (F-006) which records the user that POSTed
        // /transactions/pay. Refuse delete if this user has any.
        $owned = \DB::table('idempotency_keys')
            ->where('user_id', $row->id)
            ->where('endpoint', 'transactions.pay')
            ->count();
        if ($owned > 0) {
            return response()->json([
                'message' => "User '{$row->username}' tercatat membuat {$owned} transaksi. Tidak bisa dihapus; nonaktifkan akun sebagai gantinya.",
            ], 422);
        }

        // Revoke tokens first; cascade on FK is already there but be explicit.
        $row->tokens()->delete();
        $row->delete();

        $audit->log($request->user(), 'user.delete', 'user', (string) $row->id, [
            'username' => $row->username,
            'level'    => $row->level,
        ], null);

        return response()->json(['deleted' => true]);
    }

    private function baseRules(bool $isCreate, ?User $existing = null): array
    {
        $usernameRule = $isCreate
            ? ['required', 'string', 'max:20', Rule::unique('users', 'username')]
            : [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('users', 'username')->ignore($existing?->id),
            ];

        return [
            'username'     => $usernameRule,
            'password'     => array_merge(
                $isCreate ? ['required'] : ['sometimes', 'nullable'],
                ['string', 'min:8', 'max:255'],
            ),
            'nama_lengkap' => [$isCreate ? 'required' : 'sometimes', 'required', 'string', 'max:120'],
            'level'        => [$isCreate ? 'required' : 'sometimes', 'required', Rule::in(self::LEVELS)],
            'shift'        => [$isCreate ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:30'],
        ];
    }

    private function serialize(User $u): array
    {
        return [
            'id'           => $u->id,
            'username'     => $u->username,
            'nama_lengkap' => $u->nama_lengkap,
            'level'        => $u->level,
            'shift'        => $u->shift,
            'created_at'   => $u->created_at,
            'updated_at'   => $u->updated_at,
        ];
    }
}
