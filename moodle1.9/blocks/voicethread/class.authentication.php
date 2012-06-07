<?php
 /*
  * Copyright 2007 - 2010 VoiceThread.com.  All rights reserved.
  * 
  * Rev.  20100427
*/												

class VoiceThreadAuthModule {

	private $use_unique_identifier = false;
	private $unique_identifier_name = '';
	private $private_key = '';
	private $server = 'voicethread.com';
	private $auth_path = '/auth/';
	private $token = '';
	private $authIdentifier = '';
	private $environment_identifier;
	private $custom_errors;
	private $auth_schema = null;
	private $nonce;
	private $return_url = '';
	private $return_env = '';
	private $private_key_signature = '';
	private $error_array = array();
	private $token_data_array = array();
	private $curl_output = "Not initialized";
	private $curl_url = "Not initialized";
	
	public function __construct() {
		if (isset($_REQUEST['token']))
			$this->setToken($_REQUEST['token']);
		if ($this->getUseUniqueIdentifier() && (strlen($this->getUniqueIdentifierName()) > 0))
			$this->setAuthIdentifier($_SERVER[$this->getUniqueIdentifierName()]);
		else if (isset($_SERVER['REMOTE_USER']))
			$this->setAuthIdentifier($_SERVER['REMOTE_USER']);
	}
	
	public function authenticate($vtas = '', $ignoreBind = false) {
		if (!($vtas instanceOf VTAuthSchema)) {
			$this->setError('Authentication Schema did not validate. Please make sure you passed a valid object.'); 
			return(false);
		}
		if (! $ignoreBind) {
			$vtas_bind = $vtas->bind();
			if (!($vtas_bind))
				$this->setError($this->toString($vtas->getError()));
			$this->setAuthSchema($vtas);
			return($vtas_bind);
		}
		$this->setAuthSchema($vtas);
		return(true);
	}
	
	public function getAuthorization() {
		if (!($this->getAuthSchema() instanceOf VTAuthSchema)) {
			$this->setError('Authentication Schema did not validate. Please make sure you passed a valid object.'); 
			return(false);
		}
		return(true);
	}
	
	public function signIn() {
		$signature = $this->getSignature();
		if ($signature === false) {
			$this->setError('We are unable to sign this request. Please try your request again in a few minutes.');
			return(false);
		}
		return('https://'.$this->getServer().
						$this->getAuthPath().'?'.
						$signature);		
	}
	
	public function getAuthDataProperty() {
		$as = $this->getAuthSchema();
		if (!($as instanceOf VTAuthSchema)) {
			$this->setError('The authenticated user object is not valid. Please contact technical support.');
			return(false);
		}
		return($as->getDataProperty());	
	}
	
	public function getSignature() {
		$as = $this->getAuthSchema();
		if (!($as instanceOf VTAuthSchema)) {
			$this->setError('The authenticated user object is not valid. Please contact technical support.');
			return(false);
		}
		$data_properties = $as->getDataProperty();
		if (isset($data_properties['authIdentifier']))
			$this->setAuthIdentifier($data_properties['authIdentifier']);

		if (strlen($this->getToken()) <= 0) {
			$token = $nonce = '';
			$token_data = $this->getValidToken();
			$this->setTokenData($token_data);
			if ($token_data === false) {
				return(false);
			}
			if (isset($token_data['token'])) $this->setToken($token_data['token']);
			if (isset($token_data['nonce'])) $this->setNonce($token_data['nonce']);
			if (isset($token_data['returnUrl'])) $this->setReturnUrl($token_data['returnUrl']);
			if (isset($token_data['returnEnv'])) $this->setReturnEnv($token_data['returnEnv']);
			$params = array('authIdentifier'=>$this->getAuthIdentifier(),
											'token'=>$this->getToken(),
											'nonce'=>$this->getNonce(),
											'returnUrl'=>$this->getReturnUrl(),
											'returnEnv'=>$this->getReturnEnv());
		}
		else
			$params = array('authIdentifier'=>$this->getAuthIdentifier(),
								'token'=>$this->getToken());
	
		$data_properties = array_merge($data_properties, $params);
		
		ksort($data_properties);
	
		$signing = '';
		$values = array();
		foreach($data_properties AS $key => $value) {
			if ($key == 'email')
				$value = strtolower($value);
			if (is_array($value))
				$value = $this->toString($value);
			$signing .= $key . $value;
			$values[] = $key . '='. urlencode($value);
		}
		$private_key_signature = MD5($this->getPrivateKey().$signing);
		$this->setPrivateKeySignature($private_key_signature);
		$values[] = 'signature='.$private_key_signature;
		$auth_string = implode('&', $values);
		return($auth_string);
	}

