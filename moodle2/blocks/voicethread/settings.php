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


$settings->add(new admin_setting_configtext('voicethread_site','VoiceThread Site','This is normally your VoiceThread sub-site, i.e. myschool.ed.voicethread.com.','voicethread.com'));

$settings->add(new admin_setting_configtext('voicethread_authkey','VoiceThread Auth Key','This is the Auth Key from VoiceThread Support, required for authentication integration','unset'));

$settings->add(new admin_setting_configtext('voicethread_orgid','VoiceThread Organization ID','This is your numeric VoiceThread organization ID. If you are not sure what this is, please ask VoiceThread Support','unset'));

$settings->add(new admin_setting_configtext('voicethread_orgapikey','VoiceThread API Key','This is your organizations\'s API key. You only currently need this for assignment integration support.','unset'));

?>
