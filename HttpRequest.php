<?php

/**
 * I don't believe in license
 * You can do want you want with this program
 * - gwen -
 */

class HttpRequest
{
	const METHOD_GET = 'GET';
	const METHOD_POST = 'POST';
	const METHOD_PUT = 'PUT';
	const METHOD_DELETE = 'DELETE';
	const METHOD_HEAD = 'HEAD';
	const METHOD_OPTIONS = 'OPTIONS';

	const DEFAULT_TIMEOUT = 5;
	const DEFAULT_METHOD = self::METHOD_GET;
	const DEFAULT_HTTP = 'HTTP/1.1';

	public $request_file = null;

	public $host = '';

	public $port = 0;

	public $ssl = false;

	public $redirect = true;

	public $method = self::DEFAULT_METHOD;

	public $http = self::DEFAULT_HTTP;

	public $scheme = '';

	public $url = '';

	public $headers = [];

	public $cookies = [];
	public $cookie_file = '';

	public $get_params = [];
	
	public $post_params = [];
	
	public $fragment = '';

	public $multipart = false;

	public $content_length = false;

	public $result = '';
	public $result_length = '';
	public $result_code = 0;
	public $result_type = '';
	public $result_header = '';
	public $result_header_size = 0;
	public $result_body = '';
	public $result_body_size = 0;

	public function __construct() {
		$this->cookie_file = tempnam('/tmp', 'cook_');
	}

	public function __clone() {
		$this->result = '';
		$this->result_length = 0;
		$this->result_code = 0;
		$this->result_type = '';
	}


	public function getResultBody() {
		return $this->result_body;
	}
	public function getResultBodySize() {
		return $this->result_body_size;
	}

	public function getResultHeader( $array=true ) {
		if( $array ) {
			$t_headers = [];
			$t = explode( "\n", $this->result_header );
			foreach( $t as $k=>$v ) {
				if( !$k ) {
					continue;
				}
				$v = trim( $v );
				$tmp = explode( ':', $v );
				$key = array_shift( $tmp );
				$t_headers[ $key ] = trim( implode( ':', $tmp ) );
			}
			return $t_headers;
		} else {
			return $this->result_header;
		}
	}

	public function getResult() {
		return $this->result;
	}

	public function getResultLength() {
		return $this->result_length;
	}

	public function getResultCode() {
		return $this->result_code;
	}


	public function getRequestFile() {
		return $this->request_file;
	}
	public function setRequestFile( $v ) {
		if( is_file($v) ) {
			$this->request_file = $v;
			return true;
		} else {
			return false;
		}
	}


	public function getHost() {
		return $this->host;
	}
	public function setHost( $v ) {
		$this->host = $v;
		return true;
	}


	public function getPort() {
		return $this->port;
	}
	public function setPort( $v ) {
		$this->port = (int)$v;
		return true;
	}


	public function getRedirect() {
		return $this->redirect;
	}
	public function setRedirect( $v ) {
		$this->redirect = (bool)$v;
		return true;
	}


	public function getSsl() {
		return $this->ssl;
	}
	public function setSsl( $v ) {
		$this->ssl = (bool)$v;
		return true;
	}


	public function isMultipart() {
		return $this->multipart;
	}
	public function setMultipart( $v ) {
		$this->multipart = (bool)$v;
		return true;
	}


	public function getContentLength() {
		return $this->content_length;
	}
	public function setContentLength( $v ) {
		$this->content_length = (bool)$v;
		return true;
	}


