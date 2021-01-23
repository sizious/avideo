<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Socket\Message;
//use React\Socket\Server as Reactor;

require_once dirname(__FILE__) . '/../../videos/configuration.php';
require_once $global['systemRootPath'] . 'plugin/Socket/Message.php';
require_once $global['systemRootPath'] . 'objects/autoload.php';

if (!isCommandLineInterface()) {
    die("Command line only");
}

$SocketDataObj = AVideoPlugin::getDataObject("Socket");
$SocketDataObj->serverVersion = Socket::getServerVersion();

ob_end_flush();
_mysql_close();
session_write_close();
$SocketDataObj->port = intval($SocketDataObj->port);
_error_log("Starting Socket server at port {$SocketDataObj->port}");
killProcessOnPort();
$scheme = parse_url($global['webSiteRootURL'], PHP_URL_SCHEME);

echo "Starting AVideo Socket server version {$SocketDataObj->serverVersion} on port {$SocketDataObj->port}".PHP_EOL;

if(strtolower($scheme)!=='https'){
    echo "Your socket server does NOT use a secure connection".PHP_EOL;
    $server = IoServer::factory(
                    new HttpServer(
                            new WsServer(
                                    new Message()
                            )
                    ),
                    $SocketDataObj->port
    );

    $server->run();
} else {
    echo "Your socket server uses a secure connection".PHP_EOL;
    $parameters = [
        'local_cert' => $SocketDataObj->server_crt_file,
        'local_pk' => $SocketDataObj->server_key_file,
        'allow_self_signed' => $SocketDataObj->allow_self_signed, // Allow self signed certs (should be false in production)
        'verify_peer' => false,
        'verify_peer_name'=>false,
        'security_level'=>0
    ];
    
    foreach ($parameters as $key => $value) {
        echo "Parameter [{$key}]: $value ".PHP_EOL;
    }
    
    $loop = React\EventLoop\Factory::create();
// Set up our WebSocket server for clients wanting real-time updates
    $webSock = new React\Socket\Server('0.0.0.0:' . $SocketDataObj->port, $loop);
    $webSock = new React\Socket\SecureServer($webSock, $loop, $parameters);
    $webServer = new Ratchet\Server\IoServer(
            new HttpServer(
                    new WsServer(
                            new Message()
                    )
            ),
            $webSock
    );
//$socket = new Reactor($webServer->loop);
//$socket->listen(8082, '0.0.0.0'); //Port 2
//$socket->on('connection', [$webServer, 'handleConnect']);
//$webServer->run();
    $loop->run();
}