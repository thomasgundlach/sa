<?php

function handleError($errno, $errstr, $errfile, $errline, array $errcontext) {
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

function getNumericSubstr($string, $start_signs, $end_signs, &$start_position = 0) {
	$start_position = strpos($string, $start_signs, $start_position);
	if($start_position === false)
		return false;
	$start_position += strlen($start_signs);
	$end_position = strpos($string, $end_signs, $start_position);
	if($end_position === false)
		return false;
	if(preg_match('/(\d+(.)?\d*)/', substr($string, $start_position, $end_position-$start_position), $matches))
		return $matches[0];
	return false;
}

function logRequest($address, $timestamp, $isin, $error,$content = "") {
	$datestamp = date("Ymd",$timestamp);
	if(file_exists("../../parseErrors")) {
		if(file_exists("../../parseErrors/".$datestamp."_".$isin.".log")) {
			$current_content = file_get_contents("../../parseErrors/".$datestamp."_".$isin.".log");
		} else {
			$current_content = "";
		}
		file_put_contents("../../parseErrors/".$datestamp."_".$isin.".log", $current_content.date("Y.m.d H:i:s",$timestamp).": ".$address."\r\n".$error."\r\n\r\n");
	} else {
		if (mkdir("../../parseErrors", 0777, true)) {
			file_put_contents("../../parseErrors/".$datestamp."_".$isin.".log", date("Y.m.d H:i:s",$timestamp).": ".$address."\r\n".$error."\r\n\r\n");
		} else {
			echo "Error: Could not write Error...<br>\r\n"; //Debug
	 	}
	}
}

function writeConfig($maxHighPrice, $maxHighPriceTime, $minLowPrice, $minLowPriceTime, $isin, $typ = "", $nexttradeid = "") {
	global $today_folder;
	
	
	if(file_exists("./".$isin."/".$today_folder)) {
		$filepointer = fopen("./".$isin."/".$today_folder."/config".$typ.".inc", "w");
		fwrite($filepointer, '<?php'."\r\n".'$'.$typ.'maxHighPrice = \''.$maxHighPrice.'\';'."\r\n".
							'$'.$typ.'maxHighPriceTime = \''.$maxHighPriceTime.'\';'."\r\n".
							'$'.$typ.'minLowPrice = \''.$minLowPrice.'\';'."\r\n".
							'$'.$typ.'minLowPriceTime = \''.$minLowPriceTime.'\';'."\r\n".
							(($nexttradeid != "") ? '$nexttradeid = \'' . $nexttradeid . "';\r\n" : "").'?>');
		fclose($filepointer);
	} else {
		// ToDo: Meldung, dass Max/Min nicht neu geschrieben werden konnten
	}
}

function errorLongDuration($duration, $filename, $line) {
	throw new ErrorException("Execution to long: ".$duration." Seconds", 0, 1337, $filename, $line);
}

$timestamp = time();
$time_start = microtime(true);

$isin = "US0231351067";

	$opts = array (
		'http'=>array (
			'methode'=>"GET",
			'header'=>"User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0\r\n" . 
					  "Accept: text/html, */*; q=0.01\r\n" .
					  "Accept-Language: de,en-US;q=0.7,en;q=0.3\r\n" . 
					  "Accept-Encoding: identity\r\n" .
					  "X-Requested-With: XMLHttpRequest\r\n" .
					  "Referer: http://www.tradegate.de/orderbuch.php?isin=".$isin."\r\n".
					  "Connection: close\r\n"
			)
	);

	$context = stream_context_create($opts); 
	// $homepage = file_get_contents('https://www.comdirect.de/inf/snippet$ewf.popup.kursdaten.intraday.snippet?ID_NOTATION=253931',false,$context);

set_error_handler("handleError");
try {
	$homepage = file_get_contents('http://www.trada-erweegate.de/cgi-bin/orderbuch.cgi?/opt/bfv/etc/webtoolscgi.xml+'.$isin,false,$context);
	$duration = microtime(true)-$time_start;
	if($duration>19) {
		errorLongDuration($duration,__FILE__, __LINE__);
	}
} catch (Exception $e) {
    // Handle exception
    logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, $e->getMessage());
	exit(0);
}
restore_error_handler();
	$quotes = json_decode($homepage, true);
	var_dump($quotes);
	$json_error = json_last_error();
	if($json_error == JSON_ERROR_NONE) {
		if(array_key_exists ( 0 , $quotes )) {
			if(array_key_exists ( "ask", $quotes[0] )) {
				$quotes[0]["ask"] = filter_var($quotes[0]["ask"], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
				echo "ask:".$quotes[0]["ask"]."\"";
			} else {
				echo "Element Ask not exists\n"; // Write parsed JSON to Log
			}
			if(array_key_exists ( "bid", $quotes[0] )) {
				$quotes[0]["bid"] = filter_var($quotes[0]["bid"], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
				$quotes[0]["bid"] = str_replace(",",".",$quotes[0]["bid"]);
				if(preg_match("/^\d+\.?\d*$/",$quotes[0]["bid"])) {
					echo "bid: ".$quotes[0]["bid"]."\"";
				}
			} else {
				echo "Element Bid not exists\n"; // Write parsed JSON to Log
			}
		} else {
		echo "Main Array Zero not exists\n"; // Write parsed JSON to Log
		}
	} else {
		echo "JSON Error occurred: \"".$json_error."\"\n"; // Write parsed JSON to Log
	}
	
/*	$homepage=preg_replace_callback(
    	'/(\d+(,)\d*)/',
    	function ($treffer) {
      		return str_replace(",", ".", $treffer[0]);
    	},
    	$homepage
	);
	
	// echo $homepage;

	// Speicher aktuelles Gebot in Orderkurs-Array
	echo "\"".getNumericSubstr($homepage, "bid\"", ",")."\"";
*/
echo "Done without kill in ".(microtime(true) - $time_start)." Seconds";
?>