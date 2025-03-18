<?php

namespace Hyperf\GenBusiness\Common\Lock;

use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisProxy;
use RedisException;

class RedisLock extends Lock
{
    /**
     * @var RedisProxy|Redis
     */
    protected $redis;

    public function __construct(RedisProxy|Redis $redis, string $name, int $seconds, ?string $owner = null)
    {
        parent::__construct($name, $seconds, $owner);
        $this->redis = $redis;
    }

    /**
     * @inheritDoc
     */
    public function acquire(): bool
    {
        try {
            // 使用 Lua 脚本将 setnx 和 expire 原子化
            $luaScript = "
            if redis.call('SETNX', KEYS[1], ARGV[1]) == 1 then
                if tonumber(ARGV[2]) > 0 then
                    redis.call('EXPIRE', KEYS[1], ARGV[2])
                end
                return 1
            else
                return 0
            end
        ";
            $result = $this->redis->eval($luaScript, [$this->name, $this->owner, $this->seconds], 1);

            return $result === 1;
        } catch (RedisException $e) {
            // 记录日志并返回 false
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function release(): bool
    {
        if ($this->isOwnedByCurrentProcess()) {
            try {
                $res = $this->redis->eval(LockScripts::releaseLock(), ['name' => $this->name, 'owner' => $this->owner], 1);
                return $res === 1;
            } catch (RedisException $e) {
                // 记录日志并返回 false
                return false;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function forceRelease(): bool
    {
        try {
            $r = $this->redis->del($this->name);
            return $r === 1;
        } catch (RedisException $e) {
            // 记录日志并返回 false
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws RedisException
     */
    protected function getCurrentOwner(): ?string
    {
        try {
            return $this->redis->get($this->name);
        } catch (RedisException $e) {
            // 记录日志并返回 null
            return null;
        }
    }
}
