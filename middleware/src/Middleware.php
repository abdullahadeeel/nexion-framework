<?php

namespace FlashPHP\Middleware;

use FlashPHP\Http\Request;
use FlashPHP\Http\Response;

interface Middleware
{
    public function handle(Request $request, \Closure $next): Response;
}
