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
 * This block allows course synchronization to VoiceThread, using Moodle courses.
 *
 *************/

require_once('class.visi.php');
class block_voicethread_roster extends block_base {
  public $block_messages = '';
  public $success = FALSE;
  
  function init() {
    $this->title = 'VoiceThread Roster';
    $this->version = "2011122300";
  }
  public function specialization() {
    global $CFG, $DB;
    if (isset($_GET['runVTsync']) && ($_GET['runVTsync'] == 'yes')) {
      $course_array = array();
      $course_name_array = array();
      $courses = $DB->get_records('course', array());
      foreach($courses as $course) {
        $course_shortname = str_replace(' ', '_', $course->shortname);
        $config_name = get_class($this).'_courses';
        $config_array = explode(',', $CFG->$config_name);
        if (isset($CFG->$config_name) && is_array($config_array) && (in_array($course->id, $config_array))) {
          $course_array[$course->id] = $course->shortname;
          $course_name_array[$course->id] = $course->fullname;
        }
      }
      $filename = $this->makeCSVFile($course_array, $course_name_array);
      $visi = new VISICSVUploader();
      $block_voicethread_site = trim($CFG->voicethread_site);
      $block_voicethread_site = preg_replace('/http:\/\//','',$block_voicethread_site);
      $block_voicethread_site = preg_replace('/\/$/','',$block_voicethread_site);
      $visi->setEnvironmentIdentifier(trim($CFG->voicethread_orgid));
      $visi->setAuthKey(trim($CFG->voicethread_authkey));
      $visi->setServer($block_voicethread_site);
      if ($filename === FALSE) {
        $this->block_messages .= 'There was an issue writing the course file. Please ensure there are courses to sync and the file can be written to the /tmp directory.';
      }
      else {
        $visi->setFilenames($filename);
      }
      $visi->sendData();
      $messages = $visi->getHTTPCodeMessages();
      if (! empty($messages)) {
        foreach($messages as $key => $message) {
          $this->block_messages .= $message;
          if ($key == 200 || $key == 202)
            $this->success = TRUE;
        }
      }
    }
  }
  
  function get_content() {
    global $CFG, $DB, $PAGE;
    require_once("$CFG->dirroot/course/lib.php");
    $status = 'Sync My Roster with VoiceThread';
    if (! empty($this->block_messages)) {
      if ($this->success == TRUE)
        $status = '<div class="greenBorder roundedCorners" style="text-align: center; color: black; font-size: 12px;">Successfully synced Moodle courses with VoiceThread!</div>';
      else
        $status = '<div class="redBorder roundedCorners" style="text-align: center; color: black; font-size: 12px;">'.$this->block_messages.' Please try again after making the necessary changes.</div>';
      $this->block_messages = '';
      $this->success = FALSE;
    }
    $this->content = new stdClass;
    $this->content->text = '<script type="text/javascript">
                            function showGear(ref) {
                              document.getElementById(\'waiting_gear\').style.visibility="visible";
                              if (ref.style) {
                                ref.style.display = "none";
                                var x = document.getElementById(\'wait_message\');
                                x.style.display = "block";
                              }
                            }
                            </script>'; 
    $css_href = '/blocks/voicethread_roster/styles.css';
    $image_href = $CFG->wwwroot.'/blocks/voicethread_roster/default_gear.gif';
    $custom_padding = '10px';
    if ($status != 'Sync My Roster with VoiceThread')
      $custom_padding = '0px';
    $this->content->text .= '<link type="text/css" href="'.$css_href.'" />';
    $this->content->text .= '<div id="wait_message" style="text-align: center; display: none;"><p style="color:black; padding: 10px 5px 0px 5px; font-size:110%;">
                            <b>Please wait, synchronizing courses...</b>
                            </p></div>';
    $this->content->text .= '<a style="font-size: 12px;" onclick="showGear(this)" href="'.$this->add_querystring_var($PAGE->url->out(), 'runVTsync', 'yes').'">
                            <div style="text-align: center;"><p style="color:black; padding:'.$custom_padding.' 5px 0px 5px; font-size:110%;">
                            <b>'.$status.'</b>
                            </p>';
    $this->content->text .= '</div>';
    $this->content->text .= '<div style="background-color: #000; text-align: center; padding: 0px;">
                            <p style="position:relative; bottom:0px; padding: 0px; margin: 0px; height: 27px;">
                            <img src="http://voicethread.com/media/custom/moodle/bar.png" alt="VoiceThread"  border="0"/>
                            </p>
                            </div>
                            </a>';
    $this->content->text .= '<div style="position:fixed; z-index:3; top:50%; left:50%;"><img id="waiting_gear" src="'.$image_href.'" style="visibility:hidden;" /></div>';
    
    $this->content->footer = '';
    return $this->content;
  }

  function instance_allow_config() {
    return false;
  }
  
  function instance_allow_multiple() {
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
  
  function get_group_members($course_id) {
    global $CFG, $DB, $COURSE;
    $user_ids = array();
    require_once("$CFG->dirroot/group/lib.php");
    $group_array = groups_get_potential_members($course_id);
    return ($group_array);
  }
  
  function makeCSVFile($courses, $course_names) {
    global $CFG;
    if (empty($courses) || empty($course_names))
      return (FALSE);
    require_once("$CFG->dirroot/user/lib.php");
    $block_voicethread_site = trim($CFG->voicethread_site);
    $block_voicethread_site = preg_replace('/http:\/\//','',$block_voicethread_site);
    $block_voicethread_site = preg_replace('/\/$/','',$block_voicethread_site);
    $domain_array = explode('.voicethread.com', $block_voicethread_site);
    $domain = $domain_array[0];
    if (! strlen($domain) > 0)
      return (FALSE);
    $filename = sys_get_temp_dir();
    $filename .= '/Moodle_courses_for_'.$domain.'.csv';
    $handle = @fopen($filename, 'w');
    if (!$handle)
      return (FALSE);
    $put_header = array('username', 'email', 'firstname', 'lastname', 'course_id', 'course_name');
    @fputcsv($handle, $put_header);
    foreach($courses as $id => $course) {
      $members = $this->get_group_members($id);
      if (! empty($members)) {
        $ids = array_keys($members);
        $users = user_get_users_by_id($ids);
        foreach($members as $member) {
          $user = $users[$member->id];
          $email = (isset($user->email)) ? $user->email : '';
          $firstname = (isset($user->firstname)) ? $user->firstname : '';
          $lastname = (isset($user->lastname)) ? $user->lastname : '';
          $put_csv = array($member->username, $email, $firstname, $lastname, $course, $course_names[$id]);
          @fputcsv($handle, $put_csv);
        }
      }
    }
    fclose($handle);
    return ($filename);
  }
  
  function add_querystring_var($url, $key, $value) {
    $url = preg_replace('/(.*)(\?|&)' . $key . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&');
    $url = substr($url, 0, -1);
    if (strpos($url, '?') === false) {
        return ($url . '?' . $key . '=' . $value);
    } else {
        return ($url . '&' . $key . '=' . $value);
    }
  }
}
?>
