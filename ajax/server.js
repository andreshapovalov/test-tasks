const http = require('http');
const url = require('url');
const path = require('path');
const qs = require('querystring');
const WebSocket = require('ws');
const HTTPServerPort = process.argv[2] || 9000;
const WebSocketServerPort = process.argv[3] || 3009;

function sendResponse(res, data) {
    let callback = null;
    let requestedBy = null;

    if (data && typeof data === 'object') {
        if (data.callback) {
            callback = data.callback;
            data = {
                id: data.id,
                email: data.email
            };
        } else if (data.requestedBy) {
            requestedBy = data.requestedBy;
            data = {
                name: data.name,
                age: data.age
            };
        }

        data.rand = Math.random();
    } else {
        data = {};
    }

    data = JSON.stringify(data);

    if (callback) {
        res.writeHead(200, {'Content-Type': 'application/javascript; charset=utf-8'});
        res.write(`${callback}('${data}');`);
    } else if (requestedBy === 'iframe') {
        res.writeHead(200, {'Content-Type': 'text/html'});
        res.write(`<script type='text/javascript'>parent.postMessage('${data}','*');</script>`);
    } else {
        res.writeHead(200, {'Content-Type': 'application/json'});
        res.write(data);
    }

    res.end();
}

http.createServer(function (req, res) {
    console.log(`${req.method} ${req.url}`);
    let parsedUrl = url.parse(req.url, true);

    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Request-Method', '*');
    res.setHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST');
    res.setHeader('Access-Control-Allow-Headers', '*');

    if (parsedUrl.path.startsWith('/process')) {
        if (req.method === 'POST') {
            var chunks = [];

            req.on('data', function (chunk) {
                chunks.push(chunk);
            });

            req.on('end', function () {
                var data = Buffer.concat(chunks).toString();

                if (data) {
                    if (data.startsWith('{')) {
                        data = JSON.parse(data);
                    } else {
                        data = qs.parse(data);
                    }
                }
                sendResponse(res, data);
            });
        } else if (req.method === 'GET') {
            sendResponse(res, parsedUrl.query);
        } else if (req.method === 'OPTIONS') {
            res.writeHead(200);
            res.end();
        } else {
            res.statusCode = 501;
            res.end(`Can't proccess the request`);
        }
    } else {
        res.statusCode = 200;
        res.end('Test server');
    }
}).listen(parseInt(HTTPServerPort));

console.log(`HTTP server listening on port ${HTTPServerPort}`);

const WebSocketServer = new WebSocket.Server({port: WebSocketServerPort});

WebSocketServer.on('connection', function connection(ws) {
    ws.on('message', function incoming(message) {
        console.log('received: %s', message);
        var requestData = JSON.parse(message);
        ws.send(JSON.stringify({
            result: {
                name: 'Yohan',
                age: 25,
                rand: Math.random()
            },
            cbId: requestData.cbId
        }));
    });
});

console.log(`WebSocket server listening on port ${WebSocketServerPort}`);