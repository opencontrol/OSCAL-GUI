<?php
header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
require_once('oscal-config.php');

// ============================================================================
// All the functions below generate server-sent events via the SendStatus
// function at the bottom, and are intended to be called by a background script.
//
// All messages generated by these functions are received and processed by 
// oscal-zones.js, which is intended to be running from the calling foreground
//  script.
//
// IMPORTANT: The server-side script must call ZoneClose(); at the end of 
// processing. Otherwise, the client browser will restart the script.
// ============================================================================

set_time_limit( 0 );
ini_set('auto_detect_line_endings', 1);
ini_set('max_execution_time', '0');
ini_set('session.auto_start', '0');
ob_end_clean();

$logging = "";
$dateformat = "F j, Y \@ H:i:s";
$starttime = time();
$zones = array('header-additional', 'zone-one', 'zone-one-left', 'zone-one-right', 'zone-two', 'zone-two-left', 'zone-two-right', 'zone-three', 'zone-three-left', 'zone-three-right');
Logging("STARTING " . basename(__FILE__) . ": " . date($dateformat, time()));
if (isset($_SESSION["DEBUG"]) && !empty($_SESSION["DEBUG"]) ) {
	Logging($_SESSION["DEBUG"]);
	unset($_SESSION["DEBUG"]);
}

// ===================================================
// For all functions starting with Zone____:
// $content (or $style) is required
// $zone is optional. It identifies the zone to which the content should be
//     sent. If omitted, it defaults to 'zone-two'.
// ===================================================
// Replace the content in the specified zone with the content passed
function ClearAllZones () {
global $zones;

	foreach($zones as $zone) {
//		Logging("CLEARING: " . $zone);
		SendStatus( array( 'code' => 'content-replace', 'content' => "&nbsp;", 'zone' => $zone));
	}
}

// ===================================================
// Sends commands to the Zone Handler Javascript 
function ZoneCommand ($command) {

	SendStatus( array( 'code' => 'command', 'command' => $command));
}

// ===================================================
// Replace the content in the specified zone with the content passed
function ZoneAdjust ($style, $zone = 'zone-two') {

	SendStatus( array('code' => 'adjust',  'adjust' => $style, 'zone' => $zone));
}

// ===================================================
// Replace the content in the specified zone with the content passed
function ZoneOutput ($content, $zone = 'zone-two') {

	SendStatus( array( 'code' => 'content-replace', 'content' => $content, 'zone' => $zone));
}

// ===================================================
// Appends the content passed to any existing content in the zone
function ZoneOutputAppend ($content, $zone = 'zone-two') {

	SendStatus( array( 'code' => 'content-append', 'content' => $content, 'zone' => $zone));
}

// ===================================================
// Converts markup-sensitive characters with escape characters, which enables
// enables content such as '<tag>' to be displayed instead of interpreted, and
// replaces the content in the specified zone.
function ZoneOutputEscaped ($content, $zone = 'zone-two') {

	SendStatus( array( 'code' => 'content-replace', 'content' => UseEscapeCodes($content), 'zone' => $zone));
}

// ===================================================
// Converts markup-sensitive characters with escape characters, which enables
// enables content such as '<tag>' to be displayed instead of interpreted, and
// appends it to the content in the specified zone.
function ZoneOutputEscapedAppend ($content, $zone = 'zone-two') {

	SendStatus( array( 'code' => 'content-append', 'content' => UseEscapeCodes($content), 'zone' => $zone));
}

// ===================================================
// Converts markup-sensitive characters with escape characters, which enables
// enables content such as '<tag>' to be displayed instead of interpreted, and
// PREpends it to the content in the specified zone.
function ZoneOutputEscapedPrepend ($content, $zone = 'zone-two') {

	SendStatus( array( 'code' => 'content-prepend', 'content' => UseEscapeCodes($content), 'zone' => $zone));
}

