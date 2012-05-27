<?php

/**
 * This class is used to handle all of the information associated with a
 * websocket connection.
 */
//TO BE DEPRECIATED! MERGING WITH THE NODE CLASS!
class Client {
	private $socket;
	private $handshake;
	private $spec;
	
	private $id;
	private static $idCounter=0;

	function Client($socket) {
		$this->socket = $socket;
		//IF POSSIBLE, WE SHOULD HAVE THIS CONSTRUCTOR TAKE CARE OF THE
		//HANDSHAKE.
		$this->handshake = false;
		$this->id = self::$idCounter;
		self::$idCounter++;
		echo "Created Client with ID:".$this->id."\n";
	}

	public function getSocket() {
		return $this->socket;
	}

	public function getHandshake() {
		return $this->handshake;
	}

	/**
	 * Preferably private and set inside the constructor
	 */
	public function setHandshake($handshake) {
		$this->handshake = $handshake;
	}
	
	/**
	 * Preferably private and set inside the constructor
	 */
	public function setSpec($specification){
		$this->spec=new $specification;
	}

	public function getId() {
		return $this->id;
	}
}

class Hybi10{
	
	public function encode($text){
		
	}
	
	public function decode($text){
		
	}
}

class RFC6455{
	
	public function encode($text){
		// 0x1 text frame (FIN + opcode)
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);
		
		if($length <= 125)
			$header = pack('CC', $b1, $length);
		elseif($length > 125 && $length < 65536)
			$header = pack('CCS', $b1, 126, $length);
		elseif($length >= 65536)
			$header = pack('CCN', $b1, 127, $length);
		
    		return $header.$text;
	}
	
	/**
	Unmasks the received frame.
	*/
	public function decode($payload){
		$length = ord($payload[1]) & 127;
		
		if($length == 126) {
			$masks = substr($payload, 4, 4);
			$data = substr($payload, 8);
		}
		elseif($length == 127) {
			$masks = substr($payload, 10, 4);
			$data = substr($payload, 14);
		}
		else {
			$masks = substr($payload, 2, 4);
			$data = substr($payload, 6);
		}
		
		$text = '';
		
		for($i = 0; $i < strlen($data); ++$i){
			$text .= $data[$i] ^ $masks[$i%4];
		}
		
   		 return $text;
	}
}
?>
