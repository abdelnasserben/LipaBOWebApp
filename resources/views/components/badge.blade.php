@props(['status', 'label' => null])
@php
use App\Support\BackofficeEnums;

$map = [
    'ACTIVE'              => 'active',
    'SUSPENDED'           => 'suspended',
    'CLOSED'              => 'closed',
    'PENDING'             => 'pending',
    'PENDING_KYC'         => 'pending-kyc',
    'PENDING_APPROVAL'    => 'pending-approval',
    'FROZEN'              => 'frozen',
    'LOCKED'              => 'locked',
    'COMPLETED'           => 'completed',
    'DECLINED'            => 'declined',
    'REVERSED'            => 'reversed',
    'EXPIRED'             => 'expired',
    'AUTHORIZED'          => 'authorized',
    'APPROVED'            => 'approved',
    'ACCEPTED'            => 'accepted',
    'REJECTED'            => 'rejected',
    'KYC_NONE'            => 'kyc-none',
    'KYC_BASIC'           => 'kyc-basic',
    'KYC_VERIFIED'        => 'kyc-verified',
    'KYC_ENHANCED'        => 'kyc-enhanced',
    'SUPER_ADMIN'         => 'super-admin',
    'ADMIN'               => 'admin',
    'SUPERVISOR'          => 'supervisor',
    'OPERATOR'            => 'operator',
    'COMPLIANCE'          => 'compliance',
    'REGISTERED'          => 'registered',
    'REVOKED'             => 'revoked',
    'ISSUED'              => 'issued',
    'BLOCKED'             => 'blocked',
    'LOST'                => 'lost',
    'STOLEN'              => 'stolen',
    'IN_WAREHOUSE'        => 'in-warehouse',
    'ASSIGNED_TO_AGENT'   => 'assigned-to-agent',
    'SOLD'                => 'sold',
    'RETURNED'            => 'returned',
    'SPOILED'             => 'spoiled',
    'OK'                  => 'ok',
    'MISMATCH'            => 'mismatch',
    'OPEN'                => 'open',
    'UNDER_INVESTIGATION' => 'under-investigation',
    'RESOLVED'            => 'resolved',
    'INACTIVE'            => 'inactive',
    'PARTIAL_FAILURE'     => 'partial-failure',
    'NO_PAYOUTS'          => 'no-payouts',
    'FAILED'              => 'failed',
];

$cls = $map[$status] ?? 'inactive';

$labels = [
    'PENDING_KYC'         => 'Pending KYC',
    'PENDING_APPROVAL'    => 'Pending',
    'SUPER_ADMIN'         => 'Super Admin',
    'KYC_NONE'            => 'KYC None',
    'KYC_BASIC'           => 'KYC Basic',
    'KYC_VERIFIED'        => 'KYC Verified',
    'KYC_ENHANCED'        => 'KYC Enhanced',
    'IN_WAREHOUSE'        => 'In Warehouse',
    'ASSIGNED_TO_AGENT'   => 'Assigned',
    'UNDER_INVESTIGATION' => 'Investigating',
    'PARTIAL_FAILURE'     => 'Partial failure',
    'NO_PAYOUTS'          => 'No Payouts',
];

$displayLabel = $label ?? ($labels[$status] ?? BackofficeEnums::label((string) $status));
@endphp

<span class="badge badge-{{ $cls }}">{{ $displayLabel }}</span>
