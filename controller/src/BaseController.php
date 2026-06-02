<?php

namespace Phpify\Controller;

use Phpify\Http\Response;
use Phpify\Foundation\Application;

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
