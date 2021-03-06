<?php

/*
  Class: VTAPI
  
  ...makes calls to the VoiceThread API from PHP more convenient.
  
  Downloading:
  
  To get this library, download http://voicethread.com/api/client/VTAPI.zip
  and unzip it somewhere in your PHP include path.
  
  Requirements:
  
  To use this library, you must have PHP compiled with some extra libraries.
  
    * XML-RPC support (--with-xmlrpc)
    * cURL support (--with-curl)
    
  To find out if your instance of PHP meets the requirements, execute
  the <call> method or call phpinfo() and look for the options above
  in the output.
  
  Example:
  
  (start code)
  
  <?php
  
    require_once('VTAPI.php');
    
    try {
      $thread = VTAPI::call('thread.get', array(
        // you will need to replace this with your own key
        'orgAPIKey' => '5bcc5cf2f37c09db5e1582d19f3932c5',
        // you will need to replace this with the id of a thread
        //  that you have access to
        'id' => 512
      ));
      var_dump($thread);
    }
    catch (VTAPI_Exception $e) {
      echo('VoiceThread returned an error: '.
            $e->getCode().' ('.$e->getMessage().')');
      exit();
    }
    
  ?>
  
  This gives output like:
  
  array(...) {
    ["id"]=>
    int(512)
    ["title"]=>
    string(7) "Turtles"
    ...
  }
  
  (end)
*/
class VTAPI {
  
  // the URL of VoiceThread's API
  public static $URL = 'https://voicethread.com/api/';
  
  // a file to store cookies in
  public static $cookiePath = NULL;
  // initialize and get the cookie path
  protected static function getCookiePath() {
  	// make sure we have a temp file to store cookies in
    if (self::$cookiePath == NULL) {
    	self::$cookiePath = tempnam('', 'VT');
    }
    return(self::$cookiePath);
  }
  
  const MEDIAPATH_PATTERN = '|^\w{1,32}$|';
  
