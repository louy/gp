<?php
/**
 * GeePress Login
 */
defined('ABSPATH') or die();
//define( 'GP_LOGIN', true ); // force the GP login to be on/off.

function gp_login() {
	if( defined( 'GP_LOGIN' ) ) return GP_LOGIN;
	return gp_options('login');
}

add_action('admin_init', 'gp_login_admin_init');
function gp_login_admin_init() {
	if(defined( 'GP_LOGIN' )) return;
	
	add_settings_section('gp_login_section', __('Login Settings', 'gp'), 'gp_login_section_callback', 'gp');
	
	add_settings_field('gp_login', __('Allow users to login with Google+ accounts?', 'gp'), 'gp_setting_login', 'gp', 'gp_login_section');
}

function gp_login_section_callback() {
	//...
}

function gp_setting_login() {
	echo "<input type='checkbox' name='gp_options[login]' value='yes' " . checked( gp_login(), true, false ) . " />";
}

add_action('gp_validate_options', 'gp_login_validate_options');
function gp_login_validate_options($input) {
	foreach( explode(',', 'login') as $option ) {
		if( isset($input[$option]) && $input[$option] == 'yes' ) {
			$input[$option] = true;
		} else {
			$input[$option] = false;
		}
	}
	return $input;
}


// add the section on the user profile page
add_action('profile_personal_options','gp_login_profile_page');
function gp_login_profile_page($profile) {
	$options = gp_options();
?>
	<table class="form-table">
		<tr>
			<th><label><?php _e('Google+', 'gp'); ?></label></th>
<?php
	$guid = get_user_meta($profile->ID, 'guid', true);
	
	if (empty($guid)) {
		?>
			<td><?php echo gp_get_connect_button('login_connect'); ?></td>
	<?php
	} else { ?>
		<td><p><?php _e('Connected as ', 'gp'); ?></p>
			<table><tr><td>
				<script type="text/javascript" src="https://apis.google.com/js/plusone.js"></script>
				<div class="g-person" data-href="https://plus.google.com/<?php echo $guid; ?>" data-layout="landscape"></div>
				
				<!--img src='https://www.google.com/s2/photos/profile/<?php echo $guid; ?>?sz=100' width='100' height='100' /-->
			</td></tr><tr><td colspan="2">
				<input type="button" class="button-primary" value="<?php _e('Disconnect', 'gp'); ?>" onclick="return gp_login_disconnect()" />
			</td></tr></table>
			
			<script type="text/javascript">
			function gp_login_disconnect() {
				var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
				var data = {
					action: 'disconnect_guid',
					guid: '<?php echo $guid; ?>'
				}
				jQuery.post(ajax_url, data, function(response) {
					if (response == '1') {
						location.reload(true);
					}
				});
				return false;
			}
			</script>
		</td>
	<?php } ?>
	</tr>
	</table>
	<?php
}

add_action('wp_ajax_disconnect_guid', 'gp_login_disconnect_guid');
function gp_login_disconnect_guid() {
	$user = wp_get_current_user();

	$guid = get_user_meta($user->ID, 'guid', true);
	if ($guid == $_POST['guid']) {
		delete_user_meta($user->ID, 'guid');
	}

	echo 1;
	exit();
}

add_action('gp_login_connect','gp_login_connect');
function gp_login_connect() {
	if (!is_user_logged_in()) return; // this only works for logged in users
	
	$user = wp_get_current_user();

	$profile = gp_get_profile();

	if ($profile) {
		// we have a user, update the user meta
		update_user_meta($user->ID, 'guid', $profile['id']);
		update_user_meta($user->ID, '_gp_token', $_SESSION['gp_token']);
	}
}

add_action('login_form','gp_login_add_login_button');
function gp_login_add_login_button() {
	if(!gp_login()) return; // check settings
	
	global $action;
	$style = apply_filters('gp_login_button_style', ' style="text-align: center;"');
	if ($action == 'login') echo '<p id="tw-login"'.$style.'>'.gp_get_connect_button('login').'</p><br />';
}

add_filter('authenticate','gp_login_check');
function gp_login_check($user) {
	if(!gp_login()) return; // check settings
	
	if ( is_a($user, 'WP_User') ) { return $user; } // check if user is already logged in, skip

	$profile = gp_get_profile();
	if ($profile) {
		global $wpdb;
		$guid = $profile['id'];
		$user_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'guid' AND meta_value = '%s'", $guid) );

		if ($user_id) {
			update_user_meta($user_id, '_gp_token', $_SESSION['gp_token']);
			$user = new WP_User($user_id);
		} else {
			do_action('gp_login_new_gp_user',$guid); // hook for creating new users if desired
			global $error;
			$error = __('<strong>Error</strong>: Google user not recognized.', 'gp');
		}
	}
	return $user;
}

add_action('wp_logout','gp_logout');
function gp_logout() {
	session_destroy();
}
