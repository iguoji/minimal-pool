<?php
declare(strict_types=1);

require 'HttpServer.php';

use Swoole\Runtime;
use Swoole\Coroutine;

class Db
{
    protected $pdo;
    public function __construct(protected int $size)
    {
        $this->pdo = $this->connect();
    }
    public function connect(bool $reconnect = true) : PDO
    {
        // 配置信息
        $config = [
            'host'          =>  '127.0.0.1',
            'port'          =>  3366,
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
        try {
            $result = $this->pdo->query('SELECT * FROM `account` LIMIT 1')->fetch();
            $result = $this->pdo->query('SELECT * FROM `account` LIMIT 1')->fetch();
            $result = $this->pdo->query('SELECT * FROM `account`')->fetch();
            $result = $this->pdo->query('SELECT * FROM `account` LIMIT 1')->fetch();
            // var_dump($result);
        } catch (\Throwable $th) {
            echo 'Exec：' . $th->getMessage() , PHP_EOL;
            $this->pdo = $this->connect();
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


// .\ab.exe -c 10 -n 50 http://192.168.2.12:9501/get
// Document Path:          /get
// Document Length:        736 bytes

// Concurrency Level:      10
// Time taken for tests:   0.548 seconds
// Complete requests:      50
// Failed requests:        0
// Total transferred:      45300 bytes
// HTML transferred:       36800 bytes
// Requests per second:    91.18 [#/sec] (mean)
// Time per request:       109.671 [ms] (mean)
// Time per request:       10.967 [ms] (mean, across all concurrent requests)
// Transfer rate:          80.67 [Kbytes/sec] received


// .\ab.exe -c 100 -n 500 http://192.168.2.12:9501/get
// Document Path:          /get
// Document Length:        736 bytes

// Concurrency Level:      100
// Time taken for tests:   5.328 seconds
// Complete requests:      500
// Failed requests:        0
// Total transferred:      453000 bytes
// HTML transferred:       368000 bytes
// Requests per second:    93.85 [#/sec] (mean)
// Time per request:       1065.577 [ms] (mean)
// Time per request:       10.656 [ms] (mean, across all concurrent requests)
// Transfer rate:          83.03 [Kbytes/sec] received