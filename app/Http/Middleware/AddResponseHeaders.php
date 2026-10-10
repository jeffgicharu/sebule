<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($release = config('store.release')) {
            $response->headers->set('X-Release', $release);
        }

        if (! config('store.allow_indexing')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
