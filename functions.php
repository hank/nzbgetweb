<?php

//*****************************************************************************
// RPC-Interface to NZBGet-Server
//

if (is_readable('lib/xmlrpc.inc')) {
	include('lib/xmlrpc.inc');
}

if (is_readable('JSON.php')) {
	require_once 'JSON.php';
} else if (is_readable('lib/JSON.php')) {
	require_once 'lib/JSON.php';
}

function &SendRequest($server, $port, $path, $username, $password, $msg)
{
	global $ConnectTimeout;

	$errno=null;
	$errstr=null;

	$op= "POST " . $path. " HTTP/1.0\r\n" .
		"User-Agent: NZBGetWeb\r\n" .
		"Host: ". $server . "\r\n" .
		'Authorization: Basic ' . base64_encode($username . ':' . $password) ."\r\n".
		"Content-Length: ".strlen($msg)."\r\n".
		"\r\n".
		$msg;

	$fp=@fsockopen($server, $port, $errno, $errstr, $ConnectTimeout);
	if(!$fp)
	{
		$errstr='ERROR: Connect error: '.$errstr . ' (' . $errno . ')';
		return $errstr;
	}

	if(!fputs($fp, $op, strlen($op)))
	{
		$errstr='ERROR: Write error: '.$errstr . ' (' . $errno . ')';
		return $errstr;
	}

	$ipd='';
	while($data=fread($fp, 32768))
	{
		$ipd.=$data;
	}
	fclose($fp);
	
	if (!$ipd) {
		$errstr='ERROR: Server closed connection';
		return $errstr;
	}
	
	$pos=strpos($ipd, "\r\n\r\n");
	if ($pos)
		$ipd=substr($ipd, $pos + 4);
	
	return $ipd;
}

function ParamsLIB($params) {
	$farr=array();
	if (is_array($params)) {
		foreach ($params as $p) {
			if (is_int($p))
				$f=new xmlrpcval($p, 'int');
			else
				$f=new xmlrpcval($p, 'string');
			$farr[]=$f;	
		}
	}
	return $farr;
}

function GetRequest_XmlRpc_Lib($host, $port, $passwd, $request, $params) {
	global $ConnectTimeout;

	$f=new xmlrpcmsg($request, ParamsLIB($params));
	$c=new xmlrpc_client('xmlrpc', $host, $port);
	$c->setCredentials('nzbget',$passwd);
	$c->setDebug(False);
	$r=$c->send($f, $ConnectTimeout);
	if(!$r->faultCode())
		//Got a valid result, decode into php variables
		return php_xmlrpc_decode($r->value());
	else {
		//Got an error, print description
		trigger_error('RPC: method "'.$request.'", error '.$r->faultCode().' - '.$r->faultString());
	}
}

function GetMultiRequest_XmlRpc_Lib($host, $port, $passwd, $requestarr) {
	global $ConnectTimeout;

	$c=new xmlrpc_client('xmlrpc', $host, $port);
	$c->setCredentials('nzbget',$passwd);
	$c->setDebug(False);

	$farr=array();
	foreach ($requestarr as $request) {
		$f=new xmlrpcmsg($request[0], ParamsLIB($request[1]));
		$farr[]=$f;	
	}
	
	$ra=$c->multicall($farr, $ConnectTimeout);

	$rarr=array();
	$index = 0;
	foreach ($ra as $r) {
		if(!$r->faultCode())
			//Got a valid result, decode into php variables
			$rarr[] = php_xmlrpc_decode($r->value());
		else {
			if (!strncmp($r->faultString(), 'Connect error: ', 15)) {
				return 'ERROR: '.$r->faultString();
			}
			trigger_error('RPC: method "'.$requestarr[$index][0].'", error '.$r->faultCode().' - '.$r->faultString());
		}
		$index++;
	}

	return $rarr;
}

function GetRequest_XmlRpc_Ext($host, $port, $passwd, $method, $params) {
	$request = xmlrpc_encode_request($method, $params);
	
	$file = SendRequest($host, $port, '/xmlrpc', 'nzbget', $passwd, $request);
	
	//echo "file=".$file."<br><br><br><br>";

	if (IsConnectError($file)) {
		return $file;
	}

	$response = xmlrpc_decode($file);
	//var_dump($response);

	if (is_array($response) && xmlrpc_is_fault($response))
		trigger_error('RPC: method "'.$method.'", error '.$response['faultCode'].' - '.$response['faultString']);
	else
		return $response;
}

