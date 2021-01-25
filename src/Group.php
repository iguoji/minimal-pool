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
     * @param $size     int     此分组可允许最大的连接数
     * @param $configs  array   此分组中服务器配置列表，例如['192.168.1.1', '192.168.1.2']
     */
    public function __construct(int $size, array $configs, string|callable $constructor)
    {
        // 每台服务器平分连接数
        $avg = floor($size / count($configs));
        // 平分后多出的连接数
        $remainder = $size % count($configs);
        // 循环服务器配置
        foreach ($configs as $key => $config) {
            // 当前服务器的连接数
            $poolSize = array_key_first($configs) == $key ? $avg + $remainder : $avg;
            // 服务器对象
            $this->servers[$key] = new Server((int) $poolSize, $config, $constructor);
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