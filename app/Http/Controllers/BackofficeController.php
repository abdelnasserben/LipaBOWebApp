<?php

namespace App\Http\Controllers;

class BackofficeController extends Controller
{
    public function dashboard()    { return view('pages.dashboard'); }
    public function customers()    { return view('pages.customers'); }
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
