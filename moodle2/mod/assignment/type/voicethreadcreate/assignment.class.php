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
 * This assignment type allows teachers within Moodle to assign students to submit a VoiceThread to be graded.
 *
 *************/


require_once("$CFG->libdir/formslib.php");
if (! class_exists('VTAPI', FALSE)) require_once("VTAPI.php");

class assignment_voicethreadcreate extends assignment_base {
  function assignment_voicethreadcreate($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
    parent::assignment_base($cmid,$assignment,$cm,$course);
    $this->type = 'voicethreadcreate';
  }

  function view() {
    global $USER, $CFG, $OUTPUT;
    $edit = optional_param('edit', 0, PARAM_BOOL);
    $saved = optional_param('saved', 0, PARAM_BOOL);
    $context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
    require_capability('mod/assignment:view', $context);
    $submission = $this->get_submission();
    if(!has_capability('mod/assignment:submit', $context)) {
      $editable = null;
    } 
    else {
      $editable = $this->isopen() && (!$submission || $this->assignment->resubmit || !$submission->timemarked);
    }
    $editmode = ($editable and $edit);
    if($editmode) {
      if (!has_capability('mod/assignment:submit',$context)) {
        print_error('guestnosubmit','assignment');
      }
    }
    add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);
    $mform = new voicethreadcreate_form();
    $defaults = new object();
    $defaults->id = $this->cm->id;
    if (!empty($submission)) {
      $defaults->var1 = $submission->data1;
    }
    $mform->set_data($defaults);
    if($mform->is_cancelled()) {
      redirect('view.php?id='.$this->cm->id);
    }
    if($data = $mform->get_data()) {
      if($editable && $this->update_submission($data)) {
        $submission = $this->get_submission();
        add_to_log($this->course->id, 'assignment', 'upload', 'view.php?a=' . $this->assignment->id, $this->assignment->id,$this->cm->id);
        $this->email_teachers($submission);
        redirect('view.php?id=' . $this->cm->id . '&saved=1');
      } 
      else {
        notify(get_string("error"));
      }
    }
    if($editmode) {
      $this->view_header(get_string('editmysubmission', 'assignment'));
    } 
    else {
      $this->view_header();
    }
    $this->view_intro();
    if($saved) {
      notify(get_string('submissionsaved','assignment'), 'notifysuccess');
    }
    if(has_capability('mod/assignment:submit', $context)) {
      if($editmode) {
        echo $OUTPUT->box_start('generalbox', 'voicethreadcreate');
        $mform->display();
      } 
      else {
        echo $OUTPUT->box_start('generlabox boxwidthwide boxaligncenter', 'voicethreadcreate');
        if($submission) {
          $vtembed = $this->threadid_to_embed($submission->data1,640);
          echo $vtembed;
        } 
        elseif (!has_capability('mod/assignment:submit', $context)) {
          echo '<div style="text-align:center">' . get_string('guestnosubmit', 'assignment').'</div>';
        } 
        elseif ($this->isopen()) {
          echo '<div style="text-align:center">' . get_string('emptysubmission', 'assignment').'</div>';
        }
      }
      echo $OUTPUT->box_end();
      if(!$editmode && $editable) {
        echo "<div style='text-align:center'>";
        echo $OUTPUT->single_button('view.php?id='.$_GET['id'].'&edit=1', 'Edit my submission', get_string('editmysubmission', 'assignment'), array('id'=>$this->cm->id, 'edit'=>'1'));
        echo "</div>";
      }
    }
    $this->view_dates();
    $this->view_feedback(); $this->view_footer();
  }

  function update_submission($data) {
    global $CFG, $USER, $DB;
    $submission = $this->get_submission($USER->id, true);
    $update = new object();
    $update->id = $submission->id;
    $update->data1 = $data->var1;
    $update->timemodified = time();
    if(!$DB->update_record('assignment_submissions', $update)) {
      return false;
    }
    $submission = $this->get_submission($USER->id);
    $this->update_grade($submission);
    return true;
  }
  
  function setup_elements(&$mform) {
    global $CFG;

    $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

    $mform->addElement('select', 'resubmit', get_string('allowresubmit', 'assignment'), $ynoptions);
    $mform->addHelpButton('resubmit', 'allowresubmit', 'assignment');
    $mform->setDefault('resubmit', 0);

    $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'assignment'), $ynoptions);
    $mform->addHelpButton('emailteachers', 'emailteachers', 'assignment');
    $mform->setDefault('emailteachers', 0);
  }
  
  function threadid_to_embed($tid,$width) {
    global $CFG;
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
    $vtsite = trim($CFG->voicethread_site);
    if (stristr($vtsite, 'ed.voicethread.com') !== FALSE) $default_google_analytics_code = $ed_google_analytics_code;
    $height = $width * .75;
    $embed = '<div id="voicethread-container">';
    $embed .= '<object width="'.$width.'" height="'.$height.'"> ';
    $embed .= '<param name="movie" value="http://'.$vtsite.'/book.swf?b=' . $tid .'"></param> ';
    $embed .= '<param name="wmode" value="transparent"></param>';
    $embed .= '<embed src="http://'.$vtsite.'/book.swf?b='.$tid.'" type="application/x-shockwave-flash" wmode="transparent" width="'.$width.'" height="'.$height.'"></embed>';
    $embed .= '</object></div>';
    $embed .= $default_google_analytics_code;
    return $embed;
  }

  function print_student_answer($userid, $return=false) {
    global $CFG, $OUTPUT;
    if (!$submission = $this->get_submission($userid)) {
      return "";
    }
    if(isset($CFG->voicethread_site)) {
      $vtsite = trim($CFG->voicethread_site);
    } 
    else {
      $vtsite = 'voicethread.com';
    }
    $threadid = $submission->data1; 
    $width = 700; 
    $height = $width*0.75;
    # get thread metadata from VoiceThread
    $vtorgapikey = trim($CFG->voicethread_orgapikey);
    VTAPI::setCommonParams(array('orgAPIKey' => $vtorgapikey));
    $vtapilinked = 0; $vtapierror = 0;
    $title = 'View Thread';
    try {
      $vthread = VTAPI::call('thread.get', array(
                    'id' => $threadid
                  ));
      $vtapilinked = 1;
      if (strlen($vthread['title']) > 0)
        $title = $vthread['title'];
    }
    catch (Exception $e) {
      $vtapierror = 1;
    }
    if($vtapilinked) {
      $prettydate = userdate($vthread['creation']);
      try {
        $vtuser = VTAPI::call('user.get', array(
          'threadId' => $threadid
        ));
        $extra = "Created ".$prettydate." by ".$vtuser['name'].' ('.$vtuser['email'].").<br>Submitted ";
      }
      catch (Exception $e) {
        $extra = "Created ".$prettydate." by "."(user data unavailable)<br>Submitted ";
      }
    } 
    else {
      $extra = "VTAPIWarning: Unable to confim creator and creation date. Thread creator may be outside your organization.<br>";
    }
    $link = new moodle_url('/mod/assignment/type/voicethreadcreate/thread.php?tid='.$threadid);
    $output = $OUTPUT->action_link($link, $title, new popup_action('click', $link, 'popup', array('height' => $height, 'width' => $width)));
    $output .= '<br>';
    $output .= $extra;
    return $output;
  }
}

