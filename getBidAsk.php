<?php



function getSubstr($string, $start_signs, $end_signs, &$start_position = 0) {
	$start_position = strpos($string, $start_signs, $start_position);
	if($start_position === false)
		return false;
	$start_position += strlen($start_signs);
	$end_position = strpos($string, $end_signs, $start_position);
	if($end_position === false)
		return false;
	return substr($string, $start_position, $end_position-$start_position);
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

function getSubstrr($string, $start_signs, $end_signs, $reverse_position) {
	$start_position = strrpos($string, $start_signs, $reverse_position);
	if($start_position === false)
		return false;
	$start_position += strlen($start_signs);
	$end_position = strpos($string, $end_signs, $start_position);
	if($end_position === false)
		return false;
	return substr($string, $start_position, $end_position-$start_position);
}

function getNumericSubstrr($string, $start_signs, $end_signs, $reverse_position, $pattern) {
	$start_position = strrpos($string, $start_signs, $reverse_position);
	if($start_position === FALSE)
		return FALSE;
	$start_position += strlen($start_signs);
	$end_position = strpos($string, $end_signs, $start_position);
	if($end_position === FALSE)
		return FALSE;
	if(preg_match($pattern, substr($string, $start_position, $end_position-$start_position), $matches)) {
		return $matches[0];
	}
	return FALSE;
}

function writeValues($stock_values, $isin, $typ = "") {
	global $today_folder;
	$stock_size = count($stock_values);
	$i = 1;
	
	
	if(file_exists("./".$isin."/".$today_folder)) {
		$filepointer = fopen("./".$isin."/".$today_folder."/values".$typ.".inc", "w");
		fwrite($filepointer, "<?php\r\n\$stock_values".$typ." = array(\r\n");
		foreach ($stock_values as $order_value) {
			fwrite($filepointer, "\tarray('".$order_value[0]."', '".$order_value[1]."', '".$order_value[2]."')");
			if($i != $stock_size)
				fwrite($filepointer, ", \r\n");
			else
				fwrite($filepointer, "\r\n");
			$i++;
		}
		fwrite($filepointer, ");\r\n?>");
		fclose($filepointer);
	} else {
		// ToDo: Meldung, dass Orderwerte nicht neu geschrieben werden konnten
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

function logRequest($content, $timestamp, $isin, $error) {
	if(file_exists("../../parseErrors")) {
		if(file_exists("../../parseErrors/".$timestamp."_".$isin.".log")) {
			$current_content = file_get_contents("../../parseErrors/".$timestamp."_".$isin.".log");
		} else {
			$current_content = "";
		}
		file_put_contents("../../parseErrors/".$timestamp."_".$isin.".log", $current_content.$error."\r\n".$content."\r\n\r\n");
	} else {
		if (mkdir("../../parseErrors", 0777, true)) {
			file_put_contents("../../parseErrors/".$timestamp."_".$isin.".log", $error."\r\n\r\n".$content);
		} else {
			echo "Error: Could not write Error...<br>\r\n"; //Debug
	 	}
	}
}

function maximumHasReached($max_price, $current_price) {	
	return $max_price <= $current_price;
}

function minimumHasReached($min_price, $current_price) {
	return $min_price >= $current_price;
}

function overWriteISIN($isin) {
	$filepointer = fopen("./".$isin."/".$isin.".inc", "w");
	// ToDo: Schreiben der ISIN
	fclose($filepointer);
}

// echo $_SERVER['HTTP_USER_AGENT'];

require("stockConfig.inc");

$today_folder = date('Y/m/d');
$timestamp = time();
$today_time = strtotime($today_folder);


// Falls das Skript ausserhalb der Zeit 8-22 Uhr aufgerufen wird, wird nichts gemacht
if(($today_time+28800) > $timestamp || ($today_time+79200) < $timestamp) {
	echo "Out of trade<br>\r\n"; // Error-Message
	exit(0);
}

// Falls das Skript bereits aufgerufen wurde, lasse den Aufruf verfallen
if(file_exists("./ACTIVE") && (filemtime("./ACTIVE")+60) >= $timestamp) {
	echo "Collecting in Progress...<br>\r\n"; // Error-Message
	logRequest(gethostbyaddr($_SERVER["REMOTE_ADDR"])." (".$_SERVER["REMOTE_ADDR"].")\r\n".date("Y-m-d D H:i:s")."\r\n".$_SERVER["HTTP_USER_AGENT"]."\r\n", $today_time, "Multiaccess", "Collecting in Progress...");
	exit(0);
} else {
	if(file_exists("./ACTIVE"))
		unlink("./ACTIVE");
	// ..ansonsten Sperre fuer weitere Aufrufe
	@file_put_contents("./ACTIVE", "");
	@chmod("./ACTIVE", 0777);
	echo "Now Collecting... <br>\r\n";
}


foreach ($stocks as $isin) {
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
	
	// Da in Unix Verzeichnisse auch Dateien sind, auf file_exists checken...
	if(file_exists("./".$isin."/".$today_folder)) {
		if(file_exists("./".$isin."/".$today_folder."/config.inc"))
			require("./".$isin."/".$today_folder."/config.inc");
		else
			logRequest("./".$isin."/".$today_folder."/config.inc", $timestamp, $isin, "error: require config_market worked not");
		if(file_exists("./".$isin."/".$today_folder."/values.inc"))
			require("./".$isin."/".$today_folder."/values.inc");
		else
			logRequest("./".$isin."/".$today_folder."/values.inc", $timestamp, $isin, "error: require values_market worked not");
		if(file_exists("./".$isin."/".$today_folder."/config_sales.inc"))
			require("./".$isin."/".$today_folder."/config_sales.inc");
		else
			logRequest("./".$isin."/".$today_folder."/config_sales.inc", $timestamp, $isin, "error: require config_sales worked not");
		if(file_exists("./".$isin."/".$today_folder."/values_sales.inc"))
			require("./".$isin."/".$today_folder."/values_sales.inc");
		else
			logRequest("./".$isin."/".$today_folder."/values_sales.inc", $timestamp, $isin, "error: require values_sales worked not");
	} else {
		echo "require worked not<br>\r\n"; //Debug
		if (mkdir("./".$isin."/".$today_folder, 0777, true)) {
			$maxHighPrice = 0;
			$_salesmaxHighPrice = 0;
			$maxHighPriceTime = $minLowPriceTime = strtotime(date('Y/m/d'));
			$minLowPrice = 999999; // Unrealistisch hoch setzen, damit es vom 1. Wert auf jeden Fall ueberschrieben wird
			$_salesminLowPrice = 999999;
			$_salesmaxHighPriceTime = $_salesminLowPriceTime = strtotime(date('Y/m/d'));
			$nexttradeid = -1;
			// um sicher zu gehen, dass falls noch keine Sales vorliegen, trotzdem eine Sales_Config geschrieben wird
			writeConfig($_salesmaxHighPrice, $_salesmaxHighPriceTime, $_salesminLowPrice, $_salesminLowPriceTime, $isin, "_sales", $nexttradeid);
			$stock_values = array();
			$stock_values_sales = array();
			// um sicher zu gehen, dass falls noch keine Sales vorliegen, trotzdem eine Sales_Values geschrieben wird
			writeValues($stock_values_sales, $isin, "_sales");
			logRequest('$isin = "'.$isin."\"\r\n".'$maxHighPrice = "'.$maxHighPrice."\"\r\n".'$_salesmaxHighPrice = "'.$_salesmaxHighPrice."\"\r\n".'$maxHighPriceTime = "'.$maxHighPriceTime."\"\r\n".'$minLowPriceTime = "'.$minLowPriceTime."\"\r\n".'$minLowPrice = "'.$minLowPrice."\"\r\n".'$_salesminLowPrice = "'.$_salesminLowPrice."\"\r\n".'$_salesmaxHighPriceTime = "'.$_salesmaxHighPriceTime."\"\r\n".'$_salesminLowPriceTime = "'.$_salesminLowPriceTime."\"\r\n".'$nexttradeid = "'.$nexttradeid."\"\r\n",
			"CREATE", "note: today_folder ./".$isin."/".$today_folder. "created");
		} else {
			logRequest("./".$isin."/".$today_folder, $timestamp, $isin, "error: today_folder could not be created");
			// Todo: Error melden, dass Tageskursdaten-Order nicht erstellt werden konnte
	 		continue;
	 	}
	}
	
	
	//				  "Referer: https://www.comdirect.de/inf/aktien/detail/uebersicht.html?BRANCHEN_FILTER=true&ID_NOTATION=28085835\r\n"
	
	$context = stream_context_create($opts); 
	// $homepage = file_get_contents('https://www.comdirect.de/inf/snippet$ewf.popup.kursdaten.intraday.snippet?ID_NOTATION=253931',false,$context);
	$homepage = file_get_contents('http://www.tradegate.de/cgi-bin/orderbuch.cgi?/opt/bfv/etc/webtoolscgi.xml+'.$isin,false,$context);
	$homepage=preg_replace_callback(
    	'/(\d+(,)\d*)/',
    	function ($treffer) {
      		return str_replace(",", ".", $treffer[0]);
    	},
    	$homepage
	);
	
	// Erstelle neues Array fuer gefundenen Orderkurs
	$stock_value = array();
	/* ALTER CODE:
	 * 
	 * Speicher aktuelles Gebot in Orderkurs-Array
	$stock_value[] = str_replace(",", ".", getSubstr($homepage, "\"bid\" : \"", "\""));
	// Speicher aktuellen Orderkurs (Forderung) in Orderkurs-Array
	$stock_value[] = str_replace(",", ".", getSubstr($homepage, "\"ask\" : \"", "\""));*/

	// Speicher aktuelles Gebot in Orderkurs-Array
	$stock_value[] = getNumericSubstr($homepage, "bid\"", ",");
	// Speicher aktuellen Orderkurs (Forderung) in Orderkurs-Array
	$stock_value[] = getNumericSubstr($homepage, "ask\"", ",");
	// Speicher Zeitpunkt des Orderkurses in Orderkurs-Array
	$stock_value[] = $timestamp;
	// Speichere Orderkurs-Array in Tagesorderkurs-Array
	$stock_values[] = $stock_value;
	echo "after" . count($stock_values) . "<br>\r\n";  //Debug
	writeValues($stock_values, $isin);
	
	
	if($stock_value[0] === false) {
		logRequest($homepage, $timestamp, $isin, "Error: bid not found");

	// Ist das aktuelle Gebot, dass hoehste des Tages?
	} else if(maximumHasReached($maxHighPrice, $stock_value[0])) {
		$maxHighPrice = $stock_value[0];
		$maxHighPriceTime = $timestamp;
		writeConfig($maxHighPrice, $maxHighPriceTime, $minLowPrice, $minLowPriceTime, $isin);
	}
	
	if($stock_value[1] === false) {
		logRequest($homepage, $timestamp, $isin, "Error: ask not found");

	// Da Forderung unterschiedlich zu Gebot ist, fragen ob die aktuelle Forderung, die niedrigste des Tages ist?
	} else if(minimumHasReached($minLowPrice, $stock_value[1])) {
		// Durch unbekannten "Fehler" kam es in den Tabellen zu Forderungen von 0 Euro... 
		if($stock_value[1] > 0) {
			$minLowPrice = $stock_value[1];
			$minLowPriceTime = $timestamp;
			writeConfig($maxHighPrice, $maxHighPriceTime, $minLowPrice, $minLowPriceTime, $isin);
			// ... Falls nochmal auftritt...
		} else {
			//  .. wegschreiben!
			logRequest($homepage, $timestamp, $isin, "Error: ask =< 0");
		}
	}
	
	//-----------------Sales-----------------
	
	$opts['http']['header']="User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0\r\n" . 
					  "Accept: text/html, */*; q=0.01\r\n" .
					  "Accept-Language: de,en-US;q=0.7,en;q=0.3\r\n" . 
					  "Accept-Encoding: identity\r\n" .
					  "X-Requested-With: XMLHttpRequest\r\n" .
					  "Referer: http://www.tradegate.de/orderbuch_umsaetze.php?isin=".$isin."\r\n".
					  "Connection: close\r\n";
					  
	$context = stream_context_create($opts); 
	$homepage = file_get_contents("http://www.tradegate.de/cgi-bin/umsaetze.cgi?/opt/bfv/etc/webtoolscgi.xml+".$isin."+".$nexttradeid,false,$context);
	 /* Neue BOE.php Funktion
  * Regel:
  * $matches[0][0] = $matches[0][10]
  * !(strpos($matches[0][1], ",") === false)
  * strlen($matches[0][2]) == 4
  * strlen($matches[0][3]) == 2 && strlen($matches[0][4]) == 2 && strlen($matches[0][5]) == 2 && strlen($matches[0][6]) == 2 && strlen($matches[0][7]) == 2
  */
 	$preg_status = preg_match_all('/((\n)(.)*(umsatz\")(.)*(\n))/', $homepage, $hp_stock_values);
	
	if(!($preg_status === false) && $preg_status > 0) {
 
	 	foreach ($hp_stock_values[0] as $sale_line) {
	 		$stock_value = array();
			preg_match_all('/(\d+(,)?\d*)/', $sale_line, $matches);
			// Speicher die Aktienmenge in Orderkurs-Array
			$stock_value[] = $matches[0][8];
			// Speichere die ID fuer den naechsten Orderkurs-Abruf via HTTP
			$nexttradeid = $matches[0][10];
			// Speicher den Orderzeitpunkt als Timestamp in Orderkurs-Array
			$stock_value[] = strtotime($matches[0][2]."-".$matches[0][3]."-".$matches[0][4]." ".$matches[0][5].":".$matches[0][6].":".$matches[0][7]);
			// Speicher den Orderkurs in Orderkurs-Array
			$stock_value[] = str_replace(",", ".", $matches[0][1]);
			// Speichere Orderkurs-Array in Tagesorderkurs-Array
			$stock_values_sales[] = $stock_value;
			if($matches[0][0] != $matches[0][10])
				logRequest($sale_line, $stock_value[1], $isin, "Error: nexttradeid not same");
			if(strpos($stock_value[2], ".") === false)
				logRequest($sale_line, $stock_value[1], $isin, "Error: price not float");
			if(strlen($matches[0][2]) != 4)
				logRequest($sale_line, $stock_value[1], $isin, "Error: year not valid");
			if(strlen($matches[0][3]) != 2 || strlen($matches[0][4]) != 2 || strlen($matches[0][5]) != 2 || strlen($matches[0][6]) != 2 || strlen($matches[0][7]) != 2 || ($today_time+28800) > $stock_value[1] || ($today_time+79500) < $stock_value[1])
				logRequest($sale_line, $stock_value[1], $isin, "Error: date or time not valid");
		
			// Ist das aktuelle Gebot, dass hoehste des Tages?
			if(maximumHasReached($_salesmaxHighPrice, $stock_value[2])) {
				$_salesmaxHighPrice = $stock_value[2];
				$_salesmaxHighPriceTime = $stock_value[1];
			}
			// Da Forderung unterschiedlich zu Gebot ist, fragen ob die aktuelle Forderung, die niedrigste des Tages ist?
			if(minimumHasReached($_salesminLowPrice, $stock_value[2])) {
					$_salesminLowPrice = $stock_value[2];
					$_salesminLowPriceTime = $stock_value[1];
			}
	 	}
		writeConfig($_salesmaxHighPrice, $_salesmaxHighPriceTime, $_salesminLowPrice, $_salesminLowPriceTime, $isin, "_sales", $nexttradeid);
		writeValues($stock_values_sales, $isin, "_sales");
 	} elseif($homepage =! "[]") {
 		logRequest("http://www.tradegate.de/cgi-bin/umsaetze.cgi?/opt/bfv/etc/webtoolscgi.xml+".$isin."+".$nexttradeid."\r\n".$homepage, $timestamp, $isin, "Error: no match with regular expression with preg_match_all");
 	}
	
	// Todo: Funktionsaufruf zum neuschreiben der dayConfig
	
	// Todo: unktionsaufruf zum neuschreiben der arrayValueFile
	
	// Loeschen des geladenen (gesamten) Sales-Datensatzes, damit es nicht in falsche Tabellen gespeichert werden kann
	unset($stock_values_sales);
	// Loeschen des geladenen (gesamten) Gebotes-Datensatzes, damit es nicht in falsche Tabellen gespeichert werden kann
	unset($stock_values);
}

// Gebe Zugriff fuer weitere Aufrufe des Skripts wieder frei
@unlink("./ACTIVE");
echo "Done";


/*
 * nexttradeid / highPricePerDay / lowPricePerDay / maxLowPrice -> OnNewDayLoadFromYesterday (downAfterMailMinusOneEuro + mailInfo) / maxHighPrice -> OnNewDayLoadFromYesterday (addAfterMailPlusOneEuro + mailInfo) 
 * priceArrayPerDay
 * SchedulerRefreshEvery30Seconds
 * http://www.boerse-frankfurt.de/feeds/news_ag.rss?isin=US0231351067
 * https://www.ls-tc.de/de/aktie/41786
 * 
 */
?>