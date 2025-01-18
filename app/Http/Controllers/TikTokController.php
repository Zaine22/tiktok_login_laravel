<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use TikTok\Authentication\Authentication;
use Laravel\Pail\ValueObjects\Origin\Http;

class TikTokController extends Controller
{
    public function tiktoklogin()
    {
        $authentication = new Authentication([          // instantiate authentication
            'client_key'    => env('TIKTOK_CLIENT_ID'),     // client key from your app
            'client_secret' => env('TIKTOK_CLIENT_SECRET'), // client secret from your app
        ]);

// uri TikTok will send the user to after they login that must match what you have in your app dashboard
        // $redirectUri = 'https://path/to/tiktok/login/redirect.php';
        $redirectUri = env('TIKTOK_REDIRECT_URI');

        $scopes = [ // a list of approved scopes by tiktok for your app
            'user.info.basic',
            'user.info.profile',
            'user.info.stats',
            'video.publish',
            'video.upload',
            'video.list',
        ];

        $authenticationUrl = $authentication->getAuthenticationUrl($redirectUri, $scopes);

        return redirect($authenticationUrl);
    }

    public function tiktokcallback(Request $request)
    {
        try {
            // Get the code from the URL query parameter
            $code = $request->query('code');

            // Instantiate the Authentication class again for token exchange
            $authentication = new Authentication([
                'client_key'    => env('TIKTOK_CLIENT_ID'),
                'client_secret' => env('TIKTOK_CLIENT_SECRET'),
            ]);

            $redirectUri = env('TIKTOK_REDIRECT_URI');

            // Exchange the authorization code for an access token
            $tokenFromCode = $authentication->getAccessTokenFromCode($code, $redirectUri);

            $accessToken  = $tokenFromCode['access_token'];
            $refreshToken = $tokenFromCode['refresh_token']; // Use this for refreshing the token later

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,username,avatar_url,display_name', // Specify the fields you need
            ]);

            $userData = $response->json();

            // Check if the user already exists by their TikTok provider ID
            $existingUserByProviderId = User::where('provider_id', $userData['data']['user']['open_id'])->first();

            if ($existingUserByProviderId) {
                // If the user exists, update the tokens
                $existingUserByProviderId->access_token  = $accessToken;
                $existingUserByProviderId->refresh_token = $refreshToken;

                // Optionally, update other user data like email or name if needed
                if (! isset($userData['data']['user']['is_private_email']) && $userData['data']['user']['open_id']) {
                    $existingUserByProviderId->provider_id = $userData['data']['user']['open_id'];
                }

                $existingUserByProviderId->save();

                // Log the user in
                auth()->login($existingUserByProviderId, true);
            } else {
                // If the user does not exist, create a new user
                $newUser                    = new User();
                $newUser->provider_id       = $userData['data']['user']['open_id'];
                $newUser->provider          = 'tiktok';
                $newUser->access_token      = $accessToken;
                $newUser->refresh_token     = $refreshToken;
                $newUser->name              = $userData['data']['user']['display_name'] ?? 'TikTok User';
                $newUser->email             = $userData['data']['user']['email'] ?? null;
                $newUser->email_verified_at = now(); // Set the email verified timestamp

                $newUser->save();

                // Log the new user in
                auth()->login($newUser, true);
            }

            // Handle cart migration if necessary (e.g., moving temp user cart data to logged-in user)
            if (session('temp_user_id') != null) {
                Cart::where('user_id', auth()->user()->id)->delete(); // Remove previous user cart data
                Cart::where('temp_user_id', session('temp_user_id'))
                    ->update(['user_id' => auth()->user()->id, 'temp_user_id' => null]);

                session()->forget('temp_user_id');
            }

            // Redirect user after login
            if (session('link') != null) {
                return redirect(session('link'));
            } else {
                if (auth()->user()->user_type == 'seller') {
                    return redirect()->route('seller.dashboard');
                }

                return redirect()->route('dashboard');
            }

        } catch (\Exception $e) {
            // Handle any errors gracefully
            flush('Something went wrong. Please try again.')->error();
            return redirect()->route('user.login');
        }
    }



    public function revokeAccessToken($accessToken)
    {
        $authentication = new Authentication([
            'client_key'    => env('TIKTOK_CLIENT_ID'),
            'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        ]);

        // Revoke the token
        $revokeToken = $authentication->revokeAccessToken($accessToken);

        return response()->json(['status' => 'Token revoked']);
    }

}