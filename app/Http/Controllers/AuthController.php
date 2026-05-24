<?php

namespace App\Http\Controllers;

use App\Exceptions\BackofficeApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->has('bo_user')) {
            return redirect()->route('dashboard');
        }

        return view('pages.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        return $this->loginWithApi($request);
    }

    public function showPasswordSetup()
    {
        if (session()->has('bo_user')) {
            return redirect()->route('dashboard');
        }

        // The single-use setup token is held only in the session for the
        // duration of this short-lived flow (spec §3.1a). No token, no screen.
        if (! session()->has('bo_password_setup_token')) {
            return redirect()->route('login');
        }

        return view('pages.password-setup');
    }

    public function passwordSetup(Request $request)
    {
        $request->validate([
            'new_password' => 'required|min:8|max:128|confirmed',
        ], [
            'new_password.confirmed' => 'The password confirmation does not match.',
        ]);

        $setupToken = session('bo_password_setup_token');

        if (! is_string($setupToken) || $setupToken === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your password setup session has expired. Please sign in again.']);
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('komopay.base_url'), '/'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('komopay.timeout', 15))
                ->withToken($setupToken)
                ->post('/api/v1/auth/backoffice/password-setup', [
                    'newPassword' => $request->new_password,
                ]);
        } catch (ConnectionException) {
            return back()
                ->withErrors(['new_password' => 'Could not reach the Backoffice service. Please try again.']);
        }

        if ($response->failed()) {
            $error = BackofficeApiException::fromResponse($response);

            // A 401 means the setup token is invalid, expired, or already used —
            // the token is single-use, so there is no point keeping it. Send the
            // user back to login to obtain a fresh setup token.
            if ($response->status() === 401) {
                session()->forget('bo_password_setup_token');

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your password setup link has expired or was already used. Please sign in again.']);
            }

            return back()->withErrors(['new_password' => $error->userMessage()]);
        }

        // 204 No Content: setup complete. The token is now consumed; discard it
        // and have the user sign in normally with the new password (branch A).
        session()->forget('bo_password_setup_token');

        return redirect()->route('login')
            ->with('status', 'Your password has been set. Please sign in with your new password.');
    }

    public function logout()
    {
        if (session()->has('bo_access_token')) {
            try {
                Http::baseUrl(rtrim((string) config('komopay.base_url'), '/'))
                    ->timeout((int) config('komopay.timeout', 15))
                    ->withToken((string) session('bo_access_token'))
                    ->post('/api/v1/auth/backoffice/logout');
            } catch (\Throwable) {
                // Local logout must still complete if the upstream service is unavailable.
            }
        }

        session()->forget(['bo_user', 'bo_access_token', 'bo_refresh_token', 'bo_token_expires_at', 'bo_password_setup_token']);

        return redirect()->route('login');
    }

    private function loginWithApi(Request $request)
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('komopay.base_url'), '/'))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('komopay.timeout', 15))
                ->post('/api/v1/auth/backoffice/login', [
                    'email' => $request->email,
                    'password' => $request->password,
                ]);
        } catch (ConnectionException) {
            return back()
                ->withErrors(['email' => 'Could not reach the Backoffice service. Please try again.'])
                ->withInput($request->except('password'));
        }

        if ($response->failed()) {
            $error = BackofficeApiException::fromResponse($response);

            return back()
                ->withErrors(['email' => $error->userMessage()])
                ->withInput($request->except('password'));
        }

        $body = $response->json();
        $data = is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);

        // Branch B (spec §3.1a): the account still holds its temporary activation
        // password and must set a final one before a session is issued. No tokens
        // are present here — stash the single-use setup token and route to setup.
        if (($data['passwordSetupRequired'] ?? false) === true) {
            $setupToken = $data['passwordSetupToken'] ?? null;

            if (! is_string($setupToken) || $setupToken === '') {
                return back()
                    ->withErrors(['email' => 'The Backoffice service returned an unexpected response.'])
                    ->withInput($request->except('password'));
            }

            session(['bo_password_setup_token' => $setupToken]);

            return redirect()->route('password-setup');
        }

        // Branch A: full session. Tokens live under `data.tokens` in the new
        // envelope; fall back to the flat shape for forward/backward safety.
        $tokens = is_array($data['tokens'] ?? null) ? $data['tokens'] : $data;

        $accessToken = $tokens['accessToken'] ?? null;
        $refreshToken = $tokens['refreshToken'] ?? null;
        $expiresAt = $tokens['accessTokenExpiresAt'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            return back()
                ->withErrors(['email' => 'The Backoffice service returned an unexpected response.'])
                ->withInput($request->except('password'));
        }

        $claims = $this->decodeJwtClaims($accessToken);
        $email = is_string($claims['email'] ?? null) ? $claims['email'] : $request->email;
        $fullName = is_string($claims['name'] ?? null) ? $claims['name'] : $email;
        $role = is_string($claims['brole'] ?? null) ? $claims['brole'] : '';
        $permissions = is_array($claims['perms'] ?? null) ? $claims['perms'] : [];

        session([
            'bo_access_token' => $accessToken,
            'bo_refresh_token' => $refreshToken,
            'bo_token_expires_at' => $expiresAt,
            'bo_user' => [
                'id' => is_string($claims['sub'] ?? null) ? $claims['sub'] : '',
                'email' => $email,
                'fullName' => $fullName,
                'role' => $role,
                'permissions' => $permissions,
            ],
        ]);

        return redirect()->route('dashboard');
    }

    private function decodeJwtClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $payload = strtr($parts[1], '-_', '+/');
        $padded = str_pad($payload, strlen($payload) + (4 - strlen($payload) % 4) % 4, '=');
        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            return [];
        }

        $json = json_decode($decoded, true);

        return is_array($json) ? $json : [];
    }
}
