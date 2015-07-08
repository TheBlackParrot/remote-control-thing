<?php
namespace media_controller;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

$root = dirname(dirname(dirname(__FILE__)));

function logController($cmd,$value) {
	echo "[" . date('m/d/Y H:i:s') . "] :: [" . $cmd . "] -- " . $value . "\r\n";
}

class MediaController implements MessageComponentInterface {
	protected $clients;

	public function __construct() {
		$this->clients = new \SplObjectStorage;
	}

	public function onOpen(ConnectionInterface $conn) {
		// Store the new connection to send messages to later
		$this->clients->attach($conn);

		logController("ConnectionInterface", "New connection from {$conn->remoteAddress}");
	}

	public function onMessage(ConnectionInterface $from, $msg) {
		//$numRecv = count($this->clients)

		//echo sprintf('Received "%s" from connection %d' . "\n"
		//	, $msg, $from->resourceId);

		$returns = handleControllerData($msg);
		$id = count($returns) - 2;
		$stopSame = $returns[$id];
		unset($returns[$id]);

		if($returns != NULL && $returns != "") {
			foreach ($this->clients as $client) {
				if(!$stopSame) {
					$client->send(json_encode($returns,JSON_FORCE_OBJECT));
				} else if($stopSame == 1) {
					if($from !== $client) {
						$client->send(json_encode($returns,JSON_FORCE_OBJECT));
					}
				} else if($stopSame == 2) {
					// special case to send back variable from the recipient
					$from->send(json_encode($returns,JSON_FORCE_OBJECT));
				}
			}
		}
	}

	public function onClose(ConnectionInterface $conn) {
		// The connection is closed, remove it, as we can no longer send it messages
		$this->clients->detach($conn);

		echo "Connection {$conn->resourceId} has disconnected\n";
	}

	public function onError(ConnectionInterface $conn, \Exception $e) {
		echo "An error has occurred: {$e->getMessage()}\n";

		$conn->close();
	}
}

date_default_timezone_set("America/Chicago");
putenv("DISPLAY=:0");

$vlc['paused'] = 0;
$stream['title'] = "";

function handleControllerData($msg) {
	$root = dirname(dirname(dirname(__FILE__)));
	$data = explode("^t",$msg);

	global $vlc;
	global $stream;

	switch($data[0]) {
		case "vol":
			switch($data[1]) {
				case "set":
					exec("amixer set Master " . $data[2] . "%");
					logController($data[0],$data[2] . "%");

					return array($data[2], "type" => "vol", 1);
					break;

				case "get":
					return array(str_replace("%", "", exec('amixer get Master | egrep -o "[0-9]+%"| head -n1')), "type" => "vol", 2);
					break;
			}
			break;

		case "stat":
			switch($data[1]) {
				case "load":
					$arr = sys_getloadavg();
					$arr['type'] = "load";
					$arr[] = 2;
					return $arr;
					break;
				
				case "cpuTemp":
					$temp = exec("sensors | grep -o '+[0-9.]\+' | sed '1~2!d; s/+//' | head -n1");
					return array($temp, 'type' => "cpuTemp", 2);
					break;

				case "kernel":
					$kernel = exec("uname -r");
					return array($kernel, 'type' => "kernel", 2);
					break;
			}
			break;

		case "VLC":
			switch($data[1]) {
				case "open":
					if(exec("ps -A | grep -e [v]lc") == "") {
						return array(0, "type" => "VLCOpen", 2);
						$vlc['paused'] = 1;
						$stream['title'] = "";
					} else {
						return array(1, "type" => "VLCOpen", 2);
					}
					break;

				case "elapsed":
					return array(exec("$root/vlc-cli.sh get_time"), "type" => "VLCElapsed", 2);
					break;

				case "length":
					return array(exec("$root/vlc-cli.sh get_length"), "type" => "VLCLength", 2);
					break;

				case "seek":
					exec("$root/vlc-cli.sh seek " . $data[2]);

					logController("VLC","seek: " . $data[2]);
					return array($data[2], "type" => "VLCElapsed", 1);
					break;

				case "pause":
					exec("$root/vlc-cli.sh pause");

					if($vlc['paused']) {
						$vlc['paused'] = 0;
					} else {
						$vlc['paused'] = 1;
					}
					
					logController("VLC","pause: " . $vlc['paused']);
					return array($vlc['paused'], "type" => "VLCPaused", 0);
					break;

				case "paused":
					return array($vlc['paused'], "type" => "VLCPaused", 2);
					break;

				case "stop":
					exec("$root/vlc-cli.sh quit");
					$vlc['paused'] = 1;
					logController("VLC","stop/quit");
					break;

				case "title":
					if($stream['title'] != "") {
						return array($stream['title'], "type" => "VLCTitle", 2);
					} else {
						return array(exec("$root/vlc-cli.sh get_title"), "type" => "VLCTitle", 2);
					}
					break;
			}
			break;

		case "notification":
			exec('notify-send "' . $data[1] . '"');
			logController($data[0],$data[1]);
			break;

		case "mouse":
			switch($data[1]) {
				case "pos":
					$res = explode("x",exec("xrandr | grep '*' | awk '{print $1;}'"));
					$x = $res[0] * $data[2];
					$y = $res[1] * $data[3];
					exec("xdotool mousemove $x $y");
					logController("mouse","pos: $x $y");
					break;
				case "leftclick":
					exec("xdotool click 1");
					logController("mouse","left click");
					break;
				case "rightclick":
					exec("xdotool click 3");
					logController("mouse","right click");
					break;
			}
			break;

		case "keyboard":
			switch($data[1]) {
				case "input":
					$data[2] = str_replace(" ", "\ ", $data[2]);
					exec("xdotool type " . $data[2]);
					break;

				case "keystroke":
					exec("xdotool key " . $data[2]);
					break;
			}
			logController("keyboard",$data[2]);
			break;

		case "YouTubeURL":
			logController($data[0],$data[1]);
			// assuming most "sharing" methods put the URL at the end
			// see: the Android YouTube app
			$last_word_start = strrpos($data[1], ' ') + 1;
			$last_word = substr($data[1], $last_word_start);

			$url = urldecode($last_word);
			$parsedUrl = parse_url($url);

			if($parsedUrl['host'] != "www.youtube.com") {
				if($parsedUrl['host'] != "youtube.com") {
					if($parsedUrl['host'] != "youtu.be") {
						echo("URL is invalid.\r\n");
						break;
					}
				}
			}
			echo("URL is valid.\r\n");

			$vlc['paused'] = 0;
			$stream['title'] = exec("youtube-dl -e " . $data[1]);
			exec('stream=$(youtube-dl -f "[height <=? 720]" -g ' . $data[1] . '); vlc --intf dummy --fullscreen --no-osd --quiet --play-and-exit $stream &> /dev/null &');
			return array(1, "type" => "YouTubeURL", 0);
			break;

		case "TwitchURL":
			logController($data[0],"channel: " . $data[1]);
			if(str_word_count($data[1]) > 1) {
				$last_word_start = strrpos($data[1], ' ') + 1;
				$last_word = substr($data[1], $last_word_start);
			} else {
				$last_word = $data[1];
			}

			$vlc['paused'] = 0;
			$stream['title'] = "Twitch.TV -- Channel: " . $last_word;

			exec('livestreamer --no-version-check -O --quiet twitch.tv/' . $last_word . ' high | vlc --intf dummy --fullscreen --no-osd --quiet - &> /dev/null &');
			return array(1, "type" => "TwitchURL", 0);
			break;
	}
}