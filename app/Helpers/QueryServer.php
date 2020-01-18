<?php
namespace App\Helpers;

/*
  Version 1.0
 */
class QueryServer {
    private $buffer, $serverIP, $port, $error, $errortext, $sockHandle,
        $respArr, $timeou, $errorcode, $start, $elapsed;
    public function __construct(&$buffer, $serverIP, $port, $timeout = 2) {
        $this->buffer = &$buffer;
        $this->serverIP = $serverIP;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->errorcode = 0;
    }
    public function getResponse() {
        return $this->respArr;
    }
    public function getError() {
        return $this->errortext;
    }
    public function getErrorCode() {
        return $this->errorcode;
    }
    public function runQuery() {
        $this->ipCheck();
        if (!$this->error) {
            $this->portCheck();
        }
        if (!$this->error) {
            $this->runSocket();
        }
        if (!$this->error) {
            $this->splitResponse();
            $this->parseResponse();
        }
        return (!$this->error) ? $this->getResponse() : false;
    }
    private function runSocket() {
        if (!$this->error) {
            $this->createSocket();
        }
        if (!$this->error) {
            $this->socketConnect();
        }
        if (!$this->error) {
            $this->socketSend();
        }
        if (!$this->error) {
            $this->receiveResponse();
        }
    }
    private function ipCheck() {
        if (!filter_var($this->serverIP, FILTER_VALIDATE_IP)) {
            $this->errorcode = 1;
            $this->errortext = "Invalid IP address";
            $this->error = true;
        }
    }
    private function portCheck() {
        $this->port = (int) $this->port;
        if ($this->port < 1 || $this->port > 65535) {
            $this->errorcode = 1;
            $this->errortext = "Invalid port specified";
            $this->error = true;
        }
    }
    private function createSocket() {
        $this->sockHandle = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$this->sockHandle) {
            $this->errortext = socket_strerror(socket_last_error());
            $this->error = true;
        }
    }
    private function socketConnect() {
        socket_set_nonblock($this->sockHandle);
        while (!@socket_connect($this->sockHandle, $this->serverIP, $this->port)) {
            $this->errortext = socket_strerror(socket_last_error());
            //Stop PHP from hanging
            if ((time() - $start) >= $this->timeout) {
                $this->error = true;
                return;
            } else {
                usleep(500);
            }
        }
        socket_set_block($this->sockHandle);
        $this->start = microtime(true);
    }
    private function socketSend() {
        $start = time();
        if (!@socket_send($this->sockHandle, "\\query", 6, MSG_EOF)) {
            $this->errortext = socket_strerror(socket_last_error());
            $this->error = true;
        }
    }
    private function receiveResponse() {
        $read = array($this->sockHandle);
        $null = NULL;
        $changed = socket_select($read, $null, $null, $this->timeout);
        if ($changed) {
            $this->elapsed = microtime(true) - $this->start;
            $bytes = @socket_recv($this->sockHandle, $this->buffer, 10000, 2);
            if ($bytes === false) {
                $this->errortext = socket_strerror(socket_last_error());
                $this->error = true;
            }
        } else {
            $this->errortext = "No response received";
            $this->error = true;
        }
    }
    private function splitResponse() {
        $this->respArr = explode("\\", utf8_encode($this->buffer));
    }
    private function parseResponse() {
        unset($this->respArr[0]);
        $this->respArr = array_values($this->respArr);
        //Set offsets so we can build an assoc array
        $numPlayers = $this->respArr[19];
        $playerOffset = array_search("player_0", $this->respArr);
        $scoreOffset = $playerOffset + ($numPlayers * 2);
        $pingOffset = $playerOffset + ($numPlayers * 4);
        $teamOffset = $playerOffset + ($numPlayers * 6);
        $tempArray = $this->respArr;
        array_splice($tempArray, $playerOffset, $numPlayers * 8);
        //Begin converting data to an assoc array
        for ($i = 0, $j =  count($tempArray); $i < $j; $i += 2) {
            $key = $tempArray[$i];
            $assocArr[$key] = $tempArray[$i + 1];
        }
        $assocArr['elapsed'] = $this->elapsed; //testing something!
        $this->respArr = $assocArr;
    }
}