  /*
    Function: call
  
    ...makes an XML-RPC call to VoiceThread.
    
    Parameters:
      
      $method - The fully-qualified name of the method to call, e.g. 'thread.get'
      $params - A key-value array of named parameters, see the documentation
        for the method you're calling to find out which ones to use.
        
      It is also possible to make multiple calls in a single request, which can
      improve performance, especially when the network connection has a 
      high latency. To do this, alternate method names and parameter arrays,
      like so:
      
      (start code)
      
      // make the batch of calls
      $results = VTAPI::call($methodA, $paramsA, $methodB, $paramsB, ...);
      
      // the results are returned in an array
      $resultA = $results[0];
      $resultB = $results[1];
      ...
      
      (end)
      
    Returns:
    
      The return value of the called API method, packaged as a PHP value.
      When grouping several calls into a single request as described above,
      an array will be returned with an item for the return value of each 
      call in the same order the calls were passed in.
      
    Exceptions:
    
      This function may throw a <VTAPI_Exception> or a standard Exception
      if the VoiceThread server returns an XML-RPC fault.
  */
  public static function call() {
    // make sure we have the needed libraries installed
    if (! function_exists('xmlrpc_encode'))
      throw new Exception('Support for XML-RPC is needed but it is not installed.');
    if (! function_exists('curl_init'))
      throw new Exception('Support for cURL is needed but it is not installed.');
    // examine our arguments
    $numArgs = func_num_args();
    $args = func_get_args();
    // there must be at least one pair of arguments
    if (! ($numArgs >= 2))
      throw new Exception('You must pass a method to call and its parameters.');
    // the arguments must be in pairs
    if ($numArgs % 2 != 0)
      throw new Exception('You must pass parameters for each method called.');
    // process the pairs of arguments into a list
    $calls = array();
    for($i = 0; $i < $numArgs; $i += 2) {
      // retrieve the pair
      $method = $args[$i];
      $params = $args[$i + 1];
      // check parameter format
      if (! is_string($method))
        throw new Exception('Parameter '.($i+1).' should be a method name.');
      if (! is_array($params))
        throw new Exception('Parameter '.($i+2).' should be a parameter array.');
      // add common parameters (allowing passed ones to override if keys match)
      $params = $params + self::$commonParams;
      // add to the list
      $calls[] = array($method, $params);
    }
    // if just one call is being made, make that call alone
    if (count($calls) == 1) {
      $method = $calls[0][0];
      $params = $calls[0][1];
    }
    // otherwise make a batch call
    else {
      $method = 'multiple';
      $params = $calls;
    }
    // package the request
    $requestXML = xmlrpc_encode_request($method, $params,
      array('encoding' => 'UTF-8'));
    // make a cURL handler for the request
    $ch = curl_init();
    // set up the cURL request
    curl_setopt($ch, CURLOPT_URL, self::$URL);
    curl_setopt($ch, CURLOPT_COOKIEFILE, self::getCookiePath());
    curl_setopt($ch, CURLOPT_COOKIEJAR, self::getCookiePath());
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestXML);
    // execute the request
    $response = curl_exec($ch);
    // close the cURL session
    curl_close($ch);
    // this is required to make cookies work in early versions of PHP 5
    unset($ch);
    // decode the response
    $result = xmlrpc_decode($response);
    // see if it's an error
    if ((is_array($result)) && (xmlrpc_is_fault($result))) {
      // get the error code
      $code = $result['faultCode'];
      $message = $result['faultString'];
      // see if it's a recognized VT error
      if (strlen(VTAPI_Exception::getErrorMessage($code)) > 0) {
        // package as a custom exception
        throw VTAPI_Exception::makeException($code, $message);
      }
      else {
        // package as a generic exception
        throw new Exception($message, $code);
      }
      // return false to let the caller know that the call failed
      return(FALSE);
    }
    // if we got here, the call succeeded
    return($result);
  }
  
  /*
    Function: setCommonParams
  
    ...sets parameters that will get passed with every call.
    
    This function makes it convenient to pass authentication tokens,
    such as API keys, with every subsequent call. The stored set of common
    parameters is overwritten with each call to this function, but it
    is also returned as an array in case you want to restore it later.
    When you pass parameters explicitly to <call>, they will override
    any common parameters with the same key.
    
    Parameters:
      
      $params - A key-value array of named parameters to pass with every call
        
    Returns:
    
      The previous set of common parameters.
      
    Exceptions:
    
      This function will throw <VTAPI_BadRequest> if $params 
      is not an array.
  */
  public static function setCommonParams($params = array()) {
    // make sure we got an array
    if (! is_array($params)) 
      throw new VTAPI_BadRequest(
        "You must pass an array to setCommonParams.");
    // store the old parameters
    $oldParams = self::$commonParams;
    // put in the new ones
    self::$commonParams = $params;
    // return the old ones
    return($oldParams);
  }
  protected static $commonParams = array();
  
  /*
    Function: upload
  
    ...uploads a file to VoiceThread for inclusion in further API calls.
    
    Some API calls involve specifying a data file. For example, when creating a
    page, you might want to add a media file from your own filesystem. 
    Uploading is decoupled from the rest of the API to simplify the protocol, 
    so before creating the page, you will need to call this function first and 
    make sure that it did not fail. The function will return a unique identifier
    for the uploaded file, and you can pass this to any API function with a
    parameter called 'mediaPath' to use the uploaded file.
    
    Parameters:
      
      $path - A path to the file you want to upload from the client filesystem
      $mediaPath - A unique identifier for the file, which can only contain
        alpha-numeric characters and underscores and can be no more than
        32 characters long (it must match the pattern |[A-Za-z_]{1,32}|). 
        If you do not pass this parameter or if you pass an empty string, 
        VoiceThread will make up an identifier for you and it will be 
        returned by this function. If you do pass a non-string, it is your 
        responsibility to make sure that it's likely to be unique. Passing 
        ids without enough randomness can cause collisions with unpredictable
        results.
        
    Returns:
    
      The final value of $mediaPath, or FALSE on failure.
      
    Exceptions:
    
      This function will throw <VTAPI_NotFound> if the file
      at $path does not exist.
      
      This function will throw <VTAPI_BadRequest> if $mediaPath
      contains forbidden characters or is too long.
  */
  public static function upload($path, $mediaPath = '') {
    // we need cURL for this function
    if (! function_exists('curl_init'))
      throw new Exception('Support for cURL is needed but it is not installed.');
    // make sure the path to upload from exists
    if (! file_exists($path))
      throw new VTAPI_NotFound(
        "There is no file to upload at '$path'.");
    // generate a file id if none was passed
    if (! (strlen($mediaPath) > 0)) 
      $mediaPath = md5(php_uname().'_'.time().'_'.mt_rand());
    // if an id was passed make sure it's clean
    if (! preg_match(self::MEDIAPATH_PATTERN, $mediaPath))
      throw new VTAPI_BadRequest(
        "The value you passed for mediaPath ('$mediaPath') is too long ".
        "or contains invalid characters.");
    // do cURL setup
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, self::$URL.'?path='.$mediaPath);
    curl_setopt($ch, CURLOPT_COOKIEFILE, self::getCookiePath());
    curl_setopt($ch, CURLOPT_COOKIEJAR, self::getCookiePath());
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array('Filedata' => "@$path"));
    // upload the file
    $response = curl_exec($ch);
    // close the curl session
    curl_close($ch);
    // this is required to make cookies work in early versions of PHP 5
    unset($ch);
    // return the file id
    return($mediaPath);
  }
  
}

