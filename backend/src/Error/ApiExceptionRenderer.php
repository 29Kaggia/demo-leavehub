<?php
declare(strict_types=1);

namespace App\Error;

use Cake\Error\Renderer\WebExceptionRenderer;
use Psr\Http\Message\ResponseInterface;

class ApiExceptionRenderer extends WebExceptionRenderer
{
    public function render(): ResponseInterface
    {
        $path = $this->request?->getUri()->getPath() ?? '';
        if (!str_starts_with($path, '/api/')) {
            return parent::render();
        }

        $status = $this->getHttpCode($this->error);
        $message = $status >= 500 ? 'The server could not complete this request.' : $this->error->getMessage();
        return $this->controller->getResponse()
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody((string)json_encode(['message' => $message], JSON_THROW_ON_ERROR));
    }
}
