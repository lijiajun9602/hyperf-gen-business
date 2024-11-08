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
return [
    'generator' => [
        'DtoOut' => [
            'namespace' => 'app/Controller/Dto',
        ],
        'Enums' => [
            'namespace' => 'app/Enums',
        ],
        'BusinessServices' => [
            'namespace' => 'app/Service',
        ],
        'BusinessMappers' => [
            'namespace' => 'app/Mapper',
        ],
        'BusinessControllers' => [
            'namespace' => 'app/Controller',
        ],
    ],
];