	public function getUrl( $base64=false ) {
		$v = $this->url;
		if( $base64 ) {
			$v = base64_encode( serialize($v) );
		}
		return $v;
	}
	public function setUrl($v) {
		$this->url = $v;
		$parse = parse_url( $this->url );
		$this->url = $parse['path'];
		//var_dump( $parse );
		if( isset($parse['query']) ) {
			$this->get_params = $this->explodeGetParams( $parse['query'] );
		}
		if( isset($parse['fragment']) ) {
			$this->fragment = $parse['fragment'];
		}
		if( isset($parse['host']) ) {
			$this->host = $parse['host'];
		}
		if( isset($parse['host']) ) {
			$this->scheme = $parse['scheme'];
		}
	}
	public function implodeUrl()
	{
		$url = $this->url;
		if( $this->get_params ) {
			$url .= '?'.$this->implodeGetParams();
		}
		if( $this->fragment ) {
			$url .= '#'.$this->fragment;
		}
		if( $this->ssl ) {
			$url = str_replace( 'http://', 'https://', $url );
		}
		return $url;
	}
	public function getFullUrl()
	{
		if( $this->scheme ) {
			$url = $this->scheme.'://';
		} else {
			$url = 'http://';
		}
		$url .= $this->host;
		$url .= $this->url;
		if( $this->get_params ) {
			$url .= '?'.$this->implodeGetParams();
		}
		if( $this->fragment ) {
			$url .= '#'.$this->fragment;
		}
		if( $this->ssl ) {
			$url = str_replace( 'http://', 'https://', $url );
		}
		return $url;
	}
	

	public function getMethod() {
		return $this->method;
	}
	public function setMethod($v) {
		$this->method = strtoupper($v);
	}
	public function isPost() {
		return ($this->method==self::METHOD_POST);
	}


	public function getHttp() {
		return $this->http;
	}
	public function setHttp($v) {
		$this->http = $v;
	}


	public function getHeaderTable()
	{
		return $this->headers;
	}
	public function getHeader( $key, $base64=false ) {
		if( !isset($this->headers[$key]) ) {
			return false;
		}
		$v = $this->headers[$key];
		if( $base64 ) {
			$v = base64_encode( $v );
		}
		return $v;
	}
	public function unsetHeader($key) {
		if( isset($this->headers[$key]) ) {
			unset( $this->headers[$key] );
			return true;
		} else {
			return false;
		}
	}
	public function setHeader($v, $key) {
		$this->headers[$key] = $v;
	}
	public function getHeaders( $base64=false ) {
		$v = $this->headers;
		if( $base64 ) {
			$v = base64_encode( serialize($v) );
		}
		return $v;
	}
	public function setHeaders($array) {
		foreach ($array as $k => $v) {
			$this->setHeader($v, $k);
		}
	}
	public function getSpecialHeaders() // for this fucking curl!
	{
		$tab = [];
		foreach( $this->headers as $k=>$v ) {
			$tab[$k] = $k.': '.$v;
		}
		return $tab;
	}
	

	public function getCookieTable()
	{
		return $this->cookies;
	}
	public function getCookie( $key, $base64=false )
	{
		if( !isset($this->cookies[$key]) ) {
			return false;
		}
		$v = $this->cookies[$key];
		if( $base64 ) {
			$v = base64_encode( $v );
		}
		return $v;
	}
	public function unsetCookie($key)
	{
		if( isset($this->cookies[$key]) ) {
			unset( $this->cookies[$key] );
			return true;
		} else {
			return false;
		}
	}
	public function setCookie($v, $key)
	{
		$this->cookies[$key] = $v;
	}
	public function getCookies( $base64=false )
	{
		$v = $this->implodeCookies();
		if( $base64 ) {
			$v = base64_encode( $v );
		}
		return $v;
	}
	public function setCookies($v)
	{
		if( !is_array($v) ) {
			$v = $this->explodeCookies( $v );
		}
		$this->cookies = $v;
	}
	public function explodeCookies( $cookies )
	{
		$tab = [];
		$cookies = trim( $cookies );
		
		if( strlen($cookies) )
		{
			$t_params = explode( ';', $cookies );
			
			foreach( $t_params as $p ) {
				$tmp = explode( '=', $p );
				$k = $tmp[0];
				$v = isset($tmp[1]) ? $tmp[1] : '';
				$tab[ trim($k) ] = trim($v);
			}
		}
		
		return $tab;
	}
	public function implodeCookies()
	{
		$str = '';
		
		if( is_array($this->cookies) && count($this->cookies) ) {
			foreach( $this->cookies as $k=>$v ) {
				$str .= $k.'='.$v.'; ';
			}
			$str = trim( $str, '; ' );
		}

		return $str;
	}


