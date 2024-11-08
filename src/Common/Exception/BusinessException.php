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

namespace Hyperf\GenBusiness\Common\Exception;

use Hyperf\Server\Exception\ServerException;
use Lishun\Enums\Interfaces\EnumCodeInterface;
use Throwable;

class BusinessException extends ServerException
{
    public function __construct(mixed $message = null, mixed $code = 0, Throwable $previous = null)
    {
        if ($message instanceof EnumCodeInterface) {
            $msg = $message->getMsg();
            $code = $message->getCode();
            parent::__construct($msg, $code, $previous);
        } else {
            parent::__construct($message, $code, $previous);
        }
    }
}
