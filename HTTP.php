<?php

// reference https://faculty.cs.byu.edu/~rodham/cs462/lecture-notes/day-09-web-programming/diagrams-HTTP.pdf
// cookies: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie

class HTTP{
	public static function Request($uri){
		return new HTTP_Request($uri);
	}
	public static function File($path){
		return new HTTP_File($path);
	}
}

class HTTP_File{
	
	/**
	 * An array of content types
	 * @var array
	 */
	private static $known_mime_types=null;
	
	/**
	 * The full path to the actual file being posted
	 * @var string 
	 */
	public $path;
	
	/**
	 * The Content-Type of the file
	 * @var string 
	 */
	public $type;
	
	/**
	 * The filename as it will appear to the server
	 * @var string 
	 */
	public $file;
	
	public function __construct($path){
		if(!file_exists($path) || !is_readable($path)) throw new Exception("File is not readable or does not exist.");
		$this->path = $path;
		$this->detectMimeType();
		$this->file = basename($path);
	}
	
	/**
	 * Set the file's content type
	 * @param string $type
	 * @return $this
	 */
	public function setType($type){
		$this->type = $type;
		return $this;
	}
	
	/**
	 * Set the file's filename as it will appear to the server
	 * @param string $filename
	 * @return $this
	 */
	public function setFilename($filename){
		$this->file = $filename;
		return $this;
	}
	
	/**
	 * Attempt to determine the mime type based on the extention
	 */
	private function detectMimeType(){
		$this->gatherMimeTypes();
		if(!empty(self::$known_mime_types)){
			$parts = pathinfo($this->path);
			if(!empty($parts) && !empty($parts['extension'])){
				if(!empty(self::$known_mime_types[$parts['extension']]))
					$this->type = self::$known_mime_types[$parts['extension']];
			}
		}
		if(empty($this->type)){
			$mt = mime_content_type($this->path);
			if($mt) $this->type = $mt;
		}
		if(empty($this->type)) $this->type = 'application/octet-stream';
	}
	
	/**
	 * Get an array of content types
	 */
	private function gatherMimeTypes(){
		if(!is_null(self::$known_mime_types)) return;
		$s=array();
		foreach(@explode("\n",@file_get_contents('http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types'))as $x)
			if(isset($x[0])&&$x[0]!=='#'&&preg_match_all('#([^\s]+)#',$x,$out)&&isset($out[1])&&($c=count($out[1]))>1)
				for($i=1;$i<$c;$i++)
					$s[$out[1][$i]]=$out[1][0];
		@ksort($s);
		self::$known_mime_types = empty($s) ? false : $s;
	}
}

class HTTP_Request{
	
	/**
	 * Request Method
	 * @var string
	 */
	private $method = "GET";
	
	/**
	 * (Raw) Request Body
	 * @var string 
	 */
	private $request_body = null;
	
	/**
	 * Request Key Value Pairs (Assoc array)
	 * @var array
	 */
	private $request_parameters = array();
	
	/**
	 * HTTP Request Headers (assoc array)
	 * @var array
	 */
	private $headers = array();

	/** 
	 * Header to be explicitly omitted
	 * @var array 
	 */
	private $omitHeaders = array();
	
	/**
	 * http/https
	 * @var string
	 */
	private $protocol = "http";
	
	/**
	 * Host
	 * @var String 
	 */
	private $host;
	
	/**
	 * PORT number
	 * @var int
	 */
	private $port;
	
	/**
	 * Username for basic HTTP auth
	 * @var string 
	 */
	private $username;
	
	/**
	 * password for basic http auth
	 * @var string 
	 */
	private $password;
	
	/**
	 * Path to resource
	 * @var string 
	 */
	private $path = "/";
	
	/**
	 * Does the current request contain file(s)
	 * @var boolean
	 */
	private $hasFiles = false;
	
	/**
	 * Value of the content-type header, if applicable
	 * @var string 
	 */
	private $contentType;
	
