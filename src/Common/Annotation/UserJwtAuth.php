<?php

namespace Hyperf\GenBusiness\Common\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class UserJwtAuth extends AbstractAnnotation
{

}