<?php

declare(strict_types=1);

namespace Hyperf\GenBusiness\Common\Exception;


use Hyperf\GenBusiness\Common\Enums\ErrorEnum;
use Hyperf\Server\Exception\ServerException;

class AppBadRequestException extends ServerException
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, ErrorEnum::BAD_REQUEST->getCode());
    }

}