	/**
	 * Boundary for multipart/formdata
	 * @var string 
	 */
	private $boundary;
	
	/**
	 * Timeout
	 * @var int 
	 */
	private $timeout = 60;
	
	/**
	 * a filename where cookie data will be stored
	 * @var string
	 */
	private $cookiejar;
	
	public function __construct($uri){
		$uri = parse_url($uri);
		if(!isset($uri['scheme']) || !in_array($uri['scheme'], array('http', 'https'))) throw new Exception('Missing or invalid protocol.');
		$this->protocol = $uri['scheme'];
		if(isset($uri['user'])) $this->username = $uri['user'];
		if(isset($uri['pass'])) $this->password = $uri['pass'];
		if(!isset($uri['host'])) throw new Exception('Missing or invalid host.');
		$this->host = $uri['host'];
		if(isset($uri['port'])) $this->port = $uri['port'];
		else $this->port = $this->protocol == "http" ? 80 : 443;
		if(isset($uri['path'])) $this->path = $uri['path']; 
		if(isset($uri['query'])){
			parse_str($uri['query'], $params);
			$this->request_parameters = $params;
		}
		$this->headers['Accept'] = '*/*';
		$this->headers['User-Agent'] = 'PHP/'.phpversion();
	}
	
	/**
	 * Set the cookiejar file
	 * @param type $filename
	 * @throws Exception
	 */
	public function setCookiejar($filename){
		$this->cookiejar = new HTTP_Cookiejar($filename);
	}
	
	/**
	 * set the timeout
	 * @param type $timeout
	 */
	public function setTimeout($timeout){
		$this->timeout = $timeout;
	}
	
	/**
	 * Send the request
	 * @return \HTTP_Response
	 * @throws Exception
	 */
	public function send(){
		$request = $this->buildRequest();
		$socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
		if (!$socket) throw new Exception("Error $errno: $errstr");
		fwrite($socket, $request);
		$contents = array();
		while(!feof($socket)) $contents[] = fgets($socket, 4096);
		fclose($socket);
		$contents = implode("", $contents);
		return new HTTP_Response($contents);
	}
	
	/**
	 * Method Getter/Setter
	 * @param string $method
	 * @return $this|$method
	 * @throws Exception
	 */
	public function method($method=''){
		if(empty($method)) return $this->method;
		$method  = strtoupper($method);
		$available_methods = array('GET','HEAD','POST','PUT','DELETE','TRACE','CONNECT');
		if(!in_array($method, $available_methods)) throw new Exception("$method is not a supported method type.");
		$this->method = $method;
		return $this;
	}
	
	/**
	 * Single Parameter Getter/Setter
	 * @param string $key
	 * @param string $value
	 * @return boolean|$this|$value
	 * @throws Exception
	 */
	public function param($key, $value){
		if($value === false){
			if(!isset($this->request_parameters[$key])) return false;
			return $this->request_parameters[$key];
		}else{
			if(!is_string($value) && !($value instanceof HTTP_File) && !is_numeric($v)) throw new Exception("Invalid request parameter: $key.");
			$this->request_parameters[$key] = $value;
		}
		return $this;
	}
	
	/**
	 * Request parameter array Getter/Setter
	 * @param type $params
	 * @return type
	 * @throws Exception
	 */
	public function params($params=false){
		if(false === $params){
			return $this->request_parameters;
		}else{
			if(!is_array($params)) throw new Exception("\$params must be an array.");
			$flat = $this->nestedArrayToKVP($params);
			foreach($flat as $k=>$v) if(!($v instanceof HTTP_File) && !is_string($v) && !is_numeric($v)) throw new Exception("Invalid request parameter: $k.");
			$this->request_parameters = $params;
		}
		return $this;
	}
	
