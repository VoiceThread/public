<?php 

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.com                                            //
//                                                                       //
// Copyright (C) 2001-3001 Martin Dougiamas        http://dougiamas.com  //
//           (C) 2001-3001 Eloy Lafuente (stronk7) http://contiento.com  //
//           (C) 2009 Simon Karpen               http://voicethread.com  //
//                                                                       //
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
//                                                                       //
///////////////////////////////////////////////////////////////////////////

// This filter allows you to embed VoiceThread content within Moodle. 
// See the README.txt or the instructions at http://voicethread.com/FIXME
// for more information

if (! class_exists('VTAPI', FALSE)) require_once("VTAPI.php");

function voicethread_filter($courseid, $text) {

	global $CFG, $USER;

	$u = empty($CFG->unicodedb) ? '' : 'u'; //Unicode modifier
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
	if (!isset($CFG->voicethread_site)) {
		set_config( 'voicethread_site','voicethread.com' );
	} 
	$voicethread_site = trim($CFG->voicethread_site);
	if (stristr($voicethread_site, 'ed.voicethread.com') !== FALSE) $default_google_analytics_code = $ed_google_analytics_code;
	$voicethread_site = preg_replace('/http:\/\//','',$voicethread_site);
	$voicethread_site = preg_replace('/\/$/','',$voicethread_site);

	preg_match_all('/\[\[vt:(.*?)(\|(.*?))?\]\]/s'.$u, $text, $list_of_movies);
	preg_match_all('/\[\[vtsmall:(.*?)(\|(.*?))?\]\]/s'.$u, $text, $list_of_small_movies);

	/// No Voicethread links found. Return original text
	if (empty($list_of_movies[0]) && empty($list_of_small_movies[0])) {
		return $text;
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
        	$replace = ''; $headertext = ''; $footertext = '';
    		/// Extract info from the Voicethread link
        	$movie = new stdClass;
        	$movie->reference = $list_of_movies[1][$key];

		// Get the title from VT, calculate footer text
		if($vtapilinked) {
			try {
				$vthread = VTAPI::call('thread.get', array('id' => $movie->reference));
				$movie->title = $vthread['title'];
				$headertext .= '<br /><span class="filtervoicethread-title">'.format_string($movie->title).'</span>';
			}
			catch (Exception $e) {
				$headertext .= '';
			}

			try {
//				$vtunheard = VTAPI::call('comment.getList',array('threadId' => $movie->reference, 'newForUserId' => $vtuid));
//				$vcommtotal = VTAPI::call('comment.getList',array('threadId' => $movie->reference ));
//				$numvtunheard = count($vtunheard);
//				$numvtotal = count($vcommtotal);
//				$footertext .= '<br>Uid ' . $vtuid . ' thread ' . $movie->reference;
//				$footertext .= '<br>Comments: ' . $numvtunheard . ' new / ' . $numvtotal . ' total';
				$footertext .= '';
			}
			catch (Exception $e) {
				$footertext .= '';
			}
		}
	
	    	/// Calculate the replacement
        	$replace = '<div id="voicethread-container">'.
		   $headertext . '<br>' .
                   '<object width="800" height="600"> '.
                   '<param name="movie" value="http://'.$voicethread_site.'/book.swf?b='.$movie->reference.'"></param> '.
                   '<param name="wmode" value="transparent"></param>'.
                   '<embed src="http://'.$voicethread_site.'/book.swf?b='.$movie->reference.'" type="application/x-shockwave-flash" wmode="transparent" width="800" height="600"></embed>'.
                   '</object>'.$footertext.'</div>';
				   
			$replace .= $default_google_analytics_code;	
    		/// If replace found, do it
        	if ($replace) {
            		$text = str_replace($list_of_movies[0][$key], $replace, $text);
        	}
    	}

	foreach ($list_of_small_movies[0] as $key=>$item) {
      		$replace = ''; $headertext = ''; $footertext = '';
    		/// Extract info from the VoiceThread link
        	$movie = new stdClass;
        	$movie->reference = $list_of_small_movies[1][$key];

                // Get the title from VT, calculate footer text
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

    		/// Calculate the replacement
	        $replace = '<div id="voicethread-container">'.
		   $headertext . '<br>' .
                   '<object width="480" height="360"> '.
                   '<param name="movie" value="http://'.$voicethread_site.'/book.swf?b='.$movie->reference.'"></param> '.
                   '<param name="wmode" value="transparent"></param>'.
                   '<embed src="http://'.$voicethread_site.'/book.swf?b='.$movie->reference.'" type="application/x-shockwave-flash" wmode="transparent" width="480" height="360"></embed>'.
                   '</object>'.$footertext.'</div>';
				   
			$replace .= $default_google_analytics_code;	
    		/// If replace found, do it
        	if ($replace) {
            		$text = str_replace($list_of_small_movies[0][$key], $replace, $text);
        	}
    	}

	/// Finally, return the text
    	return $text;
}
?>
