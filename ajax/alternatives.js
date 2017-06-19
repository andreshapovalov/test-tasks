/**
 * This module provides utils methods
 */
var Util = (function () {
    function generateUUID() {
        var timestamp = Date.now();
        if (typeof performance !== undefined && typeof performance.now === 'function') {
            timestamp += performance.now();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {
            var randomValue = (timestamp + Math.random() * 16) % 16 | 0;
            timestamp = Math.floor(timestamp / 16);
            return (char === 'x' ? randomValue : (randomValue & 0x3 | 0x8)).toString(16);
        });
    }

    function buildURL(url, params) {
        var queryString = '';
        if (params && typeof params === 'object') {
            queryString = buildQueryString(params)
        }

        if (queryString) {
            url += ~url.indexOf('?') ? '&' : '?';
            url += queryString;
        }
        return url;
    }

    function buildQueryString(params) {
        var queryString = '';
        if (params && typeof params === 'object') {
            queryString = Object.keys(params)
                .map(function (key) {
                    return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
                })
                .join('&');
        }
        return queryString;
    }

    return {
        generateUUID: generateUUID,
        buildURL: buildURL,
        buildQueryString: buildQueryString
    }
}());

/**
 * This module gives ability to send/get data by JSONP
 * @requires Util
 */
var JSONPProxy = (function (util) {

    window.JSONPCallbackRegistry = {};

    function createScript(url) {
        var script = document.createElement('script');
        script.src = url;
        return script;
    }

    function prepareURL(url, callbackName, data) {
        url += ~url.indexOf('?') ? '&' : '?';
        url += 'callback=JSONPCallbackRegistry.' + callbackName;
        url += '&' + util.buildQueryString(data);
        return url;
    }

    function registerCallback(name, onSuccessCallback, onInvokeCallback) {
        JSONPCallbackRegistry[name] = function (response) {
            if (onSuccessCallback && typeof onSuccessCallback === 'function') {
                var responseData = null;
                if (response) {
                    responseData = JSON.parse(response);
                }
                onSuccessCallback(responseData);
            }

            if (onInvokeCallback && typeof onInvokeCallback === 'function') {
                onInvokeCallback();
            }
        };
    }

    function send(url, data, callback) {
        var callbackName = 'cb' + ('' + Math.random()).slice(-6);
        url = prepareURL(url, callbackName, data);
        var script = createScript(url);
        document.body.appendChild(script);
        registerCallback(callbackName, callback, function () {
            delete JSONPCallbackRegistry[callbackName];
            script.parentNode.removeChild(script);
        });
    }

    return {
        send: send
    }
}(Util));

/**
 * This module gives ability to send/get data by workaround with iframe
 * @requires Util
 */
var IFrameProxy = (function (util) {

    function registerOnMessageCallback(callback) {
        if (callback) {
            var eventMethod = window.addEventListener ? 'addEventListener' : 'attachEvent';
            var eventer = window[eventMethod];
            var messageEvent = eventMethod === 'attachEvent' ? 'onmessage' : 'message';
            eventer(messageEvent, callback, false);
        }
    }

    function removeOnMessageCallback(callback) {
        if (callback) {
            var eventMethod = window.removeEventListener ? 'removeEventListener' : 'detachEvent';
            var eventer = window[eventMethod];
            var messageEvent = eventMethod === 'detachEvent' ? 'onmessage' : 'message';
            eventer(messageEvent, callback, false);
        }
    }

    function createIFrame(name) {
        var iframe = document.createElement('iframe');
        iframe.name = name;
        iframe.style.display = 'none';
        return iframe;
    }

    function createForm(url, target, data, method) {
        var form = document.createElement('form');
        form.method = method;
        form.action = url;
        form.target = target;
        form.style.display = 'none';

        if (data && typeof data === 'object') {
            var addField = function (key, value) {
                var hiddenField = document.createElement('input');
                hiddenField.setAttribute('type', 'hidden');
                hiddenField.setAttribute('name', key);
                hiddenField.setAttribute('value', value);
                form.appendChild(hiddenField);
            };

            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    if (data[key] instanceof Array) {
                        for (var i = 0; i < data[key].length; i++) {
                            addField(key, data[key][i])
                        }
                    }
                    else {
                        addField(key, data[key]);
                    }
                }
            }
        }

        var serviceInput = document.createElement('input');
        serviceInput.type = 'hidden';
        serviceInput.value = 'iframe';
        serviceInput.name = 'requestedBy';
        form.appendChild(serviceInput);
        return form;
    }

    function send(url, data, method, callback) {
        method = method || 'post';

        var onResponseCallback = function (e) {
            var data = e.data || null;
            if (callback && typeof callback === 'function') {
                if (data) {
                    data = JSON.parse(data);
                }
                callback(data);
            } else {
                console.log(data);
            }
            removeOnMessageCallback(onResponseCallback);
        };

        registerOnMessageCallback(onResponseCallback);
        var iframeName = util.generateUUID();
        var iframe = createIFrame(iframeName);
        var form = createForm(url, iframeName, data, method);
        document.body.appendChild(iframe);
        document.body.appendChild(form);
        form.submit();
        form.parentNode.removeChild(form);

        setTimeout(function () {
            removeOnMessageCallback(onResponseCallback);
            iframe.parentNode.removeChild(iframe);
        }, 30000);
    }

    return {
        send: send
    }
}(Util));