	/**
	 * Request Header Getter/Setter
	 * @param string $key
	 * @param string $value
	 * @param boolean $overwrite 
	 * @return boolean|$this
	 * @throws Exception
	 */
	public function header($key, $value=false, $overwrite=false){
		if($value === false){
			if(!isset($this->headers[$key])) return false;
			return $this->headers[$key];
		}else{
			if(!is_string($value)) throw new Exception("\$value must be a string.");
			if(isset($this->headers[$key]) && !$overwrite){
				if(!is_array($this->headers[$key])) $this->headers[$key] = array($this->headers[$key]);
				$this->headers[$key][] = $value;
			}else{
				$this->headers[$key] = $value;
			}
		}
		return $this;
	}
	
	/**
	 * Omit any headers with the given title
	 * @param type $header
	 * @return $this
	 */
	public function rmHeader($header){
		$this->omitHeaders[] = $header;
		return $this;
	}
	
	/**
	 * Check if the request contains a file, if so make sure the method is appropriate
	 */
	private function validateMethod(){
		$flat = $this->nestedArrayToKVP($this->request_parameters);
		foreach($flat as $k=>$v){ if($v instanceof HTTP_File){ $this->hasFiles = true; break; }}
		if($this->hasFiles && !in_array($this->method, array('POST', 'PUT'))) $this->method = "POST";
	}
	
	/**
	 * Determine the correct content type
	 */
	private function sniffContentType(){
		if(in_array($this->method, array('POST', 'PUT'))){
			if($this->hasFiles) $this->contentType = 'multipart/form-data';
			if(empty($this->contentType) && !empty($this->request_parameters)){
				$this->contentType = 'application/x-www-form-urlencoded';
			}
		}
		if(!empty($this->contentType)) $this->headers['Content-Type'] = $this->contentType;
	}
	
	/**
	 * Create a boundary for multipart form data
	 */
	private function buildBoundary(){
		$boundary = ''; while(strlen($boundary) < 29) $boundary .= rand(0, 9);
		$delimiter = '-----------------------------' . $boundary;
		$this->boundary = $delimiter;
		$this->contentType = "multipart/form-data; boundary=".$delimiter;
		$this->headers['Content-Type'] = $this->contentType;
	}
	
	/**
	 * Create the requestbody
	 */
	private function buildBody(){
		if($this->hasFiles){
			$this->buildBoundary();
			$postdata = $this->nestedArrayToKVP($this->request_parameters);
			$data = '';
			$eol = "\r\n";
			foreach($postdata as $name => $content){
				if($content instanceof HTTP_File){
					$data .=  "--" . $this->boundary . $eol
						. 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $content->file . '"' . $eol
						. 'Content-Type: ' . $content->type . $eol . $eol;
					$data .= file_get_contents($content->path) . $eol;
				}else{
					$data .=  "--" . $this->boundary . $eol
						. 'Content-Disposition: form-data; name="' . $name . "\"" . $eol . $eol
						. $content . $eol;
				}
			}
			$data .=  "--" . $this->boundary . "--" . $eol;
			$this->request_body = $data;
		}else{
			$this->request_body = http_build_query($this->request_parameters);
		}
		$this->headers['Content-Length'] = strlen($this->request_body);
	}
	
	/**
	 * Get the headers as a string
	 * @return string
	 */
	private function buildHeaders(){
		$headers = array("{$this->method} {$this->path} HTTP/1.1");
		$host = isset($this->headers['host']) ? $this->headers['host'] : $this->host;
		$headers[] = "Host: $host";
		foreach($this->headers as $k=>$v){
			if($k === "Host") continue;
			if(in_array($k, $this->omitHeaders)) continue;
			if(is_array($v)) foreach($v as $val) $headers = "$k: $val";
			else $headers[] = "$k: $v";
		}
		return implode("\r\n", $headers);
	}
	
	/**
	 * Build the full request string
	 * @return string
	 */
	private function buildRequest(){
		$this->validateMethod();
		$this->sniffContentType();
		$body = $this->buildBody();
		$headers = $this->buildHeaders();
		return $headers . "\r\n\r\n" . $this->request_body . "\r\n\r\n";
	}
	
