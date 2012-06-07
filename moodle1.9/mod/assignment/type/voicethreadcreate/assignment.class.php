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


require_once("$CFG->libdir/formslib.php");
if (! class_exists('VTAPI', FALSE)) require_once("VTAPI.php");

class assignment_voicethreadcreate extends assignment_base {

	function assignment_voicethreadcreate($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
		parent::assignment_base($cmid,$assignment,$cm,$course);
		$this->type = 'voicethreadcreate';
	}

	function view() {
	        global $USER, $CFG;

		$edit = optional_param('edit', 0, PARAM_BOOL);
		$saved = optional_param('saved', 0, PARAM_BOOL);

	    	$context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
      		require_capability('mod/assignment:view', $context);

		$submission = $this->get_submission();

		if(!has_capability('mod/assignment:submit', $context)) {
			$editable = null;
		} else {
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
			$defaults->thread_id = $submission->data1;
		}

		$mform->set_data($defaults);
		if($mform->is_cancelled()) {
			redirect('view.php?id='.$this->cm->id);
		}

		if($data = $mform->get_data()) {
			if($editable && $this->update_submission($data)) {
				$submission = $this->get_submission();
				add_to_log($this->course->id, 'assignment', 'upload', 'view.php?a=' . $this->cassignment->id, $this->assignment->id,$this->cm->id);
				$this->email_teachers($submission);
				redirect('view.php?id=' . $this->cm->id . '&saved=1');
			} else {
				notify(get_string("error"));
			}
		}

		if($editmode) {
			$this->view_header(get_string('editmysubmission', 'assignment'));
		} else {
			$this->view_header();
		}

		$this->view_intro();


		if($saved) {
			notify(get_string('submissionsaved','assignment'), 'notifysuccess');
		}

		if(has_capability('mod/assignment:submit', $context)) {
			if($editmode) {
				print_box_start('generalbox', 'voicethreadcreate');

				$mform->display();
			} else {
				print_box_start('generlabox boxwidthwide boxaligncenter', 'voicethreadcreate');
				if($submission) {
					$vtembed = $this->threadid_to_embed($submission->data1,640);
					echo $vtembed;
				} else if (!has_capability('mod/assignment:submit', $context)) {
					echo '<div style="text-align:center">' . get_string('guestnosubmit', 'assignment') . '</div>';
				} else if ($this->isopen()) {
					echo '<div style="text-align:center">' . get_string('emptysubmission', 'assignment') . '</div>';
				}
			}
			print_box_end();
			if(!$editmode && $editable) {
				echo "<div style='text-align:center'>";
				print_single_button('view.php', array('id'=>$this->cm->id, 'edit'=>'1'), get_string('editmysubmission', 'assignment'));
				echo "</div>";
			}
		}
		$this->view_dates();
		$this->view_feedback(); $this->view_footer();
	}

	function update_submission($data) {
		global $CFG, $USER;

		$submission = $this->get_submission($USER->id, true);

		$update = new object();
		$update->id = $submission->id;
		$update->data1 = $data->thread_id;
		$update->timemodified = time();

		if(!update_record('assignment_submissions', $update)) {
			return false;
		}
		
		$submission = $this->get_submission($USER->id);
		$this->update_grade($submission);
		return true;
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
		$embed .= '<object width="' . $width .'" height="' . $height . '"> ';
		$embed .= '<param name="movie" value="http://'.$vtsite.'/book.swf?b=' . $tid .'"></param> ';
		$embed .= '<param name="wmode" value="transparent"></param>';
		$embed .= '<embed src="http://'.$vtsite.'/book.swf?b=' . $tid . '" type="application/x-shockwave-flash" wmode="transparent" width="' . $width . '" height="' . $height . '"></embed>';
		$embed .= '</object></div>';
		$embed .= $default_google_analytics_code;
		return $embed;
	}


	function print_student_answer($userid, $return=false) {

		global $CFG;
		if (!$submission = $this->get_submission($userid)) {
			return "";
		}
	
                if(isset($CFG->voicethread_site)) {
                        $vtsite = trim($CFG->voicethread_site);
                } else {
                        $vtsite = 'voicethread.com';
                }

		$threadid = $submission->data1; $width = 100; $height = 75;

		# get thread metadata from VoiceThread
		$vtorgid = trim($CFG->voicethread_orgapikey);
                VTAPI::setCommonParams(array('orgAPIKey' => $vtorgid));
                $vtapilinked = 0; $vtapierror = 0;

                try {
                  $vthread = VTAPI::call('thread.get', array(
                    'id' => $threadid
                  ));
                  $vtapilinked = 1;
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
		} else {
			$extra = "VTAPIWarning: Unable to confim creator and creation date. Thread creator may be outside your organization.<br>";
		}

		$output = link_to_popup_window('/mod/assignment/type/voicethreadcreate/thread.php?tid='.$threadid, 'ViewThread', 'View Thread '.$threadid.'<br>', 500, 660, get_string('submission','assignment'), 'none', true);

		$output .= $extra;

		return $output;
	}
}

class voicethreadcreate_form extends moodleform {
  function definition() {
    global $CFG, $USER;
    $mform =& $this->_form;
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
      $mform->addElement('static','VTAPIWarning','VTAPIWarning','There was an error getting your user information from VoiceThread.  Please check that the VoiceThread API has access through your firewall and can connect to the internet.');
      $vtapierror = 1;
    }

    if($vtapilinked) {
      try {
        $vthreads = VTAPI::call('thread.getList', array(
          'userId' => $vtuser['id']
        ));
        if (empty($vthreads))
          throw new Exception('No Threads');
        foreach ($vthreads as $vthread) {
          if(!strcmp($vthread['title'],'')) {
            $vtitle = '(untitled)';
          } else {
            $vtitle = $vthread['title'];
          }
          // id is vthread['id'], title is vthread['title']
          $threadlist[$vthread['id']] = 'Thread '.$vthread['id'].': '.$vtitle;
        }
        $mform->addElement('select','thread_id','Please select your VoiceThread',$threadlist);
      }
      catch (Exception $e) {
        $mform->addElement('static','VTAPIWarning','VTAPIWarning','Thread list not available. You may just need to login to VoiceThread, you might not have any threads, or your user information in VoiceThread and Moodle might not match.');
        $mform->addElement('text','thread_id','Enter Thread ID');
      }
    } 
    else {
      $mform->addElement('static','VTAPIWarning','VTAPIWarning','Thread list not available. There was an error linking the VoiceThread API with your email.');
      $mform->addElement('text','thread_id','Enter Thread ID');
    }

    $mform->addRule('thread_id','required','required',null,'client');
    $mform->addElement('hidden','id',0);
    $mform->setType('id',PARAM_INT);
    $this->add_action_buttons();
	}
}
?>
