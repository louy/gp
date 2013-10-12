<?php
/**
 * GeePress Comments
 */
defined('ABSPATH') or die();

define( "GP_ALLOW_COMMENT_SHARE", false ); // Still beta! needs schema.org markup in the website.

add_action('admin_init','gp_comm_error_check');
function gp_comm_error_check() {
	if ( get_option( 'comment_registration' ) && gp_options('allow_comment') && gp_options('allow_comment') ) {
		add_action("admin_notices", "gp_comment_admin_notice" );
	}
}

function gp_comment_admin_notice() {
	echo "<div class='error'><p>" . __("GeePress Comment function doesn&#39;t work with sites that require registration to comment.", 'gp') . "</p></div>";
}

add_action('admin_init', 'gp_comm_admin_init');
function gp_comm_admin_init() {
	add_settings_section('gp_comm', __('Comment Settings', 'gp'), 'gp_comm_section_callback', 'gp');
	add_settings_field('gp_allow_comment', __('Allow Google+ users to comment?', 'gp'), 'gp_setting_allow_comment', 'gp', 'gp_comm');
	
	if(GP_ALLOW_COMMENT_SHARE) {
		add_settings_field('gp_allow_comment_share', __('Ask Google+ users to share the post after commenting?', 'gp'), 'gp_setting_allow_comment_share', 'gp', 'gp_comm');
		add_settings_field('gp_check_comment_share', __('Google+ commenters will share by default?', 'gp'), 'gp_setting_check_comment_share', 'gp', 'gp_comm');
	}
}

function gp_comm_section_callback() {
	echo '<p>'.__('Allow Google+ users to comment.', 'gp').'</p>';
}

function gp_setting_allow_comment() {
	echo "<input type='checkbox' name='gp_options[allow_comment]' value='yes' " . checked( gp_options('allow_comment'), true, false ) . " />";
}

function gp_setting_allow_comment_share() {
	echo "<input type='checkbox' name='gp_options[allow_comment_share]' value='yes' " . checked( gp_options('allow_comment_share'), true, false ) . " />";
}

function gp_setting_check_comment_share() {
	echo "<input type='checkbox' name='gp_options[check_comment_share]' value='yes' " . checked( gp_options('check_comment_share'), true, false ) . " />";
}

add_action('gp_validate_options', 'gp_comm_validate_options');
function gp_comm_validate_options($input) {
	foreach( explode(',', 'allow_comment,allow_comment_share,check_comment_share') as $option ) {
		if( isset($input[$option]) && $input[$option] == 'yes' ) {
			$input[$option] = true;
		} else {
			$input[$option] = false;
		}
	}
	return $input;
}

// set a variable to know when we are showing comments (no point in adding js to other pages)
function gp_comm_comments_enable() {
	global $gp_comm_comments_form;
	$gp_comm_comments_form = true;
}

// add placeholder for sending comment to google checkbox
function gp_comm_send_place() {
?><p id="gp_comm_send"></p><?php
}

// hook to the footer to add our scripting
function gp_comm_footer_script() {
	global $gp_comm_comments_form;
	if ($gp_comm_comments_form != true) return; // nothing to do, not showing comments

	if ( is_user_logged_in() ) return; // don't bother with this stuff for logged in users

	?>
<script type="text/javascript">
	jQuery(function() {
		var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
		var data = { action: 'gp_comm_get_display' }
		jQuery.post(ajax_url, data, function(response) {
			if (response != '0' && response != 0) {
				jQuery('#alt-comment-login').hide();
				jQuery('#comment-user-details').hide().after(response);
				
				<?php
				if(GP_ALLOW_COMMENT_SHARE) {
					if (gp_options('allow_comment_share')) {  // dont do this if disabled
					?>
					jQuery('#gp_comm_send').html('<p><input style="width: auto;" type="checkbox" name="gp_comm_send" value="send"/><label for="gp_comm_send"><?php _e('Share post on Google+', 'gp'); ?></label></p>')
					<?php if(gp_options('check_comment_share')) {echo ".find('input').attr('checked','checked');";} ?>
					<?php }
				} ?>
			}
		});
	});
</script>
	<?php
}

function gp_comm_get_display() {
	$profile = gp_get_profile(true);
	if ($profile) {
		echo '<div id="gp-user">'.
			 '<img src="' . add_query_arg('sz', '73', $profile['image']['url']) . '" width="73" height="73" id="gp-avatar" class="avatar" />'.
			 '<h3 id="gp-msg">' . sprintf(__('Hi %s!', 'gp'), esc_html($profile['displayName'])) . '</h3>'.
			 '<p>'.__('You are connected with your Google+ account.', 'gp').'</p>'.
			 apply_filters('gp_user_logout','<a href="?google-logout=1" id="gp-logout">'.__('Logout', 'gp').'</a>').
			 '</div>';
		exit;
	}

	echo 0;
	exit;
}

