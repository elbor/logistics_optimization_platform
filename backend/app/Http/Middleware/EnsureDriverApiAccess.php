<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Другий рубіж після відкликання токенів: токен живий, але користувач уже не активний
 * водій (змінено роль, або is_active змінено в обхід моделі й хук не спрацював).
 */
class EnsureDriverApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canUseDriverApi(), 403, __('auth.driver_only'));

        return $next($request);
    }
}
