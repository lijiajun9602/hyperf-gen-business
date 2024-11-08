<?php

namespace Hyperf\GenBusiness\Common\Enums;

use Lishun\Enums\Annotations\EnumCode;
use Lishun\Enums\Annotations\EnumCodePrefix;
use Lishun\Enums\Interfaces\EnumCodeInterface;
use Lishun\Enums\Traits\EnumCodeGet;

/**
 * @method static cases()
 */
#[EnumCodePrefix(null, '系统错误码')]
enum ErrorEnum: int implements EnumCodeInterface
{
    use EnumCodeGet;

    #[EnumCode(msg: "服务器错误,请联系客服", ext: ['response' => 500, 'description' => '服务器错误,请联系客服'])]
    case SERVER_ERROR = 500;
    #[EnumCode(msg: "请求资源不存在", ext: ['response' => 404, 'description' => '请求资源不存在'])]
    case RECORD_NOT_FOUND = 404;
    #[EnumCode(msg: "请求方式错误", ext: ['response' => 405, 'description' => '请求方式错误'])]
    case METHOD_NOT_ALLOWED = 405;

    #[EnumCode(msg: "未登录或登录状态失效", ext: ['response' => 401, 'description' => '未登录或登录状态失效'])]
    case AUTH_ERROR = 401;
    #[EnumCode(msg: "请求参数错误", ext: ['response' => 400, 'description' => '请求参数错误'])]
    case BAD_REQUEST = 400;

}