	/**
	 * Takes a multidimensional array and converts it to a flat array with bracketed keys
	 * @param type $ar
	 * @param type $key_prepend
	 * @return type
	 */
	private function nestedArrayToKVP($ar, $key_prepend=false){
		$kvp = array();
		if(!is_array($ar)) return $ar;
		foreach($ar as $k=>$v){
			$key = $k;
			if($key_prepend) $key = $key_prepend."[".$key."]";
			if(is_array($v)){
				$values = $this->nestedArrayToKVP($v, $key);
				foreach($values as $kk=>$vv) $kvp[$kk] = $vv;
			}else{
				$kvp[$key] = $v;
			}
		}
		return $kvp;
	}
	
}

class HTTP_Response{
	
	/**
	 * Description of the status code
	 * @var string 
	 */
	private $http_reson_phrase;
	
	/**
	 * HTTP response code
	 * @var number 
	 */
	private $http_status;
	
	/**
	 * HTTP Version
	 * @var string 
	 */
	private $version;
	
	/**
	 * Array of headers returned from the server
	 * @var array 
	 */
	private $headers = array();
	
	/**
	 * The raw body of the request
	 * @var type 
	 */
	private $body;
	
	public function __construct($resp){
		$parts = explode("\r\n\r\n", $resp);		
		$headers = array_shift($parts);
		$headers = explode("\r\n",$headers);
		$start_line = array_shift($headers);
		$details = explode(' ', $start_line);
		$this->version = array_shift($details);
		$this->http_status = array_shift($details);
		$this->http_reson_phrase = implode(' ', $details);
		foreach($headers as $header){
			$hparts = explode(":", $header);
			$key = array_shift($hparts);
			$value = implode(" ", $hparts);
			if(isset($this->headers[$key])) $this->headers[$key] = array($this->headers[$key], trim($value));
			else $this->headers[$key] = trim($value);
		}
		$this->body = implode("\r\n\r\n", $parts);
	}
	
	/**
	 * Get the body of the response
	 * @return string
	 */
	public function getBody(){ return $this->body; }
	
	/**
	 * Get the response headers
	 * @return array
	 */
	public function getHeaders(){ return $this->headers; }
}

class HTTP_Cookiejar{
	
	/**
	 * The cookiejar file
	 * @var string
	 */
	private $filename;
	
	/**
	 * Cookies array
	 * @var array
	 */
	private $cookies = array();
	
	/**
	 * unix timestamp of the creation time
	 * @var number 
	 */
	private $created;
	
	/**
	 * Unix timestamp of the last modification made
	 * @var number 
	 */
	private $modified;
	
	public function __construct($filename){
		$this->filename = $filename;
		$this->validateFile();
	}
	
	/**
	 * Ensure the cookiejar file exists and contains valid data
	 * @throws Exception
	 */
	private function validateFile(){
		$fh = fopen($filename, "w");
		fclose($fh);
		if(!is_readable($filename) || !is_writable($filename)) throw new Exception("Cannot read or write cookiefile.");
		$contents = file_get_contents($filename);
		if(empty($contents)) $this->initCookiejar();
		else{
			$json = json_decode($contents);
			if(json_last_error() !== JSON_ERROR_NONE) $this->initCookiejar();
			if(!$this->validateData($json)) $this->initCookiejar();
		}
	}
	
	/**
	 * validate the data structure
	 * @param type $data
	 * @return boolean
	 */
	private function validateData($data){
		if(!isset($data['created']) || !is_numeric($data['created'])) return false;
		if(!isset($data['modified']) || !is_numeric($data['modified'])) return false;
		if(!is_array($data['cookies'])) return false;
		$params = array('name','value','Expires','Max-Age','Domain','Path','Secure','HttpOnly','SameSite');
		foreach($data['cookies'] as $cookie){
			foreach($params as $param){
				if(!isset($cookie[$param])) return false;
			}
		}
		return true;
	}
	
	private function initCookiejar(){
		$data = array(
			'created' => time(),
			'modified' => time(),
			'cookies' => array()
		);
		file_put_contents($this->filename, json_encode($data));
	}
	
}