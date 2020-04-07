# websocket
利用swoole扩展，保持websocket长连接，实现简易Web聊天功能，以及服务端主动推送。

[![Total Downloads](https://poser.pugx.org/haosijia/swoole-websocket-chat/downloads)](https://packagist.org/packages/haosijia/swoole-websocket-chat)
[![License](https://poser.pugx.org/haosijia/swoole-websocket-chat/license)](https://packagist.org/packages/haosijia/swoole-websocket-chat)

## 说明
本项目属于演示项目，请勿在线上生产环境使用，仅供学习参考。目前实现的功能：

- 前端（客户端）发送数据到服务器（服务端）
- 服务器（服务端）利用文件缓存，存储并管理前端（客户端）
- 服务器（服务端）将客户消息进行群发
- 多个客户端之间的群聊
- 服务端主动推送（正在更新中..）

## 准备
首先你的服务器或者电脑上要安装有以下软件和环境：

- [Apache](https://www.apache.org) Web服务器
- [Nginx](https://nginx.org) 或者这个Web服务器
- [PHP](https://www.php.net) 世界上最好的语言（滑稽）
- [Swoole](https://wiki.swoole.com) 一个 `PHP` 的 `协程` `高性能` 网络通信引擎，使用 C/C++ 语言编写。需要自己手动安装扩展，而且不支持 `Windows` 系统

并且将需要监听的端口打开，如本例中的 `2020` 端口。至于如何安装以上软件，我就不在这里详细赘述了，大家可以自行 Google 。

## 使用
先将本项目克隆下来，在命令行中输入：
```
$ git clone https://github.com/liamhao/swoole-websocket-chat.git
```
进入 `swoole-websocket-chat` 项目：
```
$ cd swoole-websocket-chat
```

### 启动 Server 服务
将项目所有文件放到你的Web服务器设置的根目录下，然后在服务器命令行中输入：
```
$ php server.php
```
如果看到下所示内容，且命令行进入等待状态，则说明websocket服务已经启动，并保持监听中：
```
$ php server.php
Swoole websocket server start

```
如果键入 `Ctrl` + `C` 退出等待状态，则websocket服务会停止监听。

### 单客户端与服务通信
如果你配置的网址为：`http://www.abc.com`，则在浏览器地址栏中输入 `http://www.abc.com/wangwu.html`。打开浏览器的调试窗口（本例为Chrome浏览器），在 `Network` tab中点击 `All`，找到 `Type` 为 `websocket` 的请求，点击此请求，在右侧的 `Messages` 中可看到消息内容。红色为服务发送的消息，绿色为浏览器（客户端）发送的消息，如下图所示。

![王五测试页](https://www.haosijia.vip/img/20200407/Websocket演示/王五测试页.png "王五测试页")

### 多客户端与服务通信
本例中你可以可打开多个页面，例如：`http://www.abc.com/zhangsan.html`、`http://www.abc.com/lisi.html`、`http://www.abc.com/wangwu.html` 同时打开，你将看到他们每个人的聊天框内都会有消息提示。如果某个人群发消息，那么其他两个人都将同时收到此消息，如下图所示。

![群聊测试页](https://www.haosijia.vip/img/20200407/Websocket演示/群聊测试页.png "群聊测试页")