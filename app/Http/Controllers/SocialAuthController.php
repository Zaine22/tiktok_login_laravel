<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    // Redirect to TikTok
    public function redirectToTikTok()
    {
        return Socialite::driver('tiktok')->redirect();
    }

    // Handle TikTok callback
    public function handleTikTokCallback()
    {
        try {
            $user = Socialite::driver('tiktok')->user();

            // Extract user data
            $tiktokUserData = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'nickname' => $user->getNickname(),
                'email' => $user->getEmail(),
                'avatar' => $user->getAvatar(),
            ];

            // Process and store user data as needed
            // Example: Create or update user in the database

            return response()->json([
                'message' => 'TikTok login successful!',
                'user' => $tiktokUserData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'TikTok login failed!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