function GetMultiRequest_XmlRpc_Ext($host, $port, $passwd, $methodarr) {
	$multirequest=array();
	
	foreach ($methodarr as $method) {
		$multirequest[] = array('methodName' => $method[0], 'params' => $method[1]);
	}

	$request = xmlrpc_encode_request('system.Multicall', $multirequest);

	$file = SendRequest($host, $port, '/xmlrpc', 'nzbget', $passwd, $request);

	//echo "file=".$file."<br><br><br><br>";
	
	if (IsConnectError($file)) {
		return $file;
	}
	
	$response = xmlrpc_decode($file);

	if (is_array($response) && xmlrpc_is_fault($response)) {
		trigger_error('RPC: method "system.Multicall", error '.$response['faultCode'].' - '.$response['faultString']);
	} else if (is_array($response)) {
		$index = 0;
		foreach ($response as $r) {
			if (is_array($r) && array_key_exists(0, $r) && is_array($r[0]) && array_key_exists('faultCode', $r[0])) {
				trigger_error('RPC: method "'.$methodarr[$index][0].'", error '.$r[0]['faultCode']." - ".$r[0]['faultString']);
			}
			$index++;
		}
		return $response;
	} else {
		return 'ERROR: Could not decode xml-data. Multicall-method.';
	}
}

function GetRequest_JsonRpc_Ext($host, $port, $passwd, $method, $params) {
	$reqarr=array('version' => '1.1', 'method' => $method, 'params' => $params);
	$request = json_encode($reqarr);
	
	$file = SendRequest($host, $port, '/jsonrpc', 'nzbget', $passwd, $request);

	if (IsConnectError($file)) {
		return $file;
	}

	$response = json_decode($file, true);
	if (is_array($response) && isset($response['error']) && isset($response['error']['code'])) 
		trigger_error('RPC: method "'.$method.'", error '.$response['error']['code']." - ".$response['error']['message']);
	else if (is_array($response) && isset($response['result']))
		return $response['result'];
	else
		return 'ERROR: Could not decode json-data. Method "'.$method.'".';
}

function GetMultiRequest_JsonRpc_Ext($host, $port, $passwd, $methodarr) {
	// There are no native support for multicalls in JSON-RPC, so we emulate it
	
	$response=array();
	
	foreach ($methodarr as $method) {
		$methodName=$method[0];
		$params=$method[1];
		$resp=GetRequest_JsonRpc_Ext($host, $port, $passwd, $methodName, $params);
		if (IsConnectError($resp)) {
			return $resp;
		}
		$response[]=$resp;
	}
	
	return $response;
}

function GetRequest_JsonRpc_Lib($host, $port, $passwd, $method, $params) {
	$reqarr=array('version' => '1.1', 'method' => $method, 'params' => $params);

	$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
	
	$request = $json->encode($reqarr);
	
	$file = SendRequest($host, $port, '/jsonrpc', 'nzbget', $passwd, $request);
	
	if (IsConnectError($file)) {
		return $file;
	}

	$response = $json->decode($file);
	if (is_array($response) && isset($response['error']) && isset($response['error']['code'])) 
		trigger_error('RPC: method "'.$method.'", error '.$response['error']['code'].' - '.$response['error']['message']);
	else if (is_array($response) && isset($response['result']))
		return $response['result'];
	else
		return 'ERROR: Could not decode json-data. Method "'.$method.'".';
}

function GetMultiRequest_JsonRpc_Lib($host, $port, $passwd, $methodarr) {
	// There are no native support for multicalls in JSON-RPC, so we emulate it
	
	$response=array();
	
	foreach ($methodarr as $method) {
		$methodName=$method[0];
		$params=$method[1];
		$resp=GetRequest_JsonRpc_Lib($host, $port, $passwd, $methodName, $params);
		if (IsConnectError($resp)) {
			return $resp;
		}
		$response[]=$resp;
	}
	
	return $response;
}

function GetAvailableApi() {
	global $RpcApi;
	if (!isset($RpcApi) || ($RpcApi== '') || ($RpcApi== 'auto')) {
		if (function_exists('json_decode'))
			$RpcApi='json-rpc-ext';
		else if (function_exists('xmlrpc_encode_request'))
			$RpcApi='xml-rpc-ext';
		else if (class_exists('Services_JSON'))
			$RpcApi='json-rpc-lib';
		else if (class_exists('xmlrpc_client'))
			$RpcApi='xml-rpc-lib';
		else
			$RpcApi = 'none';
	}
	return $RpcApi;
}

