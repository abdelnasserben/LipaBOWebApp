<?php

namespace App\Http\Controllers;

use App\Services\Api\Contracts\BackofficeApiContract;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BackofficeController extends Controller
{
    public function dashboard()    { return view('pages.dashboard'); }
    public function customers()    { return view('pages.customers'); }
    public function customerKyc(string $id) { return view('pages.customer-kyc', ['customerId' => $id]); }

    public function downloadKycDocument(string $documentId, Request $request, BackofficeApiContract $api): Response
    {
        $permissions = (array) $request->session()->get('bo_user.permissions', []);
        abort_unless(in_array('CUSTOMER_KYC_DOCUMENT_VIEW', $permissions, true), 403);

        $file = $api->downloadKycDocumentFile($documentId);
        $filename = $file['filename'] ?? "kyc-$documentId.bin";
        $rawContentType = trim((string) ($file['contentType'] ?? ''));

        // Preserve real upstream Content-Type so the browser can render PDFs/images.
        // Only fall back to octet-stream if upstream gave us nothing usable.
        $contentType = $this->resolveInlineContentType($rawContentType, $filename);

        return response($file['body'] ?? '', 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function agentKyc(string $id) { return view('pages.actor-kyc', ['ownerType' => 'agents', 'actorId' => $id]); }
    public function merchantKyc(string $id) { return view('pages.actor-kyc', ['ownerType' => 'merchants', 'actorId' => $id]); }

    public function downloadActorKycDocument(string $ownerType, string $documentId, Request $request, BackofficeApiContract $api): Response
    {
        // Owner-scoped file endpoints (spec §5.3b): agents/merchants only.
        $permission = match ($ownerType) {
            'agents'    => 'AGENT_KYC_DOCUMENT_VIEW',
            'merchants' => 'MERCHANT_KYC_DOCUMENT_VIEW',
            default     => abort(404),
        };

        $permissions = (array) $request->session()->get('bo_user.permissions', []);
        abort_unless(in_array($permission, $permissions, true), 403);

        $file = $api->downloadActorKycDocumentFile($ownerType, $documentId);
        $filename = $file['filename'] ?? "kyc-$documentId.bin";
        $rawContentType = trim((string) ($file['contentType'] ?? ''));
        $contentType = $this->resolveInlineContentType($rawContentType, $filename);

        return response($file['body'] ?? '', 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function resolveInlineContentType(string $rawContentType, string $filename): string
    {
        if ($rawContentType !== '' && stripos($rawContentType, 'octet-stream') === false) {
            return $rawContentType;
        }

        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf'           => 'application/pdf',
            'jpg', 'jpeg'   => 'image/jpeg',
            'png'           => 'image/png',
            'gif'           => 'image/gif',
            'webp'          => 'image/webp',
            'heic'          => 'image/heic',
            default         => $rawContentType !== '' ? $rawContentType : 'application/octet-stream',
        };
    }

    public function agents()       { return view('pages.agents'); }
    public function merchants()    { return view('pages.merchants'); }
    public function transactions() { return view('pages.transactions'); }
    public function wallets()      { return view('pages.wallets'); }
    public function approvals()    { return view('pages.approvals'); }
    public function audit()        { return view('pages.audit'); }
    public function reconciliation(){ return view('pages.reconciliation'); }
    public function reports()      { return view('pages.reports'); }
    public function rulesLimits()  { return view('pages.rules-limits'); }
    public function serviceProviders() { return view('pages.service-providers'); }
    public function cards()        { return view('pages.cards'); }
    public function terminals()    { return view('pages.terminals'); }
    public function treasury()     { return view('pages.treasury'); }
    public function users()        { return view('pages.users'); }
}
