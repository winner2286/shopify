<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlanFeature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        abort_unless(
            $user?->isAdmin() || $user?->activeShop()?->canUseFeature($feature),
            403,
            $feature === 'export_accounting'
                ? 'Les exports CSV sont inclus dans la formule Premium.'
                : 'Cette fonctionnalité est incluse dans les formules Pro et Premium.',
        );

        return $next($request);
    }
}