function GetRequest($method, $params) {
	global $ServerIp, $ServerPort, $ServerPassword;

	$RpcApi= GetAvailableApi();
	if ($RpcApi=='json-rpc-ext')
		return GetRequest_JsonRpc_Ext($ServerIp, $ServerPort, $ServerPassword, $method, $params);
	else if ($RpcApi=='json-rpc-lib')
		return GetRequest_JsonRpc_Lib($ServerIp, $ServerPort, $ServerPassword, $method, $params);
	else if ($RpcApi=='xml-rpc-ext')
		return GetRequest_XmlRpc_Ext($ServerIp, $ServerPort, $ServerPassword, $method, $params);
	else if ($RpcApi=='xml-rpc-lib')
		return GetRequest_XmlRpc_Lib($ServerIp, $ServerPort, $ServerPassword, $method, $params);
	else if ($RpcApi=='none')
		return 'ERROR: Could not detect available RPC-API: old PHP version or modules missing. See README for details.';
	else
		return 'ERROR: Invalid value for option "rpc_api"';
}

function GetMultiRequest($methodarr) {
	global $ServerIp, $ServerPort, $ServerPassword;

	$RpcApi= GetAvailableApi();
	if ($RpcApi=='json-rpc-ext')
		return GetMultiRequest_JsonRpc_Ext($ServerIp, $ServerPort, $ServerPassword, $methodarr);
	else if ($RpcApi=='json-rpc-lib')
		return GetMultiRequest_JsonRpc_Lib($ServerIp, $ServerPort, $ServerPassword, $methodarr);
	else if ($RpcApi=='xml-rpc-ext')
		return GetMultiRequest_XmlRpc_Ext($ServerIp, $ServerPort, $ServerPassword, $methodarr);
	else if ($RpcApi=='xml-rpc-lib')
		return GetMultiRequest_XmlRpc_Lib($ServerIp, $ServerPort, $ServerPassword, $methodarr);
	else if ($RpcApi=='none')
		return 'ERROR: Could not detect available RPC-API: old PHP version or modules missing. See README for details.';
	else
		return 'ERROR: Invalid value for option "rpc_api"';
}

function IsConnectError($resp) {
	return is_string($resp) && !strncmp($resp, 'ERROR:', 6);
}

//
// RPC-Interface to NZBGet-Server - END
//*****************************************************************************


// workaround for a bug on one of test system, where "round" with 0-argument hangs
function round0($arg) {
    return $arg==0 ? 0 : round($arg);
}

function round1($arg) {
    return $arg < 0.1 ? '0.0' : number_format($arg, 1);
}

function round2($arg) {
    return $arg < 0.01 ? '0.00' : number_format($arg, 2);
}

function freediskspace() {
	global $CheckSpaceDir;
	if (!file_exists($CheckSpaceDir)) {
		trigger_error("Directory $CheckSpaceDir does not exist. Check option \"CheckSpaceDir\"");
		return 0;
	}
	return disk_free_space($CheckSpaceDir)/1024/1024;
}

function namereplace($name) {
	global $NameReplaceChars;
	return strtr($name, $NameReplaceChars, str_pad('', strlen($NameReplaceChars)));
}

function FormatLogText($text) {
	$text = str_replace(chr(8), ' ', $text);
	global $LogBreakChars;
	foreach (str_split($LogBreakChars) as $ch)
	{
		$text = str_replace($ch, $ch.' ', $text);
	}
	return $text;
}

function sec2hms($sec) {
	$hms = '';
	$days = intval(intval($sec) / 86400); 
	if ($days > 0)
		$hms .= $days . 'd ';
	$hours = intval((intval($sec) % 86400) / 3600); 
	$hms .= $hours. ':';
	$minutes = intval(($sec / 60) % 60); 
	$hms .= str_pad($minutes, 2, '0', STR_PAD_LEFT). ':';
	$seconds = intval($sec % 60); 
	$hms .= str_pad($seconds, 2, '0', STR_PAD_LEFT);
	return $hms;
}

function formatSizeMB($MB) {
	if ($MB > 10240)
		return round1($MB / 1024.0) . ' GB';
	else if ($MB > 1024)
		return round2($MB / 1024.0) . ' GB';
	else
		return round2($MB) . ' MB';
}

