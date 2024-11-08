<?php

declare(strict_types=1);

namespace Hyperf\GenBusiness;

use Hyperf\GenBusiness\Listener\AfterDtoStartListener;
use Hyperf\GenBusiness\Listener\AfterWorkerStartListener;
use Hyperf\GenBusiness\Listener\BootAppRouteListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for gen-business.',
                    'source' => __DIR__ . '/../publish/gen-business.php',
                    'destination' => BASE_PATH . '/config/autoload/gen-business.php',
                ],
                [
                    'id' => 'config',
                    'description' => 'The config for api-docs.',
                    'source' => __DIR__ . '/../publish/api_docs.php',
                    'destination' => BASE_PATH . '/config/autoload/api_docs.php',
                ], [
                    'id' => 'config',
                    'description' => 'The config for php-accessor.',
                    'source' => __DIR__ . '/../publish/php-accessor.php',
                    'destination' => BASE_PATH . '/config/autoload/php-accessor.php',
                ],
            ],
        ];
    }
}
