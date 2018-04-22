<?php
define('__SA__TIMESTAMP__',time());
define('__SA__ERR_PATH__',"../../parseErrors");
define('__SA__TODAY_FOLDER__',date('Y/m/d',__SA__TIMESTAMP__));
define('__SA__TIME_START__',microtime(true));
define('__SA__MAX_SECONDS_PAST__',18);
// define('__SA__TODAY_TIME__', strtotime(__SA__TODAY_FOLDER__)); // Production
define('__SA__TODAY_TIME__', strtotime("2018/04/20")); // Debug

function handleError($errno, $errstr, $errfile, $errline, array $errcontext) {
    
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

function checkDurationStatus($filename, $lineno) {
    $duration = microtime(true)-__SA__TIME_START__;
    if($duration>__SA__MAX_SECONDS_PAST__) {
        errorLongDuration($duration,$filename, $lineno);
    }
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

function logRequest($address, $timestamp, $isin, $error, $content = "") {
	$datestamp = date("Ymd",$timestamp);
	if(file_exists("../../parseErrors") || mkdir("../../parseErrors", 0777, true)) {
	    file_put_contents("../../parseErrors/".$datestamp."_".$isin.".log", date("Y.m.d H:i:s",$timestamp)." (".$timestamp.") : ".$address."\r\n".$error."\r\n\r\n", FILE_APPEND | LOCK_EX);
	    // If JSON-Dump requested...
	    if($content != "" && !file_exists(__SA__ERR_PATH__."/".$datestamp."_".$isin."_".__SA__TIMESTAMP__.".dump")) {
	        file_put_contents(__SA__ERR_PATH__."/".$datestamp."_".$isin."_".__SA__TIMESTAMP__.".dump", $content);
	    }
	} else {
		echo "Error: Could not write Error...<br>\r\n"; //Debug
	}
}

function writeValues($stock_values, $isin, $filename, $lineno, $typ = "") {
    // global $today_folder;
    $stock_size = count($stock_values);
    $i = 1;
    
    if(file_exists("./".$isin."/".__SA__TODAY_FOLDER__)) {
        checkDurationStatus($filename, $lineno);
        $filepointer = fopen("./".$isin."/".__SA__TODAY_FOLDER__."/values".$typ.".inc", "w");
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
        // Meldung, dass Orderwerte nicht neu geschrieben werden konnten
        logRequest($filename." : ".$line, $timestamp, $isin, "error: ordervalues".$typ." could not wrote.");
    }
}

 function writeConfig($maxHighPrice, $maxHighPriceTime, $minLowPrice, $minLowPriceTime, $isin, $filename, $lineno, $typ = "", $nexttradeid = "") {
    // global $today_folder;
    
    
    if(file_exists("./".$isin."/".__SA__TODAY_FOLDER__)) {
        checkDurationStatus($filename, $lineno);
        $filepointer = fopen("./".$isin."/".__SA__TODAY_FOLDER__."/config".$typ.".inc", "w");
        fwrite($filepointer, '<?php'."\r\n".'$'.$typ.'maxHighPrice = \''.$maxHighPrice.'\';'."\r\n".
        					'$'.$typ.'maxHighPriceTime = \''.$maxHighPriceTime.'\';'."\r\n".
        					'$'.$typ.'minLowPrice = \''.$minLowPrice.'\';'."\r\n".
        					'$'.$typ.'minLowPriceTime = \''.$minLowPriceTime.'\';'."\r\n".
        					(($nexttradeid != "") ? '$nexttradeid = \'' . $nexttradeid . "';\r\n" : "").'?>');
        fclose($filepointer);
    } else {
        // Meldung, dass Max/Min nicht neu geschrieben werden konnten
        logRequest($filename." : ".$line, $timestamp, $isin, "error: min/max of config_values".$typ." could not wrote.");
    }
 }
 
function parseStockDate(&$value, $timestamp, $isin, $filename, $line, $err_msg, $homepage) {
    if(($date = date_create_from_format('Y-m-d H:i:s.u', $value.' 00:00:00.000')) && __SA__TODAY_TIME__ == date_format($date, 'U')) {
        return true;
    } else {
        logRequest($filename." : ".$line, $timestamp, $isin, $err_msg.$value, $homepage);
        return false;
    }
}

function parseStockTime(&$value, $timestamp, $isin, $filename, $line, $err_msg, $homepage) {
    if(($date = date_create_from_format('H:i:s.u', $value)) && ($trade_time = date_format($date, 'U')) > (__SA__TODAY_TIME__+28800) && (__SA__TODAY_TIME__+79500) > $trade_time ) {
        return true;
    } else {
        logRequest($filename." : ".$line, $timestamp, $isin, $err_msg.$value, $homepage);
        return false;
    }
}

function parseStockMarkerString(&$value, $timestamp, $isin, $filename, $line, $err_msg, $homepage) {
     if("P" == $value) {
         return true;
     } else {
         logRequest($filename." : ".$line, $timestamp, $isin, $err_msg.$value, $homepage);
         return false;
     }
}

function parseStockInt(&$value, $timestamp, $isin, $filename, $line, $err_msg, $homepage) {
    if(preg_match("/^\d+$/",$value)) {
        return true;
    } else {
        logRequest($filename." : ".$line, $timestamp, $isin, $err_msg.$value, $homepage);
        return false;
    }
}

function parseStockFloat(&$value, $timestamp, $isin, $filename, $line, $err_msg, $homepage) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
    $value = str_replace(",",".",$value);
    if(preg_match("/^\d+\.?\d*$/",$value)) {
        return true;
    } else {
        logRequest($filename." : ".$line, $timestamp, $isin, $err_msg.$value, $homepage);
        return false;
    }
}

function parseStockNumber(&$value, $data_type, $timestamp, $isin, $filename, $line, $err_msg, $homepage) {
    switch ($data_type) {
        case "float":
            return parseStockFloat($value, $timestamp, $isin, $filename, $line, $err_msg, $homepage);
            break;
        case "int":
            return parseStockInt($value, $timestamp, $isin, $filename, $line, $err_msg, $homepage);
            break;
        case "date":
            return parseStockDate($value, $timestamp, $isin, $filename, $line, $err_msg, $homepage);
            break;
        case "time":
            return parseStockTime($value, $timestamp, $isin, $filename, $line, $err_msg, $homepage);
            break;
        case "markerString":
            return parseStockMarkerString($value, $timestamp, $isin, $filename, $line, $err_msg, $homepage);
            break;
        default:
            logRequest($filename." : ".$line, $timestamp, $isin, "error: received unknown data_type ".$data_type, $homepage);
            return false;
        break;
    }
}

function parseStockValue(&$stock_value, $json_sub_data, $element_name, $data_type, $timestamp, $isin, $filename, $line, $write_log, $homepage) {
    if(array_key_exists ( $element_name, $json_sub_data ) && parseStockNumber($json_sub_data[$element_name], $data_type, $timestamp, $isin, $filename, $line, "warning: Stock Value ".$element_name." not match expected ".$data_type." Format: ",$homepage)) {
        // Speicher aktuelles Gebot in Orderkurs-Array
        $stock_value[] = $json_sub_data[$element_name];
        // Falls Umsatz nicht gefunden, nicht loggen
    } elseif ($write_log) {
        // Write parsed JSON to Log
        logRequest($filename." : ".$line, $timestamp, $isin, "error: Element ".$element_name." in JSON Stock Sub-Array not exists or invalid Value.",$homepage);
    }
}

function getUrl($url, $ref,$filename, $lineno) {
    $opts = array (
        'http'=>array (
            'methode'=>"GET",
            'header'=>"User-Agent: Mozilla/5.0 (Windows NT 6.3; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0\r\n" .
            "Accept: text/html, */*; q=0.01\r\n" .
            "Accept-Language: de,en-US;q=0.7,en;q=0.3\r\n" .
            "Accept-Encoding: identity\r\n" .
            "X-Requested-With: XMLHttpRequest\r\n" .
            "Referer: ".$ref."\r\n".
            "Connection: close\r\n"
        )
    );
    
    $context = stream_context_create($opts);
    // $homepage = file_get_contents('https://www.comdirect.de/inf/snippet$ewf.popup.kursdaten.intraday.snippet?ID_NOTATION=253931',false,$context);
    checkDurationStatus($filename, $lineno);
    return file_get_contents($url,false,$context);
}

function maximumHasReached($max_price, $current_price) {
    return $max_price < $current_price;
}

function minimumHasReached($min_price, $current_price) {
    return $min_price > $current_price;
}

function errorLongDuration($duration, $filename, $line) {
	throw new ErrorException("Execution to long: ".$duration." Seconds", 0, 1337, $filename, $line);
}

function shutdown_sa($errcode) {
    define('PROGRAM_EXECUTION_SUCCESSFUL', true);
    exit($errcode);
}

function fatal_error() {
    global $isin;
    if ( ! defined('PROGRAM_EXECUTION_SUCCESSFUL')) {
        $error = error_get_last();
        logRequest($error['file']." : ".$error['line'], __SA__TIMESTAMP__, $isin, "error: ".$error['type']." ".$error['message']);
    }
}

function validedateDataLogicValues($maxHighPrice, $minLowPrice, $maxHighPriceTime, $minLowPriceTime, $fields, $_skip, $stock_values, $stock_value, $filename, $line, $timestamp, $isin, $homepage) {
    if(count($stock_value) > 1) {
        // Speicher Zeitpunkt des Orderkurses in Orderkurs-Array
        $stock_value[] = $timestamp;
        // Ist das aktuelle Gebot, dass hoehste des Tages?
        if(!$_skip["config"] && maximumHasReached($maxHighPrice, $stock_value[0])) {
            $maxHighPrice = $stock_value[0];
            $maxHighPriceTime = $timestamp;
            writeConfig($maxHighPrice, $maxHighPriceTime, $minLowPrice, $minLowPriceTime, $isin, __FILE__, __LINE__);
        }
        if(!$_skip["config"] && minimumHasReached($minLowPrice, $stock_value[1])) {
            // Durch unbekannten "Fehler" kam es in den Tabellen zu Forderungen von 0 Euro...
            if($stock_value[1] > 0) {
                $minLowPrice = $stock_value[1];
                $minLowPriceTime = $timestamp;
                writeConfig($maxHighPrice, $maxHighPriceTime, $minLowPrice, $minLowPriceTime, $isin, __FILE__, __LINE__);
                // ... Falls nochmal auftritt...
            } else {
                //  .. wegschreiben!
                // logRequest($homepage, $timestamp, $isin, "error: ask =< 0");
                logRequest(__FILE__." : ".__LINE__, $timestamp, $isin, "error: ask =< 0.",$homepage);
            }
        }
        if(!$_skip["values"]) {
            // Speichere Orderkurs-Array in Tagesorderkurs-Array
            $stock_values[] = $stock_value;
            writeValues($stock_values, $isin, __FILE__, __LINE__);
        }
    } // error already alert by parseStockValue or exception
}

function parseStockArray($homepage, $fields, $_skip, $stock_values, $timestamp, $isin, $filename, $line) {
    $json_data = json_decode($homepage, true);
    $json_error = json_last_error();
    if($json_error == JSON_ERROR_NONE) {
        if(is_array($json_data)) {
            if(array_key_exists ( 0 , $json_data )) {
                $stock_value = array();
                for ($i = 0; $i < count($fields); $i++) {
                    parseStockValue($stock_value, $json_data[0], $fields[$i]['name'], $fields[$i]['type'], $timestamp, $isin, $filename, $line, $fields[$i]['log'], $homepage);
                    // Breche verarbeitung für Sales ab, falls Umsatz oder anderes Feld nicht vorhanden sind
                    if(count($stock_value)==$i) {
                        break;
                    }
                }
                try {
                    switch (count($fields)) {
                        case 2:
                            global $maxHighPrice, $minLowPrice, $maxHighPriceTime, $minLowPriceTime;
                            // HIER WEIERMACHEN 22.4.18
                            validedateDataLogicValues($maxHighPrice, $minLowPrice, $maxHighPriceTime, $minLowPriceTime, $fields, $_skip, $stock_values, $stock_value, $filename, $line, $timestamp, $isin, $homepage);
                            // HIER WEIERMACHEN 22.4.18
                        break;
                        case 7:
                            global $maxHighPrice, $minLowPrice, $maxHighPriceTime, $minLowPriceTime;
                            validedateDataLogicSales($maxHighPrice, $minLowPrice, $maxHighPriceTime, $minLowPriceTime, $fields, $_skip, $stock_values, $stock_value, $filename, $line, $timestamp, $isin, $homepage);
                        break;
                        default:
                            logRequest($filename." : ".$line, $timestamp, $isin, "error: received unknown \$fields-array ".$data_type, $homepage);
                            return false;
                        break;
                    }
                } catch (Exception $e) {
                    // obviate script-timeout or unexpected error
                    logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, "error: ".$e->getMessage(),$homepage);
                    shutdown_sa(0);
                }
            } else {
                // Write parsed JSON to Log
                logRequest(__FILE__." : ".__LINE__, $timestamp, $isin, "error: Element 0 of JSON Quest Root-Array not exists.",$homepage);
            }
        } else {
            // JSON Root is no array
            logRequest(__FILE__." : ".__LINE__, $timestamp, $isin, "error: JSON Root is no array: ".$json_error,$homepage);
        }
    } else {
        logRequest(__FILE__." : ".__LINE__, $timestamp, $isin, "error: JSON Error occurred: ".$json_error,$homepage);
    }
}