function formatAge($time) {
	if ($time > 0) {
		$diff = time() - $time;
		if ($diff > 60*60*24)
			return round0($diff / (60*60*24)) .' d';
		else
			return round0($diff / (60*60)) .' h';
	}
}

function Redirect($url) {
	// redirect and exit: headers not always work, so we use  META-tag
	echo "<HTML><HEAD><META HTTP-EQUIV='REFRESH' content='0; url=$url'></HEAD></HTML>";
	exit(0);
}

function GetInfo($listgroups) {
	global $LogLines, $RpcApi, $LogFilter;

	$rarr = GetMultiRequest(array(
		array('version', null), 
		array('status', null),
		array($listgroups ? 'listgroups' : 'listfiles', array(0, 0)),
		array('postqueue', $LogLines),
		array('log', array(0, $LogLines)),
		array('history', null)));

	if (IsConnectError($rarr)) {
		return $rarr;
	}

	$r=array();
	$r['version']= $RpcApi=='xml-rpc-ext' ? $rarr[0][0] : $rarr[0];
	$r['status']= $RpcApi=='xml-rpc-ext' ? $rarr[1][0] : $rarr[1];
	$r['postqueue']=$rarr[3];
	$r['log']=$rarr[4];
	$r['history']=$rarr[5];

	if ($listgroups) {
		$r['groups']=$rarr[2];

		// find active group
		// definition: active group is the top group which have active downloads
		$r['queuedgroups']=array();
		foreach ($r['groups'] as $group) {
			$grouppaused=($group['PausedSizeLo'] != 0) && ($group['RemainingSizeLo']==$group['PausedSizeLo']);
			if (!$grouppaused && !$r['status']['ServerStandBy'] && !isset($r['activegroup']) && $group['ActiveDownloads'] > 0)
				$r['activegroup'] = $group; 
			else {
				// do not add group with all paused items, if it is currently in post-processor-queue
				$postgroup=False;
				foreach ($r['postqueue'] as $postitem)
					if ($postitem['NZBFilename']==$group['NZBFilename'])
						$postgroup=True;
				if (!$postgroup)
					$r['queuedgroups'][] = $group; 
			}
		}
	} else {
		$r['files']=$rarr[2];
	}
	
	if (isset($LogFilter) && (count($LogFilter) > 0)) {
		$r['log'] = FilterLogMessages($r['log']);
	}
	
	return $r;
}

function &FilterLogMessages(&$rlog) {
	global $LogFilter;

	$loglist=array();
	foreach ($rlog as $info) {
		$text=$info['Text'];
		$found=false;
		foreach ($LogFilter as $filter) {
			if ($filter != '') {
				$pos=strpos($text, $filter);
				if ($pos !== false){
					$found=true;
					break;
				}
			}
		}
		if (!$found){
			$loglist[]=$info;
		}
	}
	return $loglist;
}

//*****************************************************************************
// Upload file functions
//

function upload_file ($nzb_file) {
	global $NzbDir;
	//return $nzb_file['tmp_name'];
    $error = validate_upload($nzb_file);
    if (!$error) {
		$uploadfile = $NzbDir. "/" .basename($nzb_file['name']);
		//echo $uploadfile."<br>";
		//echo "nzbfile: ".$nzb_file['tmp_name'];
		//exit(-1);
		if (move_uploaded_file($nzb_file['tmp_name'], $uploadfile)) {
			chmod($uploadfile, 0777);
			$error = "<b><font color=green>File upload OK </font></b><br>
			Filename: " . $nzb_file['name'] ."<br>
			Filesize: " . $nzb_file['size'] ." <br>";
			GetRequest('scan', '');
		} else {
			$error = "<b><font color=red>Error:</font></b>\nCheck the path and the permissions for the upload directory (option <b>NzbDir</b>)";
		}
    }
	return $error;
}


