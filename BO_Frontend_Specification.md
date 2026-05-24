# Backoffice - Frontend Specification Document

**Version:** 1.2 | **Source:** KomoPay backend codebase analysis | **Date:** 2026-05-24
**Status:** Single source of truth. Do not call or display anything that is not listed here.

---

## Table of Contents

1. [Scope](#1-scope)
2. [HTTP Contract](#2-http-contract)
3. [Backoffice Authentication](#3-backoffice-authentication)
4. [Backoffice Capability Map](#4-backoffice-capability-map)
5. [Exhaustive API Mapping](#5-exhaustive-api-mapping)
6. [Request Schemas](#6-request-schemas)
7. [Response Schemas](#7-response-schemas)
8. [Approval Payload Schemas](#8-approval-payload-schemas)
9. [Enums](#9-enums)
10. [Permissions](#10-permissions)
11. [Operational Rules](#11-operational-rules)
12. [Evidence Index](#12-evidence-index)

---

## 1. Scope

This document describes only the APIs a Backoffice frontend can interact with:

- `POST /api/v1/auth/backoffice/*`
- every endpoint under `/api/v1/backoffice/*`, including service-provider endpoints implemented in the `servicepayment` module

Server-to-server callbacks, customer, agent, merchant, and terminal client APIs are outside this BO scope.

Total BO endpoints in scope: **169** (including the 4 shared `/api/v1/notifications/**` endpoints, [5.22](#522-notifications-in-app-inbox), the `/api/v1/auth/backoffice/password-setup` endpoint, [3.1a](#31a-first-login-password-setup), and the 4 MFA endpoints — `login/verify-mfa`, `totp-setup`, `totp-confirm`, `DELETE totp-setup`, [3.1b](#31b-mfa-totp)).

---

## 2. HTTP Contract

### 2.1 Base Rules

| Rule | Value |
|---|---|
| Body format | JSON unless explicitly stated otherwise |
| Currency | KMF |
| Date-time format | ISO-8601 string |
| Date format | ISO local date, `YYYY-MM-DD` |
| Auth header | `Authorization: Bearer <accessToken>` for every protected endpoint |
| Optional tracing header | `X-Correlation-Id: <uuid>`; backend generates one if absent and echoes it in the response |
| Rate limit | Every `/api/v1/auth/**` request is limited to 10 requests/minute/IP by default |

### 2.2 Success Envelopes

Most non-paginated success responses use:

```json
{
  "data": {},
  "timestamp": "2026-05-06T12:00:00Z"
}
```

Cursor-paginated or list-style endpoints using `PagedResponse` return:

```json
{
  "data": [],
  "pagination": {
    "nextCursor": "opaque-string-or-null",
    "hasMore": false,
    "limit": 20
  }
}
```

Cursor rules:

- `limit` defaults to `20`.
- Backend clamps `limit` to `1..100`.
- `cursor` is opaque; use `pagination.nextCursor` as-is.
- Some endpoints use `PagedResponse.last(...)`: `hasMore=false`, `nextCursor=null`, and `limit` equals the returned list size.

### 2.3 Error Envelopes

Controller and validation errors generally return:

```json
{
  "error": {
    "code": "VALIDATION_FIELD_REQUIRED",
    "message": "Request validation failed",
    "details": ["field: message"],
    "correlationId": "uuid",
    "timestamp": "2026-05-06T12:00:00Z"
  }
}
```

Security filter exceptions are special:

- `401` and `403` emitted by Spring Security return raw `ApiError`, without the outer `{ "error": ... }` wrapper.
- `429` from `RateLimitingFilter` returns the wrapped error envelope.

### 2.4 CSV Responses

These endpoints can return raw CSV when `format=csv`:

- `GET /api/v1/backoffice/reports/transactions/summary`
- `GET /api/v1/backoffice/reports/kyc/summary`
- `GET /api/v1/backoffice/reports/actors/summary`

CSV response headers:

- `Content-Type: text/csv; charset=UTF-8`
- `Content-Disposition: attachment; filename="<generated>.csv"`

---

## 3. Backoffice Authentication

### 3.1 Login

`POST /api/v1/auth/backoffice/login`

Request:

```json
{
  "email": "admin@komopay.km",
  "password": "plain-password"
}
```

Response `200 ApiResponse<BackofficeLoginResponse>`.

> **Breaking change (2026-05-24):** `/login` no longer returns a flat `TokenResponse`. It now returns a `BackofficeLoginResponse` envelope with **exactly one** of four branches populated, in this precedence order: password-setup, MFA-enrollment, MFA-challenge, full session. The client must inspect the boolean flags in that order and only read `tokens` when all three are `false`. Fields that are not part of the active branch are omitted from the JSON (`NON_NULL` serialization). `/refresh` is unchanged and still returns a flat `TokenResponse`.

**Branch A — full session** (normal login, account already set up):

```json
{
  "data": {
    "passwordSetupRequired": false,
    "tokens": {
      "tokenType": "Bearer",
      "accessToken": "jwt",
      "accessTokenExpiresAt": "2026-05-06T16:00:00Z",
      "refreshToken": "opaque-refresh-token",
      "refreshTokenExpiresAt": "2026-05-07T00:00:00Z"
    }
  },
  "timestamp": "2026-05-06T12:00:00Z"
}
```

**Branch B — mandatory first-login password setup** (account still holds its temporary activation password):

```json
{
  "data": {
    "passwordSetupRequired": true,
    "passwordSetupToken": "single-use-jwt",
    "passwordSetupTokenExpiresAt": "2026-05-06T12:15:00Z"
  },
  "timestamp": "2026-05-06T12:00:00Z"
}
```

In branch B **no session is issued**: there is no `accessToken` and no `refreshToken`. The `passwordSetupToken` is a short-lived (15 min), single-use bearer that may only be used to call `POST .../password-setup` ([3.1a](#31a-first-login-password-setup)). The frontend must route the user to a "set your password" screen instead of the dashboard. See the [recommended flow](#login-flow-frontend) below.

**Branch C — MFA enrollment required** (password verified; the role makes MFA mandatory — `ADMIN` / `SUPER_ADMIN` — but TOTP is not enrolled yet):

```json
{
  "data": {
    "mfaEnrollmentRequired": true,
    "mfaEnrollmentToken": "single-use-jwt",
    "mfaEnrollmentTokenExpiresAt": "2026-05-06T12:15:00Z"
  },
  "timestamp": "2026-05-06T12:00:00Z"
}
```

In branch C **no session is issued**. The `mfaEnrollmentToken` is a short-lived (15 min) bearer whose sole right is to call the MFA enrollment endpoints (`totp-setup` / `totp-confirm`, [3.1b](#31b-mfa-totp)). After the user confirms enrollment they log in again and reach branch D.

**Branch D — MFA challenge** (password verified and TOTP is enrolled):

```json
{
  "data": {
    "mfaRequired": true,
    "challengeId": "uuid",
    "mfaFactor": "TOTP"
  },
  "timestamp": "2026-05-06T12:00:00Z"
}
```

In branch D **no session is issued yet**. The client must collect the current 6-digit TOTP code and call `POST .../login/verify-mfa` with `challengeId` + `code` ([3.1b](#31b-mfa-totp)) to obtain the JWT pair. The challenge is single-use, expires after 5 minutes, and locks after 3 failed codes.

Token lifetimes from `application.yml`:

| Actor | Access TTL | Refresh TTL |
|---|---:|---:|
| Backoffice role below `ADMIN` | 8h | 12h |
| `ADMIN` and `SUPER_ADMIN` | 4h | 12h |

Login lockout:

- 3 failed passwords locks the user for 30 minutes.
- `CLOSED`, `SUSPENDED`, and currently `LOCKED` users cannot obtain tokens.

A wrong password in branch B (temporary password incorrect) returns the same `401 INVALID_CREDENTIALS` as a normal wrong password and counts toward the lockout — the setup branch is only reached once the temporary password is verified.

<a id="login-flow-frontend"></a>
**Recommended login flow (frontend):**

1. `POST /login` and parse `data` as `BackofficeLoginResponse`.
2. If `data.passwordSetupRequired === true`: store `data.passwordSetupToken` in memory (not persistent storage — it is single-use and short-lived) and navigate to the password-setup screen. Do **not** treat the user as authenticated; there is no session yet.
3. Else if `data.mfaEnrollmentRequired === true`: store `data.mfaEnrollmentToken` in memory and navigate to the "enroll your authenticator" screen ([3.1b](#31b-mfa-totp)). No session yet.
4. Else if `data.mfaRequired === true`: keep `data.challengeId` in memory and navigate to the "enter your 6-digit code" screen. Submit the code via `POST .../login/verify-mfa`; its response is a flat `TokenResponse`. No session until that call succeeds.
5. Otherwise: use `data.tokens` exactly as the old `TokenResponse` was used (store access/refresh, proceed to the app).

Process the four branches in the order above — they are mutually exclusive, and the boolean for an inactive branch may be absent (treat absent as `false`).

### 3.1a First-Login Password Setup

`POST /api/v1/auth/backoffice/password-setup`

Completes the mandatory first-login password setup. The account a Backoffice user is created with carries a **temporary activation password**; the user must replace it with a final password before a normal session is granted.

Headers: `Authorization: Bearer <passwordSetupToken>` — the single-use token from login branch B. A normal `ACCESS` token is rejected; the temporary password is **not** re-sent here (it was already proven at login).

Request (`BackofficePasswordSetupRequest`):

```json
{ "newPassword": "the-final-password" }
```

- `newPassword`: required, 8–128 chars.

Response: `204 No Content`. No session is issued — after success the user logs in normally with the new password (which then returns branch A).

Errors:

| Status | Code | When |
|---|---|---|
| `401` | `UNAUTHORIZED` | No bearer token presented. |
| `401` | `AUTH_INVALID_TOKEN` | Token is not a `PASSWORD_SETUP` token (e.g. a normal `ACCESS` token), or is expired/revoked. |
| `401` | — | The setup token has already been consumed (single-use: its JTI is revoked on first success). A replay is rejected. |
| `400` | `AUTH_PASSWORD_FORMAT` | `newPassword` shorter than 8 chars. |
| `400` | `VALIDATION_FIELD_REQUIRED` | `newPassword` missing/blank (bean validation). |
| `400` | `AUTH_PASSWORD_SETUP_ALREADY_DONE` | The account no longer requires setup (flag already cleared). |

The setup token is single-use: once a password is set, the token's JTI is revoked, so calling the endpoint again with the same token returns `401`.

**Backward compatibility:** accounts that existed before this change (and any seeded admin) have `passwordSetupRequired = false` and log straight into branch A — they never see the setup screen.

### 3.1b MFA (TOTP)

Backoffice MFA is RFC 6238 TOTP (Google Authenticator, Authy, or any compatible app) — there is no SMS. MFA is **mandatory for `ADMIN` and `SUPER_ADMIN`** (login branch C forces enrollment) and **optional** for `OPERATOR`, `SUPERVISOR`, and `COMPLIANCE` (they may enroll voluntarily). Once enrolled, every login goes through the TOTP challenge (branch D).

**Enrollment is two steps.** Both setup and confirm accept either a normal session `accessToken` (a user voluntarily turning MFA on) **or** the single-use `mfaEnrollmentToken` from login branch C (a mandatory-role user who has not enrolled yet).

#### Step 1 — setup

`POST /api/v1/auth/backoffice/totp-setup`

Headers: `Authorization: Bearer <accessToken | mfaEnrollmentToken>`. No request body.

Response `200 ApiResponse<TotpSetupResponse>`:

```json
{
  "data": {
    "secret": "BASE32SECRET",
    "qrUri": "otpauth://totp/Lipa:admin@komopay.km?secret=...&issuer=Lipa"
  },
  "timestamp": "2026-05-06T12:00:00Z"
}
```

Render `qrUri` as a QR code for scanning, and show `secret` for manual entry. The secret is **pending** at this stage and is not yet active — it only becomes the second factor after a successful confirm. Treat both fields as credentials: never log or persist them. Calling setup again before confirming simply replaces the pending secret.

#### Step 2 — confirm

`POST /api/v1/auth/backoffice/totp-confirm`

Headers: same bearer as setup. Request (`TotpConfirmRequest`):

```json
{ "code": "123456" }
```

- `code`: required, exactly 6 digits.

Response: `204 No Content`. On success the pending secret becomes active, `mfaEnabled` flips to `true`, and `AUTH_MFA_ENROLLED` is audited. The next login returns branch D.

#### Step 3 (login) — verify the challenge

`POST /api/v1/auth/backoffice/login/verify-mfa`

Public endpoint (no bearer): the `challengeId` from login branch D plus the current code are the credentials. Request (`VerifyMfaRequest`):

```json
{ "challengeId": "uuid", "code": "123456" }
```

- `challengeId`: required UUID, the value from login branch D.
- `code`: required, exactly 6 digits.

Response: `200 ApiResponse<TokenResponse>` — the same flat token envelope `/refresh` returns. The challenge is single-use, expires after 5 minutes, and locks after 3 failed attempts; any of these returns `401 MFA_INVALID` and the client must restart from `POST /login`.

#### Revoke (disable MFA)

`DELETE /api/v1/auth/backoffice/totp-setup`

Headers: `Authorization: Bearer <accessToken>` (a full session — the `mfaEnrollmentToken` is **not** accepted here). Requires the current code as step-up. Request (`TotpRevokeRequest`):

```json
{ "code": "123456" }
```

Response: `204 No Content` and `AUTH_MFA_REVOKED` is audited. **Rejected for `ADMIN` / `SUPER_ADMIN`** (MFA is mandatory for those roles) with `401 FORBIDDEN`; the secret stays enrolled.

#### MFA errors

| Status | Code | When |
|---|---|---|
| `401` | `UNAUTHORIZED` | No bearer on setup/confirm/revoke. |
| `401` | `MFA_INVALID` | Wrong/expired confirm code; wrong code, expired/consumed/locked challenge on verify-mfa; wrong code or no enrollment on revoke. |
| `401` | `FORBIDDEN` | Revoke attempted by an `ADMIN` / `SUPER_ADMIN` (MFA mandatory). |
| `400` | `VALIDATION_FIELD_REQUIRED` / `VALIDATION_INVALID_FORMAT` | `code` missing or not exactly 6 digits; `challengeId` missing/not a UUID. |

### 3.2 Refresh

`POST /api/v1/auth/backoffice/refresh`

Request:

```json
{ "refreshToken": "opaque-refresh-token" }
```

Response: `200 ApiResponse<TokenResponse>`. The previous refresh token is marked replaced.

### 3.3 Logout

`POST /api/v1/auth/backoffice/logout`

Headers: `Authorization: Bearer <accessToken>`

Response: `204 No Content`.

### 3.4 JWT Claims Used By BO

The access token contains:

| Claim | Meaning |
|---|---|
| `sub` | Backoffice user UUID |
| `jti` | Access token UUID |
| `act` | `BACKOFFICE_USER` |
| `brole` | `OPERATOR`, `SUPERVISOR`, `COMPLIANCE`, `ADMIN`, `SUPER_ADMIN` |
| `perms` | array of permission strings |
| `iat`, `exp` | JWT issued-at and expiry |

The frontend may decode claims for UI gating, but server-side permission checks remain authoritative.

The single-use bootstrap tokens (`passwordSetupToken`, `mfaEnrollmentToken`) carry a `purp` claim (`PASSWORD_SETUP` / `MFA_ENROLLMENT`) and **no** `brole` or `perms`. They are not sessions: present them only to their dedicated endpoint and never treat the user as authenticated while holding one.

---

## 4. Backoffice Capability Map

| Area | BO can do |
|---|---|
| Session | login (with mandatory first-login password setup and TOTP MFA — mandatory for ADMIN/SUPER_ADMIN, optional otherwise), enroll/confirm/revoke MFA, verify MFA challenge, refresh token, logout |
| Backoffice users | create users, list, view, suspend, reactivate, close, elevate role |
| Actors | create/activate agents and merchants, list/view customers/agents/merchants, suspend/reactivate, request closure, enable/disable merchant M2M receiving |
| Customer KYC review | list/view/download a customer's KYC documents, approve or reject (with mandatory reason, file preserved), raise `kycLevel`, activate `PENDING_KYC` customer when a compatible limit profile is assigned |
| Agent/Merchant KYC/KYB review | upload agent or merchant documents from BO, list/view/download them, approve or reject them, raise `kycLevel`, activate `PENDING_KYC` agent/merchant when `KYC_ENHANCED` and a compatible limit profile are assigned |
| Agent funds | request fund-in and fund-out approval |
| Approvals | list allowed approval requests, view, approve, reject |
| Audit | query audit events and correlation-id traces |
| Wallets | view, freeze, unfreeze |
| Cards | list/view cards, block/unblock, report lost/stolen, close |
| Card stock | import stock, assign stock to agent, list/view stock |
| Terminals | register, provision, list/view, suspend/reactivate |
| Transactions | list/view, request large cash-out approval, request reversal approval |
| Fee rules | list/view, create/supersede/activate/deactivate via approval |
| Commission rules | list/view, create/supersede/activate/deactivate via approval |
| Commission settlements | list runs, view pending summary, trigger batch settlement |
| Limit profiles | list/view, create/update/activate/deactivate/assign via approval |
| Control thresholds | list/view, create/update/activate/deactivate via approval |
| Reconciliation | list/view incidents and runs, investigate, resolve, close |
| Regulatory reports | transaction, KYC, AML, float, actor summaries, export records |
| Bill-provider settlement | view balances, request settlement approval |
| Platform revenue | view balances, request withdrawal approval |
| Service providers | list/view providers and services, create/update/activate/deactivate via approval, switch operational status (ACTIVE/MAINTENANCE/SUSPENDED) and edit business rules (hours, reference rules, announced delay) directly; no BO field exists for provider API base URL, credentials, callback secret, retry, timeout or sandbox settings |
| Bill-payment processing | view the operator worklist (QUEUED + IN_PROCESSING), take/release a payment, force-release another operator's assignment (supervisor), complete with mandatory proof upload (4-eyes above threshold), refund, requeue, view proofs |
| Notifications | list own in-app notifications, see unread count, mark one or all read; receive BO notifications for bill-payment worklist items, approval lifecycle events, and reconciliation incidents |

---

## 5. Exhaustive API Mapping

### 5.1 Auth

| Method | Path | Request | Response |
|---|---|---|---|
| POST | `/api/v1/auth/backoffice/login` | `BackofficeLoginRequest` | `200 ApiResponse<BackofficeLoginResponse>` |
| POST | `/api/v1/auth/backoffice/login/verify-mfa` | `VerifyMfaRequest` (no bearer) | `200 ApiResponse<TokenResponse>` |
| POST | `/api/v1/auth/backoffice/password-setup` | `BackofficePasswordSetupRequest` (bearer = `passwordSetupToken`) | `204 No Content` |
| POST | `/api/v1/auth/backoffice/totp-setup` | none (bearer = `accessToken` or `mfaEnrollmentToken`) | `200 ApiResponse<TotpSetupResponse>` |
| POST | `/api/v1/auth/backoffice/totp-confirm` | `TotpConfirmRequest` (bearer = `accessToken` or `mfaEnrollmentToken`) | `204 No Content` |
| DELETE | `/api/v1/auth/backoffice/totp-setup` | `TotpRevokeRequest` (bearer = `accessToken`) | `204 No Content` |
| POST | `/api/v1/auth/backoffice/refresh` | `RefreshTokenRequest` | `200 ApiResponse<TokenResponse>` |
| POST | `/api/v1/auth/backoffice/logout` | none | `204 No Content` |

### 5.2 Backoffice Users

Required permission for all endpoints: `BACKOFFICE_USER_MANAGE`.

| Method | Path | Request | Response |
|---|---|---|---|
| POST | `/api/v1/backoffice/users` | `CreateBackofficeUserRequest` | `201 ApiResponse<BackofficeUserResponse>` |
| GET | `/api/v1/backoffice/users?cursor&limit` | query | `200 PagedResponse<BackofficeUserResponse>` |
| GET | `/api/v1/backoffice/users/{id}` | none | `200 ApiResponse<BackofficeUserResponse>` |
| POST | `/api/v1/backoffice/users/{id}/suspend` | none | `200 ApiResponse<BackofficeUserResponse>` |
| POST | `/api/v1/backoffice/users/{id}/reactivate` | none | `200 ApiResponse<BackofficeUserResponse>` |
| POST | `/api/v1/backoffice/users/{id}/close` | none | `200 ApiResponse<BackofficeUserResponse>` |
| POST | `/api/v1/backoffice/users/{id}/elevate-role` | `ElevateRoleRequest` | `200 ApiResponse<ElevateRoleResponse>` or `202 ApiResponse<ElevateRoleResponse>` |

Role rules enforced by use cases:

- `SUPER_ADMIN` cannot be created or assigned via API.
- Creating `ADMIN` requires initiator role `SUPER_ADMIN`.
- Creating `OPERATOR`, `SUPERVISOR`, or `COMPLIANCE` requires `ADMIN` or `SUPER_ADMIN`.
- Elevating to `ADMIN` requires `SUPER_ADMIN` and creates approval type `BACKOFFICE_USER_PRIVILEGE_ELEVATION`.
- Elevating to `OPERATOR`, `SUPERVISOR`, or `COMPLIANCE` is immediate.
- Suspend, reactivate, close, and elevate-role refuse self-targeting (initiator cannot equal target). Approve/reject of a `BACKOFFICE_USER_PRIVILEGE_ELEVATION` request also refuses when the deciding user is the elevation target.

### 5.3 Actors

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| POST | `/api/v1/backoffice/agents` | `ACTOR_KYC_UPDATE` | `CreateAgentRequest` | `201 ApiResponse<AgentResponse>` |
| POST | `/api/v1/backoffice/merchants` | `ACTOR_KYC_UPDATE` | `CreateMerchantRequest` | `201 ApiResponse<MerchantResponse>` |
| GET | `/api/v1/backoffice/customers?cursor&limit&status` | `ACTOR_VIEW_ANY` | query | `200 PagedResponse<CustomerResponse>` |
| GET | `/api/v1/backoffice/customers/{id}` | `ACTOR_VIEW_ANY` | none | `200 ApiResponse<CustomerResponse>` |
| POST | `/api/v1/backoffice/customers/{id}/suspend` | `ACTOR_SUSPEND` | none | `200 ApiResponse<CustomerResponse>` |
| POST | `/api/v1/backoffice/customers/{id}/reactivate` | `ACTOR_REACTIVATE` | none | `200 ApiResponse<CustomerResponse>` |
| POST | `/api/v1/backoffice/customers/{id}/close-request` | `ACTOR_CLOSE` | `ActionReasonRequest` | `200 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/customers/{id}/auth-pin/reset` | `ACTOR_AUTH_PIN_RESET` | none | `200 ApiResponse<CustomerResponse>` |
| GET | `/api/v1/backoffice/merchants?cursor&limit&status` | `ACTOR_VIEW_ANY` | query | `200 PagedResponse<MerchantResponse>` |
| GET | `/api/v1/backoffice/merchants/{id}` | `ACTOR_VIEW_ANY` | none | `200 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/suspend` | `ACTOR_SUSPEND` | none | `200 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/reactivate` | `ACTOR_REACTIVATE` | none | `200 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/close-request` | `ACTOR_CLOSE` | `ActionReasonRequest` | `200 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/auth-pin/reset` | `ACTOR_AUTH_PIN_RESET` | none | `200 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/m2m/enable` | `ACTOR_KYC_UPDATE` | none | `200 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/m2m/disable` | `ACTOR_KYC_UPDATE` | none | `200 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/payment-request/enable` | `ACTOR_KYC_UPDATE` | none | `200 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/payment-request/disable` | `ACTOR_KYC_UPDATE` | none | `200 ApiResponse<MerchantResponse>` |
| GET | `/api/v1/backoffice/agents?cursor&limit&status` | `ACTOR_VIEW_ANY` | query | `200 PagedResponse<AgentResponse>` |
| GET | `/api/v1/backoffice/agents/{id}` | `ACTOR_VIEW_ANY` | none | `200 ApiResponse<AgentResponse>` |
| POST | `/api/v1/backoffice/agents/{id}/suspend` | `ACTOR_SUSPEND` | none | `200 ApiResponse<AgentResponse>` |
| POST | `/api/v1/backoffice/agents/{id}/reactivate` | `ACTOR_REACTIVATE` | none | `200 ApiResponse<AgentResponse>` |
| POST | `/api/v1/backoffice/agents/{id}/close-request` | `ACTOR_CLOSE` | `ActionReasonRequest` | `200 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/agents/{id}/auth-pin/reset` | `ACTOR_AUTH_PIN_RESET` | none | `200 ApiResponse<AgentResponse>` |
| POST | `/api/v1/backoffice/agents/{id}/fund-in` | `AGENT_FUND` | `AgentFundRequest` | `201 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/agents/{id}/fund-out` | `AGENT_FUND` | `AgentFundRequest` | `201 ApiResponse<ApprovalRequestResponse>` |

Agent fund-in/fund-out are maker-only endpoints: wallet mutation happens later when a checker approves the created approval request. The maker call refuses with `INSUFFICIENT_BALANCE` when the source wallet (`SYSTEM_LIQUIDITY` for fund-in, the agent wallet for fund-out) cannot cover the requested amount; the same check is re-run at approval time.

#### Forced auth-PIN reset

The three `…/auth-pin/reset` endpoints clear the actor's `auth_pin_hash` and lock counters. The Backoffice never sees, enters, generates or transmits a PIN — these endpoints take no body and produce no PIN value. After a reset the actor's next call to `POST /login` returns `pinSetupRequired=true` with a short-lived `pinSetupToken`, and the actor completes the setup themselves via `POST /api/v1/auth/{customer|agent|merchant}/auth-pin/setup`. Every reset emits an `AUTH_PIN_RESET_BY_BACKOFFICE` audit event with the BO user, IP and user-agent. The endpoint is gated by the `ACTOR_AUTH_PIN_RESET` permission, granted by default to `SUPERVISOR`, `ADMIN` and `SUPER_ADMIN`. Forgotten-PIN self-service is out of scope in this iteration; a user who has lost their PIN must request a Backoffice reset.

### 5.3a Customer KYC Review

Direct (non-approval) BO endpoints that operate on a customer's KYC dossier. The four logical actions are **orthogonal**: approving a document does not change `kycLevel` or `status`, raising `kycLevel` does not activate, and limit-profile assignment lives in [5.14](#514-limit-profiles). Each call emits a dedicated audit event.

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/customers/{id}/kyc-documents` | `CUSTOMER_KYC_DOCUMENT_VIEW` | none | `200 ApiResponse<KycDocumentResponse[]>` |
| GET | `/api/v1/backoffice/kyc-documents/{documentId}` | `CUSTOMER_KYC_DOCUMENT_VIEW` | none | `200 ApiResponse<KycDocumentResponse>` |
| GET | `/api/v1/backoffice/kyc-documents/{documentId}/file` | `CUSTOMER_KYC_DOCUMENT_VIEW` | none | `200 <stored Content-Type>` raw bytes |
| POST | `/api/v1/backoffice/kyc-documents/{documentId}/approve` | `CUSTOMER_KYC_DOCUMENT_REVIEW` | none | `200 ApiResponse<KycDocumentResponse>` |
| POST | `/api/v1/backoffice/kyc-documents/{documentId}/reject` | `CUSTOMER_KYC_DOCUMENT_REVIEW` | `RejectKycDocumentRequest` | `200 ApiResponse<KycDocumentResponse>` |
| POST | `/api/v1/backoffice/customers/{id}/kyc-level` | `ACTOR_KYC_UPDATE` | `ChangeKycLevelRequest` | `200 ApiResponse<CustomerResponse>` |
| POST | `/api/v1/backoffice/customers/{id}/activate` | `ACTOR_ACTIVATE` | none | `200 ApiResponse<CustomerResponse>` |

#### Document storage and download

KYC files are stored encrypted by the backend. The BO frontend must not decrypt files and must not access object storage directly.

To view a document, the BO frontend must call the dedicated file endpoint:

| Method | Path | Permission | Response |
|---|---|---|---|
| GET | `/api/v1/backoffice/kyc-documents/{id}/file` | `CUSTOMER_KYC_DOCUMENT_VIEW` | Decrypted file stream |

The endpoint returns the decrypted file bytes with the real `Content-Type` and an inline disposition when possible:

- `image/jpeg`
- `image/png`
- `application/pdf`

The BO frontend must use `KycDocumentResponse.contentType` to choose the preview mode:

- `image/jpeg` or `image/png`: display with an image preview.
- `application/pdf`: display with an iframe or PDF viewer.
- `application/octet-stream`: legacy fallback; offer download/open in new tab instead of inline preview.

The BO frontend must not expose `storageRef`, encryption keys, or internal storage paths.

#### Approve / reject

- `approve` only transitions a document from `PENDING_REVIEW` → `ACCEPTED`. A second decision on an already-decided document returns `400` (business rule violation). Audit event: `KYC_DOCUMENT_APPROVED`.
- `reject` requires a non-blank `reason` (1..1000 chars). The encrypted file is **not** deleted: it stays on storage for the 10-year KYC retention; only the row's status, `reviewedAt`, `reviewedByUserId` and `rejectionReason` change. Audit event: `KYC_DOCUMENT_REJECTED` carries the reason in its payload. A DB CHECK constraint (`chk_kyc_reviewed_consistency`) enforces that a `REJECTED` row always has a non-blank `rejectionReason` and an `ACCEPTED` row never does.
- Neither approve nor reject changes the customer's `kycLevel` or `status`. Those are separate BO actions below.

#### Change KYC level

`POST /customers/{id}/kyc-level` raises `kycLevel`. The level is **monotonic** — a downgrade (e.g. `KYC_VERIFIED` → `KYC_BASIC`) is rejected with `400`. Submitting the customer's current level is a no-op and emits no audit event. Otherwise emits `CUSTOMER_KYC_LEVEL_CHANGED` with the previous and new levels.

#### Activate

`POST /customers/{id}/activate` transitions a `PENDING_KYC` customer to `ACTIVE`. The endpoint is **refused** unless the customer has a currently-assigned `LimitProfile` that is:

- `active = true`
- `applicableActorTypes` contains `CUSTOMER`
- `requiredKycLevel <= customer.kycLevel`

No default profile is created implicitly. If none is assigned, the response is `400` with code `CONFIG_LIMIT_PROFILE_NOT_FOUND` and a message asking the operator to assign one first via `PATCH /api/v1/backoffice/customers/{id}/limit-profile` (which itself goes through the existing `LIMIT_PROFILE_CHANGE` approval flow). Calling this endpoint on an already-`ACTIVE` customer is idempotent and emits no audit event. Activation from `SUSPENDED` / `FROZEN` is **not** this endpoint's responsibility — use `…/reactivate` / `…/unfreeze`. Emits `CUSTOMER_ACTIVATED` on success.

#### Recommended UI flow

1. Open a customer file → call `GET /customers/{id}/kyc-documents`.
2. Per document: view metadata, optionally `GET …/file` to inspect the raw artefact, then `approve` or `reject` (with a reason captured in a modal).
3. Once all required documents are accepted, raise `kycLevel` if needed via `POST …/kyc-level`.
4. If a limit profile is missing or incompatible, assign one via `PATCH …/limit-profile` (approval-gated; see 5.14) and wait for the checker.
5. Finally, `POST …/activate`. A `400` here means a missing or incompatible profile — surface the message verbatim so the operator knows the next step.

### 5.3b Agent And Merchant KYC/KYB Review

Direct BO endpoints for agent and merchant KYC/KYB dossiers. Unlike customer documents, agent and merchant documents are uploaded by the Backoffice itself. The actions remain separate: uploading or approving a document does not change `kycLevel`; raising `kycLevel` does not activate; limit-profile assignment remains the approval-gated flow in [5.14](#514-limit-profiles); activation is its own call.

#### Agent documents

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| POST | `/api/v1/backoffice/agents/{id}/kyc-documents` | `AGENT_KYC_DOCUMENT_UPLOAD` | `multipart/form-data` with `documentType`, `file` | `201 ApiResponse<KycDocumentResponse>` |
| GET | `/api/v1/backoffice/agents/{id}/kyc-documents` | `AGENT_KYC_DOCUMENT_VIEW` | none | `200 ApiResponse<KycDocumentResponse[]>` |
| GET | `/api/v1/backoffice/agents/kyc-documents/{documentId}` | `AGENT_KYC_DOCUMENT_VIEW` | none | `200 ApiResponse<KycDocumentResponse>` |
| GET | `/api/v1/backoffice/agents/kyc-documents/{documentId}/file` | `AGENT_KYC_DOCUMENT_VIEW` | none | `200 <stored Content-Type>` raw bytes |
| POST | `/api/v1/backoffice/agents/kyc-documents/{documentId}/approve` | `AGENT_KYC_DOCUMENT_REVIEW` | none | `200 ApiResponse<KycDocumentResponse>` |
| POST | `/api/v1/backoffice/agents/kyc-documents/{documentId}/reject` | `AGENT_KYC_DOCUMENT_REVIEW` | `RejectKycDocumentRequest` | `200 ApiResponse<KycDocumentResponse>` |
| POST | `/api/v1/backoffice/agents/{id}/kyc-level` | `ACTOR_KYC_UPDATE` | `ChangeActorKycLevelRequest` | `200 ApiResponse<AgentResponse>` |
| POST | `/api/v1/backoffice/agents/{id}/activate` | `ACTOR_ACTIVATE` | none | `200 ApiResponse<AgentResponse>` |

#### Merchant documents

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| POST | `/api/v1/backoffice/merchants/{id}/kyc-documents` | `MERCHANT_KYC_DOCUMENT_UPLOAD` | `multipart/form-data` with `documentType`, `file` | `201 ApiResponse<KycDocumentResponse>` |
| GET | `/api/v1/backoffice/merchants/{id}/kyc-documents` | `MERCHANT_KYC_DOCUMENT_VIEW` | none | `200 ApiResponse<KycDocumentResponse[]>` |
| GET | `/api/v1/backoffice/merchants/kyc-documents/{documentId}` | `MERCHANT_KYC_DOCUMENT_VIEW` | none | `200 ApiResponse<KycDocumentResponse>` |
| GET | `/api/v1/backoffice/merchants/kyc-documents/{documentId}/file` | `MERCHANT_KYC_DOCUMENT_VIEW` | none | `200 <stored Content-Type>` raw bytes |
| POST | `/api/v1/backoffice/merchants/kyc-documents/{documentId}/approve` | `MERCHANT_KYC_DOCUMENT_REVIEW` | none | `200 ApiResponse<KycDocumentResponse>` |
| POST | `/api/v1/backoffice/merchants/kyc-documents/{documentId}/reject` | `MERCHANT_KYC_DOCUMENT_REVIEW` | `RejectKycDocumentRequest` | `200 ApiResponse<KycDocumentResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/kyc-level` | `ACTOR_KYC_UPDATE` | `ChangeActorKycLevelRequest` | `200 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/activate` | `ACTOR_ACTIVATE` | none | `200 ApiResponse<MerchantResponse>` |

#### Upload and file rules

Upload accepts only `AGENT` and `MERCHANT` owners. The file is required, must be non-empty, must be at most 10 MB, and the backend accepts only byte-sniffed JPEG, PNG, or PDF content. Client-declared MIME type and filename extension are not trusted.

Uploaded documents are stored encrypted, start in `PENDING_REVIEW`, set `uploadedByActorType = BACKOFFICE_USER`, and emit `KYC_DOCUMENT_UPLOADED`. The response never includes `storageRef`; file bytes are reachable only through the owner-scoped file endpoint. The file endpoint returns the stored MIME type and `Content-Disposition: inline` for JPEG, PNG, and PDF; legacy `application/octet-stream` rows are served as attachment.

#### Review, level, and activation

Approve/reject rules match customer KYC review: only `PENDING_REVIEW` can be decided, reject requires `reason` (1..1000 chars), the encrypted file is preserved, and the owner's `kycLevel` and status are not changed by the document decision.

`POST /{agents|merchants}/{id}/kyc-level` is monotonic, refuses downgrades, and is a no-op when the submitted level equals the current one. Agents and merchants do not carry `nextReviewDate`, so this request has only `kycLevel`.

`POST /{agents|merchants}/{id}/activate` activates only `PENDING_KYC` actors. It is refused unless the actor has `kycLevel >= KYC_ENHANCED` and a currently assigned compatible `LimitProfile`: active, applicable to `AGENT` or `MERCHANT`, and with `requiredKycLevel <= actor.kycLevel`. On success it creates the actor wallet, transitions to `ACTIVE`, and emits `AGENT_ACTIVATED` or `MERCHANT_ACTIVATED`. Calling it on an already `ACTIVE` actor is idempotent and emits no audit event.

### 5.4 Approvals

Approval read/action permission is dynamic by approval type. See [10.2](#102-approval-permission-map).

| Method | Path | Request | Response |
|---|---|---|---|
| GET | `/api/v1/backoffice/approvals?cursor&limit&pendingOnly` | query | `200 PagedResponse<ApprovalRequestResponse>` |
| GET | `/api/v1/backoffice/approvals/{id}` | none | `200 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/approvals/{id}/approve` | optional `ApprovalDecisionRequest` | `200 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/approvals/{id}/reject` | `ApprovalDecisionRequest` | `200 ApiResponse<ApprovalRequestResponse>` |

Listing requires the user to hold at least one approval permission. Results are filtered to approval types that the principal is allowed to act on.

### 5.5 Audit

Required permission: `AUDIT_VIEW`.

| Method | Path | Query | Response |
|---|---|---|---|
| GET | `/api/v1/backoffice/audit` | `cursor`, `limit`, `eventType`, `actorId`, `from`, `to`, `correlationId` | `200 PagedResponse<AuditEventResponse>` |

If `correlationId` is provided, cursor pagination is bypassed and the endpoint returns `PagedResponse.last(matches)`.

### 5.6 Wallets

| Method | Path | Permission | Response |
|---|---|---|---|
| GET | `/api/v1/backoffice/wallets/{id}` | `WALLET_VIEW_ANY` | `200 ApiResponse<WalletResponse>` |
| POST | `/api/v1/backoffice/wallets/{id}/freeze` | `WALLET_FREEZE` | `200 ApiResponse<WalletResponse>` |
| POST | `/api/v1/backoffice/wallets/{id}/unfreeze` | `WALLET_UNFREEZE` | `200 ApiResponse<WalletResponse>` |

### 5.7 Cards

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/cards?customerId&cursor&limit` | `CARD_VIEW_ANY` | query | `200 PagedResponse<CardResponse>` |
| GET | `/api/v1/backoffice/cards/{id}` | `CARD_VIEW_ANY` | none | `200 ApiResponse<CardResponse>` |
| POST | `/api/v1/backoffice/cards/{id}/block` | `CARD_BLOCK_ANY` | none | `200 ApiResponse<CardResponse>` |
| POST | `/api/v1/backoffice/cards/{id}/unblock` | `CARD_BLOCK_ANY` | none | `200 ApiResponse<CardResponse>` |
| POST | `/api/v1/backoffice/cards/{id}/report-lost` | `CARD_REPORT_ANY` | none | `200 ApiResponse<CardResponse>` |
| POST | `/api/v1/backoffice/cards/{id}/report-stolen` | `CARD_REPORT_ANY` | none | `200 ApiResponse<CardResponse>` |
| POST | `/api/v1/backoffice/cards/{id}/close` | `CARD_CLOSE_ANY` | optional `{ "reason": "string" }` | `200 ApiResponse<CardResponse>` |

### 5.8 Card Stock

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| POST | `/api/v1/backoffice/card-stock/import` | `CARD_STOCK_IMPORT` | `ImportCardBatchRequest` | `200 ApiResponse<List<CardStockResponse>>` |
| POST | `/api/v1/backoffice/card-stock/assign` | `CARD_STOCK_ASSIGN` | `AssignCardStockRequest` | `200 ApiResponse<List<CardStockResponse>>` |
| GET | `/api/v1/backoffice/card-stock?status&agentId&batchRef&cursor&limit` | `CARD_VIEW_ANY` | query | `200 PagedResponse<CardStockResponse>` |
| GET | `/api/v1/backoffice/card-stock/{id}` | `CARD_VIEW_ANY` | none | `200 ApiResponse<CardStockResponse>` |

`X-Correlation-Id` can be supplied to import/assign. If absent or invalid, the controller uses a generated UUID for the use case.

### 5.9 Terminals

Required permission: `TERMINAL_MANAGE`.

| Method | Path | Request | Response |
|---|---|---|---|
| POST | `/api/v1/backoffice/terminals` | `RegisterTerminalRequest` | `201 ApiResponse<TerminalResponse>` |
| POST | `/api/v1/backoffice/terminals/{id}/provision` | none | `200 ApiResponse<ProvisionTerminalResponse>` |
| GET | `/api/v1/backoffice/terminals?merchantId` | query | `200 PagedResponse<TerminalResponse>` |
| GET | `/api/v1/backoffice/terminals/{id}` | none | `200 ApiResponse<TerminalResponse>` |
| POST | `/api/v1/backoffice/terminals/{id}/suspend` | none | `200 ApiResponse<TerminalResponse>` |
| POST | `/api/v1/backoffice/terminals/{id}/reactivate` | none | `200 ApiResponse<TerminalResponse>` |

`rawApiKey` is returned only by the provision response.

### 5.10 Transactions

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/transactions?cursor&limit&status&type&from&to` | `TX_VIEW_ANY` | query | `200 PagedResponse<TransactionResponse>` |
| GET | `/api/v1/backoffice/transactions/{id}` | `TX_VIEW_ANY` | none | `200 ApiResponse<TransactionResponse>` |
| POST | `/api/v1/backoffice/transactions/cash-out` | `TX_CASH_OUT_INITIATE` | `CreateLargeCashOutApprovalRequest` | `201 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/transactions/reversals` | `TX_REVERSAL_INITIATE` | `CreateReversalApprovalRequest` | `201 ApiResponse<ApprovalRequestResponse>` |

Cash-out and reversal endpoints create approval requests only. Execution occurs on approval.

### 5.10a Payment Requests (supervision)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/payment-requests?cursor&limit&status&merchantId&from&to` | `TX_VIEW_ANY` | query | `200 PagedResponse<BackofficePaymentRequestResponse>` |
| GET | `/api/v1/backoffice/payment-requests/{id}` | `TX_VIEW_ANY` | none | `200 ApiResponse<BackofficePaymentRequestResponse>` |

Read-only. Use for investigation: each row exposes `status`, `mode`, target payer (if RESTRICTED), `settledTransactionId`, the paying actor, and timestamps. Filter by `status` (`PaymentRequestStatus`), `merchantId`, and a created-at window.

```ts
BackofficePaymentRequestResponse = {
  id: uuid;
  shortCode: string;
  beneficiaryMerchantId: uuid;
  amount: long;
  currency: string;
  label?: string;
  status: string;              // ACTIVE | PAID | CANCELLED | EXPIRED
  mode: string;                // OPEN | RESTRICTED
  targetPayerType?: string;    // CUSTOMER | MERCHANT (RESTRICTED only)
  targetPayerId?: uuid;
  expiresAt: instant;
  settledTransactionId?: uuid;
  paidByActorType?: string;
  paidByActorId?: uuid;
  cancelledReason?: string;
  createdAt: instant;
  paidAt?: instant;
}
```

### 5.11 Fee Rules

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/fee-rules` | `FEE_RULE_VIEW` | none | `200 PagedResponse<FeeRuleResponse>` |
| GET | `/api/v1/backoffice/fee-rules/{id}` | `FEE_RULE_VIEW` | none | `200 ApiResponse<FeeRuleResponse>` |
| POST | `/api/v1/backoffice/fee-rules` | `FEE_RULE_WRITE` | `CreateFeeRuleRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/fee-rules/{id}/supersede` | `FEE_RULE_WRITE` | `CreateFeeRuleRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/fee-rules/{id}/activate` | `FEE_RULE_ACTIVATE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/fee-rules/{id}/deactivate` | `FEE_RULE_ACTIVATE` | none | `202 ApiResponse<ApprovalRequestResponse>` |

### 5.12 Commission Rules

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/commission-rules` | `FEE_RULE_VIEW` | none | `200 PagedResponse<CommissionRuleResponse>` |
| GET | `/api/v1/backoffice/commission-rules/{id}` | `FEE_RULE_VIEW` | none | `200 ApiResponse<CommissionRuleResponse>` |
| POST | `/api/v1/backoffice/commission-rules` | `COMMISSION_RULE_WRITE` | `CreateCommissionRuleRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/commission-rules/{id}/supersede` | `COMMISSION_RULE_WRITE` | `CreateCommissionRuleRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/commission-rules/{id}/activate` | `COMMISSION_RULE_ACTIVATE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/commission-rules/{id}/deactivate` | `COMMISSION_RULE_ACTIVATE` | none | `202 ApiResponse<ApprovalRequestResponse>` |

### 5.13 Commission Settlements

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/commission-settlements/runs?cursor&limit&mode&status` | `RECONCILIATION_VIEW` | query | `200 PagedResponse<CommissionSettlementRunResponse>` |
| GET | `/api/v1/backoffice/commission-settlements/pending` | `RECONCILIATION_VIEW` | none | `200 ApiResponse<CommissionPendingSummaryResponse>` |
| POST | `/api/v1/backoffice/commission-settlements/trigger` | `RECONCILIATION_RESOLVE` | `TriggerSettlementRequest` | `200 ApiResponse<CommissionSettlementRunResponse>` |

`TriggerSettlementRequest.mode` must be `BATCH_DAILY` or `BATCH_WEEKLY`; `IMMEDIATE` is rejected.

### 5.14 Limit Profiles

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/limit-profiles` | `LIMIT_PROFILE_VIEW` or `LIMIT_PROFILE_WRITE` | none | `200 PagedResponse<LimitProfileResponse>` |
| GET | `/api/v1/backoffice/limit-profiles/{id}` | `LIMIT_PROFILE_VIEW` or `LIMIT_PROFILE_WRITE` | none | `200 ApiResponse<LimitProfileResponse>` |
| POST | `/api/v1/backoffice/limit-profiles` | `LIMIT_PROFILE_WRITE` | `LimitProfileRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/limit-profiles/{id}/supersede` | `LIMIT_PROFILE_WRITE` | `LimitProfileRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| PATCH | `/api/v1/backoffice/limit-profiles/{id}/activate` | `LIMIT_PROFILE_WRITE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| PATCH | `/api/v1/backoffice/limit-profiles/{id}/deactivate` | `LIMIT_PROFILE_WRITE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| PATCH | `/api/v1/backoffice/customers/{id}/limit-profile` | `LIMIT_PROFILE_WRITE` | `AssignLimitProfileRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| PATCH | `/api/v1/backoffice/merchants/{id}/limit-profile` | `LIMIT_PROFILE_WRITE` | `AssignLimitProfileRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| PATCH | `/api/v1/backoffice/agents/{id}/limit-profile` | `LIMIT_PROFILE_WRITE` | `AssignLimitProfileRequest` | `202 ApiResponse<ApprovalRequestResponse>` |

### 5.15 Control Thresholds

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/control-thresholds` | `CONTROL_THRESHOLD_VIEW` or `CONTROL_THRESHOLD_WRITE` | none | `200 PagedResponse<ControlThresholdResponse>` |
| GET | `/api/v1/backoffice/control-thresholds/{id}` | `CONTROL_THRESHOLD_VIEW` or `CONTROL_THRESHOLD_WRITE` | none | `200 ApiResponse<ControlThresholdResponse>` |
| POST | `/api/v1/backoffice/control-thresholds` | `CONTROL_THRESHOLD_WRITE` | `ControlThresholdRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/control-thresholds/{id}/supersede` | `CONTROL_THRESHOLD_WRITE` | `ControlThresholdRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/control-thresholds/{id}/activate` | `CONTROL_THRESHOLD_WRITE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/control-thresholds/{id}/deactivate` | `CONTROL_THRESHOLD_WRITE` | none | `202 ApiResponse<ApprovalRequestResponse>` |

### 5.16 Reconciliation

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/reconciliation/incidents?cursor&limit&status` | `RECONCILIATION_VIEW` | query | `200 PagedResponse<ReconciliationIncidentResponse>` |
| GET | `/api/v1/backoffice/reconciliation/incidents/{id}` | `RECONCILIATION_VIEW` | none | `200 ApiResponse<ReconciliationIncidentResponse>` |
| POST | `/api/v1/backoffice/reconciliation/incidents/{id}/investigate` | `RECONCILIATION_RESOLVE` | optional empty object | `200 ApiResponse<ReconciliationIncidentResponse>` |
| POST | `/api/v1/backoffice/reconciliation/incidents/{id}/resolve` | `RECONCILIATION_RESOLVE` | `ResolveIncidentRequest` | `200 ApiResponse<ReconciliationIncidentActionResponse>` or `201 ApiResponse<ReconciliationIncidentActionResponse>` |
| POST | `/api/v1/backoffice/reconciliation/incidents/{id}/close` | `RECONCILIATION_RESOLVE` | `CloseIncidentRequest` | `200 ApiResponse<ReconciliationIncidentActionResponse>` or `201 ApiResponse<ReconciliationIncidentActionResponse>` |
| GET | `/api/v1/backoffice/reconciliation/runs?cursor&limit` | `RECONCILIATION_VIEW` | query | `200 PagedResponse<ReconciliationRunResponse>` |
| GET | `/api/v1/backoffice/reconciliation/runs/{id}` | `RECONCILIATION_VIEW` | none | `200 ApiResponse<ReconciliationRunResponse>` |

Resolve/close returns `201` when the operation creates a `RECONCILIATION_ADJUSTMENT` approval. Otherwise it applies immediately and returns `200`.

### 5.17 Regulatory Reports

Required permission: `REPORT_REGULATORY_EXPORT`.

| Method | Path | Query | Response |
|---|---|---|---|
| GET | `/api/v1/backoffice/reports/transactions/summary` | required `from`, `to`; optional `groupBy=MONTH`, `format=json` | `200 ApiResponse<TransactionSummaryReportResponse>` or raw CSV |
| GET | `/api/v1/backoffice/reports/kyc/summary` | optional `format=json` | `200 ApiResponse<KycSummaryReportResponse>` or raw CSV |
| GET | `/api/v1/backoffice/reports/aml/large-transactions` | required `from`, `to`; optional `thresholdKmf=500000`, `cursor`, `limit` | `200 PagedResponse<AmlTransactionResponse>` |
| GET | `/api/v1/backoffice/reports/float` | none | `200 ApiResponse<FloatReportResponse>` |
| GET | `/api/v1/backoffice/reports/actors/summary` | optional `format=json` | `200 ApiResponse<ActorSummaryReportResponse>` or raw CSV |
| POST | `/api/v1/backoffice/reports/exports` | query `reportType`, optional `periodFrom`, `periodTo`, `recordCount=0` | `200 ApiResponse<ReportExportResponse>` |
| GET | `/api/v1/backoffice/reports/exports?cursor&limit&reportType` | query | `200 PagedResponse<ReportExportResponse>` |

Accessing report endpoints writes an audit event.

### 5.18 Bill-Provider Settlement

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/bill-provider-settlement/balances` | `BILL_PROVIDER_SETTLEMENT_VIEW` | none | `200 ApiResponse<BillProviderSettlementBalancesResponse>` |
| POST | `/api/v1/backoffice/bill-provider-settlement/requests` | `BILL_PROVIDER_SETTLEMENT_REQUEST` | `RequestSettlementRequest` | `201 ApiResponse<ApprovalRequestResponse>` |

`providerCode` is required and validated against an existing `ServiceProvider` (404 `SERVICE_PROVIDER_NOT_FOUND` if unknown); it identifies which provider the disbursement is for. The financial movement still drains the shared `SYSTEM_BILL_PROVIDER_PAYABLE` pool — the provider is recorded for traceability, not balanced per-provider.

Settlement execution occurs on approval type `BILL_PROVIDER_SETTLEMENT`.

### 5.19 Platform Revenue

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/platform-revenue/balances` | `PLATFORM_REVENUE_WITHDRAWAL_VIEW` | none | `200 ApiResponse<PlatformRevenueBalancesResponse>` |
| POST | `/api/v1/backoffice/platform-revenue/withdrawal-requests` | `PLATFORM_REVENUE_WITHDRAWAL_REQUEST` | `RequestPlatformRevenueWithdrawalRequest` | `201 ApiResponse<ApprovalRequestResponse>` |

Withdrawal execution occurs on approval type `PLATFORM_REVENUE_WITHDRAWAL`.

### 5.19b Platform Liquidity (Top-Up)

The treasury / agent-funding pool is `SYSTEM_LIQUIDITY`. It is replenished by the platform via maker-checker top-ups that record the external funding (bank wire / treasury injection) against `SYSTEM_LIQUIDITY_FUNDING_CLEARING` (debit-normal, cumulative magnitude).

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/platform-liquidity/balances` | `PLATFORM_LIQUIDITY_TOP_UP_VIEW` | none | `200 ApiResponse<PlatformLiquidityBalancesResponse>` |
| POST | `/api/v1/backoffice/platform-liquidity/top-up-requests` | `PLATFORM_LIQUIDITY_TOP_UP_REQUEST` | `RequestPlatformLiquidityTopUpRequest` | `201 ApiResponse<ApprovalRequestResponse>` |

Top-up execution occurs on approval type `PLATFORM_LIQUIDITY_TOP_UP`. Approve / reject through the generic `/api/v1/backoffice/approvals/{id}/approve|reject` endpoints. The checker must hold `PLATFORM_LIQUIDITY_TOP_UP_APPROVE` and must differ from the requester (4-eyes rule).

#### UI placement

- Top-level menu **Platform → Liquidity**.
- Page **"Liquidity Top-Up"** with two cards: current `SYSTEM_LIQUIDITY` balance and cumulative top-up magnitude (`SYSTEM_LIQUIDITY_FUNDING_CLEARING`).
- Action button **"Request top-up"** opens the form below — visible only when the operator holds `PLATFORM_LIQUIDITY_TOP_UP_REQUEST`.
- Pending and historical top-up approvals appear in the existing Approvals list, filtered by `type = PLATFORM_LIQUIDITY_TOP_UP`.

#### Request form

| Field | Type | Required | Notes |
|---|---|---|---|
| `amount` | long (KMF, integer) | yes | Strictly positive. |
| `externalReference` | string (max 100) | yes | Bank wire / treasury injection identifier. Must be unique enough to trace back to the off-platform record. |
| `source` | string (max 60) | yes | Funding channel label, e.g. `BANK_WIRE`, `TREASURY`. |
| `notes` | string (max 500) | no | Free-text explanation visible to the checker. |

#### Approval behavior

- Maker submits via `POST /platform-liquidity/top-up-requests` → returns `201` with the new `ApprovalRequest` (status `PENDING_APPROVAL`).
- Checker (different user) calls the generic `POST /approvals/{id}/approve` with `PLATFORM_LIQUIDITY_TOP_UP_APPROVE`. Approval is rejected if the checker is the requester (`SELF_APPROVAL_FORBIDDEN`).
- Only one PENDING top-up approval at a time (`PLATFORM_LIQUIDITY_TOP_UP_PENDING_EXISTS` returned otherwise).
- On approve, the platform posts the top-up transaction (DEBIT funding-clearing, CREDIT liquidity), with idempotency key `PLATFORM-LIQUIDITY-TOP-UP-APPROVAL-{approvalId}`. Replays return the same transaction.
- Rejection records `PLATFORM_LIQUIDITY_TOP_UP_REJECTED` audit event; no ledger movement.

#### Statuses and user-facing messages

| Approval status | UI badge | User-facing message |
|---|---|---|
| `PENDING_APPROVAL` | "Pending checker approval" | "Awaiting a second backoffice approver." |
| `APPROVED` | "Executed" | "Liquidity replenished by `{amount}` `{currency}`. Transaction `{tx.id}`." |
| `REJECTED` | "Rejected" | "Top-up rejected: `{reason}`." |
| `EXPIRED` | "Expired" | "Approval window expired (72h). Resubmit if still needed." |

Maker validation errors surface as `400` with code `TRANSACTION_ZERO_AMOUNT` (non-positive amount) or `PLATFORM_LIQUIDITY_TOP_UP_PENDING_EXISTS` (duplicate pending approval).

#### Visibility / permission expectations

| Permission | Roles seeded by default | Use |
|---|---|---|
| `PLATFORM_LIQUIDITY_TOP_UP_VIEW` | `ADMIN`, `SUPER_ADMIN`, `COMPLIANCE` | See balances + history. |
| `PLATFORM_LIQUIDITY_TOP_UP_REQUEST` | `ADMIN`, `SUPER_ADMIN` | Create the maker request. |
| `PLATFORM_LIQUIDITY_TOP_UP_APPROVE` | `ADMIN`, `SUPER_ADMIN` | Approve / reject as checker. |

To support 4-eyes a deployment must provision at least two distinct backoffice users carrying both `_REQUEST` and `_APPROVE` (one acts as maker, the other as checker).

### 5.20 Service Providers And Bill Services

These backoffice endpoints are the source of the customer-facing payable catalogue: a provider/service the BO sets to `ACTIVE` (provider status `ACTIVE` **and** service status `ACTIVE`) becomes visible to customers through `GET /api/v1/me/bill-payments/services` (Customer spec §11.5). The catalogue is the **only** provider/service channel for the customer app — these `/backoffice/*` endpoints are never called from a customer client.

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| POST | `/api/v1/backoffice/service-providers` | `SERVICE_PROVIDER_MANAGE` | `CreateServiceProviderRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| GET | `/api/v1/backoffice/service-providers` | `SERVICE_PROVIDER_VIEW` or `SERVICE_PROVIDER_MANAGE` | none | `200 ApiResponse<List<ServiceProviderResponse>>` |
| GET | `/api/v1/backoffice/service-providers/{id}` | `SERVICE_PROVIDER_VIEW` or `SERVICE_PROVIDER_MANAGE` | none | `200 ApiResponse<ServiceProviderResponse>` |
| PUT | `/api/v1/backoffice/service-providers/{id}` | `SERVICE_PROVIDER_MANAGE` | `UpdateServiceProviderRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/service-providers/{id}/activate` | `SERVICE_PROVIDER_MANAGE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/service-providers/{id}/deactivate` | `SERVICE_PROVIDER_MANAGE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/service-providers/{providerId}/services` | `SERVICE_PROVIDER_MANAGE` | `CreateBillServiceRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| GET | `/api/v1/backoffice/service-providers/{providerId}/services` | `SERVICE_PROVIDER_VIEW` or `SERVICE_PROVIDER_MANAGE` | none | `200 ApiResponse<List<BillServiceResponse>>` |
| PUT | `/api/v1/backoffice/service-providers/{providerId}/services/{serviceId}` | `SERVICE_PROVIDER_MANAGE` | `UpdateBillServiceRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/service-providers/{providerId}/services/{serviceId}/activate` | `SERVICE_PROVIDER_MANAGE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| POST | `/api/v1/backoffice/service-providers/{providerId}/services/{serviceId}/deactivate` | `SERVICE_PROVIDER_MANAGE` | none | `202 ApiResponse<ApprovalRequestResponse>` |
| PATCH | `/api/v1/backoffice/service-providers/{id}/status` | `SERVICE_PROVIDER_MANAGE` | `ChangeServiceProviderStatusRequest` | `200 ApiResponse<ServiceProviderResponse>` |
| PATCH | `/api/v1/backoffice/service-providers/{id}/business-rules` | `SERVICE_PROVIDER_MANAGE` | `UpdateProviderBusinessRulesRequest` | `200 ApiResponse<ServiceProviderResponse>` |

Important: `CreateServiceProviderRequest` and `UpdateServiceProviderRequest` contain only the provider identity/capability fields listed in [6.12](#612-service-providers-and-bill-services). The online adapter configuration fields (`type`, `baseUrl`, `credentialsRef`, `callbackSecretRef`, `timeoutMillis`, `maxRetries`, `retryBackoffMillis`, `sandbox`) no longer exist in BO payloads or responses and must not be sent.

Important: `CreateBillServiceRequest` still declares `providerId` as `@NotNull`, even though the controller uses the path `providerId`. The frontend must send it.

Unlike create/update/activate/deactivate (which are maker-checker, `202` + `ApprovalRequest`), the two `PATCH` endpoints apply **directly** and return the updated `ServiceProviderResponse` with `200`. They are operational, time-sensitive controls — putting a provider into maintenance or fixing a reference rule should not wait for a second approver. Both emit an audit event (`SERVICE_PROVIDER_STATUS_CHANGED` with `from`/`to`/`reason`).

- `…/status` toggles `ServiceProviderStatus` between `ACTIVE`, `MAINTENANCE`, and `SUSPENDED` (and the legacy `INACTIVE`). `MAINTENANCE` blocks new customer bill payments with a clear `422`; `SUSPENDED` hides the provider entirely (customer initiation returns `404`).
- `…/business-rules` edits the processing-hours window, the announced delay, and the client-reference validation rules (regex / lengths / example). All fields are optional — only the supplied ones change.

### 5.21 Bill-Payment Processing (Operator Worklist)

Bill payments are executed **manually and asynchronously**: there is no live provider API. A customer's payment is debited (held) and queued; backoffice operators pick it up, perform the operation on the provider's own platform (Lipa is an accredited agent), upload the proof, and validate it on the Lipa side. The customer promise is processing **within 4 business hours** (08:00–18:00, Mon–Sat). These endpoints drive the operator worklist and the per-payment actions.

> **Emergency kill-switch.** All endpoints below are mounted only when `komopay.billpay.enabled=true`. The default is `true`; this is not a feature flag. Operations may set it to `false` only for incident response, in which case these routes **do not exist** and return `404` — *not* `403`. The frontend should normally show the module when permissions allow it, but must treat `404` on these paths as "bill-payment module disabled by operations", not as a missing payment.

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/backoffice/bill-payments?status&providerId&customerId&minAmount&maxAmount&fromDate&toDate&page&size` | `BILL_PAYMENT_PROCESS_VIEW` | query | `200 PagedResponse<BillPaymentProcessingResponse>` |
| GET | `/api/v1/backoffice/bill-payments/{id}` | `BILL_PAYMENT_PROCESS_VIEW` | none | `200 ApiResponse<BillPaymentProcessingResponse>` |
| POST | `/api/v1/backoffice/bill-payments/{id}/take` | `BILL_PAYMENT_PROCESS` | none | `200 ApiResponse<BillPaymentProcessingResponse>` |
| POST | `/api/v1/backoffice/bill-payments/{id}/release` | `BILL_PAYMENT_PROCESS` | none or `BillPaymentReasonRequest` | `200 ApiResponse<BillPaymentProcessingResponse>` |
| POST | `/api/v1/backoffice/bill-payments/{id}/complete` | `BILL_PAYMENT_COMPLETE` | `multipart/form-data` (`externalReference`, `internalNotes?`, `file`) + header `X-Second-Approver-Operator-Id?` | `200 ApiResponse<CompleteBillPaymentResult>` |
| POST | `/api/v1/backoffice/bill-payments/{id}/refund` | `BILL_PAYMENT_REFUND` | `multipart/form-data` (`reason`, `file?`) | `200 ApiResponse<RefundBillPaymentResult>` |
| POST | `/api/v1/backoffice/bill-payments/{id}/requeue` | `BILL_PAYMENT_REQUEUE` | `BillPaymentReasonRequest` | `200 ApiResponse<RequeueBillPaymentResult>` |
| POST | `/api/v1/backoffice/bill-payments/{id}/force-release` | `BILL_PAYMENT_FORCE_RELEASE` | `BillPaymentReasonRequest` | `200 ApiResponse<RequeueBillPaymentResult>` |
| GET | `/api/v1/backoffice/bill-payments/{id}/proof` | `BILL_PAYMENT_PROOF_VIEW` | none | `200 <stored Content-Type>` raw bytes |

The list is **FIFO** (`created_at ASC`) so the oldest unprocessed payment surfaces first; the default scope for the worklist is `status=QUEUED` (operators may also filter on `IN_PROCESSING` to see what is being worked). `page`/`size` map to the `PagedResponse` envelope — `nextCursor` carries the next page number when `hasMore=true`.

#### Take / Release / Force-release (the assignment lock)

A single payment can be processed by only one operator at a time. **Take** transitions `QUEUED → IN_PROCESSING` and gives the caller an exclusive **assignment** for **30 minutes** (the time to go execute the operation on the provider's platform). The lock is a DB unique constraint, so two operators taking the same payment simultaneously is impossible — exactly one wins and the loser gets `409 BILL_PAYMENT_ALREADY_ASSIGNED` (whose message names the current holder).

- **Release** (`…/release`) lets the **owning** operator drop their own assignment; the payment returns to `QUEUED`. Calling it on an assignment owned by someone else returns `403`.
- **Force-release** (`…/force-release`) lets a **supervisor** (`BILL_PAYMENT_FORCE_RELEASE`) drop **another** operator's assignment — for when an operator is unreachable and the 30-min TTL has not yet elapsed. The payment returns to `QUEUED`.
- An assignment also expires automatically after its 30-min TTL; a background sweeper releases it and requeues the payment.

#### Complete (settle the funds)

`…/complete` finalises a payment to `SUCCEEDED`. This is the moment the held funds are actually settled (debited for good) — never before. Constraints:

- The payment must be `IN_PROCESSING` and the caller must be the **current assignment holder** (`403 BILL_PAYMENT_OPERATOR_MISMATCH` otherwise).
- `multipart/form-data`: a **proof file is mandatory** (the provider receipt) — JPEG, PNG, or PDF, ≤ 10 MB, content byte-sniffed (the declared MIME type and extension are not trusted). `externalReference` is the provider transaction number (e.g. the MAMWE / Canal+ reference). `internalNotes` is optional and operator-only.
- **4-eyes above threshold.** When the held amount is **≥ 100 000 KMF** (`komopay.billpay.four-eyes-threshold-kmf`), a second approver is mandatory: send header `X-Second-Approver-Operator-Id` with the UUID of a **different** operator who also holds `BILL_PAYMENT_COMPLETE`. Below the threshold the header is ignored. A missing or invalid second approver returns `422` (`BILL_PAYMENT_SECOND_APPROVER_REQUIRED` / `_INVALID`); the same operator twice is rejected.

#### Refund / Requeue

- `…/refund` finalises to `FAILED_REFUNDED`: the hold is released and the customer is reimbursed atomically. `reason` is required; a proof file is optional. No 4-eyes.
- `…/requeue` returns the payment to `QUEUED` (e.g. a temporary provider issue): the funds stay held, `retryCount` is incremented, the assignment is released. `reason` is required.

#### Proof viewing

`…/proof` streams the decrypted proof bytes with its stored `Content-Type` (`image/jpeg`, `image/png`, `application/pdf`). Proofs are stored encrypted at rest (AES-256-GCM, same mechanism as KYC documents); the frontend must not access storage directly. Pick the preview mode from the returned `Content-Type` exactly as for KYC files ([5.3a](#53a-customer-kyc-review)). Each view emits a `BILL_PAYMENT_PROOF_VIEWED` audit event.

#### Action visibility by status and permission

Operator actions are gated by both the payment's status and the operator's permissions. **Hide** (do not merely disable) an action button when the permission is missing.

| Status | Available actions |
|---|---|
| `QUEUED` | Take |
| `IN_PROCESSING` (assignment held by caller) | Complete · Refund · Requeue · Release |
| `IN_PROCESSING` (assignment held by another operator) | Force-release (supervisor only) |
| `SUCCEEDED` | View proof (read-only) |
| `FAILED_REFUNDED` | View proof if one was attached (read-only) |
| `FAILED_RETRY` | No direct BO action; refresh/escalate as an exceptional non-terminal row |

> Current manual requeue/release returns the row to `QUEUED` and increments `retryCount`. `FAILED_RETRY` remains a valid backend status but is not the normal operator retry state and is not accepted by `take`.

---

### 5.22 Notifications (In-App Inbox)

Backoffice users have access to the same in-app notification inbox as end-users, scoped to their own principal. BO-facing notification categories are `BILL_PAYMENT`, `APPROVAL`, and `RECONCILIATION`; `TRANSACTION` exists for non-BO actors but is not produced for BO today.

> These endpoints live under `/api/v1/notifications/**` (a shared controller), **not** under `/api/v1/backoffice/*`. They accept any of `CUSTOMER`, `MERCHANT`, `AGENT`, `BACKOFFICE_USER` and silently scope every query to the JWT principal `(actorType, actorId)` — a backoffice user can never see or mutate another user's notifications.

| Method | Path | Auth | Request | Response |
|---|---|---|---|---|
| GET | `/api/v1/notifications?limit` | Backoffice JWT | `limit` (default 20, max 100) | `200 ApiResponse<List<NotificationResponse>>` |
| GET | `/api/v1/notifications/unread` | Backoffice JWT | none | `200 ApiResponse<UnreadCountResponse>` — `{ unread: long }` |
| POST | `/api/v1/notifications/{id}/read` | Backoffice JWT | none | `200 ApiResponse<null>` (`403` foreign notification, `404` not found) |
| POST | `/api/v1/notifications/read-all` | Backoffice JWT | none | `200 ApiResponse<MarkAllReadResponse>` — `{ updated: int }` |

```ts
NotificationResponse = {
  id: uuid;
  category: string;        // "BILL_PAYMENT" | "APPROVAL" | "RECONCILIATION" for BO
  title: string;           // pre-rendered French
  body: string;            // pre-rendered, usually includes a short reference
  data?: string;           // raw JSON string; parse by category/type
  status: string;          // "UNREAD" | "READ"
  createdAt: instant;
  readAt?: instant;        // null when UNREAD
}
```

**Delivery model — what the BO frontend must know:**

- Notifications are written **asynchronously** by backend pollers (default cadence: 5 s). Expect a few-second delay between the source event and the inbox row appearing — refetch, do not insert locally.
- `BILL_PAYMENT` fires on `SERVICE_PAYMENT_QUEUED`: one notification is created for **every active backoffice user holding `BILL_PAYMENT_PROCESS_VIEW`**. `data` carries `{ "billPaymentId": uuid, "type": "SERVICE_PAYMENT_QUEUED" }`. Tap a row → mark read optimistically, then deep-link to the payment in the worklist ([5.21](#521-bill-payment-processing-operator-worklist)) using `billPaymentId`.
- `APPROVAL` fires on `APPROVAL_REQUESTED`, `APPROVAL_APPROVED`, and `APPROVAL_REJECTED`. Pending requests fan out to every active BO user holding the approval-specific permission from [10.2](#102-approval-permission-map); the maker is excluded when `requestedBy` is present. Approved/rejected notifications go only to the maker. `data` carries `{ "approvalId": uuid, "approvalType": ApprovalType, "type": "APPROVAL_REQUESTED" | "APPROVAL_APPROVED" | "APPROVAL_REJECTED" }`. For `APPROVAL_REQUESTED`, deep-link to the approval detail using `approvalId`. For maker decision notifications, treat the row as informational unless the current user also has the approval-specific permission; `GET /api/v1/backoffice/approvals/{id}` still requires checker permission and may return `403`.
- `RECONCILIATION` fires on `RECONCILIATION_INCIDENT_OPENED`: one notification is created for every active BO user holding `RECONCILIATION_VIEW`. `data` carries `{ "incidentId": uuid, "incidentType": ReconciliationIncidentType, "type": "RECONCILIATION_INCIDENT_OPENED" }`; deep-link to the reconciliation incident detail using `incidentId`. Resolved/closed incidents do not create inbox notifications.
- Treat unknown `type`/`category` as forward-compatibility room.
- Inbox is **pull-only** (no WebSocket/SSE/FCM). Poll `/unread` for the bell badge on shell mount and after each `read`/`read-all`.

---

## 6. Request Schemas

Types: `uuid`, `string`, `long`, `int`, `boolean`, `instant`, `date`, enum names as strings unless the DTO field is an enum.

### 6.1 Auth

```ts
BackofficeLoginRequest = {
  email: string;      // email, required, max 255
  password: string;   // required, min 8, max 128
}

// Body for POST /password-setup. Bearer must be the single-use passwordSetupToken
// from login branch B (see 3.1a).
BackofficePasswordSetupRequest = {
  newPassword: string; // required, min 8, max 128
}

// Body for POST /login/verify-mfa (no bearer). challengeId comes from login branch D.
VerifyMfaRequest = {
  challengeId: uuid;   // required
  code: string;        // required, exactly 6 digits
}

// Body for POST /totp-confirm (step 2 of enrollment).
TotpConfirmRequest = {
  code: string;        // required, exactly 6 digits
}

// Body for DELETE /totp-setup (step-up to disable MFA).
TotpRevokeRequest = {
  code: string;        // required, exactly 6 digits
}

RefreshTokenRequest = {
  refreshToken: string; // required
}
```

### 6.2 User And Role

```ts
CreateBackofficeUserRequest = {
  email: string;       // email, required, max 255
  password: string;    // required, min 8, max 100
  fullName: string;    // required, max 255
  role: BackofficeRole;
}

ElevateRoleRequest = {
  newRole: BackofficeRole;
}

ApprovalDecisionRequest = {
  reason?: string; // max 500
}
```

### 6.3 Actors

```ts
CreateAgentRequest = {
  fullName: string;
  phoneCountryCode: string; // max 10
  phoneNumber: string;      // max 20
  zone?: string;            // max 100
  contractRef?: string;     // max 255
}

CreateMerchantRequest = {
  businessName: string;       // max 255
  legalName: string;          // max 255
  businessType: BusinessType;
  taxId?: string;             // max 100
  phoneCountryCode: string;   // max 10
  phoneNumber: string;        // max 20
  addressIsland?: string;     // max 100
  addressCity?: string;       // max 100
  addressDistrict?: string;   // max 100
  category: MerchantCategory;
}

ActionReasonRequest = {
  reason?: string; // max 500
}

AgentFundRequest = {
  amount: long;    // positive
  notes?: string;  // max 500
}
```

### 6.3a KYC/KYB Review

```ts
KycDocumentUploadFormData = {
  documentType: KycDocumentType; // required
  file: binary;                  // required, max 10 MB, JPEG/PNG/PDF by byte sniff
}

RejectKycDocumentRequest = {
  reason: string;  // required, non-blank, max 1000
}

ChangeKycLevelRequest = {
  kycLevel: KycLevel;   // required; monotonic increase enforced server-side
  nextReviewDate?: date;
}

ChangeActorKycLevelRequest = {
  kycLevel: KycLevel;   // required; monotonic increase enforced server-side
}
```

`ChangeKycLevelRequest.nextReviewDate` is stored on the customer as `kycNextReviewDate`. The frontend may leave it null when the use case doesn't require a fixed review horizon.

`KycDocumentUploadFormData` is used only by BO agent/merchant document upload endpoints. Customer document upload is not exposed under `/api/v1/backoffice/*`.

### 6.4 Cards And Card Stock

```ts
CloseCardRequest = {
  reason?: string;
}

ImportCardBatchRequest = {
  batchRef: string;
  producedAt?: date;
  cards: Array<{
    nfcUid: string;                 // required, /^[0-9A-F]{14}$/
    internalCardNumber: string;
    authKeyEncryptedBase64?: string;
    authKeyVersion: int;            // positive or zero
  }>;
}

AssignCardStockRequest = {
  agentId: uuid;
  cardStockIds: uuid[]; // non-empty
}
```

### 6.5 Terminals

```ts
RegisterTerminalRequest = {
  serialNumber: string;  // required, max 100
  deviceModel?: string;  // max 100
  androidVersion?: string; // max 30
  appVersion?: string;     // max 30
  merchantId: uuid;
}
```

### 6.6 Transactions

```ts
CreateLargeCashOutApprovalRequest = {
  merchantId: uuid;
  agentId: uuid;
  amount: long;       // min 1
  reason: string;     // min 3, max 500
}

CreateReversalApprovalRequest = {
  transactionId: uuid;
  reason: string;     // min 3, max 500
}
```

### 6.7 Fee And Commission Rules

```ts
FeeTier = {
  minAmount: long;
  maxAmount?: long;
  flatAmount: long;
}

CreateFeeRuleRequest = {
  name: string;                  // required, max 150
  description?: string;          // max 500
  transactionType: string;       // TransactionType name
  actorType?: string;            // ActorType name
  actorId?: uuid;
  cardType?: string;             // CardType name
  merchantCategory?: string;     // MerchantCategory name
  minAmount?: long;
  maxAmount?: long;
  zone?: string;                 // max 100
  serviceProviderId?: uuid;
  promoCode?: string;            // max 50
  calculationType: FeeCalculationType;
  flatAmount?: long;
  percentage?: decimal;          // percentage points: 1.98 means 1.98%, not 0.0198
  minFeeAmount?: long;
  maxFeeAmount?: long;
  feeBearer: FeeBearer;
  priority: int;                 // min 1
  validFrom: instant;
  validTo?: instant;
  tiers?: FeeTier[];
  activeOnApproval: boolean;
}

CreateCommissionRuleRequest = {
  name: string;                  // required, max 150
  transactionType: string;       // TransactionType name
  agentId?: uuid;
  calculationType: CommissionCalculationType;
  flatAmount?: long;
  percentage?: decimal;          // percentage points: 1.98 means 1.98%, not 0.0198
  settlementMode: SettlementMode;
  priority: int;                 // min 1
  validFrom: instant;
  validTo?: instant;
  activeOnApproval: boolean;
}
```

Calculation shape rules:

- `percentage` is expressed in percentage points across fee and commission rules.
  Example: send `1.98` for `1.98%`. Do not send `0.0198` unless the intended rate is `0.0198%`.
  The backend stores and returns the same value without UI/API conversion.
- Fee `FLAT` requires `flatAmount`.
- Fee `PERCENTAGE` requires `percentage`.
- Fee `TIERED` requires non-empty `tiers`.
- Fee `MAX_OF` and `MIN_OF` require `flatAmount` and `percentage`.
- Fee `ZERO` requires no amount fields.
- Commission `FLAT` requires `flatAmount`.
- Commission `ON_TRANSACTION_AMOUNT` and `ON_FEE_AMOUNT` require `percentage`.

### 6.8 Settlements

```ts
TriggerSettlementRequest = {
  mode: "BATCH_DAILY" | "BATCH_WEEKLY";
  businessDay?: date; // defaults to yesterday UTC
}

RequestSettlementRequest = {
  providerCode: string; // required — must match an existing ServiceProvider.code
  amount: long; // min 1
  externalReference?: string;
  notes?: string;
}

RequestPlatformRevenueWithdrawalRequest = {
  amount: long; // min 1
  notes?: string;
}
```

### 6.9 Limit Profiles

```ts
LimitProfileOperationLimitDto = {
  maxTransactionAmount?: long;
  minTransactionAmount?: long;
  maxDailyAmount?: long;
  maxWeeklyAmount?: long;
  maxMonthlyAmount?: long;
  maxDailyTransactionCount?: int;
  maxMonthlyTransactionCount?: int;
}

LimitProfileRequest = {
  name: string;
  applicableActorTypes: string[]; // non-empty ActorType names
  maxTransactionAmount?: long;
  minTransactionAmount?: long;
  maxDailyAmount?: long;
  maxWeeklyAmount?: long;
  maxMonthlyAmount?: long;
  maxDailyTransactionCount?: int;
  maxMonthlyTransactionCount?: int;
  requiredKycLevel: string; // KycLevel name
  operationLimits?: Record<TransactionType, LimitProfileOperationLimitDto>;
}

AssignLimitProfileRequest = {
  limitProfileId: uuid;
}
```

### 6.10 Control Thresholds

```ts
ControlThresholdRequest = {
  transactionType: string; // TransactionType name
  actorType: string;       // ActorType name
  scopeType: string;       // ControlThresholdScopeType name
  scopeId?: uuid;
  currency?: string;
  pinRequiredAboveAmount?: long;
  confirmationRequiredAboveAmount?: long;
  approvalRequiredAboveAmount?: long;
  approvalType?: string;   // ApprovalType name
}
```

### 6.11 Reconciliation

```ts
InvestigateIncidentRequest = {}

ResolveIncidentRequest = {
  note: string;
  suspenseAdjustmentAmount: long; // positive or zero
  suspenseDirection?: SuspenseDirection; // required when amount > 0
}

CloseIncidentRequest = {
  note: string;
  clearSuspense: boolean;
}
```

### 6.12 Service Providers And Bill Services

```ts
CreateServiceProviderRequest = {
  name: string;                       // max 200
  code: string;                       // max 60
  supportsReferenceValidation: boolean; // default false
}

UpdateServiceProviderRequest = {
  name: string;                       // max 200
  supportsReferenceValidation: boolean; // default false
}

CreateBillServiceRequest = {
  providerId: uuid; // required by validation, even though providerId is also in path
  name: string;     // max 200
  code: string;     // max 80
  category: BillServiceCategory;
  minAmount?: long; // min 1
  maxAmount?: long; // min 1
}

UpdateBillServiceRequest = {
  name: string;     // max 200
  category: BillServiceCategory;
  minAmount?: long; // min 1
  maxAmount?: long; // min 1
}

ChangeServiceProviderStatusRequest = {
  status: ServiceProviderStatus;  // ACTIVE | MAINTENANCE | SUSPENDED | INACTIVE
  reason?: string;                // free text, recorded in the audit event
}

UpdateProviderBusinessRulesRequest = {
  // processing window (operator business hours)
  processingHoursStart?: string;  // "HH:mm" / "HH:mm:ss", local time (Indian/Comoro, UTC+3)
  processingHoursEnd?: string;    // "HH:mm" / "HH:mm:ss"
  processingDays?: string;        // "MON-SAT" | "MON-FRI" | "MON-SUN" | "CUSTOM:1,3,5"
  announcedDelayHours?: int;      // >= 0; what the customer app shows as the promise
  // client-reference validation rules (applied server-side before the hold)
  referenceRegex?: string;        // max 256
  referenceMinLength?: int;       // > 0
  referenceMaxLength?: int;       // > 0; must be >= referenceMinLength
  referenceExample?: string;      // max 64; shown to the customer on a format error
}
```

All `UpdateProviderBusinessRulesRequest` fields are optional; only the supplied ones are changed. `processingDays` accepts the named ranges above or a `CUSTOM:` list of ISO weekday numbers (1=Monday). A malformed `referenceRegex` is treated server-side as "no regex" (fail-open) so a bad rule never blocks all payments.

Do not send removed online-integration fields in service-provider payloads. The backend no longer accepts `type`, `baseUrl`, `credentialsRef`, `callbackSecretRef`, `timeoutMillis`, `maxRetries`, `retryBackoffMillis` or `sandbox`.

### 6.13 Bill-Payment Processing

```ts
BillPaymentReasonRequest = {
  reason: string;   // non-blank, max 1000; required when body is sent (release / requeue / force-release)
}

// complete: multipart/form-data, not JSON
BillPaymentCompleteFormData = {
  externalReference: string;   // required — provider transaction number (MAMWE / Canal+ ...)
  internalNotes?: string;      // optional, operator-only
  file: binary;                // required, max 10 MB, JPEG/PNG/PDF by byte sniff
  // header (not a form field): X-Second-Approver-Operator-Id?: uuid
  //   required only when heldAmount >= four-eyes threshold (default 100 000 KMF);
  //   must be a different operator holding BILL_PAYMENT_COMPLETE
}

// refund: multipart/form-data, not JSON
BillPaymentRefundFormData = {
  reason: string;   // required
  file?: binary;    // optional proof, max 10 MB, JPEG/PNG/PDF by byte sniff
}
```

---

## 7. Response Schemas

### 7.1 Auth And Users

```ts
// Returned by POST /login only. Exactly one branch is populated; absent fields
// are omitted from the JSON (NON_NULL). Inspect passwordSetupRequired first.
// Exactly one branch is populated; inactive flags/fields may be absent (NON_NULL).
// Inspect the booleans in order: passwordSetup → mfaEnrollment → mfaRequired → tokens.
BackofficeLoginResponse = {
  // Branch A — full session.
  tokens?: TokenResponse;
  // Branch B — single-use password setup token, no session.
  passwordSetupRequired: boolean;
  passwordSetupToken?: string;
  passwordSetupTokenExpiresAt?: instant;
  // Branch D — TOTP challenge, no session yet.
  mfaRequired: boolean;
  challengeId?: uuid;
  mfaFactor?: string;          // always "TOTP"
  // Branch C — mandatory-role MFA enrollment, single-use token, no session.
  mfaEnrollmentRequired: boolean;
  mfaEnrollmentToken?: string;
  mfaEnrollmentTokenExpiresAt?: instant;
}

// Returned by POST /refresh and POST /login/verify-mfa (flat), and nested as
// BackofficeLoginResponse.tokens.
TokenResponse = {
  tokenType: "Bearer";
  accessToken: string;
  accessTokenExpiresAt: instant;
  refreshToken: string;
  refreshTokenExpiresAt: instant;
}

// Returned by POST /totp-setup. Both fields are sensitive — treat as credentials.
TotpSetupResponse = {
  secret: string;   // base32, for manual entry
  qrUri: string;    // otpauth://totp/... — render as a QR code
}

BackofficeUserResponse = {
  id: uuid;
  email: string;
  fullName: string;
  role: string;
  permissions: string[];
  status: string;
  mfaEnabled: boolean;
  lastLoginAt?: instant;
  createdAt: instant;
}

ElevateRoleResponse = {
  status: "APPLIED" | "PENDING_APPROVAL";
  approvalId?: uuid;
  user?: BackofficeUserResponse;
}
```

### 7.2 Actors And Wallets

```ts
CustomerResponse = {
  id: uuid;
  externalRef: string;
  fullName: string;
  dateOfBirth?: date;
  phoneCountryCode: string;
  phoneNumber: string;
  nationalIdNumber?: string;
  kycLevel: string;
  kycVerifiedAt?: instant;
  status: string;
  walletId: uuid;
  limitProfileId?: uuid;
  createdAt: instant;
}

AgentResponse = {
  id: uuid;
  externalRef: string;
  fullName: string;
  phoneCountryCode: string;
  phoneNumber: string;
  zone?: string;
  kycLevel: string;
  status: string;
  walletId?: uuid;       // null while PENDING_KYC; set on activation
  limitProfileId?: uuid;
  canSellCards: boolean;
  canDoCashIn: boolean;
  canDoCashOut: boolean;
  createdAt: instant;
}

MerchantResponse = {
  id: uuid;
  externalRef: string;
  businessName: string;
  legalName: string;
  businessType: string;
  taxId?: string;
  phoneCountryCode: string;
  phoneNumber: string;
  category: string;
  kycLevel: string;
  status: string;
  walletId?: uuid;       // null while PENDING_KYC; set on activation
  limitProfileId?: uuid;
  canCashOut: boolean;
  canReceiveFromMerchant: boolean;
  canIssuePaymentRequest: boolean;   // toggled via /merchants/{id}/payment-request/enable|disable
  createdAt: instant;
}

WalletResponse = {
  id: uuid;
  ownerType: string;
  ownerId: uuid;
  currency: string;
  status: string;
  availableBalance: long;
  frozenBalance: long;
  version: long;
  createdAt: instant;
  updatedAt: instant;
}

KycDocumentResponse = {
  id: uuid;
  ownerActorType: ActorType;             // CUSTOMER, AGENT, or MERCHANT
  ownerActorId: uuid;
  documentType: KycDocumentType;
  contentHash: string;                   // SHA-256 hex of the original (unencrypted) bytes
  contentType: "image/jpeg" | "image/png" | "application/pdf" | "application/octet-stream";
  uploadedByActorType: ActorType;
  uploadedByActorId: uuid;
  uploadedAt: instant;
  status: KycDocumentStatus;             // PENDING_REVIEW | ACCEPTED | REJECTED
  reviewedByUserId?: uuid;               // present once decided
  reviewedAt?: instant;                  // present once decided
  rejectionReason?: string;              // present only when status = REJECTED
}
```

`storageRef` is intentionally absent from the response. Customer file bytes are reachable only through `GET /api/v1/backoffice/kyc-documents/{id}/file`; agent and merchant file bytes use their owner-scoped paths under `/api/v1/backoffice/agents/kyc-documents/{id}/file` and `/api/v1/backoffice/merchants/kyc-documents/{id}/file`.

### 7.3 Approvals And Audit

```ts
ApprovalRequestResponse = {
  id: uuid;
  type: string;
  requestedBy: uuid;
  targetEntityType: string;
  targetEntityId: uuid;
  payload: string; // JSON stored as a string
  status: string;
  approvedBy?: uuid;
  rejectedBy?: uuid;
  decisionAt?: instant;
  decisionReason?: string;
  expiresAt: instant;
  createdAt: instant;
}

AuditEventResponse = {
  id: uuid;
  eventType: string;
  actorId?: uuid;
  actorType?: string;
  targetEntityType?: string;
  targetEntityId?: uuid;
  payload?: string;
  ipAddress?: string;
  userAgent?: string;
  correlationId?: uuid;
  occurredAt: instant;
}
```

### 7.4 Cards, Stock, Terminals

```ts
CardResponse = {
  id: uuid;
  nfcUid?: string;
  internalCardNumber: string;
  walletId: uuid;
  customerId: uuid;
  cardType?: string;
  status: string;
  pinEnabled: boolean;
  issuedByAgentId?: uuid;
  issuedAt: instant;
  activatedAt?: instant;
  expiresAt?: date;
  lastUsedAt?: instant;
  lastUsedTerminalId?: uuid;
  replacedByCardId?: uuid;
  replacementOfCardId?: uuid;
}

CardStockResponse = {
  id: uuid;
  nfcUid: string;
  internalCardNumber: string;
  batchRef: string;
  producedAt?: date;
  importedAt: instant;
  importedByUserId: uuid;
  authKeyVersion: int;
  status: CardStockStatus;
  assignedAgentId?: uuid;
  assignedAt?: instant;
  soldToCustomerId?: uuid;
  soldAt?: instant;
  cardId?: uuid;
}

TerminalResponse = {
  id: uuid;
  serialNumber: string;
  deviceModel?: string;
  androidVersion?: string;
  appVersion?: string;
  merchantId: uuid;
  status: string;
  apiKeyIssuedAt?: instant;
  apiKeyExpiresAt?: instant;
  lastAuthAt?: instant;
  authFailedCount: int;
  registeredAt: instant;
}

ProvisionTerminalResponse = {
  terminalId: uuid;
  serialNumber: string;
  status: string;
  rawApiKey: string;
  apiKeyIssuedAt: instant;
  apiKeyExpiresAt: instant;
}
```

### 7.5 Transactions And Rules

```ts
TransactionResponse = {
  id: uuid;
  idempotencyKey?: string;
  type: string;
  status: string;
  sourceWalletId?: uuid;
  destinationWalletId?: uuid;
  initiatorType: string;
  initiatorId: uuid;
  operatorId?: uuid;
  cardId?: uuid;
  terminalId?: uuid;
  requestedAmount: long;
  feeAmount: long;
  commissionAmount: long;
  netAmountToDestination: long;
  currency: string;
  appliedFeeRuleId?: uuid;
  appliedCommissionRuleId?: uuid;
  reversalOfTransactionId?: uuid;
  reversedByTransactionId?: uuid;
  reversalReason?: string;
  declineReason?: string;
  correlationId?: uuid;
  createdAt: instant;
  authorizedAt?: instant;
  completedAt?: instant;
  declinedAt?: instant;
}

FeeRuleResponse = {
  id: uuid;
  name: string;
  description?: string;
  transactionType?: string;
  actorType?: string;
  actorId?: uuid;
  cardType?: string;
  merchantCategory?: string;
  minAmount?: long;
  maxAmount?: long;
  calculationType: string;
  flatAmount?: long;
  percentage?: decimal;          // percentage points: 1.98 means 1.98%, not 0.0198
  minFeeAmount?: long;
  maxFeeAmount?: long;
  feeBearer: string;
  priority: int;
  validFrom: instant;
  validTo?: instant;
  active: boolean;
  version: int;
  previousVersionId?: uuid;
  createdAt: instant;
}

CommissionRuleResponse = {
  id: uuid;
  name: string;
  transactionType: string;
  agentId?: uuid;
  calculationType: string;
  flatAmount?: long;
  percentage?: decimal;          // percentage points: 1.98 means 1.98%, not 0.0198
  settlementMode: string;
  currency: string;
  priority: int;
  validFrom: instant;
  validTo?: instant;
  active: boolean;
  version: int;
  previousVersionId?: uuid;
  createdBy: uuid;
  createdAt: instant;
  modifiedAt?: instant;
}
```

### 7.6 Settlements And Configuration

```ts
CommissionSettlementRunResponse = {
  id: uuid;
  mode: string;
  businessDay: date;
  triggeredByType: string;
  triggeredById?: uuid;
  startedAt: instant;
  completedAt?: instant;
  agentsTotal: int;
  agentsSettled: int;
  agentsFailed: int;
  payoutsSettled: int;
  amountSettled: long;
  status: string;
  errorSummary?: string;
}

CommissionPendingSummaryResponse = {
  pendingDailyCount: long;
  pendingDailyAmount: long;
  pendingWeeklyCount: long;
  pendingWeeklyAmount: long;
}

BillProviderSettlementBalancesResponse = {
  providerPayableBalance: long;
  settlementClearingBalance: long;
  currency: string;
}

PlatformRevenueBalancesResponse = {
  revenueBalance: long;
  withdrawalClearingBalance: long;
  currency: string;
}

LimitProfileResponse = {
  id: string;
  name: string;
  applicableActorTypes: string[];
  maxTransactionAmount?: long;
  minTransactionAmount?: long;
  maxDailyAmount?: long;
  maxWeeklyAmount?: long;
  maxMonthlyAmount?: long;
  maxDailyTransactionCount?: int;
  maxMonthlyTransactionCount?: int;
  requiredKycLevel: string;
  operationLimits: Record<string, LimitProfileOperationLimitDto>;
  active: boolean;
  version: int;
  createdAt: instant;
  updatedAt: instant;
}

ControlThresholdResponse = {
  id: uuid;
  transactionType: string;
  actorType: string;
  scopeType: string;
  scopeId?: uuid;
  currency: string;
  pinRequiredAboveAmount?: long;
  confirmationRequiredAboveAmount?: long;
  approvalRequiredAboveAmount?: long;
  approvalType?: string;
  active: boolean;
  version: int;
  createdAt: instant;
  updatedAt: instant;
}
```

### 7.7 Reconciliation

```ts
ReconciliationIncidentResponse = {
  id: uuid;
  runId: uuid;
  incidentType: ReconciliationIncidentType;
  status: ReconciliationIncidentStatus;
  description: string;
  discrepancyAmount: long;
  suspenseEntryRef?: uuid;
  investigatedBy?: uuid;
  investigatedAt?: instant;
  resolvedBy?: uuid;
  resolvedAt?: instant;
  resolutionNote?: string;
  closedBy?: uuid;
  closedAt?: instant;
  closureNote?: string;
  openedAt: instant;
}

ReconciliationRunResponse = {
  id: uuid;
  startedAt: instant;
  completedAt?: instant;
  windowFrom: instant;
  windowTo: instant;
  status: ReconciliationStatus;
  transactionsChecked: long;
  walletsChecked: long;
  mismatchCount: int;
  details?: string;
}

ReconciliationIncidentActionResponse = {
  outcome: "APPLIED" | "PENDING_APPROVAL";
  incident?: ReconciliationIncidentResponse;
  approvalRequest?: ApprovalRequestResponse;
}
```

### 7.8 Regulatory Reports

```ts
TransactionSummaryReportResponse = {
  from: instant;
  to: instant;
  groupBy: string;
  lines: Array<{
    type: string;
    period: instant;
    count: long;
    totalAmountKmf: long;
    totalFeesKmf: long;
    totalCommissionsKmf: long;
  }>;
}

KycSummaryReportResponse = {
  lines: Array<{
    actorType: string;
    kycLevel: string;
    status: string;
    count: long;
  }>;
}

AmlTransactionResponse = {
  id: string;
  type: string;
  status: string;
  requestedAmountKmf: long;
  feeAmountKmf: long;
  sourceWalletId: string;
  destinationWalletId: string;
  initiatorType: string;
  initiatorId: string;
  channelType: string;
  createdAt: instant;
  completedAt?: instant;
}

FloatReportResponse = {
  generatedAt: instant;
  customerTotalBalanceKmf: long;
  merchantTotalBalanceKmf: long;
  agentTotalBalanceKmf: long;
  actorTotalBalanceKmf: long;
  systemFloatBalanceKmf: long;
  systemLiquidityBalanceKmf: long;
  systemRevenueBalanceKmf: long;
  systemCommissionsBalanceKmf: long;
  systemSuspenseBalanceKmf: long;
  ledgerTotalDebitKmf: long;
  ledgerTotalCreditKmf: long;
  doubleEntryIntegrityOk: boolean;
  floatDiscrepancy: long;
}

ActorSummaryReportResponse = {
  lines: Array<{
    actorType: string;
    status: string;
    count: long;
  }>;
}

ReportExportResponse = {
  id: uuid;
  reportType: string;
  periodFrom?: instant;
  periodTo?: instant;
  generatedByUserId: uuid;
  generatedAt: instant;
  recordCount: int;
}
```

CSV headers:

```text
transactions/summary: type,period,count,total_amount_kmf,total_fees_kmf,total_commissions_kmf
kyc/summary: actor_type,kyc_level,status,count
actors/summary: actor_type,status,count
```

### 7.9 Service Providers

```ts
ServiceProviderResponse = {
  id: uuid;
  name: string;
  code: string;
  status: ServiceProviderStatus;       // ACTIVE | MAINTENANCE | SUSPENDED | INACTIVE
  supportsReferenceValidation: boolean;
  // manual/deferred business rules (editable via PATCH …/business-rules)
  processingHoursStart?: string;       // "HH:mm:ss" local (Indian/Comoro)
  processingHoursEnd?: string;         // "HH:mm:ss"
  processingDays?: string;             // "MON-SAT" | "MON-FRI" | "MON-SUN" | "CUSTOM:1,3,5"
  announcedDelayHours?: int;
  referenceRegex?: string;
  referenceMinLength?: int;
  referenceMaxLength?: int;
  referenceExample?: string;
  createdAt: instant;
  updatedAt: instant;
}

BillServiceResponse = {
  id: uuid;
  name: string;
  code: string;
  providerId: uuid;
  category: BillServiceCategory;
  status: BillServiceStatus;
  minAmount?: long;
  maxAmount?: long;
  createdAt: instant;
}
```

### 7.10 Bill-Payment Processing

`BillPaymentProcessingResponse` is the **operator worklist/detail view**. It carries the business and processing fields needed to decide what action is available. It is returned by list/detail plus take/release. Complete, refund, requeue and force-release return the compact result DTOs below. It is **not** the customer-facing DTO (the customer sees a filtered subset — see the Customer/Merchant spec).

```ts
BillPaymentProcessingResponse = {
  id: uuid;
  customerId: uuid;
  serviceId: uuid;
  providerId: uuid;
  reference: string;                   // client-entered bill reference (meter/account no.)
  requestedAmount: long;
  feeAmount: long;
  netAmount: long;
  heldAmount?: long;                   // amount frozen on the customer wallet
  currency: "KMF";
  status: BillPaymentStatus;           // QUEUED | IN_PROCESSING | SUCCEEDED | FAILED_REFUNDED | FAILED_RETRY
  retryCount: int;
  externalReference?: string;          // provider tx number, set on complete
  proofRef?: uuid;                     // id of the stored proof; fetch bytes via …/proof
  internalNotes?: string;              // operator-only, set on complete/refund
  processedByOperatorId?: uuid;        // owner/last operator; null for system actions (e.g. auto-expiry)
  secondApproverOperatorId?: uuid;     // set only on a 4-eyes complete
  queuedAt?: instant;
  processingStartedAt?: instant;
  createdAt: instant;
  completedAt?: instant;
}

CompleteBillPaymentResult = {
  billPaymentId: uuid;
  status: BillPaymentStatus;           // SUCCEEDED on success
  proofId: uuid;
  externalReference: string;
  completedAt: instant;
}

RefundBillPaymentResult = {
  billPaymentId: uuid;
  status: BillPaymentStatus;           // FAILED_REFUNDED on success
  proofId?: uuid;
  reason: string;
  completedAt: instant;
}

RequeueBillPaymentResult = {
  billPaymentId: uuid;
  status: BillPaymentStatus;           // QUEUED on success
  retryCount: int;
  reason: string;
}
```

The list endpoint wraps `BillPaymentProcessingResponse` in `PagedResponse` (FIFO, `created_at ASC`). Detail, take and release wrap `BillPaymentProcessingResponse` in `ApiResponse`. Use `processedByOperatorId` to distinguish a payment held by the current operator from one held by another operator.

---

## 8. Approval Payload Schemas

`ApprovalRequestResponse.payload` is serialized JSON stored as a **string**. The UI must parse it only when it needs type-specific details.

### 8.1 Payloads By Approval Type

```ts
REVERSAL payload = {
  reason: string;
}
```

`REVERSAL` target:

- `targetEntityType = "TRANSACTION"`
- `targetEntityId = transactionId`

```ts
ACCOUNT_CLOSURE payload = {
  actorType: ActorType;
  reason?: string;
}
```

`ACCOUNT_CLOSURE` target entity is the customer, merchant, or agent id.

```ts
LARGE_CASH_OUT payload = {
  merchantId: uuid;
  agentId: uuid;
  amount: long;
  currency: "KMF";
}

AGENT_FUND_IN payload = {
  agentId: uuid;
  amount: long;
  currency: "KMF";
  notes?: string;
}

AGENT_FUND_OUT payload = {
  agentId: uuid;
  amount: long;
  currency: "KMF";
  notes?: string;
}
```

```ts
BACKOFFICE_USER_PRIVILEGE_ELEVATION payload = {
  targetRole: BackofficeRole;
}
```

```ts
FEE_RULE_CHANGE payload = {
  action: "CREATE" | "SUPERSEDE" | "ACTIVATE" | "DEACTIVATE";
  newRuleId?: uuid;
  previousVersionId?: uuid;
  name?: string;
  description?: string;
  transactionType?: TransactionType;
  actorType?: ActorType;
  actorId?: uuid;
  cardType?: CardType;
  merchantCategory?: MerchantCategory;
  minAmount?: long;
  maxAmount?: long;
  zone?: string;
  serviceProviderId?: uuid;
  promoCode?: string;
  calculationType?: FeeCalculationType;
  flatAmount?: long;
  percentage?: decimal;          // percentage points: 1.98 means 1.98%, not 0.0198
  minFeeAmount?: long;
  maxFeeAmount?: long;
  feeBearer?: FeeBearer;
  currency?: "KMF";
  tiers?: FeeTier[];
  priority?: int;
  validFrom?: instant;
  validTo?: instant;
  activeOnApproval?: boolean;
}
```

```ts
COMMISSION_RULE_CHANGE payload = {
  action: "CREATE" | "SUPERSEDE" | "ACTIVATE" | "DEACTIVATE";
  newRuleId?: uuid;
  previousVersionId?: uuid;
  name?: string;
  transactionType?: TransactionType;
  agentId?: uuid;
  calculationType?: CommissionCalculationType;
  flatAmount?: long;
  percentage?: decimal;          // percentage points: 1.98 means 1.98%, not 0.0198
  settlementMode?: SettlementMode;
  currency?: "KMF";
  priority?: int;
  validFrom?: instant;
  validTo?: instant;
  activeOnApproval?: boolean;
}
```

```ts
LIMIT_PROFILE_CHANGE payload = {
  action: "CREATE" | "SUPERSEDE" | "ACTIVATE" | "DEACTIVATE" | "ASSIGN";
  // For CREATE: pre-generated UUID of the new profile.
  // For SUPERSEDE: pre-generated UUID of the new version (chained from previousProfileId).
  // For ACTIVATE / DEACTIVATE: the targeted profile.
  // For ASSIGN: the profile to assign to actorId.
  limitProfileId: uuid;
  // Settings of the new version (CREATE, SUPERSEDE). null for status / assignment changes.
  command?: LimitProfileRequest;
  // ASSIGN only: actor receiving the profile.
  actorType?: ActorType;
  actorId?: uuid;
  // SUPERSEDE only: UUID of the predecessor row being replaced.
  previousProfileId?: uuid;
}

CONTROL_THRESHOLD_CHANGE payload = {
  action: "CREATE" | "SUPERSEDE" | "ACTIVATE" | "DEACTIVATE";
  // For CREATE: pre-generated UUID of the new threshold.
  // For SUPERSEDE: pre-generated UUID of the new version (chained from previousThresholdId).
  // For ACTIVATE / DEACTIVATE: the targeted threshold.
  thresholdId: uuid;
  command?: ControlThresholdRequest;
  // SUPERSEDE only: UUID of the predecessor row being replaced.
  previousThresholdId?: uuid;
}
```

```ts
SERVICE_PROVIDER_CHANGE payload = {
  action: "CREATE" | "UPDATE" | "ACTIVATE" | "DEACTIVATE";
  targetEntityType: "SERVICE_PROVIDER" | "BILL_SERVICE";
  providerId?: uuid;
  billServiceId?: uuid;
  providerCreate?: {
    name: string;
    code: string;
    supportsReferenceValidation: boolean;
  };
  providerUpdate?: {
    name: string;
    supportsReferenceValidation: boolean;
  };
  billServiceCreate?: {
    name: string;
    code: string;
    category: BillServiceCategory;
    minAmount?: long;
    maxAmount?: long;
  };
  billServiceUpdate?: {
    name: string;
    category: BillServiceCategory;
    minAmount?: long;
    maxAmount?: long;
  };
}
```

```ts
BILL_PROVIDER_SETTLEMENT payload = {
  providerId: string;   // UUID, resolved from providerCode at request time
  providerCode: string;
  amount: long;
  currency: "KMF";
  externalReference?: string;
  notes?: string;
}

PLATFORM_REVENUE_WITHDRAWAL payload = {
  amount: long;
  currency: "KMF";
  notes?: string;
}

RECONCILIATION_ADJUSTMENT payload = {
  action: "RESOLVE" | "CLOSE";
  incidentId: uuid;
  note: string;
  suspenseAmount: long;
  suspenseDirection?: SuspenseDirection;
  clearSuspense: boolean;
}
```

---

## 9. Enums

| Enum | Values |
|---|---|
| `BackofficeRole` | `OPERATOR`, `SUPERVISOR`, `COMPLIANCE`, `ADMIN`, `SUPER_ADMIN` |
| `UserStatus` | `ACTIVE`, `SUSPENDED`, `LOCKED`, `CLOSED` |
| `Permission` | see [10.1](#101-all-backoffice-permissions) |
| `ActorType` | `CUSTOMER`, `MERCHANT`, `AGENT`, `MERCHANT_OPERATOR`, `BACKOFFICE_USER`, `SYSTEM` |
| `CustomerStatus` | `PENDING_KYC`, `ACTIVE`, `SUSPENDED`, `FROZEN`, `CLOSED` |
| `AgentStatus` | `PENDING_KYC`, `ACTIVE`, `SUSPENDED`, `CLOSED` |
| `MerchantStatus` | `PENDING_KYC`, `ACTIVE`, `SUSPENDED`, `FROZEN`, `CLOSED` |
| `BusinessType` | `SOLE_TRADER`, `COMPANY`, `NGO` |
| `MerchantCategory` | `RETAIL`, `FOOD`, `SERVICE`, `TELECOM`, `UTILITY`, `OTHER` |
| `KycLevel` | `KYC_NONE`, `KYC_BASIC`, `KYC_VERIFIED`, `KYC_ENHANCED` |
| `KycDocumentType` | `NATIONAL_ID`, `PASSPORT`, `PROOF_OF_ADDRESS`, `BUSINESS_LICENSE`, `OTHER` |
| `KycDocumentStatus` | `PENDING_REVIEW`, `ACCEPTED`, `REJECTED` |
| `TransactionType` | `CASH_IN`, `PAYMENT`, `CASH_OUT`, `CARD_SALE`, `AGENT_FUND_IN`, `AGENT_FUND_OUT`, `FEE_COLLECTION`, `COMMISSION_PAYOUT`, `REVERSAL`, `P2P_TRANSFER`, `MERCHANT_TO_MERCHANT`, `SERVICE_PAYMENT`, `CARD_REPLACEMENT`, `BILL_PROVIDER_SETTLEMENT`, `PLATFORM_REVENUE_WITHDRAWAL`, `PLATFORM_LIQUIDITY_TOP_UP` |
| `TransactionStatus` | `PENDING`, `AUTHORIZED`, `COMPLETED`, `DECLINED`, `EXPIRED`, `REVERSED` |
| `ChannelType` | `TERMINAL_NFC`, `TERMINAL_MANUAL`, `MOBILE_APP`, `AGENT_CHANNEL`, `WEB_APP`, `BACKOFFICE_UI`, `BACKOFFICE_JOB` |
| `WalletStatus` | `ACTIVE`, `FROZEN`, `SUSPENDED`, `CLOSED` |
| `TerminalStatus` | `REGISTERED`, `ACTIVE`, `SUSPENDED`, `REVOKED` |
| `CardType` | `STANDARD`, `PREMIUM`, `CORPORATE` |
| `CardStatus` | `ISSUED`, `ACTIVE`, `BLOCKED`, `LOST`, `STOLEN`, `EXPIRED`, `CLOSED` |
| `CardStockStatus` | `IN_WAREHOUSE`, `ASSIGNED_TO_AGENT`, `SOLD`, `RETURNED`, `SPOILED` |
| `FeeCalculationType` | `FLAT`, `PERCENTAGE`, `TIERED`, `MAX_OF`, `MIN_OF`, `ZERO` |
| `FeeBearer` | `SENDER`, `RECEIVER`, `PLATFORM` |
| `CommissionCalculationType` | `ON_TRANSACTION_AMOUNT`, `ON_FEE_AMOUNT`, `FLAT` |
| `SettlementMode` | `IMMEDIATE`, `BATCH_DAILY`, `BATCH_WEEKLY` |
| `CommissionSettlementRunStatus` | `COMPLETED`, `PARTIAL_FAILURE`, `NO_PAYOUTS`, `FAILED` |
| `ApprovalType` | `REVERSAL`, `ACCOUNT_CLOSURE`, `LARGE_CASH_OUT`, `BACKOFFICE_USER_PRIVILEGE_ELEVATION`, `FEE_RULE_CHANGE`, `COMMISSION_RULE_CHANGE`, `CONTROL_THRESHOLD_CHANGE`, `LIMIT_PROFILE_CHANGE`, `SERVICE_PROVIDER_CHANGE`, `BILL_PROVIDER_SETTLEMENT`, `PLATFORM_REVENUE_WITHDRAWAL`, `PLATFORM_LIQUIDITY_TOP_UP`, `RECONCILIATION_ADJUSTMENT`, `AGENT_FUND_IN`, `AGENT_FUND_OUT` |
| `ApprovalStatus` | `PENDING_APPROVAL`, `APPROVED`, `REJECTED`, `EXPIRED` |
| `ControlThresholdScopeType` | `GLOBAL`, `LIMIT_PROFILE`, `MERCHANT`, `AGENT`, `CUSTOMER` |
| `ReconciliationIncidentStatus` | `OPEN`, `UNDER_INVESTIGATION`, `RESOLVED`, `CLOSED` |
| `ReconciliationIncidentType` | `DOUBLE_ENTRY_MISMATCH`, `BALANCE_MISMATCH`, `FLOAT_IDENTITY_BREACH` |
| `ReconciliationStatus` | `OK`, `MISMATCH` |
| `SuspenseDirection` | `TO_SUSPENSE`, `FROM_SUSPENSE` |
| `ReportType` | `TRANSACTION_SUMMARY`, `KYC_SUMMARY`, `AML_LARGE_TRANSACTIONS`, `FLOAT_REPORT`, `ACTOR_SUMMARY` |
| `ReportGroupBy` | `DAY`, `WEEK`, `MONTH` |
| `ServiceProviderStatus` | `ACTIVE`, `MAINTENANCE`, `SUSPENDED`, `INACTIVE` |
| `BillServiceCategory` | `ELECTRICITY`, `WATER`, `TV`, `TELECOM`, `AIRTIME`, `INTERNET`, `OTHER` |
| `BillServiceStatus` | `ACTIVE`, `INACTIVE` |
| `BillPaymentStatus` | `QUEUED`, `IN_PROCESSING`, `SUCCEEDED`, `FAILED_REFUNDED`, `FAILED_RETRY` |
| `ProcessingAssignmentStatus` | `ACTIVE`, `RELEASED`, `EXPIRED` |
| `NotificationCategory` | `TRANSACTION`, `BILL_PAYMENT`, `APPROVAL`, `RECONCILIATION` (BO receives `BILL_PAYMENT`, `APPROVAL`, and `RECONCILIATION`; `TRANSACTION` is not produced for BO today) |
| `NotificationStatus` | `UNREAD`, `READ` |

---

## 10. Permissions

### 10.1 All Backoffice Permissions

```text
ACTOR_ACTIVATE
ACTOR_AUTH_PIN_RESET
ACTOR_CLOSE
ACTOR_CLOSE_APPROVE
ACTOR_KYC_UPDATE
ACTOR_REACTIVATE
ACTOR_SUSPEND
ACTOR_VIEW_ANY
AGENT_FUND
AGENT_FUND_APPROVE
AGENT_KYC_DOCUMENT_REVIEW
AGENT_KYC_DOCUMENT_UPLOAD
AGENT_KYC_DOCUMENT_VIEW
AUDIT_VIEW
BACKOFFICE_USER_MANAGE
BACKOFFICE_USER_PRIVILEGE_ELEVATION_APPROVE
BILL_PAYMENT_COMPLETE
BILL_PAYMENT_FORCE_RELEASE
BILL_PAYMENT_PROCESS
BILL_PAYMENT_PROCESS_VIEW
BILL_PAYMENT_PROOF_VIEW
BILL_PAYMENT_REFUND
BILL_PAYMENT_REQUEUE
BILL_PROVIDER_SETTLEMENT_APPROVE
BILL_PROVIDER_SETTLEMENT_REQUEST
BILL_PROVIDER_SETTLEMENT_VIEW
CARD_BLOCK_ANY
CARD_CLOSE_ANY
CARD_REPORT_ANY
CARD_STOCK_ASSIGN
CARD_STOCK_IMPORT
CARD_VIEW_ANY
COMMISSION_RULE_ACTIVATE
COMMISSION_RULE_APPROVE
COMMISSION_RULE_WRITE
CONTROL_THRESHOLD_APPROVE
CONTROL_THRESHOLD_VIEW
CONTROL_THRESHOLD_WRITE
CUSTOMER_KYC_DOCUMENT_REVIEW
CUSTOMER_KYC_DOCUMENT_VIEW
FEE_RULE_ACTIVATE
FEE_RULE_APPROVE
FEE_RULE_VIEW
FEE_RULE_WRITE
LIMIT_PROFILE_APPROVE
LIMIT_PROFILE_VIEW
LIMIT_PROFILE_WRITE
MERCHANT_KYC_DOCUMENT_REVIEW
MERCHANT_KYC_DOCUMENT_UPLOAD
MERCHANT_KYC_DOCUMENT_VIEW
PLATFORM_REVENUE_WITHDRAWAL_APPROVE
PLATFORM_REVENUE_WITHDRAWAL_REQUEST
PLATFORM_REVENUE_WITHDRAWAL_VIEW
PLATFORM_LIQUIDITY_TOP_UP_APPROVE
PLATFORM_LIQUIDITY_TOP_UP_REQUEST
PLATFORM_LIQUIDITY_TOP_UP_VIEW
RECONCILIATION_ADJUSTMENT_APPROVE
RECONCILIATION_RESOLVE
RECONCILIATION_VIEW
REPORT_REGULATORY_EXPORT
SERVICE_PROVIDER_APPROVE
SERVICE_PROVIDER_MANAGE
SERVICE_PROVIDER_VIEW
TERMINAL_MANAGE
TX_CASH_OUT_INITIATE
TX_LARGE_CASH_OUT_APPROVE
TX_REVERSAL_APPROVE
TX_REVERSAL_INITIATE
TX_VIEW_ANY
WALLET_FREEZE
WALLET_UNFREEZE
WALLET_VIEW_ANY
```

### 10.2 Approval Permission Map

| Approval type | Required permission to list/view/approve/reject |
|---|---|
| `REVERSAL` | `TX_REVERSAL_APPROVE` |
| `LARGE_CASH_OUT` | `TX_LARGE_CASH_OUT_APPROVE` |
| `BACKOFFICE_USER_PRIVILEGE_ELEVATION` | `BACKOFFICE_USER_PRIVILEGE_ELEVATION_APPROVE` |
| `FEE_RULE_CHANGE` | `FEE_RULE_APPROVE` |
| `COMMISSION_RULE_CHANGE` | `COMMISSION_RULE_APPROVE` |
| `CONTROL_THRESHOLD_CHANGE` | `CONTROL_THRESHOLD_APPROVE` |
| `LIMIT_PROFILE_CHANGE` | `LIMIT_PROFILE_APPROVE` |
| `SERVICE_PROVIDER_CHANGE` | `SERVICE_PROVIDER_APPROVE` |
| `BILL_PROVIDER_SETTLEMENT` | `BILL_PROVIDER_SETTLEMENT_APPROVE` |
| `PLATFORM_REVENUE_WITHDRAWAL` | `PLATFORM_REVENUE_WITHDRAWAL_APPROVE` |
| `PLATFORM_LIQUIDITY_TOP_UP` | `PLATFORM_LIQUIDITY_TOP_UP_APPROVE` |
| `RECONCILIATION_ADJUSTMENT` | `RECONCILIATION_ADJUSTMENT_APPROVE` |
| `ACCOUNT_CLOSURE` | `ACTOR_CLOSE_APPROVE` |
| `AGENT_FUND_IN` | `AGENT_FUND_APPROVE` |
| `AGENT_FUND_OUT` | `AGENT_FUND_APPROVE` |

### 10.3 Baseline Role Grants

Endpoint authorization uses stored permissions, not role names. Baselines assigned when users are created or elevated:

| Role | Baseline permissions |
|---|---|
| `OPERATOR` | `ACTOR_KYC_UPDATE`, `ACTOR_VIEW_ANY`, `BILL_PAYMENT_PROCESS_VIEW`, `BILL_PAYMENT_PROCESS`, `BILL_PAYMENT_COMPLETE`, `BILL_PAYMENT_REFUND`, `BILL_PAYMENT_REQUEUE`, `BILL_PAYMENT_PROOF_VIEW`, `CUSTOMER_KYC_DOCUMENT_VIEW`, `AGENT_KYC_DOCUMENT_VIEW`, `MERCHANT_KYC_DOCUMENT_VIEW`, `LIMIT_PROFILE_VIEW`, `SERVICE_PROVIDER_VIEW`, `TX_VIEW_ANY` |
| `SUPERVISOR` | `ACTOR_ACTIVATE`, `ACTOR_AUTH_PIN_RESET`, `ACTOR_KYC_UPDATE`, `ACTOR_REACTIVATE`, `ACTOR_SUSPEND`, `ACTOR_VIEW_ANY`, `AGENT_FUND`, `BILL_PAYMENT_PROCESS_VIEW`, `BILL_PAYMENT_PROCESS`, `BILL_PAYMENT_COMPLETE`, `BILL_PAYMENT_REFUND`, `BILL_PAYMENT_REQUEUE`, `BILL_PAYMENT_FORCE_RELEASE`, `BILL_PAYMENT_PROOF_VIEW`, `BILL_PROVIDER_SETTLEMENT_REQUEST`, `BILL_PROVIDER_SETTLEMENT_VIEW`, `CARD_REPORT_ANY`, `CARD_STOCK_ASSIGN`, `CARD_VIEW_ANY`, `CUSTOMER_KYC_DOCUMENT_REVIEW`, `CUSTOMER_KYC_DOCUMENT_VIEW`, `AGENT_KYC_DOCUMENT_UPLOAD`, `AGENT_KYC_DOCUMENT_VIEW`, `AGENT_KYC_DOCUMENT_REVIEW`, `MERCHANT_KYC_DOCUMENT_UPLOAD`, `MERCHANT_KYC_DOCUMENT_VIEW`, `MERCHANT_KYC_DOCUMENT_REVIEW`, `FEE_RULE_VIEW`, `LIMIT_PROFILE_VIEW`, `RECONCILIATION_RESOLVE`, `RECONCILIATION_VIEW`, `SERVICE_PROVIDER_VIEW`, `TX_CASH_OUT_INITIATE`, `TX_REVERSAL_INITIATE`, `TX_VIEW_ANY`, `WALLET_VIEW_ANY` |
| `COMPLIANCE` | `ACTOR_VIEW_ANY`, `AUDIT_VIEW`, `BILL_PROVIDER_SETTLEMENT_VIEW`, `CARD_VIEW_ANY`, `CUSTOMER_KYC_DOCUMENT_REVIEW`, `CUSTOMER_KYC_DOCUMENT_VIEW`, `AGENT_KYC_DOCUMENT_UPLOAD`, `AGENT_KYC_DOCUMENT_VIEW`, `AGENT_KYC_DOCUMENT_REVIEW`, `MERCHANT_KYC_DOCUMENT_UPLOAD`, `MERCHANT_KYC_DOCUMENT_VIEW`, `MERCHANT_KYC_DOCUMENT_REVIEW`, `FEE_RULE_VIEW`, `PLATFORM_REVENUE_WITHDRAWAL_VIEW`, `PLATFORM_LIQUIDITY_TOP_UP_VIEW`, `RECONCILIATION_RESOLVE`, `RECONCILIATION_VIEW`, `REPORT_REGULATORY_EXPORT`, `SERVICE_PROVIDER_VIEW`, `TX_VIEW_ANY`, `WALLET_VIEW_ANY` |
| `ADMIN` | all guarded BO permissions except `BACKOFFICE_USER_PRIVILEGE_ELEVATION_APPROVE` |
| `SUPER_ADMIN` | all guarded BO permissions |

---

## 11. Operational Rules

### 11.1 Maker-Checker Flow

The following BO actions create approval requests and do not immediately mutate the final target:

- account closures
- agent fund-in/fund-out
- large cash-out
- transaction reversal
- role elevation to `ADMIN`
- fee rule changes
- commission rule changes
- limit profile changes and assignments
- control threshold changes
- service provider and bill service changes
- bill-provider settlement
- platform revenue withdrawal
- reconciliation adjustments when suspense posting is required

The checker action is always one of:

- `POST /api/v1/backoffice/approvals/{id}/approve`
- `POST /api/v1/backoffice/approvals/{id}/reject`

The checker must hold the approval-specific permission and cannot bypass the type-specific check.

### 11.2 Idempotency

No BO endpoint in this specification requires the `Idempotency-Key` header. Approval-driven BO money movements are deterministic from the approval request id and execute during approval.

### 11.3 Frontend Gating

Use `perms[]` from the JWT to hide actions the user cannot call. If the backend still returns `403`, treat it as authoritative and refresh the session/profile state if needed.

### 11.4 Empty Bodies

For endpoints listed with `none`, send no JSON body. For `InvestigateIncidentRequest`, an empty object is accepted because the controller marks the body optional and the record has no fields.

### 11.5 Versioning of regulatory entities (FeeRule, CommissionRule, LimitProfile, ControlThreshold)

These four entities are **immutable once persisted**. There is **no destructive update**: a modification produces a *new version* and preserves the predecessor row for audit and reproducibility.

| Entity | Modification endpoint | Approval type |
|---|---|---|
| Fee rule | `POST /api/v1/backoffice/fee-rules/{id}/supersede` | `FEE_RULE_CHANGE` |
| Commission rule | `POST /api/v1/backoffice/commission-rules/{id}/supersede` | `COMMISSION_RULE_CHANGE` |
| Limit profile | `POST /api/v1/backoffice/limit-profiles/{id}/supersede` | `LIMIT_PROFILE_CHANGE` |
| Control threshold | `POST /api/v1/backoffice/control-thresholds/{id}/supersede` | `CONTROL_THRESHOLD_CHANGE` |

**Common rules**

1. The HTTP verb is `POST … /supersede`, never `PUT`. There is no destructive-update endpoint.
2. Every supersede goes through 4-eyes approval (`202 Accepted` with an `ApprovalRequest` in `PENDING_APPROVAL`). The new version does not exist (and the predecessor is not deactivated) until a different backoffice user approves the request.
3. **Self-approval is forbidden**: an approver cannot approve their own request. Attempting it returns `403 Forbidden`.
4. A second supersede attempt on a row that already has a pending change is rejected with `409` / `APPROVAL_REQUIRED`.
5. On approval, in a single database transaction:
   - a new row is inserted with a fresh UUID, `version = previous.version + 1`, `previousVersionId = previous.id`, `isActive = true`;
   - the previous row is updated to `isActive = false`, `supersededAt = now`, `supersededById = newVersionId`;
   - LimitProfile additionally re-points actor assignments (see below).
6. Response payloads expose the chain (`version`, `previousVersionId`, `supersededById`, `supersededAt`) so the frontend can render history.

**LimitProfile-specific: automatic re-pointing of actor assignments**

`LimitProfile` is referenced by foreign key on **three** actor tables:

- `customers.limit_profile_id`
- `merchants.limit_profile_id`
- `agents.limit_profile_id`

On a successful `LIMIT_PROFILE_CHANGE / SUPERSEDE` approval, the backend re-points **all** rows in those three tables that referenced the predecessor to the new version. The re-pointing is atomic with the supersede (same DB transaction): operators do **not** need to re-assign actors manually.

The `LIMIT_PROFILE_SUPERSEDED` audit event payload includes counts:

```json
{
  "supersededBy": "<newVersionId>",
  "newVersion": 2,
  "repointedCustomers": 137,
  "repointedMerchants": 12,
  "repointedAgents": 4
}
```

Only actors **currently assigned to the predecessor** at the moment of approval are re-pointed. Actors previously moved off (e.g. to another logical profile between maker and checker) are not touched.

**Recommended BO frontend action label**

For the four entities above, surface the modification action as **« Créer une nouvelle version »** / **« Create new version »** — never as « Edit » or « Update ». For LimitProfile, before submission, show the operator a confirmation note:

> *« L'approbation re-pointera automatiquement tous les customers, agents et merchants actuellement assignés à cette version vers la nouvelle version. »*

### 11.6 Bill-Payment Processing Rules

The bill-payment worklist is **not** a maker-checker flow — every action in [5.21](#521-bill-payment-processing-operator-worklist) applies directly with `200`. The only second-approver step is the in-line **4-eyes on complete** above the threshold (header `X-Second-Approver-Operator-Id`), which is not an `ApprovalRequest` and does not appear in the Approvals list.

**Emergency kill-switch.** The whole worklist is gated by `komopay.billpay.enabled`. The default is `true`; this is not a feature toggle. If operations set it to `false` during an incident, every `/api/v1/backoffice/bill-payments/**` route returns `404` and the bill-payment sweepers stop. Probe one read endpoint at startup if needed, and hide the processing section only when it answers `404`.

**Action gating.** Use `perms[]` to hide buttons the operator cannot use (`take`/`release` need `BILL_PAYMENT_PROCESS`, `complete` needs `BILL_PAYMENT_COMPLETE`, etc.). Buttons must be **hidden, not disabled**, when the permission is missing. `force-release` is supervisor-only (`BILL_PAYMENT_FORCE_RELEASE`).

**No idempotency header.** Like every other BO endpoint, the processing actions do not require `Idempotency-Key`. The assignment lock and the state machine make the actions safe to retry: a second `take` returns `409`, a `complete` from a non-`IN_PROCESSING` status returns `422`.

#### Bill-payment error codes

| Code | HTTP | When | Suggested UI |
|---|---|---|---|
| `BILL_PAYMENT_ALREADY_ASSIGNED` | `409` | Two operators take the same payment; the loser gets this | "Already taken by {holder}." Refresh the row — it is now `IN_PROCESSING`. |
| `BILL_PAYMENT_OPERATOR_MISMATCH` | `403` | Complete/refund/requeue/release by someone who is not the assignment holder | "This payment is assigned to another operator." Refresh; offer force-release to supervisors. |
| `BILL_PAYMENT_INVALID_TRANSITION` | `422` | Action not allowed from the current status (e.g. complete from `QUEUED`) | "This action is not available for the current status." Refresh the detail. |
| `BILL_PAYMENT_ASSIGNMENT_NOT_FOUND` | `404` | Release/force-release with no active assignment | Refresh; the assignment likely expired or was already released. |
| `BILL_PAYMENT_ASSIGNMENT_NOT_ACTIVE` | `422` | Acting on an assignment that is no longer `ACTIVE` | Refresh the detail. |
| `BILL_PAYMENT_SECOND_APPROVER_REQUIRED` | `422` | Complete ≥ threshold without `X-Second-Approver-Operator-Id` | Reveal the second-approver field; require a different operator. |
| `BILL_PAYMENT_SECOND_APPROVER_INVALID` | `422` | Second approver equals the caller, or lacks `BILL_PAYMENT_COMPLETE` | "The second approver must be a different operator with completion rights." |
| `BILL_PAYMENT_PROOF_NOT_FOUND` | `404` | `…/proof` on a payment with no stored proof | Hide the "view proof" affordance when `proofRef` is null. |
| `BACKOFFICE_USER_NOT_FOUND` | `404` | Referenced operator id (e.g. second approver) does not exist | Re-pick the second approver from a valid list. |

> `SERVICE_PROVIDER_IN_MAINTENANCE` (`422`) is a customer-initiation error; it should not surface in the BO worklist except as a stale row — there is no operator action that produces it.

---

## 12. Evidence Index

| Area | Source class |
|---|---|
| Auth BO | `security.api.BackofficeAuthController`, `security.api.BackofficeLoginResponse`, `security.api.BackofficePasswordSetupRequest`, `security.api.TokenResponse`, `security.application.BackofficeAuthenticationService`, `security.domain.TokenPurpose` (`PASSWORD_SETUP`, `MFA_ENROLLMENT`), `security.infrastructure.JwtService` |
| MFA BO | `security.api.BackofficeMfaController`, `security.api.TotpSetupResponse`, `security.api.TotpConfirmRequest`, `security.api.TotpRevokeRequest`, `security.api.VerifyMfaRequest`, `security.application.BackofficeMfaService`, `security.application.BackofficeAuthenticationService` (`login` MFA branches, `verifyMfa`), `identity.domain.BackofficeUser` (`mfa_secret`, `pending_mfa_secret`), `db/migration/V068__backoffice_mfa_enrollment.sql` |
| HTTP envelopes | `shared.infrastructure.web.ApiResponse`, `PagedResponse`, `ApiError`, `shared.infrastructure.exception.GlobalExceptionHandler` |
| Security and rate limit | `shared.infrastructure.config.SecurityConfig`, `shared.infrastructure.web.RateLimitingFilter`, `CorrelationIdFilter` |
| Users | `backoffice.api.BackofficeUserController`, `CreateBackofficeUserUseCase`, `ElevateBackofficeUserRoleUseCase` |
| Actors | `backoffice.api.BackofficeActorController` |
| Customer KYC review | `backoffice.api.BackofficeCustomerKycController`, `backoffice.application.BackofficeKycReviewService`, `backoffice.application.BackofficeCustomerKycService`, `kyc.domain.KycDocument`, `kyc.domain.KycStoragePort` |
| Agent/Merchant KYC review | `backoffice.api.BackofficeAgentKycController`, `backoffice.api.BackofficeMerchantKycController`, `backoffice.application.BackofficeKycReviewService`, `backoffice.application.BackofficeActorKycService`, `backoffice.api.KycDocumentFileResponse` |
| Approvals | `backoffice.api.BackofficeApprovalController`, `backoffice.domain.ApprovalAuthorization`, `ApprovalType`, `ApprovalStatus` |
| Audit | `backoffice.api.BackofficeAuditController` |
| Wallets | `backoffice.api.BackofficeWalletController` |
| Cards | `backoffice.api.BackofficeCardController`, `BackofficeCardStockController` |
| Terminals | `backoffice.api.BackofficeTerminalController` |
| Transactions | `backoffice.api.BackofficeTransactionController` |
| Fee rules | `backoffice.api.BackofficeFeeRuleController`, `FeeRuleChangePayload` |
| Commission rules | `backoffice.api.BackofficeCommissionRuleController`, `CommissionRuleChangePayload` |
| Commission settlements | `backoffice.api.BackofficeCommissionSettlementController` |
| Limit profiles | `backoffice.api.BackofficeLimitProfileController`, `LimitProfileChangePayload` |
| Control thresholds | `backoffice.api.BackofficeControlThresholdController`, `ControlThresholdChangePayload` |
| Reconciliation | `backoffice.api.BackofficeReconciliationController`, `ReconciliationAdjustmentPayload` |
| Regulatory reports | `backoffice.api.BackofficeRegulatoryController` |
| Bill-provider settlement | `backoffice.api.BackofficeBillProviderSettlementController`, `SettlementApprovalPayload` |
| Platform revenue | `backoffice.api.BackofficePlatformRevenueController`, `PlatformRevenueWithdrawalApprovalPayload` |
| Service providers | `servicepayment.api.BackofficeServiceProviderController`, `servicepayment.application.ServiceProviderChangePayload`, `UpdateServiceProviderUseCase`, `BusinessHoursService`, `ReferenceValidator`, `servicepayment.domain.ServiceProviderStatus` |
| Bill-payment processing | `servicepayment.api.BackofficeBillPaymentController`, `servicepayment.api.dto.BillPaymentProcessingResponse`, `BillPaymentReasonRequest`, `servicepayment.application.TakeBillPaymentUseCase`, `CompleteBillPaymentUseCase`, `RefundBillPaymentUseCase`, `RequeueBillPaymentUseCase`, `ForceReleaseAssignmentUseCase`, `BillPaymentLedgerService`, `PaymentProofService`, `servicepayment.domain.BillPayment`, `BillPaymentStatus`, `ProcessingAssignment`, `ProcessingAssignmentStatus` |
| Notifications | `notification.api.NotificationController`, `notification.application.NotificationReadService`, `BillPaymentNotificationConsumer`, `BackofficeNotificationConsumer`, `BackofficeNotificationPoller`, `notification.domain.NotificationCategory` |
| Permission matrix | `identity.domain.Permission`, `BackofficePermissionMatrix` |

---

End of document. All content above is derived from the current KomoPay backend codebase only.
