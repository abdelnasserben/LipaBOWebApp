# Backoffice - Frontend Specification Document

**Version:** 1.0 | **Source:** KomoPay backend codebase analysis | **Date:** 2026-05-06  
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

Total BO endpoints in scope: **121**.

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

Response `200 ApiResponse<TokenResponse>`:

```json
{
  "data": {
    "tokenType": "Bearer",
    "accessToken": "jwt",
    "accessTokenExpiresAt": "2026-05-06T16:00:00Z",
    "refreshToken": "opaque-refresh-token",
    "refreshTokenExpiresAt": "2026-05-07T00:00:00Z"
  },
  "timestamp": "2026-05-06T12:00:00Z"
}
```

Token lifetimes from `application.yml`:

| Actor | Access TTL | Refresh TTL |
|---|---:|---:|
| Backoffice role below `ADMIN` | 8h | 12h |
| `ADMIN` and `SUPER_ADMIN` | 4h | 12h |

Login lockout:

- 3 failed passwords locks the user for 30 minutes.
- `CLOSED`, `SUSPENDED`, and currently `LOCKED` users cannot obtain tokens.

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

---

## 4. Backoffice Capability Map

| Area | BO can do |
|---|---|
| Session | login, refresh token, logout |
| Backoffice users | create users, list, view, suspend, reactivate, close, elevate role |
| Actors | create/activate agents and merchants, list/view customers/agents/merchants, suspend/reactivate, request closure, enable/disable merchant M2M receiving |
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
| Service providers | list/view providers and services, create/update/activate/deactivate via approval |

---

## 5. Exhaustive API Mapping

### 5.1 Auth

