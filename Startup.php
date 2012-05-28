<?php
function loadIni($file){
	$contents=file_get_contents($file);
	$lines=explode("\n",$contents);
		for($i=0,$y=count($lines),$removed=0;$i<$y;$i++){
			$firstChar=substr($lines[$i-$removed],0,1);
			if($firstChar=="#" || $firstChar==""){array_splice($lines,$i-$removed,1);$removed++;} //if first character is a # then "ignore it".
		}
		for($i=0,$y=count($lines);$i<$y;$i++){
			$pairs=explode('=',$lines[$i]); //take the key and the value
			$startup[$pairs[0]]=$pairs[1]; //put the found key and value into an array element
		}
		
		return $startup; //return the found array of key/value elements.
}

$default=loadIni("wsmpi_default.conf");
$startup=loadIni("wsmpi.conf");

//variables we need to supply to the Classes.
set_time_limit(($startup["time_limit"]) ? $startup["time_limit"] : $default['time_limit']);
$address=($startup["address"]) ? $startup["address"] : $default['address'];
$port=($startup["port"]) ? $startup["port"] : $default['port'];
$crossSite=($startup["crossSite"]) ? $startup["crossSite"] : $default['crossSite'];
$verbose=($startup["verbose"]) ? $startup["verbose"] : $default['verbose'];
?>
