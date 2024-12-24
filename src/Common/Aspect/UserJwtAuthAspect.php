<?php
declare(strict_types=1);

namespace Hyperf\GenBusiness\Common\Aspect;


use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\GenBusiness\Common\Annotation\UserJwtAuth;
use Hyperf\GenBusiness\Common\Dto\UserJwtAuthIn;
use Hyperf\GenBusiness\Common\Enums\ErrorEnum;
use Hyperf\GenBusiness\Common\Exception\BusinessException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qbhy\HyperfAuth\AuthManager;
use Qbhy\HyperfAuth\Exception\AuthException;
use Qbhy\HyperfAuth\Exception\GuardException;
use Qbhy\HyperfAuth\Exception\UnauthorizedException;
use Qbhy\HyperfAuth\Exception\UserProviderException;
use Throwable;

#[Aspect]
class UserJwtAuthAspect extends AbstractAspect
{
    public array $annotations = [
        UserJwtAuth::class,
    ];

    #[Inject]
    protected AuthManager $authManager;

    /**
     * @throws \Hyperf\Di\Exception\Exception
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $request = ApplicationContext::getContainer()->get(ServerRequestInterface::class);
        $logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        try {
            $parserData = $this->authManager->user();
            if ($parserData) {
                $parserData = $this->authManager->user();
                $user = $parserData->toArray();
                $request = $request->withAttribute('user', $user);
                Context::set(ServerRequestInterface::class, $request);
                /** @var UserJwtAuthIn $arguments */
                $arguments = $proceedingJoinPoint->getArguments()[0];
                if ($arguments) {
                    $arguments->userId = $user['userId'];
                    $arguments->nickname = $user['nickName']??"无";
                }

            } else {
                throw new BusinessException(ErrorEnum::AUTH_ERROR);
            }
        } catch (BusinessException|AuthException|GuardException|UnauthorizedException|UserProviderException $exception) {
            $logger->error("token失败异常：code:{code},message:{message},trace:{trace}", ['code' => $exception->getCode(), 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            throw new BusinessException(ErrorEnum::AUTH_ERROR);

        } catch (Exception|Throwable $exception) {
            $logger->error("服务器错误异常：code:{code},message:{message},trace:{trace}", ['code' => $exception->getCode(), 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            throw new BusinessException(ErrorEnum::SERVER_ERROR);
        }
        return $proceedingJoinPoint->process();
    }
}