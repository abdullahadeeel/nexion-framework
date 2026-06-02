<?php

namespace Phpify\Middleware;

use Phpify\Http\Request;
use Phpify\Http\Response;

interface Middleware
{
    public function handle(Request $request, \Closure $next): Response;
}
