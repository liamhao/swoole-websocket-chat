// 简单封装一下websocket
var simpleWS = {

  url: '127.0.0.1',
  port: '2020',
  name: '游客',

  // 设置url
  setUrl: function(url){
    this.url = url;
    return this;
  },

  // 设置端口号
  setPort: function(port){
    this.port = port;
    return this;
  },

  // 在url中的query中设置当前客户端客户姓名
  setName: function(name){
    this.name = name;
    return this;
  },

  // 启动连接
  connect: function(){
    // 通过js的websocket接口连接服务端,此接口需要现代浏览器才能支持,API详情：https://developer.mozilla.org/zh-CN/docs/Web/API/Websockets_API#浏览器兼容性
    // 如果服务端是带有SSL安全证书的端口，则需要写成“wss://”
    var realUrl = 'ws://'+this.url + ':' + this.port;
    // 这里简单的设置一下当前用户的姓名,传给后台做姓名与fd匹配
    this.ws = new WebSocket(realUrl+'?name='+this.name);

    // 连接成功
    this.ws.onopen = function(event){
      console.log(event);
      showOnWindow({type:'系统消息',content:'正在连接...'});
    };

    // 连接关闭
    this.ws.onclose = function(event){
      showOnWindow({type:'系统消息',content:'连接关闭'});
    };

    // 收到服务器数据
    this.ws.onmessage = function(event){
      try {
        if (typeof JSON.parse(event.data) == "object") {
          // 展示消息
          showOnWindow(JSON.parse(event.data));
        }
      } catch(e) {
        console.log(e);
        console.log(event);
      }
    };
    return this;
  },

  // 向服务端发送数据
  send: function(msg){
    this.ws.send(msg);
  },

  // 断开连接
  close: function(){
    this.ws.close();
  },

};

// 将消息展示到页面
var showOnWindow = function(msg){
  var color = msg.type=='系统消息'?'red':'#3973e2';
  var html = '<div><span style="color: '+color+';">'+msg.type+'</span> : '+msg.content+'</div>';
  $('.message-window').append(html);
}