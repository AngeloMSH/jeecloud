var webSocketUrl = "wss://api.artik.cloud/v1.1/websocket?ack=true";

var devices = [];

var isWebSocketReady = false;
var ws = null;

var WebSocket = require('ws');
var net = require('net');
var fs = require('fs');
var request = require('request');
var urlJeedom = '';

process.argv.forEach(function(val, index, array) {
    switch (index) {
        case 2:
            urlJeedom = val;
            break;
        case 3:
            log = val;
            break;
    }
});

printLog('url: ' + urlJeedom);
printLog('log: ' + log);

// for watchdog, set to 60 the first time
var remainingTime = 60;

// create a timer of 1 sec
var watchdog = setInterval(function() {
    //printLog("Watchdog timer: " + remainingTime);
    remainingTime--;
    if (remainingTime < 0) {
        remainingTime = 60;
        printLog("No ping received, Resetting websocket...");
        ws = null;
        startArtik();
    }
}, 1000);

/**
 * Function to get list of devices found in file resources
 */
function getListOfDevices() {
    // sync read
    console.log(process.cwd());
    var buffer = fs.readFileSync("../../plugins/jeecloud/resources/devices.json");
    printLog(buffer);
    var json = JSON.parse(buffer);
    //var json = require("/var/www/html/plugins/jeecloud/resources/devices.json");
    for (var key in json) {
        printLog("key:" + key + ", id:" + json[key].device_id + ", token:" + json[key].device_token);
        devices.push(json[key]);
    }
}

/**
 * Gets the current time in millis
 */
function getTimeMillis() {
    return parseInt(Date.now().toString());
}

/**
 * Print log messages in console
 * @param message string
 */
function printLog($message) {
    console.log((new Date().toLocaleString()) + ' : ' + $message);
}

/**
 * Print error messages in console
 * @param message string
 */
function printError($message) {
    console.error((new Date().toLocaleString()) + ' : ' + $message);
}

/**
 * Create a /websocket bi-directional connection 
 */
function startArtik() {
    //Create the websocket connection
    isWebSocketReady = false;
    ws = new WebSocket(webSocketUrl);
    printLog("Creating a websocket connection ....");
    ws.on('open', function() {
        printLog("Websocket connection is open ....");
        // regiter all devices
        register();
    });
    ws.on('message', function(data, flags) {
        processData(data);
    });
    ws.on('close', function() {
        printLog("Websocket connection is closed ....");
    });
}

/**
 * Function to process data received from cloud
 * @params string data
 */
function processData(data) {
    printLog("Received message from cloud: " + data);
    var type = '';
    var message = '';

    try {
        type = JSON.parse(data).type;
    } catch (e) {
        printError("Failed to reading type. Error in message: " + e.toString());
    }

    try {
        message = JSON.parse(data);
    } catch (e) {
        printError("Failed to reading type. Error in message: " + e.toString());
    }

    if (type == 'ping') {
        printLog("Got ping from Artik, connexion is alive...")
        remainingTime = 40;
    }
    // send message to jeedom
    request.post(urlJeedom, function(error, response, body) {
        if (!error && response.statusCode == 200) {
            if (log == 'debug') {
                printLog("Return OK from Jeedom for: " + data);
            }
        } else {
            printLog(error);
        }
    }).form(message);
}

/*
 * Sends a register message to the websocket and starts the message flooder
 */
function register() {
    printLog("Registering all devices on the websocket connection");
    for (var key in devices) {
        printLog("key:" + key + ", id:" + devices[key].device_id + ", token:" + devices[key].device_token);
        try {
            // type can be register, unregister or list
            var registerMessage = '{"type":"register", "sdid":"' + devices[key].device_id + '", "Authorization":"bearer ' + devices[key].device_token + '", "cid":"' + getTimeMillis() + '"}';
            printLog("Sending register message " + registerMessage + "\n");
            ws.send(registerMessage, { mask: true });
            isWebSocketReady = true;
        } catch (e) {
            printError("Failed to register message. Error in registering message: " + e.toString());
        }
    }
}

/**
 * Send one message to ARTIK Cloud
 */
function sendData(device_id, message) {
    try {
        ts = ', "ts": ' + getTimeMillis();
        var payload = '{"sdid":"' + device_id + '"' + ts + ', "data": ' + JSON.stringify(message) + ', "cid":"' + getTimeMillis() + '"}';
        printLog('Sending payload ' + payload);
        ws.send(payload, { mask: true });
    } catch (e) {
        printError('Error sending a message: ' + e.toString());
    }
}

/**
 * Create server on port 8099 for incoming commands from jeedom
 */
function launchGateway() {
    var pathsocket = '/tmp/jeecloud.sock';
    fs.unlink(pathsocket, function() {
        var server = net.createServer(function(c) {
            printLog("New connexion from Jeedom");
            c.on('error', function(e) {
                printLog("Error server disconnected");
            });
            c.on('close', function() {
                printLog("Connexion closed");
            });
            c.on('data', function(data) {
                printLog("Received from jeedom: " + data);
                var device_id = '';
                var message = '';
                try {
                    device_id = JSON.parse(data).device_id;
                } catch (e) {
                    printError("Failed to reading device_id. Error in message: " + e.toString());
                }
                try {
                    message = JSON.parse(data).data;
                } catch (e) {
                    printError("Failed to reading message. Error in message: " + e.toString());
                }
                if (device_id != '') {
                    sendData(device_id, message);
                }
            });
        });
        server.listen(8099, function(e) {
            printLog("Incoming server bound on 8099");
        });
    });
}

/**
 * All start here
 */

// get list of devices
getListOfDevices();

// create websocket connection with artik
startArtik();

// create interface between jeedom and nodeserver
launchGateway();