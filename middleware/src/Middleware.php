<?php

namespace Nexion\Middleware;

use Nexion\Http\Request;
use Nexion\Http\Response;

interface Middleware
{
    public function handle(Request $request, \Closure $next): Response;
}
