<?php

namespace App\Http\Controllers;

use App\Exceptions\BackofficeApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Backoffice TOTP MFA (spec §3.1b).
 *
 * Two distinct entry points share the same backend enrollment endpoints:
 *
 *  - Mandatory enrollment (login branch C). Reached mid-login with no session,
 *    gated by the single-use `bo_mfa_enrollment_token` stashed by AuthController.
 *  - Voluntary management. Reached from the in-app security page by an already
 *    authenticated user (OPERATOR / SUPERVISOR / COMPLIANCE), using their
 *    normal `bo_access_token`.
 *
 * Both setup and confirm accept either bearer. Revoke requires a full session.
 */
class MfaController extends Controller
{
    /** Roles for which MFA is mandatory and therefore cannot be revoked (spec §3.1b). */
    private const MANDATORY_ROLES = ['ADMIN', 'SUPER_ADMIN'];

    // --- Mandatory enrollment during login (branch C) ------------------------

    public function showEnroll()
    {
        if (session()->has('bo_user')) {
            // Already authenticated: voluntary management lives on the security page.
            return redirect()->route('security');
        }

        if (! session()->has('bo_mfa_enrollment_token')) {
            return redirect()->route('login');
        }

        return $this->renderSetupScreen(
            bearer: (string) session('bo_mfa_enrollment_token'),
            view: 'pages.mfa-enroll',
            onTokenRejected: function () {
                session()->forget('bo_mfa_enrollment_token');

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your enrollment session has expired. Please sign in again.']);
            },
        );
    }

    public function confirmEnroll(Request $request)
    {
        $this->validateCode($request);

        if (! session()->has('bo_mfa_enrollment_token')) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your enrollment session has expired. Please sign in again.']);
        }

        $bearer = (string) session('bo_mfa_enrollment_token');

        $result = $this->postConfirm($bearer, (string) $request->code);

        if ($result instanceof BackofficeApiException) {
            // 401 here means the enrollment token is spent/expired — restart login.
            if ($result->status === 401 && $result->errorCode !== 'MFA_INVALID') {
                session()->forget('bo_mfa_enrollment_token');

                return redirect()->route('login')
                    ->withErrors(['email' => 'Your enrollment session has expired. Please sign in again.']);
            }

            // Recoverable wrong/expired code: re-prompt for a code WITHOUT calling
            // setup again — the pending secret the user scanned is still valid.
            return $this->retryConfirmView('pages.mfa-enroll-retry', $this->confirmErrorMessage($result));
        }

        if ($result !== true) {
            return $this->retryConfirmView('pages.mfa-enroll-retry', $result); // connection error string
        }

        // Enrollment complete (204). The single-use token is consumed; the user
        // now signs in normally and reaches the TOTP challenge (branch D).
        session()->forget('bo_mfa_enrollment_token');

