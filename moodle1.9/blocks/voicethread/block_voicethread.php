<?php

// VoiceThread Authentication
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


require_once('class.authentication.php');

class block_voicethread extends block_base {

  function init() {
    $this->title = 'VoiceThread';
    $this->version = "2010041800";
  }

  function get_content() {
    global $CFG, $USER, $SESSION;	
    $nowtime = time();
    $regenerate = 0;
  
    if (!isset($SESSION->voicethread_lastkeyed)) {
      $SESSION->voicethread_lastkeyed = $nowtime;
      $regenerate = 1;
    }
    if (($nowtime - $SESSION->voicethread_lastkeyed) > 300) {
      $SESSION->voicethread_lastkeyed = $nowtime;
      $regenerate = 1;
    }

    if ($regenerate || (!isset($SESSION->voicethread_content))) {

      if((!isset($USER->firstname)) || (!isset($USER->lastname)) || (!isset($USER->email)) || (!isset($USER->username))) {
        $this->content = new stdClass;
        $this->content->text .= "ERROR: VoiceThread Auth requires a First name, Last name, E-mail and Username";
        $this->content->footer = '';
        return $this->content;
      }
      
      $auth = new VoiceThreadAuthModule();

      $block_voicethread_site = trim($CFG->voicethread_site);
      $block_voicethread_site = preg_replace('/http:\/\//','',$block_voicethread_site);
      $block_voicethread_site = preg_replace('/\/$/','',$block_voicethread_site);

      $auth->setOrgKey(trim($CFG->voicethread_orgid));
      $auth->setPrivateKey(trim($CFG->voicethread_authkey));
      $auth->setServer($block_voicethread_site);
      $auth->setCustomError('Please contact your helpdesk for assistance');
      $as = new VTAuthSchema();
      $as->setDataProperty(array('first_name' => $USER->firstname,
               'last_name' => $USER->lastname,
               'email' => $USER->email,
               'department' => $USER->department,
               'institution' => $USER->institution,
               'lang' => $USER->lang,
               'authIdentifier' => $USER->username));

      $auth->authenticate($as,true);

      $voicethread_authstring = $auth->signIn();
       
      $voicethread_error_text = "VoiceThread Auth Token Unavailable. Please contact VoiceThread support.";

      $css_href = '/blocks/voicethread/styles.css';
      $this->content->text .= '<link type="text/css" href="'.$css_href.'" />';
      $vtjscode .= '<script type="text/javascript">';
      $vtjscode .= 'function openvtpopup(url, name, options) {';
      $vtjscode .= 'var fullurl = url;';
      $vtjscode .= 'var windowobj = window.open(fullurl,name,options);';
      $vtjscode .= 'if (!windowobj) { return true; }';
      $vtjscode .= 'windowobj.focus();';
      $vtjscode .= 'return false;';
      $vtjscode .= '}</script>';

      $this->content = new stdClass;

      $this->content->text = $vtjscode;
      if (! $voicethread_authstring)
        $this->content->text .= $voicethread_error_text;
      else {
        $this->content->text .= '<a href="'.$voicethread_authstring.'"';
        $this->content->text .= "onclick=\"this.target='VoiceThread'; ";
        $this->content->text .= "return openvtpopup('".$voicethread_authstring;
        $this->content->text .= "', 'VoiceThread', 'menubar=0,location=0,scrollbars,resizable,width=980,height=700', 0);\"";
        $this->content->text .= '><img src="http://voicethread.com/media/custom/moodle/vt_moodle_auth_logo.png" alt="Go To VoiceThread!" width="100%" border="0" /></a>';
      }
      $this->content->footer = '';
      $SESSION->voicethread_content = $this->content;
      return $this->content;
    } else {
      $this->content = $SESSION->voicethread_content;
      return $this->content;
    }
  }

  function instance_allow_config() {
    return false;
  }

  function has_config() {
    return true;
  }
  
  function preferred_width() {
    return 180;
  }
  
  function hide_header() {
    return true;
  }
}
?>