function validate_upload($nzb_file) {
	global $UploadMaxFileSize;
	if ($nzb_file['error'] <> 0) {
    		if ($nzb_file['error'] == 4) { # do we even have a file?
        		$error = "\n<br>You did not upload anything!\n";
    		}
    		else if ($nzb_file['error'] == 2 || $nzb_file['error'] == 1) { # Think the file is too big
        		$error = "\n<br>Filesize is bigger then allowed! Please check the setting \"UploadMaxFileSize\" in php.ini.\n";
			} else {
        		$error = "\n<br>Could not upload file! Error code: " . $nzb_file['error'] . ".\n";
			}
    } else { 
		# check size and file type
		if ($nzb_file['size'] > $UploadMaxFileSize) 
			$error = "<br>The file <b>" .$nzb_file['name']. "</b> is bigger than " .$UploadMaxFileSize. " bytes!\n";
    }
    if (isset ($error))
		return "<b><font color=red>Error:</font></b>\n" . $error;
}

function DebugLog($filename, $text) {
	$file=fopen($filename,"a");
	fprintf($file, $text);
	fprintf($file, "\n");
	fclose($file);
}

function StartServer(&$output) {
	global $ServerStartCommand;
	$retval = 0;
	$r = exec($ServerStartCommand, $output, $retval);
	return $retval;
}

function StopServer(&$output) {
	global $ServerStopCommand;
	$retval = 0;
	$r = exec($ServerStopCommand, $output, $retval);
	return $retval;
}

function msie() {
	$agent = $_SERVER['HTTP_USER_AGENT'];
	$msie = (strpos($agent, 'MSIE')!==false) && (strpos($agent, 'Opera')===false);
	return $msie ;
}


//*****************************************************************************
// NEWZBIN SUPPORT FUNCTIONS
//

// DECODE CHUNKED CONTENT
//
// This is required as the http response from Newzbin uses chunked transfer-
// encoding. (We check the header to ensure it is before using this)
//
function decode_Chunked_Content($content)
{
   	$output = '';

	while (trim($content))
	{
     	if (! preg_match("/^([\da-fA-F]+)[^\r\n]*\r\n/sm", $content, $m)) 
		{
        	return false;
        }

       	$length = hexdec(trim($m[1]));
       	$cut = strlen($m[0]);

       	$output .= substr($content, $cut, $length);
       	$content = substr($content, $cut + $length + 2);
   }
        
   return $output ;
}


function FetchFromNewzbin($id)
{
	$errmsg = GetNzbFromNewzbin($id);
	if ($errmsg)
	{
		return "<b><font color=red>Error:</font></b>\n$errmsg";
	}
	else
	{
		GetRequest('scan', '');
		return "<b><font color=green>Report ID: $id fetched</font></b>";
	}
}

