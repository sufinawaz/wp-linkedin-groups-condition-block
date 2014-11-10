<?php
defined('ABSPATH') or die("No script kiddies please!");

function oauth_session_exists() {
  if((is_array($_SESSION)) && (array_key_exists('oauth', $_SESSION))) {
    return TRUE;
  } else {
    return FALSE;
  }
}
function init_sessions() {
    if (!session_id()) {
		if(!session_start()) {
			throw new LinkedInException('This script requires session support, which appears to be disabled according to session_start().');
		}
	}
}
init_sessions();
function inUser($url) {
	try {
	  $API_CONFIG = array(
		'appKey'       => get_option( 'linkedin_api_key_opt' ) ,
		'appSecret'    => get_option( 'linkedin_api_secret_opt' ) ,
		'callbackUrl'  => NULL
	  );
	  define('PORT_HTTP', '80');
	  define('PORT_HTTP_SSL', '443');
	  // set index
	  $_REQUEST[LINKEDIN::_GET_TYPE] = (isset($_REQUEST[LINKEDIN::_GET_TYPE])) ? $_REQUEST[LINKEDIN::_GET_TYPE] : '';
	  switch($_REQUEST[LINKEDIN::_GET_TYPE]) {
		case 'initiate':
		  /**
		   * Handle user initiated LinkedIn connection, create the LinkedIn object.
		   */
		  // check for the correct http protocol (i.e. is this script being served via http or https)
		  if(isset( $_SERVER["HTTPS"] ) && strtolower( $_SERVER["HTTPS"] ) == "on") {
			$protocol = 'https';
		  } else {
			$protocol = 'http';
		  }
		  // set the callback url
		  $API_CONFIG['callbackUrl'] = $url.'?' . LINKEDIN::_GET_TYPE . '=initiate&' . LINKEDIN::_GET_RESPONSE . '=1';
		  $OBJ_linkedin = new LinkedIn($API_CONFIG);
		  //echo $API_CONFIG['callbackUrl'];
		  // check for response from LinkedIn
		  $_GET[LINKEDIN::_GET_RESPONSE] = (isset($_GET[LINKEDIN::_GET_RESPONSE])) ? $_GET[LINKEDIN::_GET_RESPONSE] : '';
		  if(!$_GET[LINKEDIN::_GET_RESPONSE]) {
			// LinkedIn hasn't sent us a response, the user is initiating the connection
			// send a request for a LinkedIn access token
			$response = $OBJ_linkedin->retrieveTokenRequest();
			if($response['success'] === TRUE) {
			  // store the request token
			  $_SESSION['oauth']['linkedin']['request'] = $response['linkedin'];
			  // redirect the user to the LinkedIn authentication/authorisation page to initiate validation.
			  header('Location: ' . LINKEDIN::_URL_AUTH . $response['linkedin']['oauth_token']);
			  //echo LINKEDIN::_URL_AUTH . $response['linkedin']['oauth_token'];
			} else {
			  // bad token request
			  echo "Request token retrieval failed:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
			  return false; 
			}
		  } else {
			// LinkedIn has sent a response, user has granted permission, take the temp access token, the user's secret and the verifier to request the user's real secret key
			$response = $OBJ_linkedin->retrieveTokenAccess($_SESSION['oauth']['linkedin']['request']['oauth_token'], $_SESSION['oauth']['linkedin']['request']['oauth_token_secret'], $_GET['oauth_verifier']);
			if($response['success'] === TRUE) {
			  // the request went through without an error, gather user's 'access' tokens
			  $_SESSION['oauth']['linkedin']['access'] = $response['linkedin'];
			  // set the user as authorized for future quick reference
			  $_SESSION['oauth']['linkedin']['authorized'] = TRUE;
			  // redirect the user back to the demo page
			  header('Location: ' . $url);
			} else {
			  // bad token access
			  echo "Access token retrieval failed:<br /><br />RESPONSE:<br /><br /><pre>" . print_r($response, TRUE) . "</pre><br /><br />LINKEDIN OBJ:<br /><br /><pre>" . print_r($OBJ_linkedin, TRUE) . "</pre>";
			  return false; 
			}
		  }
		  break;
		case 'revoke':
		  /**
		   * Handle authorization revocation.
		   */
		  // check the session
		  if(!oauth_session_exists()) {
			throw new LinkedInException('This script requires session support, which doesn\'t appear to be working correctly.');
		  }
		  $OBJ_linkedin = new LinkedIn($API_CONFIG);
		  $OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
		  $response = $OBJ_linkedin->revoke();
		  if($response['success'] === TRUE) {
			// revocation successful, clear session
			session_unset();
			$_SESSION = array();
			if(session_destroy()) {
			  // session destroyed
			  header('Location: ' . $url);
			}
		  }
		  break;
		default:
		  // nothing being passed back, display demo page
		  // check PHP version
		  if(version_compare(PHP_VERSION, '5.0.0', '<')) {
			throw new LinkedInException('You must be running version 5.x or greater of PHP to use this library.');
		  }
		  // check for cURL
		  if(extension_loaded('curl')) {
			$curl_version = curl_version();
			$curl_version = $curl_version['version'];
		  } else {
			throw new LinkedInException('You must load the cURL extension to use this library.');
		  }
			  $_SESSION['oauth']['linkedin']['authorized'] = (isset($_SESSION['oauth']['linkedin']['authorized'])) ? $_SESSION['oauth']['linkedin']['authorized'] : FALSE;
			  if($_SESSION['oauth']['linkedin']['authorized'] === TRUE) {
				// user is already connected
				$OBJ_linkedin = new LinkedIn($API_CONFIG);
				$OBJ_linkedin->setTokenAccess($_SESSION['oauth']['linkedin']['access']);
				$OBJ_linkedin->setResponseFormat(LINKEDIN::_RESPONSE_XML);
			  }
			  if($_SESSION['oauth']['linkedin']['authorized'] === TRUE) {
				$response = $OBJ_linkedin->groupMemberships();
				 echo "<pre>"; print_r($response['linkedin']);
				 echo "</pre>";
				if (strpos($response['linkedin'], "1014" ) !== false && strpos($response['linkedin'], "Graduate Business Forum (Alumni Group)" ) !== false) {
					return "isMember";
				} else {
					return "isNotMember";
				}
			  } else {
				// user isn't connected
				return false;
			  }
		  break;
	  }
	} catch(LinkedInException $e) {
	  // exception raised by library call
	  echo $e->getMessage();
	  return false;
	}
	return false;
}
function getRevokeForm(){
	return 	'<form id="linkedin_revoke_form" method="get">
				<input type="hidden" name="'.LINKEDIN::_GET_TYPE.'" id="'.LINKEDIN::_GET_TYPE.'" value="revoke" />
				<input type="submit" value="Logout" />
			</form>';
}
function getLoginForm(){
	return 	'<form id="linkedin_connect_form" method="get">
			  <input type="hidden" name="'.LINKEDIN::_GET_TYPE.'" id="'.LINKEDIN::_GET_TYPE.'" value="initiate" />
			  <input type="submit" value="Login with LinkedIn" />
			</form>';
}
function getRevokeLink(){
	return 	'<a href="'.$_SERVER['PHP_SELF'].'?'.LINKEDIN::_GET_TYPE.'=revoke" >Logout</a>';
}
function getLoginLink(){
	return 	'<a href="'.$_SERVER['PHP_SELF'].'?'.LINKEDIN::_GET_TYPE.'=initiate" />Login with LinkedIn</a>';
}
//add_shortcode( 'eailinkedin', 'eailinkedin' );
//define( 'eailinkedin_path', plugin_dir_path(__FILE__) );
function eailinkedin() {
	$test = inUser("http://localhost/in/linkedin.php");
	$str = "";
	if( $test === false ) {
		$str .= "<br /><strong>You must ".getLoginLink()." to view entire content of this page!</strong><br />";
	} else if ( $test == 'isMember' ) {
		//$str .= $content;
		$str .= getRevokeLink();
	} else if ( $test == 'isNotMember' ) {
		$str .= '<span style="display: block;
					border: 1px solid #EDEF00;
					background: #FFC;
					color: #777;
					color: black;
					border-radius: 5px;
					padding: 20px 32px 20px 20px;
					margin: 5px auto;
					word-wrap: break-word;
					color: #333;
					">Sorry, but you must be a member of the <a href="http://www.linkedin.com/groups?gid=1014" title="Graduate Business Forum on LinkedIn">Graduate Business Forum</a> to view this page.</span>';
		$str .= getRevokeLink();
	}
	return $str;
}
echo eailinkedin();
?>

