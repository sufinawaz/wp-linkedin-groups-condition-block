<?php
define('API_KEY',      get_option( 'sufi_linkedin_opts' )["key"]);
define('API_SECRET',   get_option( 'sufi_linkedin_opts' )["secret"]);
define('REDIRECT_URI', 'http://localhost/wp/?page_id=6');
define('SCOPE',        'r_basicprofile r_emailaddress rw_groups');
 
// You'll probably use a database
session_name('linkedin');
session_start();
 
function getAuthorizationCode() {
    $params = array(
        'response_type' => 'code',
        'client_id' => get_option( 'sufi_linkedin_opts' )["key"],
        'scope' => SCOPE,
        'state' => uniqid('', true), // unique long string
        'redirect_uri' => REDIRECT_URI,
    );
 
    // Authentication request
    $url = 'https://www.linkedin.com/uas/oauth2/authorization?' . http_build_query($params);
     
    // Needed to identify request when it returns to us
    $_SESSION['state'] = $params['state'];
 
    // Redirect user to authenticate
    // header("Location: $url");
    // echo "<br />";
    return $url;
}
     
function getAccessToken() {
    $params = array(
        'grant_type' => 'authorization_code',
        'client_id' => API_KEY,
        'client_secret' => API_SECRET,
        'code' => $_GET['code'],
        'redirect_uri' => REDIRECT_URI,
    );
     
    // Access Token request
    $url = 'https://www.linkedin.com/uas/oauth2/accessToken?'. http_build_query($params);
     
    // Tell streams to make a POST request
    $context = stream_context_create(
        array('http' => 
            array('method' => 'POST',
            )
        )
    );
 
    // Retrieve access token information
    // print  "entered access token";
    $response = @file_get_contents($url, false, $context);

    // var_dump( $response);
 
    // Native PHP object, please
    $token = json_decode($response);
 
    // Store access token and expiration time
    // echo "<pre>";
    // var_dump($token);
    // echo "</pre>";
    if(isset($token)){
    $_SESSION['access_token'] = $token->access_token; // guard this! 
    // echo $_SESSION['access_token'];
    $_SESSION['expires_in']   = $token->expires_in; // relative time (in seconds)
    $_SESSION['expires_at']   = time() + $_SESSION['expires_in']; // absolute time
     
    return true;
    } else {
        return false;
    }
}
 
function fetch($method, $resource, $body = '') {
    // $_SESSION['access_token']="";
    // print $_SESSION['access_token'];
    $opts = array(
        'http'=>array(
            'method' => $method,
            'header' => "Authorization: Bearer " . $_SESSION['access_token'] . "\r\n" . "x-li-format: json\r\n"
        )
    );
 
    // Need to use HTTPS
    $url = 'https://api.linkedin.com' . $resource;
 
    // Append query parameters (if there are any)
    if (isset($params) && count($params)) { $url .= '?' . http_build_query($params); }
 
    // Tell streams to make a (GET, POST, PUT, or DELETE) request
    // And use OAuth 2 access token as Authorization
    $context = stream_context_create($opts);
 
    // Hocus Pocus
    try{
        $response = @file_get_contents($url, false, $context);
        // Native PHP object, please
        return json_decode($response);
    } catch (Exception $e) {
        return null;
    }
}
?>