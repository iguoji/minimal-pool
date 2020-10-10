<?php
declare(strict_types=1);

namespace Minimal\Pool;

use RuntimeException;
use Swoole\Atomic;
use Swoole\Coroutine\Channel;

/**
 * 服务器类
 * 管理当前服务器的连接池
 */
class Server
{
    /**
     * 连接通道
     */
    protected Channel $channel;

    /**
     * 已创建的数量
     */
    protected Atomic $number;

    /**
     * 构造函数
     */
    public function __construct(protected int $size, protected array $config, protected string|callback $constructor)
    {
        $this->channel = new Channel($size);
        $this->number = new Atomic();
        $this->fill();
    }

    /**
     * 是否创建完了
     */
    public function isFull() : bool
    {
        return $this->number->get() >= $this->channel->capacity;
    }

    /**
     * 是否有空闲连接
     */
    public function isIdle() : bool
    {
        return !$this->channel->isEmpty();
    }

    /**
     * 取出连接
     */
    public function get() : mixed
    {
        if (!$this->isIdle() && !$this->isFull()) {
            $this->make();
        }
        $conn = $this->channel->pop($this->config['options']['timeout'] ?? 2);
        if (false === $conn) {
            throw new RuntimeException('get connection timeout');
        }
        return $conn;
    }

    /**
     * 归还连接
     */
    public function put(mixed $conn) : void
    {
        $bool = $this->channel->push($conn, $this->config['options']['timeout'] ?? 2);
        if (!$bool) {
            throw new RuntimeException('put connection timeout');
        }
    }

    /**
     * 填充连接
     */
    public function fill(int $size = null) : void
    {
        $size = $size ?? $this->size;
        for ($i = 0;$i < $size && !$this->isFull(); $i++) {
            $this->make();
        }
    }

    /**
     * 关闭连接
     */
    public function close() : void
    {
        $this->channel->close();
        $this->channel = null;
        $this->number->set(0);
        $this->number = null;
        $this->config = [];
        $this->config = null;
    }

    /**
     * 创建连接
     */
    private function make() : void
    {
        $this->number->add();
        try {
            $constructor = $this->constructor;
            if (is_string($constructor) && class_exists($constructor)) {
                $conn = new $constructor($this->config);
            } else if (is_callable($constructor)) {
                $conn = $constructor($this->config);
            } else {
                throw new RuntimeException('error pool constructor');
            }
        } catch (Throwable $th) {
            $this->number->sub();
            throw $th;
        }
        $this->put($conn);
    }
}