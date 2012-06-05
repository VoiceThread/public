<?php
/**********
 *
 * Copyright (c) 2011, VoiceThread.com
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
 * This module allows simple upload of documents to the external VoiceThread Information Systems Integration
 * (VISI) system.  To use this code, a file name "visi.ini" must be contained within the same directory as
 * this script.
 *
 * The "visi.ini" file must contain:
 * auth_key = your VoiceThread authentication string, surrounded by quotes (ex. "abcdef")
 * org_id = a numeric value to identify your VoiceThread organization
 * domain = the VoiceThread domain prepended to your VoiceThread URL (ex. "mycollege" for mycollege.voicethread.com)
 *
 * The "visi.ini" file must also contain at least one filename, and can contain as many files as desired.
 * Example of uploading two files (must use "filenames[] as the value name)
 * filenames[] = "example.csv"
 * filenames[] = "example2.csv"
 *
 *************/
 

//This is the module that processes the upload request
class VISICSVUploader {
  private $auth_key;
  private $org_id;
  private $server = 'voicethread.com';
  private $visi_path = '/visi/';
  private $curl_url;
  private $curl_output;
  private $filenames = array();
  private $allowed_file_types = array('.csv');
  private $error_array = array();
  private $httpCodes = array();
  public $httpCodeMessages = array(
    0 => 'Please ensure that the URL and domain entered for your organization is valid.',
    1 => '',
    200 => 'The course/user data has been entered in the queue for update!',
    202 => 'The request was valid, but the course/user data has not changed since last update.',
    206 => 'The request contained empty data.',
    400 => 'There was an issue processing the data.  Please ensure a valid file is sent with the request.',
    401 => 'The authentication key was not configured or does not match.  Please ensure an authentication key is set and is correct.',
    404 => 'The Organization ID was not configured.  Please ensure an Org ID is set.',
    405 => 'The request requires that data be sent securely over HTTPS.  Please ensure the request is using HTTPS.',
    406 => 'The request contained a file type not supported.  Please ensure the file type is CSV.',
    501 => 'Your organization may not be configured correctly.  Please contact <a href="http://voicethread.com/support/contact/">VoiceThread Support</a>.'
  );
  
  // Initialization processes the initialization file, and returns errors for any issues encountered.
  function initialize() {
    $config = @parse_ini_file('visi.ini', true);
    // error if the visi.ini file was not found
    if ($config === FALSE || empty($config)) {
      $this->setError('There was an error locating your .ini file. Please check that it exists within the same directory you are executing this script from.');
    }
    // error if the necessary variables aren't set
    if (empty($config['auth_details']['domain']) || empty($config['auth_details']['auth_key']) || empty($config['auth_details']['org_id'])) {
      $this->setError('One of the config variables was not set within the .ini file. Please check that your domain, auth key, and org ID have values.');
    }
    if (stristr($config['auth_details']['domain'], 'voicethread.com')) {
      $this->setServer($config['auth_details']['domain']);
    }
    else {
      $this->setServer($config['auth_details']['domain'].'.voicethread.com');
    }
    $this->setAuthKey($config['auth_details']['auth_key']);
    $this->setEnvironmentIdentifier($config['auth_details']['org_id']);
    
    // save all the filenames to process in the next step
    if ((!empty($config['files'])) &&  (!empty($config['files']['filenames']))) {
      foreach($config['files']['filenames'] as $file) {
        foreach ($this->getAllowedFileTypes() as $type) {
          if (stristr($file, $type)) {
            $this->setFilenames($file);
          }
        }
      }
    }
    
    $file_array = $this->getFilenames();
    // error if no filenames were entered, or if they were not an allowed type
    if (empty($file_array)) {
      $this->setError('There was an issue saving your filename. Please check that it matches one of the allowed file types, or that it can be located.');
    }
  }
  
  function sendData() {
    //All transactions must be done with cURL
    if (function_exists('curl_init')) {
      $file_array = $this->getFilenames();
      // run a request for each file desired
      foreach($file_array as $file) {
        $ch = @curl_init();
        $server = $this->getServer();
        $path = $this->getVISIPath();
        $env_id = $this->getEnvironmentIdentifier();
        $url = 'https://'.$server.$path.'?env='.$env_id;
        $this->setCurlURL($url);
        // error if the file does not exist
        if (! file_exists($file)) {
          $this->setError('File '.$file.' was not found.');
          continue;
        }
        $post = array(
          'upload' => '@'.$file,
          'auth_key' => $this->getAuthKey()
        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $output = @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->setHTTPCodes($httpCode);
        if ($httpCode == 202)
          $this->setError('The File '.$file.' does not contain any changes since last import.');
        elseif ($httpCode == 0)
          $this->setError('Please ensure that the URL and domain entered for your organization is valid.');
        elseif ($httpCode != 200)
          $this->setError('Sending '.$file.' returned an error. HTTP Code: '.$httpCode);
        $this->setCurlOutput($output);
        $error = @curl_errno($ch);
        // error for any generic errors that might happen on the VoiceThread side of processing
        if (($output != 1) && ($output != NULL))
          $this->setError('There was an error encountered while processing your file.  Please contact the VoiceThread Integration Team at integration@voicethread.com.');
      }
      return(TRUE);
    }
    $this->setError('Requests must be made through cURL. Please check that you have cURL set up correctly for PHP');
    return(FALSE);
  }
  
  //getter and setter functions for private variables
  public function getServer() {return ($this->server);}
  public function setServer($server) {$this->server = $server;}
  public function getEnvironmentIdentifier() {return ($this->org_id);}
  public function setEnvironmentIdentifier($id) {$this->org_id = $id;}
  public function getVISIPath() {return ($this->visi_path);}
  public function setVISIPath($path) {$this->visi_path = $path;}
  public function getAuthKey() {return ($this->auth_key);}
  public function setAuthKey($key) {$this->auth_key = $key;}
  public function setCurlURL($url) {$this->curl_url = $url;}
  public function getCurlURL() {return($this->curl_url);}
  public function setCurlOutput($output) {$this->curl_output = $output;}
  public function getCurlOutput() {return($this->curl_output);}
  
  public function getAllowedFileTypes() {return($this->allowed_file_types);}
  
  public function setFilenames($name) {
    array_push($this->filenames, $name);
  }
  
  public function getFilenames() {
    return($this->filenames);
  }
  
  public function setHTTPCodes($name) {
    array_push($this->httpCodes, $name);
  }
  
  public function getHTTPCodes() {
    return($this->httpCodes);
  }
  
  public function getError() {
    return($this->error_array);
  }
  
  public function setError($error) {
    array_push($this->error_array, $error); 
  }
  
  public function getHTTPCodeMessages() {
    $codes = $this->getHTTPCodes();
    $messages = array();
    foreach($codes as $code) {
      $messages[$code] = $this->httpCodeMessages[$code];
    }
    return($messages);
  }
  
}
?>