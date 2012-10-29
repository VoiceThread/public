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
 * This assignment type allows teachers within Moodle to assign students to comment on a particular VoiceThread
 *
 *************/

require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot.'/mod/url/locallib.php');
if (! class_exists('VTAPI', FALSE)) require_once("VTAPI.php");

class assignment_voicethreadcomment extends assignment_base {

  function assignment_voicethreadcomment($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
    parent::assignment_base($cmid,$assignment,$cm,$course);
    $this->type = 'voicethreadcomment';
  }

  function view() {
    global $USER, $CFG, $OUTPUT, $DB;
    $edit = optional_param('edit', 0, PARAM_BOOL);
    $saved = optional_param('saved', 0, PARAM_BOOL);
    $context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
    require_capability('mod/assignment:view', $context);
    $submission = $this->get_submission();
    $threadid = $this->assignment->var1;
    $required_count = $this->assignment->var2;
    $comment_count = $this->getComments($threadid, FALSE);
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
    $mform = new voicethreadcomment_form(null, array('thread_id'=>$threadid, 'comment_array' => $comment_count));
    $defaults = new object();
    $defaults->id = $this->cm->id;
    if (!empty($submission)) {
      $defaults->comment_id = $submission->data1;
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
        echo $OUTPUT->box_start('generalbox', 'voicethreadcomment');
        $mform->display();
      } 
      else {
        echo $OUTPUT->box_start('generlabox boxwidthwide boxaligncenter', 'voicethreadcomment');
        $vtembed = $this->threadid_to_embed($threadid, 640);
        echo $vtembed;
        if (!has_capability('mod/assignment:submit', $context)) {
          echo '<div style="text-align:center">'.get_string('guestnosubmit', 'assignment').'</div>';
        } 
        else if ($this->isopen()) {
        // echo '<div style="text-align:center">'.get_string('emptysubmission', 'assignment').'</div>';
        }
      }
      echo $OUTPUT->box_end();
      if(!$editmode && $editable) {
        echo "<div style='text-align:center'>";
        if (count($comment_count) >= $required_count) {
          echo $OUTPUT->single_button('view.php?id='.$_GET['id'].'&edit=1', 'Submit Assignment', get_string('editmysubmission', 'assignment'), array('id'=>$this->cm->id, 'edit'=>'1'));
        }
        elseif (!empty($submission)) {
          echo '<p>You may have deleted comments on the above VoiceThread after submitting your assignment.  You can comment on the VoiceThread above.</p><button onclick="window.location.reload()">I have made '.$required_count.' comment'.(($required_count == 1) ? '' : 's').'</button>';
        }
        else {
          echo '<p>The required number of comments is '.$required_count.'.</p><button onclick="window.location.reload()">I have made '.$required_count.' comment'.(($required_count == 1) ? '' : 's').'</button>';
        }
        echo "</div>";
      }
    }
    $this->view_dates();
    $this->view_feedback(); 
    $this->view_footer();
  }
  function setup_elements(&$mform) {
    global $CFG, $USER, $PAGE;
    
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
        $vtsite = trim($CFG->voicethread_site);
        $src = 'http://'.$vtsite.'/picker/?picktype=moodle&parent_url='.urlencode(htmlspecialchars($PAGE->url->out()));
        $mform->addElement('text','var1', 'Please select your VoiceThread');
        $mform->addElement('html', '<iframe name="voicethread_select "id="voicethread_select" src="#" frameborder="1" width="415" height="350" scrolling="yes" style="display: block;">VoiceThread list</iframe>
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
    $mform->addElement('text', 'var2', 'Enter number of comments');
    $mform->addHelpButton('var1','threadnum', 'assignment_voicethreadcomment');
    $mform->addHelpButton('var2','reqnumber', 'assignment_voicethreadcomment');
    $mform->addRule('var2','required','required',null,'client');
    $mform->addRule('var2','Must be numeric','numeric',null,'client');
    $mform->setType('var2',PARAM_INT);
    $mform->setDefault('var2', 1);
    $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

    $mform->addElement('select', 'resubmit', get_string('allowresubmit', 'assignment'), $ynoptions);
    $mform->addHelpButton('resubmit', 'allowresubmit', 'assignment');
    $mform->setDefault('resubmit', 0);

    $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'assignment'), $ynoptions);
    $mform->addHelpButton('emailteachers', 'emailteachers', 'assignment');
    $mform->setDefault('emailteachers', 0);
  }
  
  function update_submission($data) {
    global $CFG, $USER, $DB;
    $submission = $this->get_submission($USER->id, true);
    $update = new object();
    $update->id = $submission->id;
    $update->data1 = $data->comment_id;
    $update->timemodified = time();
    if(!$DB->update_record('assignment_submissions', $update)) {
      return false;
    }
    $submission = $this->get_submission($USER->id);
    $this->update_grade($submission);
    return true;
  }

  function threadid_to_embed($tid, $width) {
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
    $embed .= '<object width="' . $width .'" height="' . $height . '"> ';
    $embed .= '<param name="movie" value="http://'.$vtsite.'/book.swf?b='.$tid.'"></param> ';
    $embed .= '<param name="wmode" value="transparent"></param>';
    $embed .= '<embed src="http://'.$vtsite.'/book.swf?b='.$tid.'" type="application/x-shockwave-flash" wmode="transparent" width="'.$width .'" height="'.$height.'"></embed>';
    $embed .= '</object></div>';
    $embed .= $default_google_analytics_code;
    return $embed;
  }

  function print_student_answer($userid, $return=false) {
    global $CFG, $USER, $OUTPUT;
    require_once("$CFG->dirroot/user/lib.php");
    if (!$submission = $this->get_submission($userid)) {
      return "";
    }
    $required_count = $this->assignment->var2;
    if(isset($CFG->voicethread_site)) {
      $vtsite = trim($CFG->voicethread_site);
    } 
    else {
      $vtsite = 'voicethread.com';
    }
    $comment_submitted = $submission->data1; 
    $threadid = $this->assignment->var1;
    $vtorgid = trim($CFG->voicethread_orgapikey);
    VTAPI::setCommonParams(array('orgAPIKey' => $vtorgid));
    $vtapilinked = 0; $vtapierror = 0;
    $user = user_get_users_by_id(array($userid));
    if (isset($user[$userid]) && isset($user[$userid]->email))
      $email = $user[$userid]->email;
    try {
      $vtuser = VTAPI::call('user.get', array(
                'email' => $email
              ));
    }
    catch (Exception $e) {
      $vtapierror = 1;
    }
    $output = '';
    try {
      $comment_list = VTAPI::call('comment.getList', array(
        'threadId' => $threadid,
        'userId' => $vtuser['id'],
        'full' => TRUE
      ));
      $vtapilinked = 1;
    }
    catch (Exception $e) {
      $vtapierror = 1;
    }
    if($vtapilinked) {
      foreach ($comment_list as $comment) {
        $prettydate = userdate($comment['creation']);
        $identity_name = $comment['name'];
        $pageid = $comment['pageId'];
        $commentid = $comment['id'];
        $output .= 'Commented on '.$prettydate.'<br>';
        $link = 'http://'.$vtsite.'/share/'.$threadid.'/'.$pageid.'/'.$commentid;
        $embed = '<a href="'.$link.'" onclick="window.open('.$link.'); return false;" target="newWin">Review Comment</a>';
        $output .= $embed;
        $output .= '<br>';
      }
      if (count($comment_list) < $required_count) {
        $output .= '<br>Comments were deleted after submission. Please have the student comment and resubmit.<br>';
      }
    }
    else {
      $extra = "VTAPIWarning: Unable to confim creator and creation date. Thread creator may be outside your organization.<br>";
      $output .= $extra;
    }
    $output .= '<br>';
    //$output .= 'Assignment submitted on: ';
    return $output;
  }
  
  function getComments ($threadid, $numberOnly = TRUE) {
    global $USER, $CFG;
    $vtorgid = trim($CFG->voicethread_orgapikey);
    $comment_list = array();
    VTAPI::setCommonParams(array('orgAPIKey' => $vtorgid));
    $vtapilinked = 0; $vtapierror = 0;
    try {
      $vtuser = VTAPI::call('user.get', array(
                  'email' => $USER->email
                ));
    }
    catch (Exception $e) {
      return 0;
    }
    $output = '';
    try {
      $comment_list = VTAPI::call('comment.getList', array(
        'threadId' => $threadid,
        'userId' => $vtuser['id'],
        'brief' => FALSE
      ));
    }
    catch (Exception $e) {
      $vtapierror = 1;
    }
    if ($vtapierror == 1) return 0;
    elseif (!$numberOnly) {
      return ($comment_list);
    }
    else {
      return count($comment_list);
    }
  }
}