// GET NZB FROM NEWZBIN
//
// This is the main Newzbin function
// It encodes the correct HTTP request (using POST) according to 
// Newzbin's DirectNZB API
//
// To reduce bandwidth requirements we request the use of GZIP compression
//
// It's just a case of parsing the returned headers and queuing the nzb
// In this version we just save the file to the NzbDir or NzbDir/Category (User configured)
//
function GetNzbFromNewzbin($id)
{
	global $NzbDir;
	global $NewzbinUsername;
	global $NewzbinPassword;
	global $NewzbinUseCategories;

	// Set params up for the request..
	// username=xxxxxxx
	// password=xxxxxxx
	// reportid=xxxxxxx

	if($id === "")
		return "Please enter a report ID";

	$id = trim($id);

	if($NewzbinUsername == "")
		return "Please set Username in Config";

	if($NewzbinUsername == "")
		return "Please set Password in Config";

	$data = array('username' => $NewzbinUsername,
			'password' => $NewzbinPassword,
			'reportid' => $id);
 
	// Post the Request, we will set the referrer url to newzbin, as it makes no sense to refer back to us!	
	list($header, $content) = PostRequest("http://www.newzbin.com/api/dnzb/","http://www.newzbin.com",$data);

	// We need to check the Headers, these contain a load of info we can use to validate
	$header_lines = explode("\r\n",$header,50);
	$rcode = "";
	$rtext = "";
	$rcategory = "";
	$rname= "";
	$transfer_encoding = "";
	$content_encoding = "";
	
	foreach ($header_lines as &$value)
	{
   		$pos = stripos($value,"X-DNZB-RCode");
		if($pos !== false)
		{
			$length = strlen("X-DNZB-RCode:");
			$rcode = trim(substr($value,$length));
			
		}
   		$pos = stripos($value,"X-DNZB-RText");
		if($pos !== false)
		{
			$length = strlen("X-DNZB-RText:");
			$rtext = trim(substr($value,$length));
			
		}	
   		$pos = stripos($value,"X-DNZB-Name");
		if($pos !== false)
		{
			$length = strlen("X-DNZB-RName:");
			$rname = trim(substr($value,$length));
			
		}	
   		$pos = stripos($value,"X-DNZB-Category");
		if($pos !== false)
		{
			$length = strlen("X-DNZB-Category:");
			$rcategory = trim(substr($value,$length));
		}	
   		$pos = stripos($value,"Transfer-Encoding");
		if($pos !== false)
		{
			$length = strlen("Transfer-Encoding:");
			$transfer_encoding = strtolower(trim(substr($value,$length)));
		}	
   		$pos = stripos($value,"Content-Encoding");
		if($pos !== false)
		{
			$length = strlen("Content-Encoding:");
			$content_encoding = strtolower(trim(substr($value,$length)));
		}	
	}

	// Now we should have everything we need..
	// Check Everything..
	if($rcode === "")
	{
		return "Server Response Invalid";
	}

	if($rcode !== "200")
	{
		return $rtext;
	}
	
	if($rname === "")
	{
		return "No NZB Name returned";
	}

	if($rcategory === "")
	{
		return "No Category returned";
	}
	
	if($transfer_encoding === "chunked")
	{
		$content = decode_Chunked_Content($content);
		if($content === false)
		{
			return "Response could not be decoded (Chunked)";
		}
	}
	if($content_encoding  === "gzip")
	{
		$content = gzinflate(substr($content, 10));    
		if($content === false)
		{
			return "Response could not be decoded (GZip)";
		}
	}

	// now we have the File contents and the Category
	// We can queue it up
	// To do this
	// We should save the file to a 'Category' Sub-directory of the main NzbDir
	// Then we can tell the NZB Server to queue it

	$path = "$NzbDir";
	if($NewzbinUseCategories === true)
	{
		$path = "$NzbDir/$rcategory";
	}

	if(file_exists($path) == false)
	{
		if(mkdir($path, 0777) == false)
		{
			return "Could not create NZB Directory";
		}
		else
		{
			chmod($path, 0777);
		}
	}
	
	$nzbfilename = "$path/$rname.nzb";

	if(file_exists($nzbfilename))
	{
		return "NZB already exists, ignoring";
	}	
	if(file_exists("$nzbfilename.queued"))
	{
		return "NZB previously downloaded, ignoring";
	}	
	if(file_exists("$nzbfilename.error"))
	{
		return "NZB has an error";
	}	

	$fhandle = fopen($nzbfilename,"w");

	if(fwrite($fhandle,$content ) == false)
	{
		return "Could not save NZB";
	}

	fclose($fhandle);

	// Ensure File has full permissions
	chmod($nzbfilename, 0777);

	//  Finally return null indicating we've fetched the news report.
	return null;
}

// POST REQUEST
//
// This is yet another http request method (POST method)
// I didn't want to pollute the other request funtions
// 
function PostRequest($url, $referer, $_data) {
 
    // convert variables array to string:
    $data = array();    
    while(list($n,$v) = each($_data)){
        $data[] = "$n=$v";
    }    
    $data = implode('&', $data);
    // format --> test1=a&test2=b etc.
 
    // parse the given URL
    $url = parse_url($url);
    if ($url['scheme'] != 'http') { 
        die('Only HTTP request are supported !');
    }
 
    // extract host and path:
    $host = $url['host'];
    $path = $url['path'];
 
    // open a socket connection on port 80
    $fp = fsockopen($host, 80);
 
    // send the request headers:
    fputs($fp, "POST $path HTTP/1.1\r\n");
    fputs($fp, "Host: $host\r\n");
    fputs($fp, "Referer: $referer\r\n");
    fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
    fputs($fp, "Content-length: ". strlen($data) ."\r\n");
	if (function_exists('gzinflate')) {
		fputs($fp, "Accept-Encoding: gzip\r\n");
	}
    fputs($fp, "Connection: close\r\n\r\n");
    fputs($fp, $data);
 
    $result = ''; 
    while(!feof($fp)) {
        // receive the results of the request
        $result .= fgets($fp, 128);
    }
 
    // close the socket connection:
    fclose($fp);
 
    // split the result header from the content
    $result = explode("\r\n\r\n", $result, 2);
 
    $header = isset($result[0]) ? $result[0] : '';
    $content = isset($result[1]) ? $result[1] : '';
 
    // return as array:
    return array($header, $content);
}

?>