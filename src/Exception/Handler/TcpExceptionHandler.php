<?php

declare(strict_types=1);

namespace Hyperf\DTO\Exception\Handler;

use App\Kernel\Http\Response;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\JsonRpc\ResponseBuilder;
use Hyperf\JsonRpc\TcpServer;
use Hyperf\Utils\Context;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class TcpExceptionHandler extends ExceptionHandler
{
    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var TcpServer
     */
    private $server;

    private FormatterInterface $formatter;

    public function __construct(ContainerInterface $container, FormatterInterface $formatter)
    {
        $this->response = $container->get(Response::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->server = $container->get(TcpServer::class);
        $this->formatter = $formatter;
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->logger->warning($this->formatter->format($throwable));

        if (empty($response->getBody()->getContents())) {
            $request = Context::get(ServerRequestInterface::class);
            $response = $this->getResponseBuilder()->buildErrorResponse($request, ResponseBuilder::SERVER_ERROR, $throwable);
            Context::set(ResponseInterface::class, $response);
        }

        $this->stopPropagation();

        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }

    protected function getResponseBuilder(): ResponseBuilder
    {
        $getResponseBuilder = function () {
            return $this->responseBuilder;
        };
        return $getResponseBuilder->call($this->server);
    }
}
