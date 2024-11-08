<?php

namespace Hyperf\GenBusiness\Common\Dto;


use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\PhpAccessor\Annotation\HyperfData;
use PhpAccessor\Attribute\Data;

#[Data]
#[HyperfData]
trait PageClass
{
    #[ApiModelProperty("页数")]
    public int $pageNo = 1;
    #[ApiModelProperty("每页数量")]
    public int $pageSize = 15;

    /**
     * @return int
     */
    public function getPageNo(): int
    {
        return $this->pageNo;
    }

    /**
     * @param int $pageNo
     */
    public function setPageNo(int $pageNo): void
    {
        $this->pageNo = $pageNo;
    }

    /**
     * @return int
     */
    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * @param int $pageSize
     */
    public function setPageSize(int $pageSize): void
    {
        $this->pageSize = $pageSize;
    }


}