<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CmsAuth
{
    /**
     * Authorize the request using the JWT the CMS (octokit-llm-management-service)
     * issues as a cookie on login. Both services share the same JWT secret and
     * root domain, so logging into the CMS is enough to use this tool too.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(config('services.cms.auth_cookie'));

        if (!$token) {
            return $this->deny($request, 'Bạn cần đăng nhập ở CMS trước khi dùng công cụ này.');
        }

        try {
            $claims = JWT::decode($token, new Key(config('services.cms.jwt_secret'), 'HS256'));
        } catch (Throwable $e) {
            return $this->deny($request, 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.');
        }

        $request->attributes->set('cms_user_id', $claims->user_id);

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => $message], 401);
        }

        return redirect()->away(config('services.cms.login_url'));
    }
}
