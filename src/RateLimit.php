<?php

namespace App;

use App\Http\ServerRequest;
use Redis;

class RateLimit
{
    protected Redis $redis;

    protected Environment $environment;

    public function __construct(Redis $redis, Environment $environment)
    {
        $this->redis = $redis;
        $this->environment = $environment;
    }

    /**
     * @param ServerRequest $request
     * @param string $group_name
     * @param int $timeout
     * @param int $interval
     *
     * @throws Exception\RateLimitExceededException
     */
    public function checkRateLimit(
        ServerRequest $request,
        string $group_name = 'default',
        int $timeout = 5,
        int $interval = 2
    ): bool {
        if ($this->environment->isTesting() || $this->environment->isCli()) {
            return true;
        }

        $ip = $request->getIp();
        $cache_name = sprintf(
            'rate_limit|%s|%s',
            $group_name,
            str_replace(':', '.', $ip)
        );

        $result = $this->redis->get($cache_name);

        if ($result !== false) {
            if ((int)$result + 1 > $interval) {
                throw new Exception\RateLimitExceededException();
            }

            $this->redis->incr($cache_name);
        } else {
            $this->redis->setex($cache_name, $timeout, 1);
        }

        return true;
    }
}