// ===================================================
// if SHOW_DEBUG is true, content is sent to the 'debug' zone 
// if SHOW_DEBUG is false, content is discarded 
// Optionally, if $escaped is true, the markup-sensitive characters
//    are escaped, which enables content such as '<tag>' to be 
//    displayed instead of interpreted. Default is false.
function Logging ($content, $escaped=false, $alert=false) {
global $logging;

	if (SHOW_DEBUG) {
		
		if ($escaped) {
			$content = UseEscapeCodes($content);
		}
		if ($alert) {
			$content = "<span style='color: red;'>!! " . $content . "</span>";
		} else {
			$content = '-- ' . $content;
		}
		$logging .= $content . "<br />";
//		SendStatus( array('code' => 'content-append', 'content' => $content . "<br />", 'zone' => 'debug'));
	} else {
		// do nothing (Ignore)
	}
}

// ===================================================
// 
//function DisplayError ($content) {
function ErrorMessage ($content, $escaped=true) {
	Logging($content, $escaped, true);
	SendStatus( array( 'code' => 'content-prepend', 'content' => $content . "<br />", 'zone' => 'zone-one'));
}

// ===================================================
// 
function NewFunction ($url) {

	SendStatus( array( 'code' => 'url', 'url' => $url));
}

// ===================================================
// If $zone is a specific zone name, clears the style and content
//    for that zone.
// If $zone = "all", loop through each zone name and recursively
//    call this function with individual zone names. In essence,
//    this clears every zone - one at a time.
function ZoneClear ($zone) {
	global $zones;
	if ($zone == 'all') {
		foreach ($zones as $item) {
			ZoneClear($item);
		}
	} else {
		ZoneAdjust("", $zone);
		ZoneOutput("", $zone);
	}
}


// ===================================================
// This must always be called at the end of a server-side script
// to close the connection. Otherwise, the browser will restart
// the script, reating a continuous loop.
function ZoneClose () {
	global $starttime, $dateformat, $logging;
	$endtime = time();
	Logging("ENDING: " . date($dateformat, time()));
	Logging("RUN TIME: " . ($endtime - $starttime)  . " seconds.");
	SendStatus( array('code' => 'content-append', 'content' => $logging, 'zone' => 'debug'));
	SendStatus( array( 'code' => 'end'));
}

// ============================================================================
// Helper functions
// ============================================================================
function HandleException($exception, $message) {
	
Logging($message ); // . "<br>RAW XML:<br>" . $exception->saveXML());
}

// ====================================================================
function UseEscapeCodes($dirtystring) {

$badstuff  = Array(    '&', '<',    '>',    chr(22),  chr(27) );
$goodstuff = Array('&amp;', '&lt;', '&gt;', '&quot;', '&apos;' );

$cleanstring = str_replace($badstuff, $goodstuff, $dirtystring );
$cleanstring = str_replace(chr(10), '<br />', $cleanstring);

return $cleanstring;
}

// ===================================================
function Convert2Binary ($item) {

$out1 = "";
$out2 = "";
$itemlen = strlen($item);

for ($i = 0; $i < $itemlen; $i++) {
	if (ord($item[$i]) > 127) {
		$out1 = $out1 . 'x' . str_pad(dechex(ord($item[$i])), 2, '0', STR_PAD_LEFT);
	} else {
		$out1 = $out1 . str_pad($item[$i], 3, ' ', STR_PAD_LEFT);
		
	} ;
    $out2 = $out2 . '-' . str_pad(dechex(ord($item[$i])), 2, '0', STR_PAD_LEFT);
}	
	Logging("BINARY DUMP: ", 'debug');
	Logging($out1);
	Logging($out2);
}

// ============================================================================
// Sends JSON-encoded messages to the client script running oscal-zones.js
// This is called only by the above functions. 
// Calling it directly will likely result in the data
// being ignored.
// ============================================================================
function SendStatus($message) {
	
	echo ("data: " . json_encode($message) . "\n\n");
	@ob_flush;
	flush();	
	// NOTE: Both ob_flush and flush are required.
}

?>
