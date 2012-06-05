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
 * This block allows authentication to VoiceThread, using Moodle user information.
 *
 *************/


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
      $this->content = new stdClass;
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
      $vtjscode = '<script type="text/javascript">';
      $vtjscode .= 'function openvtpopup(url, name, options) {';
      $vtjscode .= 'var fullurl = url;';
      $vtjscode .= 'var windowobj = window.open(fullurl,name,options);';
      $vtjscode .= 'if (!windowobj) { return true; }';
      $vtjscode .= 'windowobj.focus();';
      $vtjscode .= 'return false;';
      $vtjscode .= '}</script>';

      

      $this->content->text = $vtjscode;
      if (! $voicethread_authstring)
        $this->content->text .= $voicethread_error_text;
      else {
        $this->content->text .= '<a href="'.$voicethread_authstring.'"';
        $this->content->text .= "onclick=\"this.target='VoiceThread'; ";
        $this->content->text .= "return openvtpopup('".$voicethread_authstring;
        $this->content->text .= "', 'VoiceThread', 'menubar=0,location=0,scrollbars,resizable,width=980,height=700', 0);\"";
        $this->content->text .= '><div style="text-align: center; line-height: 40px; padding: 0px;">
                                <p style="color:black; padding: 10px 1px 0px 1px; font-size:120%;"><b>Go To VoiceThread!</b></p>
                                </div>
                                <div style="background-color: #000; text-align: center; padding: 0px;">
                                <p style="position:relative; bottom:0px; padding: 0px; margin: 0px; height: 27px;">
                                <img src="http://voicethread.com/media/custom/moodle/bar.png" alt="VoiceThread" border="0"/>
                                </p>
                                </div></a>';
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
    return false;
  }
}
?>
