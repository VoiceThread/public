<?php

/**********
 *
 * Copyright (c) 2010-2012, VoiceThread.com
 * All rights reserved.

 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the 
 * distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR 
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND 
 * FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR 
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL 
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, 
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, 
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *
 * Helper for the VoiceThread "Create a VoiceThread" assignment
 *
 *************/

global $PAGE, $CFG, $OUTPUT;
require("../../../../config.php");
require("../../lib.php");

$tid = required_param('tid', PARAM_INT);      // Course Module ID
$PAGE->set_url('/mod/assignment/type/voicethreadcreate/thread.php?tid='.$tid);
$PAGE->set_pagelayout('base');
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
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$header = 'Viewing Thread '.$tid;
echo $OUTPUT->header($header);
$vtsite = trim($CFG->voicethread_site);
if (stristr($vtsite, 'ed.voicethread.com') !== FALSE) $default_google_analytics_code = $ed_google_analytics_code;
$width = 640;
$height = $width * .75;
$embed = '<div id="voicethread-container" style="text-align:center;">';
$embed .= '<object width="'.$width.'" height="'.$height.'"> ';
$embed .= '<param name="movie" value="http://'.$vtsite.'/book.swf?b='.$tid.'"></param> ';
$embed .= '<param name="wmode" value="transparent"></param>';
$embed .= '<embed src="http://'.$vtsite.'/book.swf?b='.$tid.'" type="application/x-shockwave-flash" wmode="transparent" width="'.$width.'" height="'.$height.'"></embed>';
$embed .= '</object></div>';
$embed .= $default_google_analytics_code;
print $embed; 
echo $OUTPUT->footer('none');
?>