// check for logout request
function gp_comm_logout() {
	if (isset($_GET['google-logout'])) {
		gp_do_request('logout');
		session_unset();
		$page = gp_get_current_url();
		if (strpos($page, "?") !== false) $page = reset(explode("?", $page));
		wp_redirect($page);
		exit;
	}
}

function gp_comm_send_to_google($comment_id) {
	if(!GP_ALLOW_COMMENT_SHARE) { return; }
	
	$options = gp_options();
	
	$postid = (int) $_POST['comment_post_ID'];
	if (!$postid) return;

	// send the comment to google
	if (isset($_POST['gp_comm_send']) && $_POST['gp_comm_send'] == 'send') {
		
		$link = get_permalink($postid) . '#comment-' . $comment_id;
		
		$comment = get_comment($comment_id);
		
		$moment_body = new Google_Moment();
		$moment_body->setType("http://schemas.google.com/CommentActivity");
		$item_scope = new Google_ItemScope();
		$item_scope->setUrl($link);
		
		$item_scope->setId("comment-".$comment_id);
		$item_scope->setType("http://schemas.google.com/CommentActivity");
		//$item_scope->setName("New Comment");
		$item_scope->setText($comment->comment_content);
		
		$moment_body->setTarget($item_scope);
		
		//$optParams = array(
		//);
		
		gp_do_request('moments/insert', array(
			'userId' => 'me',
			'body' => $moment_body,
			//'optParams' => $optParams,
		));
	}
}

function gp_comm_login_button() {
	echo '<p id="gp-connect">'.gp_get_connect_button('comment').'</p>';
}

if( !function_exists('alt_comment_login') ) {
	function alt_comment_login() {
		echo '<div id="alt-comment-login">';
		do_action('alt_comment_login');
		echo '</div>';
	}
	function comment_user_details_begin() { echo '<div id="comment-user-details">'; }
	function comment_user_details_end() { echo '</div>'; }
}

// generate avatar code for google user comments
add_filter('get_avatar','gp_comm_avatar', 10, 5);
function gp_comm_avatar($avatar, $id_or_email, $size = '96', $default = '', $alt = false) {
	// check to be sure this is for a comment
	if ( !is_object($id_or_email) || !isset($id_or_email->comment_ID) || $id_or_email->user_id) 
		 return $avatar;
		 
	// check for gpid comment meta
	$gpid = get_comment_meta($id_or_email->comment_ID, 'gpid', true);
	if ($gpid) {
		// return the avatar code
		return "<img width='{$size}' height='{$size}' class='avatar avatar-{$size} gpavatar' src='https://plus.google.com/s2/photos/profile/{$gpid}?sz={$size}' />";
	}
	
	return $avatar;
}

// store the google screen_name as comment meta data ('gpid')
function gp_comm_add_meta($comment_id) {
	$profile = gp_get_profile();
	if ($profile) {
		update_comment_meta($comment_id, 'gpid', $profile['id']);
	}
}

// Add user fields for Google+ commenters
function gp_comm_fill_in_fields($comment_post_ID) {
	if (is_user_logged_in()) return; // do nothing to WP users

	$profile = gp_get_profile();
	if ($profile) {
		$_POST['author'] = $profile['displayName'];
		$_POST['url'] = $profile['url'];
		
		$user = gp_do_request('userinfo');
		$_POST['email'] = $user['email'];
	}
}

if( gp_options('allow_comment') ) {
    add_action('comment_form','gp_comm_comments_enable');
    add_action('comment_form','gp_comm_send_place');
    add_action('wp_footer','gp_comm_footer_script',30);
    add_action('wp_ajax_nopriv_gp_comm_get_display', 'gp_comm_get_display');
    add_action('init','gp_comm_logout');
    add_action('comment_form_before_fields', 'alt_comment_login',1,0);
    add_action('alt_comment_login', 'gp_comm_login_button');
    add_action('comment_form_before_fields', 'comment_user_details_begin',2,0);
    add_action('comment_form_after_fields', 'comment_user_details_end',20,0);
    add_action('comment_post','gp_comm_add_meta', 10, 1);
    add_filter('pre_comment_on_post','gp_comm_fill_in_fields');
    if(GP_ALLOW_COMMENT_SHARE) {
    	add_action('comment_post','gp_comm_send_to_google', 10, 1);
    }
}
