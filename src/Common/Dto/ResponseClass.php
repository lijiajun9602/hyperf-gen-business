<?php /** @noinspection PhpUnusedAliasInspection */

namespace Hyperf\GenBusiness\Common\Dto;

use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\ApiDocs\Annotation\ApiVariable;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use PhpAccessor\Attribute\Data;

#[HyperfData]
#[Data]
class ResponseClass
{
    #[ApiModelProperty('状态0可用1不可用')]
    public int $status = 0;
    #[ApiModelProperty('请求code')]
    public int $code = 200;
    #[ApiModelProperty('信息')]
    public string $message;

    #[ApiVariable]
    #[ApiModelProperty('内容')]
    public mixed $data;
    #[ApiModelProperty('分页可用')]
    public ?MetaClass $meta;

    #[ApiModelProperty('token')]
    public string $token = "";

    public function __construct(mixed $data)
    {
        $this->data = $data;
    }

}