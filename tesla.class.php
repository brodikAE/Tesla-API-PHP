<?php

/*
	Tesla API PHP
	class file
	v.0.0.2
*/

class Tesla{

	function __construct($debug=false){

		$this->en_debug = $debug;
		$this->version = "0.0.2";
		$this->api_oauth2 = "https://auth.tesla.com/oauth2/v3";
		$this->api_redirect = "https://auth.tesla.com/void/callback";
		$this->api_owners = "https://owner-api.teslamotors.com/oauth/token";
		$this->api_url = "https://owner-api.teslamotors.com/";
		$this->vehicle_list_fix = "api/1/products?orders=true"; //VEHICLE_LIST fix 2024/01/28
		$this->api_code_vlc = 86;
		$this->user_agent = "TeslaPHP/".$this->version;
		$dir = ".".DIRECTORY_SEPARATOR;
		$this->cookie_file = $dir."cookies.txt";
		$this->token_file = $dir."token.txt";
		$this->debug_file = $dir."debug.txt";
		require_once "template.php";
		$this->template = new template;
		$this->load_endpoints();
		$this->get_token();
	}

	function _connect($url, $returntransfer=1, $referer="", $http_header="", $post="", $need_header=1, $cookies="", $timeout=10){

		!empty($post) ? $cpost = 1 : $cpost = 0;
		is_array($http_header) ? $chheader = 1 : $chheader = 0;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, $returntransfer);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_HEADER, $need_header);
		curl_setopt($ch, CURLOPT_POST, $cpost);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //1
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //2
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		if(defined('CURL_SSLVERSION_MAX_TLSv1_2'))
			curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_2);

		if(!empty($referer))
			curl_setopt($ch, CURLOPT_REFERER, $referer);

		if($chheader == 1)
			curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);

		if($cpost == 1)
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		
		if(!empty($cookies))
			curl_setopt($ch, CURLOPT_COOKIE, $cookies);

		$response = curl_exec($ch);
		$header = curl_getinfo($ch);
		curl_close($ch);

		if($this->en_debug)
			$this->debug("Response: ".$response);
		if($this->en_debug)
			$this->debug("Header: ".json_encode($header));

		return array("response" => $response, "header" => $header);
	}

	function generateRandomString($length=10) {

		$character_list = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		return substr(str_shuffle(str_repeat($character_list, ceil($length/strlen($character_list)) )),1,$length);
	}

	function pkce_code_challenge($verifier) {

		$hash = hash('sha256', $verifier, true);
		return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
	}

	function gen_challenge(){

		$code_verifier = $this->generateRandomString($this->api_code_vlc);
		$code_challenge = $this->pkce_code_challenge($code_verifier); 
		$state = rtrim(strtr(base64_encode($this->generateRandomString(12)), '+/', '-_'), '='); 

		$array = array("code_verifier" => $code_verifier, "code_challenge" => $code_challenge, "state" => $state);

		if($this->en_debug)
			$this->debug(json_encode($array));

		return $array;
	}

	function gen_url($code_challenge, $state){

		$datas = array(
			'client_id' => 'ownerapi',
			'code_challenge' => $code_challenge,
			'code_challenge_method' => 'S256',
			'redirect_uri' => $this->api_redirect,
			'response_type' => 'code',
			'scope' => 'openid email offline_access',
			'state' => $state
		);

		$url_req = $this->api_oauth2."/authorize?".http_build_query($datas);

		if($this->en_debug)
			$this->debug("URL request: ".$url_req);

		return $url_req;
	}

	function return_msg($code, $msg){

		return json_encode(array("success" => $code, "message" => $msg));
	}

	function login($weburl, $code_verifier){

		parse_str(parse_url($weburl, PHP_URL_QUERY), $query);
		$code = $query["code"];

		if($this->en_debug)
			$this->debug("code: ".$code);

		if(empty($code)){
			return $this->return_msg(0, "Code not exists");
		}

		$http_header = array('Content-Type: application/json', 'Accept: application/json', 'User-Agent: {$this->user_agent}');
		$post = json_encode(array("grant_type" => "authorization_code", "client_id" => "ownerapi", "code" => $code, "code_verifier" => $code_verifier, "redirect_uri" => $this->api_redirect));
		$response = $this->_connect($this->api_oauth2."/token", 1, "", $http_header, $post, 0);

		$tokens = json_decode($response["response"], true, 512, JSON_BIGINT_AS_STRING);

		if($this->en_debug)
			$this->debug("Login tokens: ".$response["response"]);

		if(empty($tokens['access_token']))
			return $this->return_msg(0, "Error: '" . $tokens['error'] . "' Description: '" . $tokens['error_description'] . "'");

		$now = new DateTime();
		$tokens["created_at"] = $now->getTimestamp();
		$return_message = json_encode($tokens);

		return $this->return_msg(1, $return_message);
	}

	function refresh($bearer_refresh_token){

		$http_header = array('Content-Type: application/json', 'Accept: application/json', 'User-Agent: {$this->user_agent}');
		$post = json_encode(array("grant_type" => "refresh_token", "client_id" => "ownerapi", "refresh_token" => $bearer_refresh_token, "scope" => "openid email offline_access"));
		$response = $this->_connect($this->api_oauth2."/token", 1, "https://auth.tesla.com/", $http_header, $post, 0);

		$tokens = json_decode($response["response"], true, 512, JSON_BIGINT_AS_STRING);

		if($this->en_debug)
			$this->debug("Refresh tokens: ".$response["response"]);

		if(empty($tokens['access_token']))
			return $this->return_msg(0, "Token issue");

		$now = new DateTime();
		$this->token = [
			"refresh_token" => $tokens['refresh_token'],
			"access_token"  => $tokens['access_token'],
			"expires_in"    => $tokens['expires_in'],
			"created_at"    => $now->getTimestamp()
		];

		$return_message = json_encode($this->token);

		return $this->return_msg(1, $return_message);
	}

	function html_login(){

		$challenge = $this->gen_challenge();
		$timestamp = time();

		print $this->template->main_page("TeslaPHP Login", $challenge["code_verifier"], $challenge["code_challenge"], $challenge["state"], $_SERVER['REQUEST_URI'], $timestamp, $this->api_redirect, $this->gen_url($challenge["code_challenge"], $challenge["state"]));
	}

	function get_token(){

		$clean_file = false;
		if(file_exists($this->token_file)){
			$_existent = file_get_contents($this->token_file);
			if($_existent !== false){
				$existentJson = json_decode($_existent, true, 512, JSON_BIGINT_AS_STRING);

				if($this->en_debug)
					$this->debug("Existent token: ".$_existent);

				$this->token = [
					"refresh_token" => $existentJson['refresh_token'],
					"access_token"  => $existentJson['access_token'],
					"expires_in"    => $existentJson['expires_in'],
					"created_at"    => $existentJson['created_at']
				];

				if($this->token["access_token"] == "")
					$clean_file = true;

				if($clean_file){
					if(file_exists($this->cookie_file)){
						unlink($this->cookie_file);
					}
					if(file_exists($this->token_file)){
						unlink($this->token_file);
					}
					print "Data removed with success";
					$this->html_login();
					exit;
				}

				$timestamp = time();
				$expiration_date = $this->token["created_at"] + $this->token["expires_in"];

				if($timestamp > $expiration_date){
					$this->refresh($this->token["refresh_token"]);
				}
			}
		}elseif(isset($_REQUEST["go"])){
			switch($_REQUEST["go"]){
				case "login":
					$result = $this->login($_POST["weburl"], $_POST["code_verifier"]);
					$resultJson = json_decode($result, false, 512, JSON_BIGINT_AS_STRING);

					if($resultJson->{'success'} == 1){
						$message = json_decode($resultJson->{'message'}, true, 512, JSON_BIGINT_AS_STRING);

						if($this->en_debug)
							$this->debug("Access token: ".$resultJson->{'message'});

						$this->token = [
							"refresh_token" => $message['refresh_token'],
							"access_token"  => $message['access_token'],
							"expires_in"    => $message['expires_in'],
							"created_at"    => $message['created_at']
						];

						$tk = file_put_contents($this->token_file, json_encode($this->token));

						if($tk === false){
							$msg = "Error saving token file";
						}else{
							$msg = "Token saved with success";
						}
						print $msg;
						if($this->en_debug)
							$this->debug($msg);
					}else{
						print "Error: ".$resultJson->{'message'};
					}
				break;
				default:
					$this->html_login();
					exit;
				break;
			}
		}else{
			$this->html_login();
			exit;
		}
	}

	function API($endpoint, $params=""){

		if(!array_key_exists($endpoint, $this->list_api)){
			if($this->en_debug)
				$this->debug($endpoint." endpoint not exists");
			return false;
		}

		$type = $this->list_api[$endpoint]["TYPE"];
		$uri = $this->list_api[$endpoint]["URI"];
		$auth = $this->list_api[$endpoint]["AUTH"];

		$magic_array = array("{vehicle_id}", "{device_token}", "{battery_id}", "{site_id}", "{file_path}", "{path}", "{incidentsId}", "{serviceVisitID}", "{uuid}", "{invoiceId}", "{trtId}", "{issue-name}");

		if($this->string_contains_any($uri, $magic_array)){
			if(stripos($uri, "{vehicle_id}")){
				if(!isset($this->vehicleId)){
					if($this->en_debug)
						$this->debug("vehicleId is not set");
					return "vehicleId is not set";
				}
				$uri = str_replace("{vehicle_id}", $this->vehicleId, $uri);
			}elseif(stripos($uri, "{battery_id}")){
				if(!isset($this->batteryId)){
					if($this->en_debug)
						$this->debug("batteryId is not set");
					return "batteryId is not set";
				}
				$uri = str_replace("{battery_id}", $this->batteryId, $uri);
			}elseif(stripos($uri, "{site_id}")){
				if(!isset($this->siteId)){
					if($this->en_debug)
						$this->debug("siteId is not set");
					return "siteId is not set";
				}
				$uri = str_replace("{site_id}", $this->siteId, $uri);
			}else{
				if($this->en_debug)
					$this->debug($endpoint." is not supported yet");
				return $endpoint." is not supported yet";
			}
		}

		//VEHICLE_LIST fix 2024/01/28
		if($endpoint == "VEHICLE_LIST")
			$uri = $this->vehicle_list_fix;

		$API_url = $this->api_url.$uri;

		$ch = curl_init();

		$http_header = array('Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer '.$this->token["access_token"]);

		curl_setopt($ch, CURLOPT_URL, $API_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //1
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //2

		if($type == "POST" || $type == "PUT" || $type == "DELETE"){
			if($type == 'POST')
				curl_setopt($ch, CURLOPT_POST, 1);
			if(in_array($type, array("PUT", 'DELETE')))
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
			if(!is_array($params) || count($params) < 1){
				$params = array($params);
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		}

		$apiResult = curl_exec($ch);
		$headerInfo = curl_getinfo($ch);
		$apiResultJson = json_decode($apiResult, true, 512, JSON_BIGINT_AS_STRING);

		if($this->en_debug)
			$this->debug("API header: ".json_encode($headerInfo));
		if($this->en_debug)
			$this->debug("API results: ".$apiResult);

		$result = array();
		if($apiResult === false){
			$result['errorcode'] = 0;
			$result['errormessage'] = curl_error($ch);
			if($this->en_debug)
				$this->debug("Curl error: ".$result['errormessage']);
		}

		if(!in_array($headerInfo['http_code'], array('200', '201', '204'))){
			$result['errorcode'] = $headerInfo['http_code'];
			if(isset($apiResult))
				$result['errormessage'] = $apiResult;
		}

		curl_close($ch);

		return $apiResultJson ?? $apiResult;

    }

	function load_endpoints(){
		$this->list_api = json_decode(file_get_contents("endpoints.json"), true, 512, JSON_BIGINT_AS_STRING);
	}

	function setId($type, $id){

		switch($type){
			case "vehicle":
				$this->vehicleId = $id;
				$return = true;
			break;
			case "battery":
				$this->batteryId = $id;
				$return = true;
			break;
			case "site":
				$this->siteId = $id;
				$return = true;
			break;
			default:
				$return = false;
			break;
		}
		return $return;
	}

    function vehicle_list(){

		return $this->API("VEHICLE_LIST");
	}

    function battery_list(){

		$array = $this->API("PRODUCT_LIST");
		$list = array_filter($array, function($var){
			return ($var["resource_type"] == "battery");
		});
		return $list;
	}

    function solar_list(){

		$array = $this->API("PRODUCT_LIST");
		$list = array_filter($array, function($var){
			return ($var["resource_type"] == "solar");
		});
		return $list;
	}

    function select_vehicle_by_name($name, $returnId=1, $array=""){

		if(!isset($name)){
			if($this->en_debug)
				$this->debug("No name given");
			return false;
		}

		if(!is_array($array) || count($array) < 1){
			$array = $this->vehicle_list()['response'];
		}

		$list = array_filter($array, function($var) use ($name){
			return ($var['display_name'] == $name);
		});

		if(count($list) < 1){
			if($this->en_debug)
				$this->debug("No vehicle found with this name");
			return false;
		}

		$id = $list[0]["id_s"];
		$this->setId("vehicle", $id);

		if($returnId)
			return $id;
		else
			return $list;
	}

	function debug($msg){

		$debug = "\n===================================================\n";
		$debug .= " ".date("D, d M Y H:m:s O")."\n";
		$debug .= " ".$msg."\n";

		file_put_contents($this->debug_file, $debug, FILE_APPEND);
	}

	function string_contains_any($haystack, $needles, $ignoreCase=false){
		foreach($needles as $needle){
			$position = $ignoreCase ? stripos($haystack, $needle) : strpos($haystack, $needle);
			if($position !== false){
				return true;
			}
		}
		return false;
	}

}

?>