// Alle Fehler an Funktion handleError weiterleiten und als Exception abfangen um sie mittels logRequest zu loggen
set_error_handler("handleError");
ignore_user_abort(true);

register_shutdown_function('fatal_error');

$timestamp = time();
$isin = "US0231351067";
$homepage = "";
$_skip["config"]=false;
$_skip["values"]=false;
$_skip["config_sales"]=false;
$_skip["sales"]=false;
$_errors = array();

// Da in Unix Verzeichnisse auch Dateien sind, auf file_exists checken...
if(file_exists("./".$isin."/".__SA__TODAY_FOLDER__)) {
    try {
        include("./".$isin."/".__SA__TODAY_FOLDER__."/config.inc");
    } catch (Exception $e) {
        logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, "error: ".$e->getMessage()." - Updating Quests Config skipped");
        $_skip["config"]=true;
        $maxHighPrice = 0;
        $maxHighPriceTime = $minLowPriceTime = $timestamp;
        $minLowPrice = 999999; // Unrealistisch hoch setzen, damit es vom 1. Wert auf jeden Fall ueberschrieben wird
    }
    try {
        include("./".$isin."/".__SA__TODAY_FOLDER__."/values.inc");
    } catch (Exception $e) {
        logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, "error: ".$e->getMessage()." - Updating Quests skipped");
        $_skip["values"]=true;
        $stock_values = array();
    }
    try {
        include("./".$isin."/".__SA__TODAY_FOLDER__."/config_sales.inc");
    } catch (Exception $e) {
        logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, "error: ".$e->getMessage()." - Updating Sales Config skipped");
        $_skip["config_sales"]=true;
        $_salesminLowPrice = 999999;
        $_salesmaxHighPrice = 0;
        $_salesmaxHighPriceTime = $_salesminLowPriceTime = $timestamp;
    }
    try {
        include("./".$isin."/".__SA__TODAY_FOLDER__."/values_sales.inc");
    } catch (Exception $e) {
        logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, "error: ".$e->getMessage()." - Updating Sales skipped");
        $_skip["sales"]=true;
        $stock_values_sales = array();
    }
} else {
    try {
        mkdir("./".$isin."/".__SA__TODAY_FOLDER__, 0777, true);
        $maxHighPrice = 0;
        $maxHighPriceTime = $minLowPriceTime = $timestamp;
        $minLowPrice = 999999; // Unrealistisch hoch setzen, damit es vom 1. Wert auf jeden Fall ueberschrieben wird
        $_salesminLowPrice = 999999;
        $_salesmaxHighPrice = 0;
        $_salesmaxHighPriceTime = $_salesminLowPriceTime = $timestamp;
        $nexttradeid = -1;
        // um sicher zu gehen, dass falls noch keine Sales vorliegen, trotzdem eine Sales_Config geschrieben wird
        writeConfig($_salesmaxHighPrice, $_salesmaxHighPriceTime, $_salesminLowPrice, $_salesminLowPriceTime, $isin, __FILE__, __LINE__, "_sales", $nexttradeid);
        $stock_values = array();
        $stock_values_sales = array();
        // um sicher zu gehen, dass falls noch keine Sales vorliegen, trotzdem eine Sales_Values geschrieben wird
        writeValues($stock_values_sales, $isin, __FILE__, __LINE__, "_sales");
        // logRequest('$isin = "'.$isin."\"\r\n".'$maxHighPrice = "'.$maxHighPrice."\"\r\n".'$_salesmaxHighPrice = "'.$_salesmaxHighPrice."\"\r\n".'$maxHighPriceTime = "'.$maxHighPriceTime."\"\r\n".'$minLowPriceTime = "'.$minLowPriceTime."\"\r\n".'$minLowPrice = "'.$minLowPrice."\"\r\n".'$_salesminLowPrice = "'.$_salesminLowPrice."\"\r\n".'$_salesmaxHighPriceTime = "'.$_salesmaxHighPriceTime."\"\r\n".'$_salesminLowPriceTime = "'.$_salesminLowPriceTime."\"\r\n".'$nexttradeid = "'.$nexttradeid."\"\r\n", "CREATE", "note: today_folder ./".$isin."/".$today_folder. "created");
    } catch (Exception $e) {
        logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, "error: ".$e->getMessage()." - today_folder could not be created");
        shutdown_sa(0);
    }
}

