<?php
declare(strict_types=1);

namespace Minimal;

use Swoole\Timer;
use Swoole\Table;
use Swoole\Atomic;
use Swoole\Coroutine;
use RuntimeException;

/**
 * 通道类
 */
class Channel
{
    /**
     * 错误码
     */
    public int $errCode = SWOOLE_CHANNEL_OK;

    /**
     * 事件类型
     */
    public const EVENT_PUSH = 1;
    public const EVENT_POP = 2;

    /**
     * 通道容量
     */
    public int $capacity = 1;

    /**
     * 数据状态
     */
    protected Table $status;

    /**
     * 全局数据
     */
    protected array $dataset = [];

    /**
     * 超时队列
     */
    protected array $queue = [];

    /**
     * 构造函数
     */
    public function __construct(int $capacity = 1)
    {
        // 容量最小值
        if ($capacity < 1) {
            $capacity = 1;
        }
        // 元素状态
        $this->status = new Table($capacity * 2);
        $this->status->column('id', Table::TYPE_INT, 2);
        $this->status->column('status', Table::TYPE_INT, 1);
        $this->status->column('push', Table::TYPE_INT, 8);
        $this->status->column('pop', Table::TYPE_INT, 8);
        $this->status->create();
        // 通道容量
        $this->capacity = $capacity;
    }

    /**
     * 写入数据
     */
    public function push(object $obj, float $timeout = -1) : bool
    {
        // 对象编号
        $id = (string) spl_object_id($obj);
        echo sprintf('准备写入：%s', $id), PHP_EOL;
        // 是否存在
        $exist = $this->status->exist($id);
        echo sprintf('判断存在：%s', $exist ? 'true' : 'false'), PHP_EOL;
        // 如果存在
        if ($exist) {
            // 状态不对 - 稍微等等
            if (1 === $this->status->get($id, 'status') && -1 != $timeout) {
                echo sprintf('状态不对，稍微等等：%s', $this->status->get($id, 'status')), PHP_EOL;
                $this->wait(static::EVENT_POP, $id, $timeout);
                echo sprintf('等等完毕，继续操作：%s', $this->status->get($id, 'status')), PHP_EOL;
            }
        } else {
            // 容量足够 - 无法新增
            if ($this->status->count() >= $this->capacity) {
                echo sprintf('容量到达上限：%s', $this->status->count()), PHP_EOL;
                return false;
            }
        }
        // 状态不对 - 无需在等
        if (1 === $this->status->get($id, 'status')) {
            echo sprintf('状态二次判断不对：%s', $this->status->get($id, 'status')), PHP_EOL;
            return false;
        }
        // 保存对象
        if (!isset($this->dataset[$id])) {
            $obj->id = $id;
            $this->dataset[$id] = $obj;
            echo sprintf('第一次保存：%s', $id), PHP_EOL;
        }
        // 更新状态
        $bool = $this->status->set($id, [
            'id'        =>  $id,
            'status'    =>  1,
        ]);
        if (false === $bool) {
            echo sprintf('更新状态失败：%s', 'false'), PHP_EOL;
            throw new RuntimeException('很抱歉、对象标记为已归还失败了！');
        }
        // 统计次数
        $count = $this->status->incr($id, 'push');
        echo sprintf('更新次数：%s', $count), PHP_EOL;

        // 触发事件
        $this->trigger(static::EVENT_PUSH, $id);

        echo PHP_EOL;

        // 返回结果
        return true;
    }

