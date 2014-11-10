<?php
/**
 *
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

defined( 'ABSPATH' ) or die( "No script kiddies please!" );

foreach ( glob( plugin_dir_path( __FILE__ ) . "/*.php" ) as $file ) {
	include_once $file;
}

/*****************************************************
 *  Options Menu       								 *
 *****************************************************/

function linkedin_api_menu() {
	add_options_page( 'LinkedIn', 'LinkedIn Groups', 'manage_options', 'linkedin-groups-block', 'linkedin_api_options' );
}

add_action( 'admin_menu', 'linkedin_api_menu' );

function linkedin_api_options() {
	if ( !current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	// variables for the field and option names
	$hidden_field_name = 'mt_submit_hidden';
	$key_field_name = 'linkedin_api_key';
	$uri_field_name = 'linkedin_api_redirect_uri';
	$secret_field_name = 'linkedin_api_secret';

	// Read in existing option value from database
	$getOpts = get_option( 'sufi_linkedin_opts' );
	$key_val = $getOpts["key"];
	$uri_val = $getOpts["uri"];
	$secret_val = $getOpts["secret"];

	if ( isset( $_POST[ $hidden_field_name ] ) && $_POST[ $hidden_field_name ] == 'Y' ) {
		$key_val = $_POST[ $key_field_name ];
		$uri_val = $_POST[ $uri_field_name ];
		$secret_val = $_POST[ $secret_field_name ];

		$updateOpts = array(
			"key"=>$key_val,
			"uri"=>$uri_val,
			"secret"=>$secret_val
		);
		// Save the posted value in the database
		update_option( 'sufi_linkedin_opts', $updateOpts );
		echo '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
	}
	echo '<div class="wrap">';
	echo "<h2>" . __( 'LinkedIn API Settings', 'linkedin-groups-block' ) . "</h2>";

	// settings form
	echo <<<HEREDOC
		<p>If you do not have an API key and secret, please click <a href="https://www.linkedin.com/secure/developer?newapp=" target="_blank" title="Create new LinkedIn Application">here</a> to create a new LinkedIn Application.</p>
		<form name="linkedin-api-form" method="post">
			<p>API Key: <input type="text" name="$key_field_name" value="$key_val" size="20"></p>
			<p>API Secret: <input type="text" name="$secret_field_name" value="$secret_val" size="20"></p>
			<p>API Redirect URI: <input type="text" name="$uri_field_name" value="$uri_val" size="20"></p>
			<hr />
			<p class="submit">
				<input type="hidden" name="$hidden_field_name" value="Y">
				<input type="submit" name="Submit" class="button-primary" value="Save Changes" />
			</p>
		</form>
		</div>
HEREDOC;
}

/*****************************************************
 *  Notices      								     *
 *****************************************************/

if ( isset( $_GET['page'] ) && $_GET['page'] != "linkedin-groups-block" &&
	( !get_option( 'sufi_linkedin_opts' )["key"] || !get_option( 'sufi_linkedin_opts' )["secret"] || !get_option( 'sufi_linkedin_opts' )["uri"] ) ) {
	add_action( 'admin_notices' , 'linkedin_api_admin_notices' );
}

function remove_notice() {
	remove_action( 'admin_notices' , 'linkedin_api_admin_notices' );
}

function linkedin_api_admin_notices() {
	echo "<div class='updated fade error'><p>LinkedIn Groups Plugin is not configured yet. <a href='".get_admin_url()."options-general.php?page=linkedin-groups-block'>Please do it now</a>.</p></div>\n";
}
function getLogoutLink() {
	echo '<a href="'.add_query_arg( 'LinkedIn' , 'revoke' , get_the_permalink() ).'" title="Log out of LinkedIn">Log out of LinkedIn</a><br />';
}
function getAuthorizationLink() {
	echo 'You must <a href="'.getAuthorizationCode().'" title="Log in to LinkedIn">Login with LinkedIn</a> to view entire content of this page!';
}
/*****************************************************
 *  Shortcodes     								     *
 *****************************************************/
function linkedin_shortcode( $atts , $content=null ) {
	if ( isset( $_GET['error'] ) ) {
		print $_GET['error'] . ': ' . $_GET['error_description'];
	} elseif ( isset( $_GET['code'] ) ) {
		if ( $_SESSION['state'] == $_GET['state'] ) {
			getAccessToken();
		} else {
			// Possible CSRF Attack
			exit;
		}
	} else if ( isset( $_GET['LinkedIn'] ) && $_GET['LinkedIn'] == "revoke" ) {
			$_SESSION = array();
			getAuthorizationLink();
		} else {
		// Check Token expiry
		if ( ( empty( $_SESSION['expires_at'] ) ) || ( time() > $_SESSION['expires_at'] ) ) {
			$_SESSION = array();
		}
		// Show authorization link
		if ( !isset( $_SESSION['access_token'] ) || empty( $_SESSION['access_token'] ) ) {
			getAuthorizationLink();
		}
	}
	$a = shortcode_atts( array(
			'group' => ''
		), $atts );

	if ( isset( $_SESSION['access_token'] ) && !empty( $_SESSION['access_token'] ) ) {
		$group = fetch( 'GET', '/v1/people/~/group-memberships/'.$a['group'] );
		if ( isset( $group ) && $group->membershipState->code == "member" ) {
			getLogoutLink();
			return $content;
		} else {
			echo '<span style="display: block;
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
		}
	}
	return "";
}
add_shortcode( 'linkedin-group', 'linkedin_shortcode' );
?>
