<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TabLayout
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('_tab') || $request->ajax()) {
            config(['view.tab_mode' => true]);
        }

        return $next($request);
    }
}