    /**
     * 读取数据
     */
    public function pop(float $timeout = -1) : mixed
    {
        // 寻找编号
        $id = -1;
        $idList = [];
        foreach ($this->status as $key => $item) {
            if (1 === $item['status']) {
                $idList[] = $item['id'];
            }
        }
        // 没有编号 - 稍微等等
        if (empty($idList) && -1 != $timeout) {
            $this->wait(static::EVENT_PUSH, $id, $timeout);
            return $this->pop(-1);
        }
        // 随机编号
        if (!empty($idList)) {
            $id = $idList[mt_rand(0, count($idList) - 1)];
        } else {
            return false;
        }
        // 真没编号
        if (-1 == $id) {
            echo sprintf('没有编号：%s', $id), PHP_EOL;
            $this->stats();
            return false;
        }
        $id = (string) $id;
        echo sprintf('读取编号：%s', $id), PHP_EOL;
        // 更新状态
        $bool = $this->status->set($id, [
            'status'    =>  0,
        ]);
        if (false === $bool) {
            echo sprintf('更新状态失败：%s', 'false'), PHP_EOL;
            throw new Exception('很抱歉、对象标记为已取出失败了！');
        }
        // 统计次数
        $count = $this->status->incr($id, 'pop');
        echo sprintf('更新次数：%s', $count), PHP_EOL;
        // 取出对象
        $conn = $this->dataset[$id];
        $conn->token = Coroutine::getCid();

        // 延时归还
        Coroutine::defer(function() use($id){
            echo sprintf('延时归还：%s', $id), PHP_EOL;
            $this->trigger(static::EVENT_POP, $id);
        });
        echo PHP_EOL;

        // 返回结果
        return $conn;
    }

    /**
     * 通道状态
     */
    public function stats() : array
    {
        $consumer_num = 0;
        $producer_num = 0;
        foreach ($this->queue ?? [] as $item) {
            if (1 == $item['type']) {
                $producer_num++;
            } else if (2 == $item['type']) {
                $consumer_num++;
            }
        }
        var_dump($this->queue ?? []);
        foreach ($this->status as $item) {
            if (0 === $item['status']) {
                print_r($item);
            }
        }
        return [
            'consumer_num'  =>  $consumer_num,
            'producer_num'  =>  $producer_num,
            'queue_num'     =>  count($this->queue ?? []),
        ];
    }

    /**
     * 关闭通道
     * 并唤醒所有等待读写的协程。
     * 唤醒所有生产者协程，push 方法返回 false；唤醒所有消费者协程，pop 方法返回 false
     */
    public function close() : bool
    {
        return true;
    }

    /**
     * 元素数量
     */
    public function length() : int
    {
        return $this->status->count();
    }

    /**
     * 是否为空
     */
    public function isEmpty() : bool
    {
        $isEmpty = true;
        foreach ($this->status as $id => $item) {
            if (1 == $item['status']) {
                $isEmpty = false;
                break;
            }
        }
        return 0 == $this->length() || $isEmpty;
    }

    /**
     * 是否已满
     */
    public function isFull() : bool
    {
        $isFull = true;
        foreach ($this->status as $id => $item) {
            if (0 == $item['status']) {
                $isFull = false;
                break;
            }
        }
        return $this->capacity <= $this->length() && $isFull;
    }

    /**
     * 等待事件
     */
    public function wait(int $type, int|float|string $id, float $timeout = null) : bool
    {
        // 调整参数
        if (is_null($timeout)) {
            $timeout = $id;
            $id = -1;
        }
        $id = (int) $id;
        $timeout = (int) ($timeout * 1000);
        // 保存队列
        array_push($this->queue, [
            'id'        =>  $id,
            'type'      =>  $type,
            'coroutine' =>  Coroutine::getCid(),
            'start_at'  =>  time(),
            'end_at'    =>  time() + $timeout
        ]);
        // 超时设置
        $cid = Coroutine::getCid();
        Timer::after($timeout, function() use($cid){
            return Coroutine::resume($cid);
        });
        // 让出协程
        return Coroutine::yield();
    }

    /**
     * 触发事件
     */
    public function trigger(int $type, int|string $id) : bool
    {
        // 对象编号
        $id = (int) $id;
        // 队列编号
        $qid = null;
        // 循环队列
        foreach ($this->queue as $key => $item) {
            // 类型相同
            if ($type === $item['type'] && (-1 === $item['id'] || $id === $item['id'])) {
                // 保存编号
                $qid = $key;
                // 退出循环 - 一次只需处理一个事件
                break;
            }
        }
        // 需要处理
        if (!is_null($qid)) {
            // 恢复协程
            Coroutine::resume($this->queue[$qid]['coroutine']);
            // 移除队列
            unset($this->queue[$qid]);
        }
        // 返回结果
        return true;
    }
}