<?php
/**
 * @package LinkedIn Group Membership Conditional Blocks
 * @version 0.1.0
 */
/*
Plugin Name: LinkedIn Group Membership Conditional Blocks
Plugin URI: http://sufinawaz.com/plugins/linkedin-groups-block
Description: With this plugin one can use short codes to hide/show a block of codes depending on whether or not a user belongs to a LinkedIn group. This requires users to authenticate using LinkedIn. 
Author: Sufi Nawaz
Version: 0.1.0
Author URI: http://sufinawaz.com/
*/

defined('ABSPATH') or die("No script kiddies please!");

foreach ( glob( plugin_dir_path( __FILE__ ) . "/*.php" ) as $file ) {
    include_once $file;
}

/*****************************************************
 *  Options Menu       								 *
 *****************************************************/

/** Step 1. */
function linkedin_api_menu() {
	add_options_page( 'LinkedIn', 'LinkedIn Groups', 'manage_options', 'linkedin-groups-block', 'linkedin_api_options' );
}

/** Step 2. */
add_action( 'admin_menu', 'linkedin_api_menu' );

/** Step 3. */
function linkedin_api_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	// variables for the field and option names 
	$hidden_field_name = 'mt_submit_hidden';
	$key_field_name = 'linkedin_api_key';
	$secret_field_name = 'linkedin_api_secret';
	$uri_field_name = 'linkedin_api_redirect_uri';

    // Read in existing option value from database
    $optsArray = get_option( 'sufi_linkedin_opts' );
    $key_val = $optsArray["key"];
    $secret_val = $optsArray["secret"];
    $uri_val = $optsArray["uri"];

    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
        // Read their posted value
        $key_val = $_POST[ $key_field_name ];
        $secret_val = $_POST[ $secret_field_name ];
        $uri_val = $_POST[ $uri_field_name ];

        $opts = array(
        	"key"=>$key_val,
        	"secret"=>$secret_val,
        	"uri"=>$uri_val
        	);
        // Save the posted value in the database
        update_option( 'sufi_linkedin_opts', $opts );
        // Put an settings updated message on the screen
        echo '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
    }
    echo '<div class="wrap">';
    echo "<h2>" . __( 'LinkedIn API Settings', 'linkedin-groups-block' ) . "</h2>";

	// settings form
	echo <<<HEREDOC
		<p>If you do not have an API key and secret, please click <a href="https://www.linkedin.com/secure/developer?newapp=" target="_blank" title="Create new LinkedIn Application">here</a> to create a new LinkedIn Application.</p>
		<form name="linkedin-api-form" method="post" action="">
		<input type="hidden" name="$hidden_field_name" value="Y">
		<p>API Key: <input type="text" name="$key_field_name" value="$key_val" size="20"></p>
		<p>API Secret: <input type="text" name="$secret_field_name" value="$secret_val" size="20"></p>
		<p>API Redirect URI: <input type="text" name="$uri_field_name" value="$uri_val" size="20"></p>
		<hr />
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="Save Changes" />
		</p>
		</form>
		</div>
HEREDOC;
}

/*****************************************************
 *  Notices      								     *
 *****************************************************/

if(isset($_GET['page']) && $_GET['page'] != "linkedin-groups-block" && (!get_option( 'sufi_linkedin_opts' )["key"] || !get_option( 'sufi_linkedin_opts' )["secret"] || !get_option( 'sufi_linkedin_opts' )["uri"] )){
	add_action( 'admin_notices', 'linkedin_api_admin_notices' );
}

function remove_notice() {
	remove_action( 'admin_notices', 'linkedin_api_admin_notices' );
}

function linkedin_api_admin_notices() {
	echo "<div class='updated fade error'><p>LinkedIn Groups Plugin is not configured yet. <a href='".get_admin_url()."options-general.php?page=linkedin-groups-block'>Please do it now</a>.</p></div>\n";
}
function getRevokeLink(){
	echo '<a href="'.add_query_arg('LinkedIn','revoke', get_the_permalink()).'" title="Revoke LinkedIn Authorization">Revoke</a><br />';
}
function getAuthorizationLink(){
	echo '<a href="'.getAuthorizationCode().'" title="Authorize at LinkedIn">Authorize</a>';
}
/*****************************************************
 *  Shortcodes     								     *
 *****************************************************/
function linkedin_shortcode( $atts , $content=null) {
	// OAuth 2 Control Flow
	if (isset($_GET['error'])) {
	    // LinkedIn returned an error
	    print $_GET['error'] . ': ' . $_GET['error_description'];
	} elseif (isset($_GET['code'])) {
	    // User authorized your application
	    if ($_SESSION['state'] == $_GET['state']) {
	        // Get token so you can make API calls
	        // echo $_SESSION['access_token'];
	        getAccessToken();
	    } else {
	        // CSRF attack? Or did you mix up your states?
	        exit;
	    }
	} else if( isset( $_GET['LinkedIn'] ) && $_GET['LinkedIn'] == "revoke" ) {
        $_SESSION = array();
        getAuthorizationLink();
	} else { 
	    if ((empty($_SESSION['expires_at'])) || (time() > $_SESSION['expires_at'])) {
	        // Token has expired, clear the state
	         $_SESSION = array();
	    }
	    if (!isset($_SESSION['access_token']) || empty($_SESSION['access_token'])) {
	        getAuthorizationLink();
	    } 
	}
    $a = shortcode_atts( array(
        'group' => ''
    ), $atts );

    // Congratulations! You have a valid token. Now fetch your profile 
    if(isset($_SESSION['access_token']) && !empty($_SESSION['access_token'])){
		$user = fetch('GET', '/v1/people/~/group-memberships/'.$a['group']);
		// echo '<pre>';
		// var_dump($user);
		// echo '</pre>';
		if(isset($user) && $user->membershipState->code == "member") {
			getRevokeLink();
			return $content;
		} else {
			echo "Not a member of Graduate Business Forum (Alumni Group)";
		}
	} 
    return "";
}
add_shortcode( 'linkedin-group', 'linkedin_shortcode' );

?>
