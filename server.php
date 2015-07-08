<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use media_controller\MediaController;

	require dirname(__FILE__) . '/vendor/autoload.php';

	$server = IoServer::factory(
		new HttpServer(
			new WsServer(
				$cont = new MediaController()
			)
		),
		8080
	);

	print("Running...\r\n");

	$server->run();