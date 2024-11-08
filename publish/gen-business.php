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
            'namespace' => 'app/Enums/gen',
        ],
        'BusinessServices' => [
            'namespace' => 'app/Service/gen',
        ],
        'BusinessMappers' => [
            'namespace' => 'app/Mapper/gen',
        ],
        'BusinessControllers' => [
            'namespace' => 'app/Controller/gen',
        ],
    ],
];
