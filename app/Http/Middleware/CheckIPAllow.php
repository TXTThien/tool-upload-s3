<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckIPAllow
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip();
        $isAllowed = DB::table('internal_tool_ip_allow')->where('ip',$clientIp)->exists();
        if (!$isAllowed) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'IP Access Denied: ' . $clientIp], 403);
            }

            abort(404);
        }
        return $next($request);
    }
}