	public function getGetTable()
	{
		return $this->get_params;
	}
	public function getGetParam( $key, $base64=false ) {
		if( !isset($this->get_params[$key]) ) {
			return false;
		}
		$v = $this->get_params[$key];
		if( $base64 ) {
			$v = base64_encode( $v );
		}
		return $v;
	}
	public function unsetGetParam($key) {
		if( isset($this->get_params[$key]) ) {
			unset( $this->get_params[$key] );
			return true;
		} else {
			return false;
		}
	}
	public function setGetParam($v, $key) {
		$this->get_params[$key] = $v;
	}
	public function getGetParams( $base64=false )
	{
		$v = $this->implodeGetParams();
		if( $base64 ) {
			$v = base64_encode( $v );
		}
		return $v;
	}
	public function setGetParams($v)
	{
		if( !is_array($v) ) {
			$v = $this->explodeGetParams( $v );
		}
		$this->get_params = $v;
		return true;
	}
	public function explodeGetParams( $get )
	{
		$tab = [];
		$get = trim( $get );

		if( strlen($get) )
		{
			$t_params = explode( '&', $get );
			
			foreach( $t_params as $p ) {
				$tmp = explode( '=', $p );
				$k = $tmp[0];
				$v = isset($tmp[1]) ? $tmp[1] : '';
				$tab[ trim($k) ] = trim($v);
			}
		}
		
		return $tab;
	}
	public function implodeGetParams()
	{
		$str = '';
		
		if( is_array($this->get_params) && count($this->get_params) ) {
			foreach( $this->get_params as $k=>$v ) {
				$str .= $k.'='.$v.'&';
			}
			$str = trim( $str, '&' );
		}

		return $str;
	}
	
	
	public function getPostTable()
	{
		return $this->post_params;
	}
	public function getPostParam( $key, $base64=false ) {
		if( !isset($this->post_params[$key]) ) {
			return false;
		}
		$v = $this->post_params[$key];
		if( $base64 ) {
			$v = base64_encode( $v );
		}
		return $v;
	}
	public function unsetPostParam($key) {
		if( isset($this->post_params[$key]) ) {
			unset( $this->post_params[$key] );
			return true;
		} else {
			return false;
		}
	}
	public function setPostParam($v, $key) {
		$this->post_params[$key] = $v;
	}
	public function getPostParams( $base64=false )
	{
		$v = $this->implodePostParams();
		if( $base64 ) {
			$v = base64_encode( $v );
		}
		return $v;
	}
	public function setPostParams($v)
	{
		if( !is_array($v) ) {
			$v = $this->explodePostParams( $v );
		}
		$this->post_params = $v;
	}
	public function explodePostParams( $post )
	{
		$tab = [];
		$post = trim( $post );
		
		if( strlen($post) )
		{
			$t_params = explode( '&', $post );
			
			foreach( $t_params as $p ) {
				$tmp = explode( '=', $p );
				$k = $tmp[0];
				$v = isset($tmp[1]) ? $tmp[1] : '';
				$tab[ trim($k) ] = trim($v);
			}
		}
		
		return $tab;
	}
	public function implodePostParams()
	{
		$str = '';
		
		if( is_array($this->post_params) && count($this->post_params) ) {
			foreach( $this->post_params as $k=>$v ) {
				$str .= $k.'='.$v.'&';
			}
			$str = trim( $str, '&' );
		}

		return $str;
	}
	
	
	public function request()
	{
		$surplace = array();
		
		$c = curl_init();
		curl_setopt( $c, CURLOPT_CUSTOMREQUEST, $this->method );
		curl_setopt( $c, CURLOPT_URL, $this->getFullUrl() );
		curl_setopt( $c, CURLOPT_HTTP_VERSION, $this->http );
		curl_setopt( $c, CURLOPT_HEADER, true );
		if( $this->port ) {
			curl_setopt( $c, CURLOPT_PORT, $this->port );
		}
		curl_setopt( $c, CURLOPT_SSL_VERIFYPEER, false );
		//curl_setopt( $c, CURLOPT_NOBODY, true );
		//curl_setopt($c, CURLOPT_PROXY, '127.0.0.1:9050' );
		//curl_setopt($c, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5 );
		curl_setopt( $c, CURLOPT_TIMEOUT, self::DEFAULT_TIMEOUT );
		curl_setopt( $c, CURLOPT_FOLLOWLOCATION, $this->redirect );
		if( count($this->cookies) ) {
			curl_setopt( $c, CURLOPT_COOKIE, $this->implodeCookies() );
		}
		//curl_setopt( $c, CURLOPT_COOKIEJAR, $this->cookie_file );
		//curl_setopt( $c, CURLOPT_COOKIEFILE, $this->cookie_file );
		if( count($this->post_params) ) {
			if( $this->content_length ) {
				// this header seems to fuck the request...
				//$surplace['Content-Length'] = 'Content-Length: '.strlen( $this->params );
				// but this works great!
				$surplace['Content-Length'] = 'Content-Length: 0';
			}
			if( $this->isPost() && count($this->post_params) ) {
				curl_setopt( $c, CURLOPT_POST, true );
				curl_setopt( $c, CURLOPT_POSTFIELDS, $this->implodePostParams() );
			}
		}
		curl_setopt( $c, CURLOPT_HTTPHEADER, array_merge($this->getSpecialHeaders(),$surplace) );
		curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
		
		$this->result = curl_exec( $c );
		$this->result_info = curl_getinfo( $c );
		$this->result_code = $this->result_info['http_code'];
		$type = explode( ' ', $this->result_info['content_type'] );
		$this->result_type = trim( $type[0], ' ,;' );
		$this->result_header_size = $this->result_info['header_size'];
		$this->result_length = strlen($this->result);
		$this->result_body_size = $this->result_length - $this->result_header_size;
		$this->result_header = trim( substr( $this->result, 0, $this->result_header_size ) );
		$this->result_body = trim( substr( $this->result, $this->result_header_size ) );
		//var_dump( $this->result );
		//exit();
	}


