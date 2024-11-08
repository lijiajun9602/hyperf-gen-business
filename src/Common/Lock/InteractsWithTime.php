<?php

namespace Hyperf\GenBusiness\Common\Lock;

use Carbon\Carbon;
use DateInterval;
use DateTimeInterface;

trait InteractsWithTime
{
    /**
     * Get the number of seconds Until the given Datetime
     * @param $delay
     * @return int|mixed
     */
    protected function secondsUtil($delay): mixed
    {
        $delay = $this->getDateTimeAfterInterval($delay);
        return $delay instanceof DateTimeInterface
            ? max(0, $delay->getTimestamp() - $this->currentTime())
            : (int)$delay;
    }

    /**
     * Get DateTime Instance from an interval
     */
    protected function getDateTimeAfterInterval($delay)
    {
        if ($delay instanceof DateInterval) {
            $delay = Carbon::now()->add($delay);
        }

        return $delay;
    }

    /**
     * Get current Time
     * @return int
     */
    protected function currentTime(): int
    {
        return Carbon::now()->getTimestamp();
    }

    /**
     * @param DateInterval | DateTimeInterface| int $delay
     * @return int
     */
    protected function availableAt(DateInterval|DateTimeInterface|int $delay = 0): int
    {
        $delay = $this->getDateTimeAfterInterval($delay);
        return $delay instanceof DateTimeInterface
            ? $delay->getTimestamp()
            : Carbon::now()->addRealSeconds($delay)->getTimestamp();
    }
}
