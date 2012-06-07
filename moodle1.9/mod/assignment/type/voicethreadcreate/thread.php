<?php

// VoiceThread Creation Assignment
// Copyright 2010 VoiceThread, LLC
// Author: Simon Karpen, skarpen@voicethread.com
//

// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //


require("../../../../config.php");
require("../../lib.php");

$tid     = required_param('tid', PARAM_INT);      // Course Module ID

$header = 'Viewing Thread ' . $tid;

print_header($header);

global $CFG;
$vtsite = trim($CFG->voicethread_site);
$default_google_analytics_code = '<script src="http://www.google-analytics.com/urchin.js" type="text/javascript">
		</script>
		<script type="text/javascript">
		_uacct = "UA-177384-8";
		urchinTracker();
		</script>';
$ed_google_analytics_code = '<script src="http://www.google-analytics.com/urchin.js" type="text/javascript">
		</script>
		<script type="text/javascript">
		_uacct = "UA-177384-11";
		urchinTracker();
		</script>';
if (stristr($vtsite, 'ed.voicethread.com') !== FALSE) $default_google_analytics_code = $ed_google_analytics_code;
$width = 640;
$height = $width * .75;

$embed = '<div id="voicethread-container">';
$embed .= '<object width="' . $width .'" height="' . $height . '"> ';
$embed .= '<param name="movie" value="http://'.$vtsite.'/book.swf?b=' . $tid .'"></param> ';
$embed .= '<param name="wmode" value="transparent"></param>';
$embed .= '<embed src="http://'.$vtsite.'/book.swf?b=' . $tid . '" type="application/x-shockwave-flash" wmode="transparent" width="' . $width . '" height="' . $height . '"></embed>';
$embed .= '</object></div>';

$embed .= $default_google_analytics_code;
print $embed; 

print_footer('none');
?>