	public function loadFile( $file )
	{
		if( !$this->setRequestFile($file) ) {
			return false;
		}
		
		$request = trim( file_get_contents($file) ); // the full request
		$request = str_replace( "\r", "", $request );
		$t_request = explode( "\n\n", $request ); // separate headers and post parameters
		$t_headers = explode( "\n", array_shift($t_request) ); // headers
		$h_request = array_map( function($str){return explode(':',trim($str));}, $t_headers ); // splited headers
		array_shift( $h_request );

		$first = array_shift( $t_headers ); // first ligne is: method, url, http version
		list( $method, $url, $http ) = explode( ' ', $first );

		$host = '';
		$h_replay = []; // headers kept in the replay request

		foreach( $h_request as $header )
		{
			$h = trim( array_shift($header) );
			$v = trim( implode(':',$header) );

			switch( $h )
			{
				case 'Accept-Encoding':
				case 'Content-Length':
					break;

				case 'Cookie':
					//$cookies = $h.': '.$v;
					$cookies = $v;
					break;

				case 'Host':
					$host = $v;
					break;

				/*case 'Accept':
				case 'Accept-Language':
				case 'Connection':
				case 'Referer':
				case 'User-Agent':
				case 'x-ajax-replace':
				case 'X-Requested-With':*/
				case 'Content-Type':
					if( stristr($v,'multipart') !== false ) {
						$this->setMultipart( true );
					}
				default:
					$h_replay[ $h ] = $v;
					break;
			}
		}
		
		$params = ''; // post parameters
		if( count($t_request) ) {
			$this->setPostParams( $this->explodePostParams($t_request[0]) );
		}
		
		if( isset($cookies) ) {
			$this->setCookies( $cookies );
		}
		
		$this->setUrl( $url );
		$this->setHost( $host );
		$this->setMethod( $method );
		$this->setHttp( $http );
		$this->setHeaders( $h_replay );
		
		return true;
	}


	public function export( $echo=true )
	{
		$output = '';
		$output .= $this->method.' '.$this->implodeUrl().' '.$this->http."\n";
		$output .= 'Host: '.$this->host."\n";
		
		/*foreach( $this->headers as $k=>$h ) {
			$output .= $k.": ".$h."\n";
		}*/
		$h = implode( "\n", $this->getSpecialHeaders() );
		if( strlen($h) ) {
			$output .= $h."\n";
		}
		
		$c = $this->implodeCookies();
		if( strlen($c) ) {
			$output .= 'Cookies: '.$c."\n\n";
		}
		
		$output .= $this->implodePostParams();

		if( $echo ) {
			echo $output;
		} else {
			return $output;
		}
	}
}
