<?php
//Simple tool to grab VODS from Twitch.tv/Justin.tv and convert them to one 720p file using ffmpeg

//Config
$finishedDir = "./videos";
$downloadDir = "./downloads";
$debug = true;

$files = array();

function fetchFile($infile, $outfile) {
    $chunksize = 10 * (1024 * 1024); // 10 Megs

    $parts = parse_url($infile);
    $i_handle = fsockopen($parts['host'], 80, $errstr, $errcode, 5);
    $o_handle = fopen($outfile, 'wb');

    if ($i_handle == false || $o_handle == false) {
        return false;
    }

    if (!empty($parts['query'])) {
        $parts['path'] .= '?' . $parts['query'];
    }

	//Send request to the server
    $request = "GET {$parts['path']} HTTP/1.1\r\n";
    $request .= "Host: {$parts['host']}\r\n";
    $request .= "User-Agent: Mozilla/5.0\r\n";
    $request .= "Keep-Alive: 115\r\n";
    $request .= "Connection: keep-alive\r\n\r\n";
    fwrite($i_handle, $request);

	//Read headers from server
    $headers = array();
    while(!feof($i_handle)) {
        $line = fgets($i_handle);
        if ($line == "\r\n") break;
        $headers[] = $line;
    }

	//Check Content-Length header for file size
    $length = 0;
    foreach($headers as $header) {
        if (stripos($header, 'Content-Length:') === 0) {
            $length = (int)str_replace('Content-Length: ', '', $header);
            break;
        }
    }

    //Start reading remote file, and writing it locally. One chunk at a time
    $cnt = 0;
    while(!feof($i_handle)) {
        $buf = '';
        $buf = fread($i_handle, $chunksize);
        $bytes = fwrite($o_handle, $buf);
        if ($bytes == false) {
            return false;
        }
        $cnt += $bytes;

        //Once you reach the files size, we're done downloading
        if ($cnt >= $length) break;
    }

    fclose($i_handle);
    fclose($o_handle);
    return $cnt;
}

function download($url, $name){
	if(file_exists($name)){
		echo "$name already exists<br>";
	}else{
		fetchFile($url, $downloadDir.$name);
	}
}

function combine($files, $id){
	$str = '';
	foreach($files as $file){
		$str .= $file."|";
	}

	//Runs ffmpeg in the background and redirects all output to /dev/null
	exec("./ffmpeg y- -i \"concat:$str\" -c:v libx264 -b:v 1000k ./videos/$id.mp4 >/dev/null 2>/dev/null &", $out, $ret);
	
	if ($ret){
		echo "There was a problem!<br>";
		print_r($out);
	}else{
		echo "Everything went better than expected!<br>";
	}
	
	return $id;
}

//Make sure URL is actually from Twitch/Justin
function checkURLs($urls){
	foreach($urls as $url){
		$url = parse_url($url, PHP_URL_HOST)."<br>";
		$match = "/^store\d{0,3}\.media\d{0,3}\.justin.tv/";
		
		if(!preg_match($match, $url)){ return false; }
	}
	
	return true;
}

//Grab .flv files from Twitch/Justin
function fetchURLs($archive, $link = NULL){
	$videos = "http://api.justin.tv/api/broadcast/by_archive/";
	$xml = simplexml_load_file($videos.$archive.".xml");
	$links = array();
	
	for($x=0; $x < count($xml) - 6; $x++){
		array_push($links, $xml->archive[$x]->video_file_url);
	}
	return $links;
}

if(isset($_GET['id'])){
	$urls = fetchURLs($_GET['id']);
	if(checkURLs($urls)){
	
		foreach($urls as $part){
			if($debug){
				echo "$part<br>";
			}
			$file = basename($part);
			array_push($files, $file);
			if(!$debug){
				download($part, $file);
			}
		}
		
		if(file_exists($finishedDir.$_GET['id'].".mp4")){
			echo "File has already been converted.<br>";
		}else{
			echo "Converting<br>";
			if(!$debug){
				combine($files, $_GET['id']);
			}
		}
		
	}else{
		echo "Illegal URL(s)<br>";
	}
}

?>
