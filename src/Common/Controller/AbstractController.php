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

namespace Hyperf\GenBusiness\Common\Controller;

use Hyperf\GenBusiness\Common\Http\RequestJson;
use Hyperf\GenBusiness\Common\Http\ResponseJson;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;

abstract class AbstractController
{
    #[Inject]
    protected ContainerInterface $container;

    #[Inject]
    protected RequestJson $request;

    #[Inject]
    protected ResponseJson $response;
}