class voicethreadcomment_form extends moodleform {
  function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true) {
    parent::__construct($action, $customdata, $method, $target, $attributes, $editable);
  }
  
  function definition() {
    global $CFG, $USER;
    $mform =& $this->_form;
    $mform->addElement('static','submission_successful',null,'');
    $mform->addElement('hidden','comment_id',1);
    $mform->addElement('hidden','id',0);
    $mform->setType('id',PARAM_INT);
    $mform->addElement('html', '<p><strong>You have made the required number of comments for this assignment.  Click "Save Changes" below to submit your assignment and notify your instructor.</strong></p>');
    if(isset($CFG->voicethread_site)) {
      $vtsite = trim($CFG->voicethread_site);
    } 
    else {
      $vtsite = 'voicethread.com';
    }
    if (isset($this->_customdata)) {
      $comment_list = $this->_customdata['comment_array'];
      if (! empty($comment_list) && !empty($this->_customdata['thread_id'])) {
        foreach ($comment_list as $comment) {
          $prettydate = userdate($comment['creation']);
          $pageid = $comment['pageId'];
          $commentid = $comment['id'];
          $output = '<div style="border-style:solid; border-width:1px; padding:0 5px;"><p>';
          $output .= '<p>Commented on '.$prettydate.'</p>';
          $link = 'http://'.$vtsite.'/share/'.$this->_customdata['thread_id'].'/'.$pageid.'/'.$commentid;
          $embed = '<a href="'.$link.'" onclick="window.open('.$link.'); return false;" target="newWin">View Comment</a>';
          $output .= $embed;
          $output .= '</p></div>';
          $mform->addElement('html', $output);
        }
      }
    }
    $this->add_action_buttons();
  }
}
?>