try {
    $homepage = getUrl('http://www.tradegate.de/cgi-bin/orderbuch.cgi?/opt/bfv/etc/webtoolscgi.xml+'.$isin, 'http://www.tradegate.de/orderbuch.php?isin='.$isin, __FILE__, __LINE__);
    parseStockArray($homepage, array(["name" => "bid","type" => "float","log" => true],["name" => "ask","type" => "float","log" => true]), $_skip, $stock_values, $timestamp, $isin, __FILE__, __LINE__);

} catch (Exception $e) {
    logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, "error: ".$e->getMessage(),$homepage);
}

//-----------------Sales-----------------

try {
    $homepage = getUrl('http://www.tradegate.de/cgi-bin/umsaetze.cgi?/opt/bfv/etc/webtoolscgi.xml+'.$isin.'+'.$nexttradeid, 'http://www.tradegate.de/orderbuch_umsaetze.php?isin='.$isin, __FILE__, __LINE__);
} catch (Exception $e) {
    // Handle exception
    logRequest($e->getFile().":".$e->getLine(), $timestamp, $isin, "error: ".$e->getMessage(),$homepage);
    shutdown_sa(0);
}
$sales = json_decode($homepage, true);
$json_error = json_last_error();
// var_dump($sales); // Debug
echo "DEBUG: reached end"; // DEBUG
restore_error_handler();
define('PROGRAM_EXECUTION_SUCCESSFUL', true);
?>