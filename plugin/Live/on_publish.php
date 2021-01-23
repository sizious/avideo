<?php

require_once '../../videos/configuration.php';
require_once './Objects/LiveTransmition.php';
require_once './Objects/LiveTransmitionHistory.php';
$obj = new stdClass();
$obj->error = true;
$obj->liveTransmitionHistory_id = 0;

_error_log("NGINX ON Publish POST: " . json_encode($_POST));
_error_log("NGINX ON Publish GET: " . json_encode($_GET));
_error_log("NGINX ON Publish php://input" . file_get_contents("php://input"));

// get GET parameters
$url = $_POST['tcurl'];
if (empty($url)) {
    $url = $_POST['swfurl'];
}
$parts = parse_url($url);
parse_str($parts["query"], $_GET);
_error_log("NGINX ON Publish parse_url: " . json_encode($parts));
_error_log("NGINX ON Publish parse_str: " . json_encode($_GET));

$_GET = object_to_array($_GET);

if ($_POST['name'] == 'live') {
    _error_log("NGINX ON Publish wrong name {$_POST['p']}");
    // fix name for streamlab
    $pParts = explode("/", $_POST['p']);
    if (!empty($pParts[1])) {
        _error_log("NGINX ON Publish like key fixed");
        $_POST['name'] = $pParts[1];
    }
}

if (empty($_POST['name']) && !empty($_GET['name'])) {
    $_POST['name'] = $_GET['name'];
}
if (empty($_POST['name']) && !empty($_GET['key'])) {
    $_POST['name'] = $_GET['key'];
}
if (strpos($_GET['p'], '/') !== false) {
    $parts = explode("/", $_GET['p']);
    if (!empty($parts[1])) {
        $_GET['p'] = $parts[0];
        $_POST['name'] = $parts[1];
    }
}

if (!empty($_GET['p'])) {
    $_GET['p'] = str_replace("/", "", $_GET['p']);
    _error_log("NGINX ON Publish check if key exists ({$_POST['name']})");
    $obj->row = LiveTransmition::keyExists($_POST['name']);
    _error_log("NGINX ON Publish key exists return " . json_encode($obj->row));
    if (!empty($obj->row)) {
        _error_log("NGINX ON Publish new User({$obj->row['users_id']})");
        $user = new User($obj->row['users_id']);
        if (!$user->thisUserCanStream()) {
            _error_log("NGINX ON Publish User [{$obj->row['users_id']}] can not stream");
        } else if (!empty($_GET['p']) && $_GET['p'] === $user->getPassword()) {
            _error_log("NGINX ON Publish get LiveTransmitionHistory");
            $lth = new LiveTransmitionHistory();
            $lth->setTitle($obj->row['title']);
            $lth->setDescription($obj->row['description']);
            $lth->setKey($_POST['name']);
            $lth->setUsers_id($user->getBdId());
            $lth->setLive_servers_id(Live_servers::getServerIdFromRTMPHost($url));
            _error_log("NGINX ON Publish saving LiveTransmitionHistory");
            $obj->liveTransmitionHistory_id = $lth->save();
            _error_log("NGINX ON Publish saved LiveTransmitionHistory");
            $obj->error = false;
        } else if (empty($_GET['p'])) {
            _error_log("NGINX ON Publish error, Password is empty");
        } else {
            _error_log("NGINX ON Publish error, Password does not match ({$_GET['p']}) expect (" . $user->getPassword() . ")");
        }
    } else {
        _error_log("NGINX ON Publish error, Transmition name not found ({$_POST['name']}) ", AVideoLog::$SECURITY);
    }
} else {
    _error_log("NGINX ON Publish error, Password not found ", AVideoLog::$SECURITY);
}
_error_log("NGINX ON Publish deciding ...");
if (!empty($obj) && empty($obj->error)) {
    _error_log("NGINX ON Publish success");
    http_response_code(200);
    header("HTTP/1.1 200 OK");
    echo "success";
    Live::on_publish($obj->liveTransmitionHistory_id);
    ob_end_flush();
    $lth = new LiveTransmitionHistory($obj->liveTransmitionHistory_id);
    $m3u8 = Live::getM3U8File($lth->getKey());
    for ($i = 5; $i > 0; $i--) {
        if (!isURL200($m3u8)) {
            //live is not ready request again
            sleep($i);
        } else {
            break;
        }
    }
    $array = setLiveKey($lth->getKey(), $lth->getLive_servers_id());
    $array['stats'] = LiveTransmitionHistory::getStatsAndAddApplication($obj->liveTransmitionHistory_id);
    $socketObj = sendSocketMessageToAll($array, "socketLiveONCallback");

    exit;
} else {
    _error_log("NGINX ON Publish denied ", AVideoLog::$SECURITY);
    http_response_code(401);
    header("HTTP/1.1 401 Unauthorized Error");
    exit;
}
//_error_log(print_r($_POST, true));
//_error_log(print_r($obj, true));
//echo json_encode($obj);