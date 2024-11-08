<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Common\Exception\Handler;


use App\Common\Enums\ErrorEnum;
use App\Common\Exception\AppBadRequestException;
use App\Common\Exception\BusinessException;
use App\Common\Http\ResponseJson;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Exception\BadRequestHttpException;
use Hyperf\HttpMessage\Exception\MethodNotAllowedHttpException;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\Validation\ValidationException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;


    /**
     * @var StdoutLoggerInterface
     */
    protected mixed $logger;
    /**
     * @var ResponseJson
     */
    protected ResponseJson $response;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->response = $container->get(ResponseJson::class);
        $this->logger = $container->get(StdoutLoggerInterface::class);
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        if ($throwable instanceof NotFoundHttpException) {
            $this->logger->warning(($throwable));
            return $this->response->error(ErrorEnum::RECORD_NOT_FOUND->getMsg(), ErrorEnum::RECORD_NOT_FOUND->getCode());
        }
        if ($throwable instanceof BusinessException) {
            $this->logger->warning(($throwable));
            return $this->response->error($throwable->getMessage(), $throwable->getCode());
        }
        if ($throwable instanceof BadRequestHttpException) {
            $this->logger->warning(($throwable));
            return $this->response->error(ErrorEnum::BAD_REQUEST->getMsg(), ErrorEnum::BAD_REQUEST->getCode());
        }
        if ($throwable instanceof AppBadRequestException) {
            $this->logger->warning(($throwable));
            return $this->response->error($throwable->getMessage(), $throwable->getCode());
        }
        if ($throwable instanceof MethodNotAllowedHttpException) {
            return $this->response->error(ErrorEnum::METHOD_NOT_ALLOWED->getMsg(), ErrorEnum::METHOD_NOT_ALLOWED->getCode());
        }
        if ($throwable instanceof ValidationException) {
            $message = $throwable->validator->errors()->first();
            return $this->response->error($message, ErrorEnum::BAD_REQUEST->getCode());
        }
        $this->logger->error(($throwable));
        return $this->response->error(ErrorEnum::SERVER_ERROR->getMsg(), ErrorEnum::SERVER_ERROR->getCode());
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
