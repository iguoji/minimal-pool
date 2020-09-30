<?php
declare(strict_types=1);

namespace Minimal\Pool;

/**
 * 分组类
 * 管理多个服务器的连接池
 */
class Group
{
    /**
     * 服务器列表
     */
    protected array $servers;

    /**
     * 构造函数
     */
    public function __construct(int $size, array $configs)
    {
        $avg = floor($size / count($configs));
        $remainder = $size % count($configs);
        foreach ($configs as $key => $config) {
            $poolSize = array_key_first($configs) == $key ? $avg + $remainder : $avg;
            $this->servers[$key] = new Server((int) $poolSize, $config);
        }
    }

    /**
     * 获取连接
     */
    public function get(string|int $key = null) : array
    {
        if (is_null($key) || !isset($this->servers[$key])) {
            foreach ($this->servers as $k => $server) {
                if ($server->isIdle() || !$server->isFull()) {
                    $key = $k;
                    break;
                }
            }
            if (is_null($key)) {
                $key = array_rand($this->servers);
            }
        }
        return [$key, $this->servers[$key]->get()];
    }

    /**
     * 归还连接
     */
    public function put(string|int $key, mixed $conn) : void
    {
        $this->servers[$key]->put($conn);
    }
}