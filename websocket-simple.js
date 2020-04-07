// 简单封装一下websocket
var simpleWS = {
  onopen: function(){},
  onclose: function(){},
  onmessage: function(){},
  send: function(){},
};

simpleWS.connect = function(url){

  // 通过js的websocket接口连接服务端,此接口需要现代浏览器才能支持,API详情：https://developer.mozilla.org/zh-CN/docs/Web/API/Websockets_API#浏览器兼容性
  // 如果服务端是带有SSL安全证书的端口，则需要写成“wss://”
  // 这里简单的设置一下当前用户的姓名,传给后台做姓名与fd匹配
  this.ws = new WebSocket(url);

  // 连接成功
  this.ws.onopen = function(event){
    showOnWindow({type:'系统消息',content:'正在连接...'});
    console.log(event);
    simpleWS.onopen(event);
  };

  // 连接关闭
  this.ws.onclose = function(event){
    showOnWindow({type:'系统消息',content:'连接关闭'});
    console.log(event);
    simpleWS.onclose(event);
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
    simpleWS.onmessage(event);
  };

  // 向服务端发送数据
  this.send = function(msg){
    this.ws.send(msg)
  }
  
  return this;
}

// 将消息展示到页面
var showOnWindow = function(msg){
  var color = msg.type=='系统消息'?'red':'#3973e2';
  var html = '<div><span style="color: '+color+';">'+msg.type+'</span> : '+msg.content+'</div>';
  $('.message-window').append(html);
}