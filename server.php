<?php
  // netstat -tunlp|grep 2020
  // ps -ef|grep {pid}
  // pstree -p {pid}
  // kill -9 {pid}

  $host = '0.0.0.0';

  $port = '2020';

  // 同步 IO 的代码变成可以协程调度的异步 IO，即一键协程化
  \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

  // 获取当前进程的pid
  // $mpid = posix_getpid();
  // 为进程命名
  swoole_set_process_name('Swoole:Master');

  // 实例化一个WebSocket服务端
  $server = new \Swoole\WebSocket\Server($host, $port, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

  // 配置服务信息，更多参数参考官方文档：https://wiki.swoole.com/#/server/setting
  $server->set([
    'enable_coroutine' => 1,
    // 'log_file' => '',
    // 'ssl_cert_file' => '',
    // 'ssl_key_file' => '',
    'daemonize' => 0,
    'worker_num' => 1,
    'max_request' => 2,
  ]);

  // 监听连接进入事件
  $server->on('connect', function ($serv, $fd) {
    echo "fd: $fd Connect\n";
    // 此时还未完成websocket连接的建立,还不能向客户端发送消息
  });

  // 监听WebSocket连接打开事件
  $server->on('open', function ($serv, $request) {
    echo "fd: $request->fd Open\n";

    // 管理客户端连接,计入缓存,这里获取到request参数,做姓名与fd的匹配,以便后面做给指定个人发送消息
    setNameByCache($request->get['name'], $request->fd);

    // 向此客户端发送消息
    $serv->push($request->fd, json_encode(['type'=>'系统消息','content'=>'连接成功'], JSON_UNESCAPED_UNICODE));
    // 将文件缓存中的fd和对应的姓名取出
    $names = getNamesByCache();
    // 群发
    foreach ($serv->connections as $fd) {
      $serv->push($fd, json_encode(['type'=>'系统消息','content'=>$names[$request->fd].'已登录']));
    }
  });

  // 监听WebSocket服务端收到消息事件
  $server->on('message', function ($serv, $frame) {
    echo "fd: $frame->fd send message: $frame->data\n";
    // 将文件缓存中的fd和对应的姓名取出
    $names = getNamesByCache();

    // 群发
    foreach ($serv->connections as $fd) {
      $serv->push($fd, json_encode(['type'=>$names[$frame->fd],'content'=>$frame->data]));
    }

  });

  // 监听http请求
  $server->on('request', function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) {
    echo "Request\n";
  });

  // 监听WebSocket客户端关闭事件
  $server->on('close', function ($serv, $fd) {
    echo "fd: $fd Close\n";
    // 将文件缓存中的fd和对应的姓名取出
    $names = getNamesByCache();
    // 群发
    foreach ($serv->connections as $connect_fd) {
      $serv->push($connect_fd, json_encode(['type'=>'系统消息','content'=>$names[$fd].'已退出']));
    }
  });

  $server->start();

  // 通过本地的文件缓存获取对应的用户名和fd
  function getNamesByCache()
  {
    $names = [];
    $dir = dir('cache');
    while(($file = $dir->read()) !== false){
      if ($file != "." && $file != "..") {
        if (file_exists('cache/'.$file)) {
          $fd = file_get_contents('cache/'.$file);
          $names[$fd] = explode('.', $file)[0];
        }
      }
    }
    return $names;
  }

  // 记录姓名和fd的对应关系到文件缓存,简单的用本地文件做存储,实际开发中可以用Redis等性能更好的缓存
  function setNameByCache(String $name, Int $fd)
  {
    if(!file_exists('cache')){ //检查文件夹是否存在
      mkdir ("cache"); //没有就创建一个新文件夹
    }
    $name_fd_file = fopen('cache/'.$name.".fd", "w") or die("Unable to open file!");
    //生成的文件名类似"张三.fd",文件内容为websocket连接的fd
    fwrite($name_fd_file, $fd);
    fclose($name_fd_file);
    return $name_fd_file;
  }