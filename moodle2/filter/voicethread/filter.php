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
 * This filter allows easy embedding of VoiceThreads to the Moodle site using shortcodes
 *
 *************/

if (! class_exists('VTAPI', FALSE)) require_once("VTAPI.php");

class filter_voicethread extends moodle_text_filter {
  function filter($text, array $options = array()){
    global $CFG, $USER;
    $newtext = $text;
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
    $u = empty($CFG->unicodedb) ? '' : 'u'; //Unicode modifier

    if (!isset($CFG->voicethread_site)) {
      set_config( 'voicethread_site','voicethread.com' );
    } 
    
    $voicethread_site = trim($CFG->voicethread_site);
    if (stristr($voicethread_site, 'ed.voicethread.com') !== FALSE) $default_google_analytics_code = $ed_google_analytics_code;
    $voicethread_site = preg_replace('/http:\/\//','',$voicethread_site);
    $voicethread_site = preg_replace('/\/$/','',$voicethread_site);

    preg_match_all('/\[\[vt:(.*?)(\|(.*?))?\]\]/s'.$u, $newtext, $list_of_movies);
    preg_match_all('/\[\[vtsmall:(.*?)(\|(.*?))?\]\]/s'.$u, $newtext, $list_of_small_movies);

    /// No Voicethread links found. Return original text
    if (empty($list_of_movies[0]) && empty($list_of_small_movies[0])) {
      return $newtext;
    }

    // see if we can get user information
    // only make API calls if we're doing work, otherwise possible performance issue
    $vtapikey = trim($CFG->voicethread_orgapikey);
    VTAPI::setCommonParams(array('orgAPIKey' => $vtapikey));
    $vtapilinked = 0; $vtapierror = 0;
    try {
      $vtuser = VTAPI::call('user.get', array('email' => $USER->email));
      $vtuid = $vtuser['id'];
      $vtapilinked = 1;
    }
    catch (Exception $e) {
      $vtapierror = 1;
    }
    foreach ($list_of_movies[0] as $key=>$item) {
      $replace = ''; $headertext = '';
      // Extract info from the Voicethread link
      $movie = new stdClass;
      $movie->reference = $list_of_movies[1][$key];

      // Get the title from VT
      if($vtapilinked) {
        try {
          $vthread = VTAPI::call('thread.get', array('id' => $movie->reference));
          $movie->title = $vthread['title'];
          $headertext .= '<br /><span class="filtervoicethread-title">'.format_string($movie->title).'</span>';
        }
        catch (Exception $e) {
          $headertext .= '';
        }
      }
    
      // Calculate the replacement
      $replace = '<div id="voicethread-container">'.$headertext.'<br>'.
                 '<object width="800" height="600"> '.
                 '<param name="movie" value="http://'.$voicethread_site.'/book.swf?b='.$movie->reference.'"></param> '.
                 '<param name="wmode" value="transparent"></param>'.
                 '<embed src="http://'.$voicethread_site.'/book.swf?b='.$movie->reference.'" type="application/x-shockwave-flash" wmode="transparent" width="800" height="600"></embed>'.
                 '</object></div>';
      $replace .= $default_google_analytics_code;	   
      // If replace found, do it
      if ($replace) {
        $newtext = str_replace($list_of_movies[0][$key], $replace, $newtext);
      }
    }
    foreach ($list_of_small_movies[0] as $key=>$item) {
      $replace = ''; $headertext = '';
      // Extract info from the VoiceThread link
      $movie = new stdClass;
      $movie->reference = $list_of_small_movies[1][$key];

      // Get the title from VT
      if($vtapilinked) {
        try {
          $vthread = VTAPI::call('thread.get', array('id' => $movie->reference));
          $movie->title = $vthread['title'];
          $headertext .= '<br /><span class="filtervoicethread-title">'.format_string($movie->title).'</span>';
        }
        catch (Exception $e) {
          $headertext .= '';
        }
      }

      // Calculate the replacement
      $replace = '<div id="voicethread-container">'.$headertext.'<br>'.
                 '<object width="480" height="360"> '.
                 '<param name="movie" value="http://'.$voicethread_site.'/book.swf?b='.$movie->reference.'"></param> '.
                 '<param name="wmode" value="transparent"></param>'.
                 '<embed src="http://'.$voicethread_site.'/book.swf?b='.$movie->reference.'" type="application/x-shockwave-flash" wmode="transparent" width="480" height="360"></embed>'.
                 '</object></div>';
             
      $replace .= $default_google_analytics_code;
      // If replace found, do it
      if ($replace) {
        $newtext = str_replace($list_of_small_movies[0][$key], $replace, $newtext);
      }
    }
    // Finally, return the text
    return $newtext;
  }
}
?>
