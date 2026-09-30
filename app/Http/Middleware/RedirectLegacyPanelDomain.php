<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyPanelDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonicalUrl = rtrim((string) config('app.url'), '/');
        $canonicalHost = strtolower((string) parse_url($canonicalUrl, PHP_URL_HOST));
        $requestHost = strtolower($request->getHost());

        // Once Nodexa uses nordicnode.org as its canonical URL, keep the old
        // panel.nordicnode.org hostname only as a compatibility alias. Browser
        // navigation is redirected to the same path on the primary domain.
        if (
            $canonicalHost === 'nordicnode.org'
            && $requestHost === 'panel.nordicnode.org'
            && in_array($request->getMethod(), ['GET', 'HEAD'], true)
        ) {
            return redirect()->away($canonicalUrl . $request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
