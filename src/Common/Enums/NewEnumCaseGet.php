<?php

namespace App\Common\Enums;

use Lishun\Enums\Interfaces\EnumCaseInterface;
use Lishun\Enums\Traits\EnumCaseGet;
use Lishun\Enums\Utils\EnumStore;
use ReflectionEnum;

trait NewEnumCaseGet
{
    use EnumCaseGet;

    public static function getValueEnums(): array
    {
        $enum = new ReflectionEnum(static::class);
        if (EnumStore::isset($enum->getName())) {
            return EnumStore::get($enum->getName());
        }
        $enumCases = $enum->getCases();
        foreach ($enumCases as $enumCase) {
            /** @var EnumCaseGet $case */
            $case = $enumCase->getValue();
            $obj = $case->getEnumCase();
            $caseArr = [
                'name' => $case->name,
                'value' => $case->value ?? null,
                'msg' => $obj->msg ?? null,
                'data' => $obj->data ?? null,
                'group' => $obj->group ?? null,
                'ext' => $obj->ext ?? null,
            ];

            EnumStore::set($enum->getName(), $case->value, $caseArr);
        }
        return EnumStore::get($enum->getName());
    }

    public static function getValueGroupEnums(string|int|null|array|EnumCaseInterface $groupName = null, string $val = null): array|null
    {
        $groups = self::loadGroupsEnums();

        $res = [];

        if ($groupName instanceof EnumCaseInterface) {
            $groupName = $groupName->getGroup();
        }

        if ($groupName !== null) {

            if (is_array($groupName)) {
                foreach ($groupName as $value) {
                    $value && $res[$value] = $groups[$value] ?? null;
                }
            } else {
                $res = $groups[$groupName] ?? null;
            }
            if (!empty($val) && !empty($res)) {
                return collect($res)->pluck($val)->toArray();
            }
            return $res;
        }
        return $groups;
    }
}