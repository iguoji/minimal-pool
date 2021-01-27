<?php
declare(strict_types=1);

namespace Minimal\Pool;

use Minimal\Support\Arr;

/**
 * 集群类
 * 管理多个连接池分组
 */
class Cluster
{
    /**
     * 分组列表
     */
    protected array $groups;

    /**
     * 构造函数
     */
    public function __construct(array $configs, string|callable $constructor)
    {
        // 合并默认配置
        $configs = Arr::array_merge_recursive_distinct($this->getDefaultConfigStruct(), $configs);
        // 用户默认配置
        $defaultConfig = $configs['default'];
        // 工作进程数量 -
        $workerNum = $configs['worker_num'];
        // 循环集群分组
        foreach ($configs['cluster'] as $groupName => $groupConfigs) {
            // 分组连接数量
            $poolSize = $configs['pool'][$groupName] ?? 0;
            // 每个分组按工作进程均分连接数
            $poolSize = (int) floor($poolSize / $workerNum);
            if ($poolSize > 0) {
                // 分组配置
                $groupConfigs = array_map(fn($config) => array_merge($defaultConfig, $config), $groupConfigs ?: [$defaultConfig]);
                // 分组对象
                $this->groups[$groupName] = new Group($poolSize, $groupConfigs, $constructor);
            }
        }
    }

    /**
     * 获取默认配置结构
     */
    public function getDefaultConfigStruct() : array
    {
        return [
            'worker_num'    =>  1,
            'pool'          =>  [
                'master'    =>  140,
                'slave'     =>  0,
            ],
            'default'       =>  [],
            'cluster'       =>  [
                'master'    =>  [],
                'slave'     =>  [],
            ],
        ];
    }

    /**
     * 获取连接
     */
    public function get(string|int $group = null, string|int $key = null) : array
    {
        if (!isset($group) || (isset($group) && !isset($this->groups[$group]))) {
            $group = array_key_first($this->groups);
        }
        [$key, $conn] = $this->groups[$group]->get($key);
        return [$group, $key, $conn];
    }

    /**
     * 归还连接
     */
    public function put(string|int $group, string|int $key, mixed $conn) : void
    {
        $this->groups[$group]->put($key, $conn);
    }
}