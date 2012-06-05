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

$settings->add(new admin_setting_configtext('voicethread_site', 'VoiceThread Site','This is normally your VoiceThread sub-site, i.e. myschool.ed.voicethread.com.','voicethread.com'));

$settings->add(new admin_setting_configtext('voicethread_authkey','VoiceThread Auth Key','This is the Auth Key from VoiceThread Support, required for authentication integration','unset'));

$settings->add(new admin_setting_configtext('voicethread_orgid','VoiceThread Organization ID','This is your numeric VoiceThread organization ID. You need this for advanced embedding functionality, auth integration and assignment integration.','unset'));

$settings->add(new admin_setting_configtext('voicethread_orgapikey','VoiceThread API Key','This is your organizations\'s API key. You need this for advanced embedding functionality and assignment integration support.','unset'));

?>
