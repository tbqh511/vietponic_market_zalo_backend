<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function zaloExchange(Request $request)
    {
        $request->validate([
            'zalo_access_token' => 'required|string',
        ]);

        $zaloToken = $request->input('zalo_access_token');

        $resp = Http::get(env('ZALO_VERIFY_URL', 'https://open.zalo.me/v2.0/me'), [
            'access_token' => $zaloToken,
        ]);

        if (! $resp->successful()) {
            return response()->json(['message' => 'Invalid Zalo token'], 401);
        }

        $data = $resp->json();
        $zaloId = $data['id'] ?? null;
        $name = $data['name'] ?? 'Zalo user';
        $email = $data['email'] ?? null;
        $avatar = $data['picture'] ?? null;

        if (! $zaloId) {
            return response()->json(['message' => 'Unable to fetch Zalo user id'], 400);
        }

        $user = User::firstOrCreate(
            ['zalo_id' => $zaloId],
            [
                'name' => $name,
                'email' => $email,
                'avatar' => $avatar,
                'password' => bcrypt(Str::random(16)),
            ]
        );

        try {
            $token = JWTAuth::fromUser($user);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Could not create token'], 500);
        }

        // store token in user's api_token field
        $user->api_token = $token;
        $user->save();

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) env('JWT_TTL', 3600),
        ]);
    }
}
