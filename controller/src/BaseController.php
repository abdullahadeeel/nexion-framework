<?php

namespace Nexion\Controller;

use Nexion\Http\Response;
use Nexion\Foundation\Application;

abstract class BaseController
{
    protected function render(string $view, array $data = []): Response
    {
        $content = Application::$app->view->render($view, $data);
        $response = new Response();
        $response->setContent($content);
        return $response;
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }
}
