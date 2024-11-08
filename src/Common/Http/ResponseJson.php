<?php

namespace App\Common\Http;

use App\Common\Dto\MetaClass;
use App\Common\Dto\ResponseClass;
use Hyperf\DTO\Mapper;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Paginator\LengthAwarePaginator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class ResponseJson
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var ResponseInterface
     */
    protected mixed $response;

    /** @noinspection PhpUnhandledExceptionInspection */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->response = $container->get(ResponseInterface::class);
    }

    public function success($responseData = null, $classDto = null, string $token = null): ResponseClass
    {
        $class = new ResponseClass($responseData);
        $message = is_string($responseData) && !empty($responseData) ? $responseData : 'ok';
        $class->message = $message;
        if (!is_string($responseData)) {
            if ($responseData instanceof LengthAwarePaginator) {
                $class->meta = new MetaClass();
                $class->meta->page = $responseData->currentPage();
                $class->meta->perPage = $responseData->perPage();
                $class->meta->total = $responseData->total();
                $class->meta->hasPages = $responseData->hasMorePages();
                $responseData = $responseData->items();
            }

            if (empty($classDto)) {
                $class->data = $responseData;
            } else {
                $responseData = \Hyperf\Collection\collect($responseData)->toArray();
                $class->data = !$this->isAssocArray($responseData) ? Mapper::mapArray($responseData, $classDto) : Mapper::copyProperties($responseData, new $classDto);
            }
        }
        if (!empty($token)) {
            $class->token = $token;
        }

        return $class;
    }


    private function isAssocArray($array): bool
    {
        return array_diff_key($array, range(0, count($array) - 1)) !== [];
    }


    /**
     * @param string $message
     * @param int $code
     * @return PsrResponseInterface
     */
    public function error(string $message = '', int $code = 500): PsrResponseInterface
    {
        return $this->response->json([
            'status' => 1,
            'code' => $code,
            'message' => $message,
            'data' => null,
        ])->withStatus($code);
    }

}