/*
  Exception: VTAPI_Exception
  
  ...packages exceptions thrown by the VoiceThread API.
  
  The API will actually throw exceptions that extend this class, but 
  each one has a unique error code in case you want to trap VTAPI 
  exceptions with this class. Below is a list of all possible error codes.
  
    BAD_REQUEST (400) - Something is wrong with your request. Double-check
      the documentation and check your code for typos.
    NOT_AUTHENTICATED (401) - Either you didn't pass any authentication credentials, or
      they didn't match up with what's in our database. Make sure you have your API
      key typed exactly right, and that you aren't using the bogus one from the
      examples.
    NOT_AUTHORIZED (403) - You are authenticated, but you still aren't allowed to do what
      you're trying to do. Make sure you have the right API key.
    NOT_FOUND (404) - The requested resource could not be found or you do not have access
      to it.
    CONFLICT (409) - The operation you requested conflicts with the VoiceThread
      database. Check that you are passing correct ids.
    UNSUPPORTED_MEDIA (415) - The operation involves a media format or variant
      that VoiceThread does not support at this time. If you feel the file type
      in question should be supported, please let us know.
    NOT_IMPLEMENTED (500) - The requested functionality has not been implemented yet.
      We're probably working on it.
    INTERNAL_ERROR (501) - Something unexpected has gone wrong on the VoiceThread server.
      If you keep getting this error, please let us know.
      
    You will notice that, as much as possible, these error codes correspond to
    HTTP status codes.
*/
class VTAPI_Exception extends Exception {
  
  const BAD_REQUEST = 400;
  const NOT_AUTHENTICATED = 401;
  const NOT_AUTHORIZED = 403;
  const NOT_FOUND = 404;
  const CONFLICT = 409;
  const UNSUPPORTED_MEDIA = 415;
  const NOT_IMPLEMENTED = 500;
  const INTERNAL_ERROR = 501;
  // error messages
  public static function getErrorMessage($code) {
    switch($code) {
      case self::BAD_REQUEST:
        return('Something is wrong with your request.');
      case self::NOT_AUTHENTICATED:
        return('The caller could not be authenticated.');
      case self::NOT_AUTHORIZED:
        return('The caller is not allowed to perform this action.');
      case self::NOT_FOUND:
        return('The resource could not be found.');
      case self::CONFLICT:
        return('The operation causes a conflict with the database.');
      case self::UNSUPPORTED_MEDIA:
        return('The operation involves a media type or variant that '.
               'is not supported at this time.');
      case self::NOT_IMPLEMENTED:
        return('This functionality is not yet implemented.');
      case self::INTERNAL_ERROR:
        return('There was an internal error. Please contact VoiceThread if this persists.');
    }
    return('');
  }
  
