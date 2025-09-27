<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = User::where('email', $request->email)->first();

            if (! $user || !Hash::check($request->password, $user->password)) {
                Log::debug('Login gagal: email atau password salah', [
                    'email_input' => $request->email,
                    'user_found' => $user ? true : false,
                ]);
                throw ValidationException::withMessages([
                    'email' => ['Email atau kata sandi salah.'],
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Login berhasil', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Login berhasil',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'user' => $user
                ]
            ]);
        } catch (ValidationException $e) {
            Log::debug('Login gagal: validasi error', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Login gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error login: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat login',
            ], 500);
        }
    }

     public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout',
            'data' => null,
        ]);
    }

    public function me(Request $r)
    {
        return response()->json([
            'user'      => $r->user()->only(['id','name','email','role']),
            'abilities' => optional($r->user()->currentAccessToken())->abilities ?? [],
        ]);
    }

}
