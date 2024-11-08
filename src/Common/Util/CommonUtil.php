<?php

namespace Hyperf\GenBusiness\Common\Util;

use Hyperf\CodeParser\Project;
use Hyperf\DbConnection\Db;
use RuntimeException;

class  CommonUtil
{
    public static function mkdir(string $path, $type = 0): void
    {
        if ($type === 1) {
            $dir = $path;
        } else {
            $dir = dirname($path);
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
    }

    public static function mkdirClass($class, string $outName, mixed $path): array
    {
        $isMkdir = false;
        $project = new Project();
        $className = $class . $outName;
        $namespace = $project->namespace($path);
        $genClass = $namespace . $className;
        $classPath = BASE_PATH . '/' . $project->path($genClass);
        if (!file_exists($classPath)) {
            static::mkdir($classPath);
            $isMkdir = true;
        }

        return array($namespace, $classPath, $isMkdir);
    }

    public static function getTableComment($table){
       return Db::connection()->table('information_schema.TABLES')->where('TABLE_NAME', $table)
            ->where('TABLE_SCHEMA', Db::connection()->getDatabaseName())->value('TABLE_COMMENT');
    }

    public static function getTablePrimaryKey($table)
    {
        return Db::connection()->table('information_schema.columns')->where('TABLE_NAME', $table)
            ->where('TABLE_SCHEMA', Db::connection()->getDatabaseName())
            ->where('column_key','PRI')->value('column_name');
    }
}