        return redirect()->route('login')
            ->with('status', 'Two-factor authentication is enabled. Sign in and enter a code from your app.');
    }

    // --- Voluntary management for authenticated users ------------------------

    public function security()
    {
        return view('pages.security', [
            'mfaMandatory' => $this->roleRequiresMfa(),
        ]);
    }

    public function showSetup()
    {
        return $this->renderSetupScreen(
            bearer: (string) session('bo_access_token'),
            view: 'pages.mfa-setup',
            onTokenRejected: fn () => redirect()->route('login'),
        );
    }

    public function confirmSetup(Request $request)
    {
        $this->validateCode($request);

        $result = $this->postConfirm((string) session('bo_access_token'), (string) $request->code);

        if ($result instanceof BackofficeApiException) {
            if ($result->status === 401 && $result->errorCode !== 'MFA_INVALID') {
                return redirect()->route('login');
            }

            return $this->retryConfirmView('pages.mfa-setup-retry', $this->confirmErrorMessage($result));
        }

        if ($result !== true) {
            return $this->retryConfirmView('pages.mfa-setup-retry', $result);
        }

        return redirect()->route('security')
            ->with('status', 'Two-factor authentication is now enabled on your account.');
    }

    public function revoke(Request $request)
    {
        $this->validateCode($request);

        // Defensive UI guard; the backend is authoritative and also rejects this
        // for mandatory roles with 401 FORBIDDEN (spec §3.1b).
        if ($this->roleRequiresMfa()) {
            return back()->withErrors(['code' => 'Two-factor authentication is mandatory for your role and cannot be disabled.']);
        }

        try {
            $response = $this->client()
                ->withToken((string) session('bo_access_token'))
                ->delete('/api/v1/auth/backoffice/totp-setup', [
                    'code' => $request->code,
                ]);
        } catch (ConnectionException) {
            return back()->withErrors(['code' => 'Could not reach the Backoffice service. Please try again.']);
        }

        if ($response->failed()) {
            $error = BackofficeApiException::fromResponse($response);

            if ($response->status() === 401 && $error->errorCode === 'FORBIDDEN') {
                return back()->withErrors(['code' => 'Two-factor authentication is mandatory for your role and cannot be disabled.']);
            }

            if ($response->status() === 401 && $error->errorCode === 'MFA_INVALID') {
                return back()->withErrors(['code' => 'That code was incorrect. Please try again.']);
            }

            if ($response->status() === 401) {
                return redirect()->route('login');
            }

            return back()->withErrors(['code' => $error->userMessage()]);
        }

        return redirect()->route('security')
            ->with('status', 'Two-factor authentication has been disabled.');
    }

    // --- Shared helpers ------------------------------------------------------

    /**
     * Call POST /totp-setup with the given bearer and render the QR/secret screen.
     * On a 401 (token rejected) the caller decides where to send the user.
     */
    private function renderSetupScreen(string $bearer, string $view, \Closure $onTokenRejected)
    {
        if ($bearer === '') {
            return $onTokenRejected();
        }

        try {
            $response = $this->client()
                ->withToken($bearer)
                ->post('/api/v1/auth/backoffice/totp-setup');
        } catch (ConnectionException) {
            return redirect()->route(session()->has('bo_user') ? 'security' : 'login')
                ->withErrors(['code' => 'Could not reach the Backoffice service. Please try again.']);
        }

        if ($response->failed()) {
            if ($response->status() === 401) {
                return $onTokenRejected();
            }

            $error = BackofficeApiException::fromResponse($response);

            return redirect()->route(session()->has('bo_user') ? 'security' : 'login')
                ->withErrors(['code' => $error->userMessage()]);
        }

        $body = $response->json();
        $data = is_array($body['data'] ?? null) ? $body['data'] : (is_array($body) ? $body : []);

        $secret = is_string($data['secret'] ?? null) ? $data['secret'] : '';
        $qrUri = is_string($data['qrUri'] ?? null) ? $data['qrUri'] : '';

        if ($secret === '' || $qrUri === '') {
            return redirect()->route(session()->has('bo_user') ? 'security' : 'login')
                ->withErrors(['code' => 'The Backoffice service returned an unexpected response.']);
        }

        // The secret/qrUri are credentials (spec §3.1b): pass them straight to the
        // view for this single render and never persist them in the session.
        return view($view, compact('secret', 'qrUri'));
    }

    /**
     * POST /totp-confirm. Returns true on 204, a BackofficeApiException on an
     * API error, or a string on a connection failure.
     */
    private function postConfirm(string $bearer, string $code): bool|BackofficeApiException|string
    {
        if ($bearer === '') {
            return 'Your session has expired. Please sign in again.';
        }

        try {
            $response = $this->client()
                ->withToken($bearer)
                ->post('/api/v1/auth/backoffice/totp-confirm', ['code' => $code]);
        } catch (ConnectionException) {
            return 'Could not reach the Backoffice service. Please try again.';
        }

        if ($response->failed()) {
            return BackofficeApiException::fromResponse($response);
        }

        return true;
    }

    /**
     * Re-render a code-only confirm screen with an error, without redirecting
     * back (which would re-run setup and rotate the pending secret the user
     * already scanned). The error is injected into a fresh view error bag so the
     * shared `$errors->first()` rendering works on a direct view response.
     */
    private function retryConfirmView(string $view, string $message)
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['code' => [$message]]));

        return response()->view($view, ['errors' => $errors]);
    }

    private function confirmErrorMessage(BackofficeApiException $error): string
    {
        // A wrong/expired confirm code surfaces as 401 MFA_INVALID (spec §3.1b).
        if ($error->status === 401 && $error->errorCode === 'MFA_INVALID') {
            return 'That code was incorrect or has expired. Scan the QR again and enter a fresh code.';
        }

        return $error->userMessage();
    }

    private function validateCode(Request $request): void
    {
        $request->validate([
            'code' => ['required', 'regex:/^[0-9]{6}$/'],
        ], [
            'code.regex' => 'Enter the 6-digit code from your authenticator app.',
        ]);
    }

    private function roleRequiresMfa(): bool
    {
        $role = (string) session('bo_user.role', '');

        return in_array($role, self::MANDATORY_ROLES, true);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('komopay.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('komopay.timeout', 15));
    }
}
