<?php

/**
 * This file contains specifications and awaits any future specifications that have necessary differences.
 * Any specification before Hybi-10 is designated insecure and shouldn't be implemented. Hybi-10 has no
 * obvious encode/decode difference from RFC6455 that has been observed yet.
*/

class RFC6455{
	
	/**
	 * Can encode to send as much data as necessary, but watch out for trying to encode/send more information than what is allowed
	 * in a string variable?
	 * @to-do: handle 32/64 bit operating systems and/or arbitrary precision?
	 * @param text
	 * @return The frame to be sent over the socket, or an array of fragments to send if the value is too large.
	*/
	public function encode($text){ 
		$length = strlen($text);
		
		if($length <= 125){
			$frame = pack("C2", 129, $length).$text; //C=octet, or 8 bits (network byte order/big endian). 
		}
		elseif($length >= 65536){ //sends frame fragments until all frags sent. :)
			for($i=0, $y=ceil($length/65535);$i<$y;$i++){ //the contents of this for loop can be optimized a bit...
				if($i==0){$frame[]=pack("C2n", 1, 126, 65535).(substr($text,65535*$i,65535*($i+1)));}
				else if($i==$y-1){
					$substr=substr($text,65535*$i);
					if(strlen($substr)<=125){$frame[]=pack("C2", 128, strlen($substr)).$substr;}
					else{$frame[]=pack("C2n", 128, 126, strlen($substr)).$substr;}
				}
				else{
					$frame[]=pack("C2n", 0, 126, 65535).(substr($text,65535*$i,65535*($i+1)));
				}
			}
		}
		else{ //($length > 125 && $length < 65536)
			$frame = pack("CCn", 129, 126, $length).$text; //n=16 bit (network byte order/big endian).
		}
		
    return $frame;
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
