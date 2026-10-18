<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safaricom does not sign its callbacks, so each callback URL carries a long secret (MPESA_CALLBACK_SECRET) and,
 * when MPESA_ALLOWED_IPS is set, must come from one of Safaricom's addresses. Anything else gets a plain 404,
 * so the URLs give nothing away.
 */
class VerifyMpesaCallback
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $expected = (string) config('mpesa.callback_secret');
        $given = $route instanceof Route ? $route->parameter('secret') : null;
        $given = is_string($given) ? $given : '';
        $allowedIps = (array) config('mpesa.allowed_ips');

        abort_if(strlen($expected) < 32 || ! hash_equals($expected, $given), 404);
        abort_if($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true), 404);

        $route?->forgetParameter('secret');

        return $next($request);
    }
}
