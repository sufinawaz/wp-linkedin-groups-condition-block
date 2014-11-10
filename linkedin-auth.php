<?php
$opts = get_option( 'sufi_linkedin_opts' );
define( 'API_KEY',      $opts["key"] );
define( 'API_SECRET',   $opts["secret"] );
define( 'REDIRECT_URI', $opts["uri"] );
define( 'SCOPE',        'r_emailaddress rw_groups' );

session_name( 'linkedin' );
session_start();

function getAuthorizationCode() {
    $opts= get_option( 'sufi_linkedin_opts' );
    $params = array(
        'response_type' => 'code',
        'client_id' =>$opts["key"],
        'scope' => SCOPE,
        'state' => uniqid( '', true ),
        'redirect_uri' => REDIRECT_URI
    );
    $url = 'https://www.linkedin.com/uas/oauth2/authorization?' . http_build_query( $params );
    $_SESSION['state'] = $params['state'];
    return $url;
}

function getAccessToken() {
    // Request the Access Token
    $params = array(
        'grant_type' => 'authorization_code',
        'client_id' => API_KEY,
        'client_secret' => API_SECRET,
        'redirect_uri' => REDIRECT_URI,
        'code' => $_GET['code']
    );
    $url = 'https://www.linkedin.com/uas/oauth2/accessToken?'. http_build_query( $params );
    $context = stream_context_create(
        array( 'http' =>
            array( 'method' => 'POST',
            )
        )
    );
    $response = file_get_contents( $url, false, $context );
    $token = json_decode( $response );
    if ( isset( $token ) ) {
        $_SESSION['access_token'] = $token->access_token;
        $_SESSION['expires_in']   = $token->expires_in;
        $_SESSION['expires_at']   = time() + $_SESSION['expires_in'];
        return true;
    } else {
        return false;
    }
}

function fetch( $method, $resource, $body = '' ) {
    $opts = array(
        'http'=>array(
            'method' => $method,
            'header' => "Authorization: Bearer " . $_SESSION['access_token'] . "\r\n" . "x-li-format: json\r\n"
        )
    );
    $url = 'https://api.linkedin.com' . $resource;
    if ( isset( $params ) && count( $params ) ) { $url .= '?' . http_build_query( $params ); }
    $context = stream_context_create( $opts );
    try{
        $response = @file_get_contents( $url, false, $context );
        return json_decode( $response );
    } catch ( Exception $e ) {
        return null;
    }
}
?>
