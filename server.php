<?php
  // netstat -tunlp|grep 2020
  // ps -ef|grep {pid}
  // pstree -p {pid}
  // kill -9 {pid}

  $host = '0.0.0.0';

  $port = '2020';

  // 同步 IO 的代码变成可以协程调度的异步 IO，即一键协程化
  // \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

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
    // 每个worker进程最大处理任务次数（解读：当server启动后，后台会拉起[worker_num]个数量的worker进程，设置这个值以后，会给每个worker进程一个[处理任务数]的限制，当某个worker进程到达或者超过[处理任务数]，则会销毁这个worker进程，释放资源，然后再拉起一个新的worker进程）
    // 这个参数的目的是为了解决swoole常驻内存中，因为php编码不规范，没有销毁全局变量，导致系统内存溢出的问题。建议设置为非零的数字
    'max_request' => 2,
  ]);

  // 服务端启动时
  $server->on('start', function ($serv) {
    echo "Swoole websocket server start\n";
    // 清理全部缓存
    cleanCache();
  });

  // 监听连接进入事件
  $server->on('connect', function ($serv, $fd) {
    echo "fd: $fd Connect\n";
    // 此时还未完成websocket连接的建立,还不能向客户端发送消息

  });

  // 监听WebSocket连接打开事件
  $server->on('open', function ($serv, $request) {
    echo "fd: $request->fd Open\n";

    // 将文件缓存中的fd和对应的姓名取出
    $names = getNamesByCache();
    // 当同一个用户打开多个网页时,需要关闭旧的连接,释放系统资源,并将旧的连接缓存清理掉,否则的话会造成用户之间的串线等严重问题
    foreach ($names as $fd => $name) {
      if($name == $request->get['name']){
        // 服务端主动关闭连接,因为在onclose事件中已经清理过缓存,这里就不用再清理一遍了
        $serv->close($fd);
      }
    }
    // 管理客户端连接,计入缓存,这里获取到request参数,做姓名与fd的匹配,以便后面做给指定个人发送消息
    setCache($request->get['name'], $request->fd);

    // 向此客户端发送消息
    $serv->push($request->fd, encode('系统消息','连接成功'));

    // 将文件缓存中的fd和对应的姓名取出
    $names = getNamesByCache();
    // 群发
    foreach ($serv->connections as $fd) {
      if ($serv->isEstablished($fd)) {
        $serv->push($fd, encode('系统消息',$names[$request->fd].'已进入群聊'));
      }
    }
  });

  // 监听WebSocket服务端收到消息事件
  $server->on('message', function ($serv, $frame) {
    echo "fd: $frame->fd send message: $frame->data\n";
    // 将文件缓存中的fd和对应的姓名取出
    $names = getNamesByCache();

    // 群发
    foreach ($serv->connections as $fd) {
      if ($serv->isEstablished($fd)) {
        $serv->push($fd, encode($names[$frame->fd],$frame->data));
      }
    }

  });

  // 监听http请求
  $server->on('request', function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) {
    global $server;
    echo "Request\n";
    if(
      $request->server['request_method'] == 'POST'
      &&
      $request->server['request_uri'] == '/push-tongzhi'
    ){
      $response->header('Access-Control-Allow-Origin', '*');
      // 开始通过websocket群发消息
      foreach ($server->connections as $fd) {
        if ($server->isEstablished($fd)) {
          $server->push($fd, encode('系统主动推送消息',$request->post['content']));
        }
      }
      // 向http请求返回响应内容
      $response->header("Content-Type", "application/json; charset=utf-8");
      $response->status(200);
      $response->end(encode('系统主动推送消息结果','成功'));
    } else {
      // 未匹配到推送消息的路由,返回http响应内容
      $response->header("Content-Type", "text/html; charset=utf-8");
      $response->status(200);
      $response->end('服务端已收到请求，但未匹配到主动推送消息的路由');
    }
  });

  // 监听WebSocket客户端关闭事件
  $server->on('close', function ($serv, $fd) {
    echo "fd: $fd Close\n";
    // 将文件缓存中的fd和对应的姓名取出
    $names = getNamesByCache();
    // 群发
    foreach ($serv->connections as $connect_fd) {
      // 向有效的连接发送消息,当前此$fd的连接已经断开,无法发送消息,所以要排除掉
      if ($serv->isEstablished($connect_fd) && $connect_fd != $fd) {
        $serv->push($connect_fd, encode('系统消息',$names[$fd].'已退出群聊'));
      }
    }
    // 删除此客户的缓存
    delCache($names[$fd]);
  });

  // 启动服务
  $server->start();

  /**
   * 以下为简单封装的一些函数，通过文件保存的方式模拟MySQL和Redis缓存等存储服务
   */
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
  function setCache(String $name, Int $fd)
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

  // 删除某个客户的缓存
  function delCache(String $name)
  {
    unlink('cache/'.$name.'.fd');
  }

  // 清理全部缓存
  function cleanCache()
  {
    $dir = dir('cache');
    while(($file = $dir->read()) !== false){
      if ($file != "." && $file != "..") {
        if (file_exists('cache/'.$file)) {
          unlink('cache/'.$file);
        }
      }
    }
  }

  // 将消息进行json转换
  function encode($type, $content)
  {
    // 不转义中文
    return json_encode(['type'=>$type,'content'=>$content], JSON_UNESCAPED_UNICODE);
  }