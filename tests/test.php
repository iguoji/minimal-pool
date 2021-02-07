<?php
declare(strict_types=1);

require 'Channel.php';
require 'HttpServer.php';

use Minimal\Channel;
use Swoole\Runtime;
use Swoole\Coroutine;

class Db
{
    protected Channel $channel;
    public function __construct(protected int $size)
    {
        $this->channel = new Channel($size);
        for ($i = 0;$i < $this->size; $i++) {
            $this->channel->push($this->connect(), 2);
        }
    }
    public function connect(bool $reconnect = true) : PDO
    {
        // 配置信息
        $config = [
            'host'          =>  '192.168.2.12',
            'port'          =>  3306,
            'dbname'        =>  'pk10',
            'username'      =>  'root',
            'password'      =>  '123456',
            'charset'       =>  'utf8mb4',
            'collation'     =>  'utf8mb4_unicode_ci',
        ];
        try {
            // 创建驱动
            $handle = new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s'
                    , $config['host']
                    , (int) $config['port']
                    , $config['dbname']
                    , $config['charset']
                )
                , $config['username']
                , $config['password']
                , $config['options'] ?? []
            );
            // 返回驱动
            return $handle;
        } catch (Throwable $th) {
            // 尝试重连一次
            if ($reconnect) {
                return $this->connect(false);
            }
            throw $th;
        }
    }
    public function get()
    {
        $conn = $this->channel->pop(2);
        if (false === $conn) {
            echo '找不到可用的连接！', PHP_EOL;
            return true;
        }
        Coroutine::defer(function() use($conn){
            echo '手动归还！', PHP_EOL;
            $this->channel->push($conn, 2);
        });
        try {
            $result = $conn->query('SELECT * FROM `account` LIMIT 1')->fetch();
            $result = $conn->query('SELECT * FROM `account` LIMIT 1')->fetch();
            $result = $conn->query('SELECT * FROM `account`')->fetch();
            $result = $conn->query('SELECT * FROM `account` LIMIT 1')->fetch();
            // var_dump($result);
        } catch (\Throwable $th) {
            // echo '出错了: ' . microtime(true) , PHP_EOL;
            // echo '协程：' . Coroutine::getCid(), PHP_EOL;
            // echo '标识：' . spl_object_id($conn), PHP_EOL;
            echo 'Exec：' . $th->getMessage() , PHP_EOL;
        }
        return $result ?? [];
    }
}
$db = new Db(40);


// 启动服务
new HttpServer('0.0.0.0', 9501, function($server, $req, $res) use($db){
    $res->status(200);
    $res->header('Content-Type', 'application/json;charset=utf-8');
    $result = $db->get();
    $res->end(json_encode($result));
});