/**
 * This module wraps Fetch Api and provides a method for sending/receiving data
 * @requires Util
 */
var FetchApiProxy = (function (util) {

    function send(url, data, method, onSuccessCallback, onErrorCallback) {
        method = method || 'post';

        var requestOptions = {
            method: method,
            mode: 'cors'
        };

        if (method === 'post') {
            requestOptions.body = JSON.stringify(data);
        } else {
            url = util.buildURL(url, data);
        }

        var request = new Request(url, requestOptions);

        fetch(request).then(function (response) {
            return response.json();
        }).then(onSuccessCallback).catch(onErrorCallback);
    }

    return {
        send: send
    }
}(Util));

/**
 * This module wraps WebSocket and provides a method for sending/receiving data
 * @requires Util
 */
var WebSocketProxy = (function (util) {
    var ws = null;
    var callbacksQueue = {};

    function init(url) {
        if (window.WebSocket) {
            ws = new WebSocket(url);
            ws.onmessage = function (e) {
                var data = null;

                try {
                    data = JSON.parse(e.data);

                    if (typeof data['cbId'] !== undefined && typeof callbacksQueue['cbId-' + data['cbId']] === 'function') {
                        var callBackId = 'cbId-' + data['cbId'];
                        callbacksQueue[callBackId](data['result']);
                        delete callbacksQueue[callBackId];
                    } else {
                        onData(data);
                    }
                } catch (ex) {
                    console.error('WebSocket error: %s', e.data);
                }
            };
        } else {
            console.warn('WebSocket object is not supported in your browser');
        }
    }

    function send(data, callback) {
        var callbackQueueId = null;
        if (typeof(callback) === 'function') {
            callbackQueueId = util.generateUUID();
            callbacksQueue['cbId-' + callbackQueueId] = callback;
        }
        var requestData = JSON.stringify({'data': data, 'cbId': callbackQueueId});

        try {
            ws.send(requestData);
        } catch (e) {
            console.error('WebSocket, sending failed!');
        }
    }

    function onData(data) {
        console.dir(data);
        //nop
    }

    return {
        init: init,
        send: send
    }
}(Util));

(function () {
    var httpServerURL = 'http://localhost:9000';
    var webSocketServerURL = 'ws://localhost:3009';

    document.getElementById('jsonp-send-request-btn').addEventListener('click', function () {
        JSONPProxy.send(httpServerURL + '/process', {id: 6554, email: 'user6554@gmail.com'}, function (data) {
            console.log('JSONP, get request, received: %s', JSON.stringify(data));
        });
    });

    document.getElementById('iframe-post-request-btn').addEventListener('click', function () {
        IFrameProxy.send(httpServerURL + '/process', {name: 'John', age: 50}, 'post', function (data) {
            console.log('Iframe, post request, received: %s', JSON.stringify(data));
        });
    });

    document.getElementById('iframe-get-request-btn').addEventListener('click', function () {
        IFrameProxy.send(httpServerURL + '/process', {name: 'Mary', age: 21}, 'get', function (data) {
            console.log('Iframe, get request, received: %s', JSON.stringify(data));
        });
    });

    document.getElementById('fetch-api-post-request-btn').addEventListener('click', function () {
        FetchApiProxy.send(httpServerURL + '/process', {id: 10, name: 'Marek'}, 'post', function (data) {
            console.log('Fetch API, post request, received: %s', JSON.stringify(data));
        });
    });

    document.getElementById('fetch-api-get-request-btn').addEventListener('click', function () {
        FetchApiProxy.send(httpServerURL + '/process', {id: 15, name: 'Audrey'}, 'get', function (data) {
            console.log('Fetch API, get request, received: %s', JSON.stringify(data));
        });
    });

    WebSocketProxy.init(webSocketServerURL);
    document.getElementById('websocket-send-request-btn').addEventListener('click', function () {
        WebSocketProxy.send({email: 'user1@gmail.com', key: 'DD454DFD6663433koioeDDDE45'}, function (data) {
            console.log('WebSocket, request, received: %s', JSON.stringify(data));
        });
    });
})();