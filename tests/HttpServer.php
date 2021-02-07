<?php

use Swoole\Http\Server;
use Swoole\Coroutine;

class HttpServer
{
    public function __construct(string $ip, int $port, callable $callback)
    {
        $server = new Server($ip, $port);
        $server->set([
            'worker_num'        =>  2,
            'task_worker_num'   =>  2,
            // 'hook_flags'        =>  SWOOLE_HOOK_TCP,
        ]);
        $server->on('start', function($server){
            echo 'onStart', PHP_EOL;
        });
        $server->on('ManagerStart', function($server){
            echo 'onManagerStart', PHP_EOL;
            cli_set_process_title('php swoole manager');
        });
        $server->on('workerStart', function($server, $workerId){
            echo 'onWorkerStart', PHP_EOL;
            echo 'workerId: '. $workerId, PHP_EOL;
            cli_set_process_title(sprintf('php swoole http server worker #%s', $workerId));
        });
        $server->on('workerStop', function($server, $workerId){
            echo 'onWorkerStop', PHP_EOL;
            echo 'workerId: '. $workerId, PHP_EOL;
        });
        $server->on('workerExit', function($server, $workerId){
            echo 'onWorkerExit', PHP_EOL;
            echo 'workerId: '. $workerId, PHP_EOL;
        });
        $server->on('connect', function($server, $fd, $reactorId){
            // echo 'onConnect', PHP_EOL;
        });
        $server->on('request', function($req, $res) use($server, $callback){
            // echo 'onRequest', PHP_EOL;
            Coroutine::create(function() use($server, $req, $res, $callback){
                try {
                    // 回调函数
                    $callback($server, $req, $res);
                } catch (\Throwable $th) {
                    echo $th->getMessage(), PHP_EOL;
                    echo $th->getFile(), PHP_EOL;
                    echo $th->getLine(), PHP_EOL;
                }
            });
        });
        $server->on('Receive', function($server, $fd, $reactorId, $data){
            echo 'onReceive', PHP_EOL;
        });
        $server->on('Packet', function($server, $data, $clientInfo){
            echo 'onPacket', PHP_EOL;
        });
        $server->on('Close', function($server, $fd, $reactorId){
            // echo 'onClose', PHP_EOL;
        });
        $server->on('task', function($server, $task){
            echo 'onTask', PHP_EOL;
            $task->finish(time());
        });
        $server->on('finish', function($server, $task_id, $data){
            echo 'onFinish', PHP_EOL;
        });
        $server->on('PipeMessage', function($server, $src_worker_id, $message){
            echo 'onPipeMessage', PHP_EOL;
        });
        $server->on('WorkerError', function($server, $workerId, $worker_pid, $exit_code, $signal){
            echo 'onWorkerError', PHP_EOL;
            echo 'workerId: '. $workerId, PHP_EOL;
        });
        $server->on('ManagerStop', function($server){
            echo 'onManagerStop', PHP_EOL;
        });
        $server->on('BeforeReload', function($server){
            echo 'onBeforeReload', PHP_EOL;
        });
        $server->on('AfterReload', function($server){
            echo 'onAfterReload', PHP_EOL;
        });
        $server->on('shutdown', function($server){
            echo 'onShutdown', PHP_EOL;
        });
        $server->start();
    }
}