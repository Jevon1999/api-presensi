<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Member;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    /**
     * @OA\Post(
     *     path="/api/register",
     *     tags={"Authentication"},
     *     summary="Register a new user account",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registration successful"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'user',
            'is_active' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'member' => null,
        ], 201);
    }

/**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Authentication"},
     *     summary="Login admin/user",
     *     description="Login untuk mendapatkan Bearer token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@test.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login berhasil"),
     *             @OA\Property(property="access_token", type="string", example="1|abcdefghijklmnop"),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
                ]);
    }


    //cek appakah user aktif
    if (!$user->is_active) {
        return response()->json([
            'message' => 'Akun Anda tidak aktif. Hubungi administrator.'
        ], 403);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    // Load member data for role-based routing
    $member = null;
    if ($user->role === 'user') {
        $memberRecord = Member::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->with('office:id,name')
            ->first();
        if ($memberRecord) {
            $member = [
                'id' => $memberRecord->id,
                'nama_lengkap' => $memberRecord->nama_lengkap,
                'status' => $memberRecord->status,
                'status_aktif' => $memberRecord->status_aktif,
                'office' => $memberRecord->office,
            ];
        }
    }

    return response()->json([
        'message' => 'Login berhasil',
        'access_token' => $token,
        'token_type' => 'Bearer',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ],
        'member' => $member,
    ]);
    }


    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Authentication"},
     *     summary="Logout current session",
     *     description="Revoke the current access token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logout berhasil.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     tags={"Authentication"},
     *     summary="Get current user information",
     *     description="Returns the authenticated user's details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $member = null;

        if ($user->role === 'user') {
            $memberRecord = Member::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->with('office:id,name')
                ->first();
            if ($memberRecord) {
                $member = [
                    'id' => $memberRecord->id,
                    'nama_lengkap' => $memberRecord->nama_lengkap,
                    'status' => $memberRecord->status,
                    'status_aktif' => $memberRecord->status_aktif,
                    'office' => $memberRecord->office,
                ];
            }
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'member' => $member,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout-all",
     *     tags={"Authentication"},
     *     summary="Logout from all devices",
     *     description="Revoke all access tokens for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All sessions logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logout dari semua device berhasil.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function revokeTokens(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout dari semua device berhasil.'
        ]);
    }

}   