| Method | Path | Request | Response |
|---|---|---|---|
| POST | `/api/v1/auth/backoffice/login` | `BackofficeLoginRequest` | `200 ApiResponse<TokenResponse>` |
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
| POST | `/api/v1/backoffice/agents/{id}/approve-kyc` | `ACTOR_KYC_UPDATE` | `ActivateAgentRequest` | `200 ApiResponse<AgentResponse>` |
| POST | `/api/v1/backoffice/merchants` | `ACTOR_KYC_UPDATE` | `CreateMerchantRequest` | `201 ApiResponse<MerchantResponse>` |
| POST | `/api/v1/backoffice/merchants/{id}/approve-kyc` | `ACTOR_KYC_UPDATE` | `ActivateMerchantRequest` | `200 ApiResponse<MerchantResponse>` |
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
| PUT | `/api/v1/backoffice/limit-profiles/{id}` | `LIMIT_PROFILE_WRITE` | `LimitProfileRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
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
| PUT | `/api/v1/backoffice/control-thresholds/{id}` | `CONTROL_THRESHOLD_WRITE` | `ControlThresholdRequest` | `202 ApiResponse<ApprovalRequestResponse>` |
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

Important: `CreateBillServiceRequest` still declares `providerId` as `@NotNull`, even though the controller uses the path `providerId`. The frontend must send it.

---

## 6. Request Schemas

Types: `uuid`, `string`, `long`, `int`, `boolean`, `instant`, `date`, enum names as strings unless the DTO field is an enum.

### 6.1 Auth

```ts
BackofficeLoginRequest = {
  email: string;      // email, required, max 255
  password: string;   // required, min 8, max 128
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

ActivateAgentRequest = {
  kycLevel: KycLevel;
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

ActivateMerchantRequest = {
  kycLevel: KycLevel;
}

ActionReasonRequest = {
  reason?: string; // max 500
}

AgentFundRequest = {
  amount: long;    // positive
  notes?: string;  // max 500
}
```

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
  percentage?: decimal;
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
  percentage?: decimal;
  settlementMode: SettlementMode;
  priority: int;                 // min 1
  validFrom: instant;
  validTo?: instant;
  activeOnApproval: boolean;
}
```

Calculation shape rules:

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
  type: ServiceProviderType;
  baseUrl?: string;                   // max 500
  credentialsRef?: string;            // max 200, never raw secret
  timeoutMillis: int;                 // 100..60000, default 10000
  maxRetries: int;                    // 0..10, default 2
  retryBackoffMillis: int;            // 0..30000, default 500
  sandbox: boolean;                   // default false
  supportsReferenceValidation: boolean; // default false
  callbackSecretRef?: string;         // max 200
}

UpdateServiceProviderRequest = {
  name: string;                       // max 200
  baseUrl?: string;                   // max 500
  credentialsRef?: string;            // max 200
  timeoutMillis: int;                 // 100..60000, default 10000
  maxRetries: int;                    // 0..10, default 2
  retryBackoffMillis: int;            // 0..30000, default 500
  sandbox: boolean;
  supportsReferenceValidation: boolean;
  callbackSecretRef?: string;
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
```

---

## 7. Response Schemas

### 7.1 Auth And Users

```ts
TokenResponse = {
  tokenType: "Bearer";
  accessToken: string;
  accessTokenExpiresAt: instant;
  refreshToken: string;
  refreshTokenExpiresAt: instant;
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
  walletId: uuid;
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
  walletId: uuid;
  limitProfileId?: uuid;
  canCashOut: boolean;
  canReceiveFromMerchant: boolean;
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
```

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
  percentage?: decimal;
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
  percentage?: decimal;
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
  type: ServiceProviderType;
  status: ServiceProviderStatus;
  baseUrl?: string;
  timeoutMillis: int;
  maxRetries: int;
  retryBackoffMillis: int;
  sandbox: boolean;
  supportsReferenceValidation: boolean;
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
  percentage?: decimal;
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
  percentage?: decimal;
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
  action: "CREATE" | "UPDATE" | "ACTIVATE" | "DEACTIVATE" | "ASSIGN";
  limitProfileId: uuid;
  command?: LimitProfileRequest;
  actorType?: ActorType;
  actorId?: uuid;
}

CONTROL_THRESHOLD_CHANGE payload = {
  action: "CREATE" | "UPDATE" | "ACTIVATE" | "DEACTIVATE";
  thresholdId: uuid;
  command?: ControlThresholdRequest;
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
    type: ServiceProviderType;
    baseUrl?: string;
    credentialsRef?: string;
    timeoutMillis: int;
    maxRetries: int;
    retryBackoffMillis: int;
    sandbox: boolean;
    supportsReferenceValidation: boolean;
    callbackSecretRef?: string;
  };
  providerUpdate?: {
    name: string;
    baseUrl?: string;
    credentialsRef?: string;
    timeoutMillis: int;
    maxRetries: int;
    retryBackoffMillis: int;
    sandbox: boolean;
    supportsReferenceValidation: boolean;
    callbackSecretRef?: string;
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
| `ServiceProviderType` | `EXTERNAL_API`, `INTERNAL` |
| `ServiceProviderStatus` | `ACTIVE`, `INACTIVE` |
| `BillServiceCategory` | `ELECTRICITY`, `WATER`, `TV`, `TELECOM`, `AIRTIME`, `INTERNET`, `OTHER` |
| `BillServiceStatus` | `ACTIVE`, `INACTIVE` |

---

## 10. Permissions

### 10.1 All Backoffice Permissions

```text
ACTOR_CLOSE
ACTOR_CLOSE_APPROVE
ACTOR_KYC_UPDATE
ACTOR_REACTIVATE
ACTOR_SUSPEND
ACTOR_VIEW_ANY
AGENT_FUND
AGENT_FUND_APPROVE
AUDIT_VIEW
BACKOFFICE_USER_MANAGE
BACKOFFICE_USER_PRIVILEGE_ELEVATION_APPROVE
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
FEE_RULE_ACTIVATE
FEE_RULE_APPROVE
FEE_RULE_VIEW
FEE_RULE_WRITE
LIMIT_PROFILE_APPROVE
LIMIT_PROFILE_VIEW
LIMIT_PROFILE_WRITE
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
| `OPERATOR` | `ACTOR_KYC_UPDATE`, `ACTOR_VIEW_ANY`, `LIMIT_PROFILE_VIEW`, `SERVICE_PROVIDER_VIEW`, `TX_VIEW_ANY` |
| `SUPERVISOR` | `ACTOR_KYC_UPDATE`, `ACTOR_REACTIVATE`, `ACTOR_SUSPEND`, `ACTOR_VIEW_ANY`, `AGENT_FUND`, `BILL_PROVIDER_SETTLEMENT_REQUEST`, `BILL_PROVIDER_SETTLEMENT_VIEW`, `CARD_REPORT_ANY`, `CARD_STOCK_ASSIGN`, `CARD_VIEW_ANY`, `FEE_RULE_VIEW`, `LIMIT_PROFILE_VIEW`, `RECONCILIATION_RESOLVE`, `RECONCILIATION_VIEW`, `SERVICE_PROVIDER_VIEW`, `TX_CASH_OUT_INITIATE`, `TX_REVERSAL_INITIATE`, `TX_VIEW_ANY`, `WALLET_VIEW_ANY` |
| `COMPLIANCE` | `ACTOR_VIEW_ANY`, `AUDIT_VIEW`, `BILL_PROVIDER_SETTLEMENT_VIEW`, `CARD_VIEW_ANY`, `FEE_RULE_VIEW`, `PLATFORM_REVENUE_WITHDRAWAL_VIEW`, `RECONCILIATION_RESOLVE`, `RECONCILIATION_VIEW`, `REPORT_REGULATORY_EXPORT`, `SERVICE_PROVIDER_VIEW`, `TX_VIEW_ANY`, `WALLET_VIEW_ANY` |
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

---

## 12. Evidence Index

| Area | Source class |
|---|---|
| Auth BO | `security.api.BackofficeAuthController`, `security.application.BackofficeAuthenticationService`, `security.infrastructure.JwtService` |
| HTTP envelopes | `shared.infrastructure.web.ApiResponse`, `PagedResponse`, `ApiError`, `shared.infrastructure.exception.GlobalExceptionHandler` |
| Security and rate limit | `shared.infrastructure.config.SecurityConfig`, `shared.infrastructure.web.RateLimitingFilter`, `CorrelationIdFilter` |
| Users | `backoffice.api.BackofficeUserController`, `CreateBackofficeUserUseCase`, `ElevateBackofficeUserRoleUseCase` |
| Actors | `backoffice.api.BackofficeActorController` |
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
| Service providers | `servicepayment.api.BackofficeServiceProviderController`, `servicepayment.application.ServiceProviderChangePayload` |
| Permission matrix | `identity.domain.Permission`, `BackofficePermissionMatrix` |

---

End of document. All content above is derived from the current KomoPay backend codebase only.