class voicethreadcreate_form extends moodleform {
  function definition() {
    global $CFG, $USER, $PAGE;
    $mform =& $this->_form;
    $vtsite = trim($CFG->voicethread_site);
    $vtapikey = trim($CFG->voicethread_orgapikey);
    VTAPI::setCommonParams(array('orgAPIKey' => $vtapikey));
    $vtapilinked = 0; $vtapierror = 0;
    try {
      $vtuser = VTAPI::call('user.get', array(
                  'email' => $USER->email
                ));
      $vtapilinked = 1;
    }
    catch (Exception $e) {
      $vtapierror = 1;
    }
    if($vtapilinked) {
      try {
        $vthreads = VTAPI::call('thread.getList', array(
                      'userId' => $vtuser['id']
                    ));
        $js_href = $CFG->wwwroot.'/mod/assignment/type/voicethreadcomment/porthole.min.js';
        $src = 'http://'.$vtsite.'/picker/?picktype=moodle&parent_url='.urlencode(htmlspecialchars($PAGE->url->out()));
        $mform->addElement('text','var1','Please select your VoiceThread');
        $mform->addElement('html', '<iframe name="voicethread_select "id="voicethread_select" src="#" frameborder="1" width="500" height="325" scrolling="yes" style="display: block; padding: 5px 0;">VoiceThread list</iframe>
                                    <script type="text/javascript" src="'.$js_href.'"></script>
                                    <script type="text/javascript">
                                      window.onload=function(){ Porthole.WindowProxyDispatcher.start(); };
                                    </script>
                                    <script type="text/javascript">
                                      var guestDomain = "voicethread.com";
                                      function onMessage(messageEvent) {  
                                        var parameters = Porthole.WindowProxy.splitMessageParameters(messageEvent.data);
                                        if (parameters["book"]) {
                                          var response = eval(\'(\'+parameters["book"]+\')\');
                                          x = document.getElementById(\'id_var1\');
                                          x.value = response["id"];
                                        }
                                      }
                                      var windowProxy;
                                      window.onload=function(){ 
                                          windowProxy = new Porthole.WindowProxy("http://" + guestDomain + "/picker/", "voicethread_select");
                                          windowProxy.addEventListener(onMessage);
                                      };
                                      var iframe = document.getElementById(\'voicethread_select\');
                                      var book_id = document.getElementById(\'id_var1\');
                                      if (iframe && book_id) {
                                        iframe.src = "'.$src.'&book="+book_id.value;
                                      }
                                      else if (iframe) {
                                        iframe.src = "'.$src.'";
                                      }
                                    </script>
                                    
                            ');
      }
      catch (Exception $e) {
        $mform->addElement('static','VTAPIWarning','VTAPIWarning','Thread list not available. You may just need to login to VoiceThread or there was an issue with the VoiceThread API.');
        $mform->addElement('text','var1','Enter Thread ID');
      }
    }
    else {
      $mform->addElement('static','VTAPIWarning','VTAPIWarning','Thread list not available. You may just need to login to VoiceThread or ensure that Moodle is configured correctly.');
      $mform->addElement('text','var1','Enter Thread ID');
    }
    $mform->addRule('var1','required','required',null,'client');
    $mform->addElement('hidden','id',0);
    $mform->setType('id',PARAM_INT);
    $this->add_action_buttons();
  }
}
?>
