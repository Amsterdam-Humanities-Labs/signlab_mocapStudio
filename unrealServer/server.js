const WebSocket = require('ws');

const wss = new WebSocket.Server({ port: 3002 });
let intervalId;  // This will hold the interval ID for clearing it later

wss.on('connection', function connection(ws) {
  console.log('Client connected');


  ws.on('message', function incoming(message) {
    console.log('received: %s', message);
    try {
      const data = JSON.parse(message);
      console.log(data.handler)

       if (data) {
      console.log(data.handler);
      var wsArray = {};
      if(data.data == "startCapture")
      {
            wsArray = {
          "set": "startRecord",
          "handler": "recorder"
        }
        wsArray = JSON.stringify(wsArray);

        wss.clients.forEach(function each(client) {
          client.send(wsArray, function ack(error) {
            if (error) {
              console.error('Error sending "startrecord":', error);
              return;
            }
            console.log('Sent command: startrecord');
          });
        });
      }
      if(data.data == "stopCapture")
      {
          wsArray = {
          "set": "stopRecord",
          "handler": "recorder"
        }
        wsArray = JSON.stringify(wsArray);

         wss.clients.forEach(function each(client) {
          client.send(wsArray, function ack(error) {
            if (error) {
              console.error('Error sending "startrecord":', error);
              return;
            }
            console.log('Sent command: startrecord');
          });
        });
      }
       if(data.data == "replayCapture")
      {
          wsArray = {
          "set": "replayRecord",
          "handler": "recorder"
        }
        wsArray = JSON.stringify(wsArray);

         wss.clients.forEach(function each(client) {
          client.send(wsArray, function ack(error) {
            if (error) {
              console.error('Error sending "startrecord":', error);
              return;
            }
            console.log('Sent command: startrecord');
          });
        });
      }
      if(data.data == "glosName")
      {
         wsArray = {
          "glosName": data.glosName,
          "handler": "glosName"
        }
        wsArray = JSON.stringify(wsArray);

       wss.clients.forEach(function each(client) {
          client.send(wsArray, function ack(error) {
            if (error) {
              console.error('Error sending "startrecord":', error);
              return;
            }
            console.log('Sent command: startrecord');
          });
        });
      }
      if(data.data == "exportLevelSequenceName")
      {
         wsArray = {
          "set": "exportLevelSequenceName",
          "handler": "exportLevelSequenceName"
        }
        wsArray = JSON.stringify(wsArray);

       wss.clients.forEach(function each(client) {
          client.send(wsArray, function ack(error) {
            if (error) {
              console.error('Error sending "startrecord":', error);
              return;
            }
            console.log('Sent command: startrecord');
          });
        });
      }

      if(data.data == "broadcastGlos")
      {
         wsArray = {
          "set": data.glos,
          "handler": "broadcastGlos"
        }
        wsArray = JSON.stringify(wsArray);

       wss.clients.forEach(function each(client) {
          client.send(wsArray, function ack(error) {
            if (error) {
              console.error('Error sending "startrecord":', error);
              return;
            }
            console.log('Sent command: startrecord');
          });
        });
      }
      if(data.handler == "fbxExportName")
      {
         wsArray = {
          "glosName": data.glosName,
          "data": data.data,
          "set": "fbxExportName"
        }
        wsArray = JSON.stringify(wsArray);

        console.log(wsArray)

       wss.clients.forEach(function each(client) {
          client.send(wsArray, function ack(error) {
            if (error) {
              console.error('Error sending "startrecord":', error);
              return;
            }
            console.log('Sent command: startrecord');
          });
        });
      }

    }
    } catch (error) {
      console.log(error);
    }
  });

  ws.on('close', function close() {
    console.log('Client disconnected');
    // Clear the interval when the client disconnects
    if (intervalId) {
      clearInterval(intervalId);
    }
  });
});