	public function getValidToken() {
		if (function_exists('curl_init')) {
			$ch = @curl_init();
			$server = $this->getServer();
			$path = $this->getAuthPath();
			$env_id = $this->getEnvironmentIdentifier();
			$url = 'http://'.$server.$path.'?getToken&env='.$env_id;
			$this->setCurlURL($url);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 0);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			$output = @curl_exec($ch);
			$this->setCurlOutput($output);
			$error = @curl_errno($ch);
			if ($error > 0) {
				$this->setError('We are unable to contact VoiceThread to obtain a token. Please try again in a few moments.'.
					'We detected the following error: '.var_export($error, true).' connecting to '.$url.' with '.
					'the following output: '.var_export($output, true));
				return(false);
			}
			return($this->parseValuePairs($output));
		} 
		else if (function_exists('fsockopen')) {
			$server = $this->getServer();
                        $path = $this->getAuthPath();
                        $env_id = $this->getEnvironmentIdentifier();

			$url = $server;
			$header  = 'Host: '.$server."\r\n";
			$header .= 'User-Agent: VoiceThread Authenticator PHP'."\r\n";
			$header .= 'Content-Type: text/plain'."\r\n";
			$header .= 'Connection: close'."\r\n\r\n";
			$fp = fsockopen($url, 80, $error, $error_str, 30);
			if (!$fp) {
				$this->setError('We are unable to contact VoiceThread to obtain a token. Please try again in a few moments.');
				return(false);
			}
			$path .= $path.'?getToken&env='.$env_id;
			fputs($fp, 'GET '.$path.'  HTTP/1.1'."\r\n");
			@fputs($fp, $header.$content);
			@fwrite($fp, $out);
			$output = "";
			while (!feof($fp)) {
				$output = $output . fgets($fp, 128);
			}
			$position = stripos($output, "\r\n\r\n");
			if ($position !== false)
				$output = substr($output, $position, strlen($output));
			@fclose($fp);
			$this->setCurlURL("Fsock: " . $url . " " . $path);
			$this->setCurlOutput($output);
			return($this->parseValuePairs($output));
		}
		$this->setError('We are unable to connect to the VoiceThread Server to obtain a token (method B). 
										Please contact your systems administrator for additional information.');
		return(false);
	}

	public function parseValuePairs($data) {
		$value_pairs = explode('&', $data);
		$key_value = array();
		foreach($value_pairs AS $value_pair) {
			$temp = explode('=', $value_pair);
			if (@is_array($temp)) {
				$key = trim($temp[0]);
				$value = $temp[1];
				$key_value[$key] = @urldecode($value);
			}
		}
		if (!is_array($key_value))
			$key_value = array();
		return($key_value);
	}
	
	public function setUseUniqueIdentifier($value) { 
		if(!(is_bool($value))) $value = false;
		$this->use_unique_identifier = $value;
	}
	
	public function setError($error) {
		array_push($this->error_array, $error);
	}

	public function getError() {
		return($this->error_array);
	}

	public function setTokenData($value) {
		$this->token_data_array = $value;
	}

	public function getTokenData() {
		return($this->token_data_array);
	}

	public function setCurlOutput($value) {
		$this->curl_output = $value;
	}

	public function getCurlOutput() {
		return($this->curl_output);
	}

	public function setCurlURL($value) {
		$this->curl_url = $value;
	}

	public function getCurlURL() {
		return($this->curl_url);
	}
	
	public function toString($obj, $html = true) {
		$string = '';
		if (is_array($obj)) {
			foreach($obj AS $data) {
				$string .= $data;
				if ($html) $data .= '<br />';
				else $data .= "\r";
			}
		}
		if (is_object($obj)) {
			$string .= $obj-->__toString();
		}
		return($string);
	}

	protected function setAuthSchema($s) {$this->auth_schema = $s;}
	public function setUniqueIdentifierName($s) {$this->use_unique_identifier = $s;}
	public function setPrivateKey($s) { $this->private_key = $s; }
	public function setServer($s) { $this->server = $s; }
	public function setAuthPath($s) { $this->auth_path = $s; }
	protected function setToken($s) { $this->token = $s; }
	protected function setPrivateKeySignature($s) { $this->private_key_signature = $s;}
	public function setAuthIdentifier($s) { $this->authIdentifier = $s; }
	public function setOrgKey($s) { $this->setEnvironmentIdentifier($s); }
	public function setEnvironmentIdentifier($s) { $this->environment_identifier = $s; }
	public function setNonce($s) { $this->nonce = $s; }
	public function setReturnUrl($s) { $this->return_url = $s; }
	public function setReturnEnv($s) { $this->return_env = $s; }
	public function setCustomError($s) { $this->custom_error = $s; }
	
	public function getAuthSchema() { return($this->auth_schema); }
	public function getUseUniqueIdentifier() { return($this->use_unique_identifier); }
	public function getUniqueIdentifierName() { return($this->use_unique_name); }
	public function getPrivateKey() { return($this->private_key); }
	public function getPrivateKeySignature() { return($this->private_key_signature); }
	public function getServer() { return($this->server); }
	public function getAuthPath() { return($this->auth_path); }
	public function getToken() { return($this->token); }
	public function getAuthIdentifier() { return($this->authIdentifier); }
	public function getEnvironmentIdentifier() { return($this->environment_identifier); }
	public function getNonce() { return($this->nonce); }
	public function getReturnEnv() { return($this->return_env); }
	public function getReturnUrl() { return($this->return_url); }
	public function getCustomError() { return($this->custom_error); }
	
}

class VTAuthSchema {
	private $data_property = array();
	protected $attributes;
	private $filter;	
	private $error_array = array();

	public function bind() {
		return('Property not set');
	}
	
	public function setDataProperty($data) {
		$this->data_property = $data;
	}
	
	public function getDataProperty() {
		return($this->data_property);
	}
	
	public function setError($error) {
		array_push($this->error_array, $error);
	}
	
	public function getError() {
		return($this->error_array);
	}
	
	public function setAttributes($s) { $this->attributes = $s; }
}
?>
