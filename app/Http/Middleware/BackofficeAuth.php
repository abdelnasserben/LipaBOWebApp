<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BackofficeAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!session()->has('bo_user')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
