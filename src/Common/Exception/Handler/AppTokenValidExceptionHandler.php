<?php

declare(strict_types=1);

namespace App\Common\Exception\Handler;

use App\Common\Http\ResponseJson;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Phper666\JwtAuth\Exception\TokenValidException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppTokenValidExceptionHandler extends ExceptionHandler
{

    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var ResponseJson
     */
    protected $response;

    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->response = $container->get(ResponseJson::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();
        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
        return $this->response->error($throwable->getMessage(), $throwable->getCode());
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof TokenValidException;
    }

}