  function __construct($code, $message = '') {
    // if no message was passed, use a default one
    if (! (strlen($message) > 0))
      $message = self::getErrorMessage($code);
    // call the base constructor
    parent::__construct($message, $code);
  }
  
  // an exception factory
  public static function makeException($code, $message) {
    switch($code) {
      case self::BAD_REQUEST:
        return(new VTAPI_BadRequest($message));
      case self::NOT_AUTHENTICATED:
        return(new VTAPI_NotAuthenticated($message));
      case self::NOT_AUTHORIZED:
        return(new VTAPI_NotAuthorized($message));
      case self::NOT_FOUND:
        return(new VTAPI_NotFound($message));
      case self::CONFLICT:
        return(new VTAPI_Conflict($message));
      case self::UNSUPPORTED_MEDIA:
        return(new VTAPI_UnsupportedMedia($message));
      case self::NOT_IMPLEMENTED:
        return(new VTAPI_NotImplemented($message));
      case self::INTERNAL_ERROR:
        return(new VTAPI_InternalError($message));
      default:
        return(new VTAPI_Exception($code, $message));
    }
  }
  
}

/*
  Exception: VTAPI_BadRequest
  
  ...indicates that something is wrong with your request.
  Double-check the documentation and check your code for typos.
*/
class VTAPI_BadRequest extends VTAPI_Exception {
  function __construct($message = '') {
    parent::__construct(VTAPI_Exception::BAD_REQUEST, $message);
  }
}

/*
  Exception: VTAPI_NotAuthenticated
  
  ...indicates that either you didn't pass any 
  authentication credentials, or they didn't match up with 
  what's in our database. Make sure you have your API
  key typed exactly right, and that you aren't using the 
  bogus one from the examples.
*/
class VTAPI_NotAuthenticated extends VTAPI_Exception {
  function __construct($message = '') {
    parent::__construct(VTAPI_Exception::NOT_AUTHENTICATED, $message);
  }
}

/*
  Exception: VTAPI_NotAuthorized
  
  ...indicates that you are authenticated, but you still 
  aren't allowed to do what you're trying to do. Make sure you have 
  the right API key.
*/
class VTAPI_NotAuthorized extends VTAPI_Exception {
  function __construct($message = '') {
    parent::__construct(VTAPI_Exception::NOT_AUTHORIZED, $message);
  }
}

/*
  Exception: VTAPI_NotFound
  
  ...indicates that the requested resource could 
  not be found or you do not have access to it.
*/
class VTAPI_NotFound extends VTAPI_Exception {
  function __construct($message = '') {
    parent::__construct(VTAPI_Exception::NOT_FOUND, $message);
  }
}

/*
  Exception: VTAPI_Conflict
  
  ...indicates that the operation you requested 
  conflicts with the VoiceThread database. Check that you 
  are passing correct ids.
*/
class VTAPI_Conflict extends VTAPI_Exception {
  function __construct($message = '') {
    parent::__construct(VTAPI_Exception::CONFLICT, $message);
  }
}

/*
  Exception: VTAPI_UnsupportedMedia
  
  ...indicates that the operation involves a media 
  format or variant that VoiceThread does not support at this 
  time. If you feel the file type in question should be supported, 
  please let us know.
*/
class VTAPI_UnsupportedMedia extends VTAPI_Exception {
  function __construct($message = '') {
    parent::__construct(VTAPI_Exception::UNSUPPORTED_MEDIA, $message);
  }
}

/*
  Exception: VTAPI_NotImplemented
  
  ...indicates that the requested functionality 
  has not been implemented yet. We're probably working on it.
*/
class VTAPI_NotImplemented extends VTAPI_Exception {
  function __construct($message = '') {
    parent::__construct(VTAPI_Exception::NOT_IMPLEMENTED, $message);
  }
}

/*
  Exception: VTAPI_InternalError
  
  ...indicates that something unexpected has gone 
  wrong on the VoiceThread server. If you keep getting this error, 
  please let us know.
*/
class VTAPI_InternalError extends VTAPI_Exception {
  function __construct($message = '') {
    parent::__construct(VTAPI_Exception::INTERNAL_ERROR, $message);
  }
}

?>
