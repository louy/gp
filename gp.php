<?php
/*
Plugin Name: GP - GeePress
Description: All the tools you need to integrate your wordpress and Google+.
Author: Louy Alakkad
Version: 1.0
Author URI: http://l0uy.com/
Text Domain: gp
Domain Path: /po
*/
/*
if you want to force the plugin to use a client id and secret,
add your keys and copy the following 2 lines to your wp-config.php
*/
//define('GOOGLE_CLIENT_ID', 'EnterYourIDHere');
//define('GOOGLE_CLIENT_SECRET', 'EnterYourSecretHere');

// Load translations
load_plugin_textdomain( 'gp', false, dirname( plugin_basename( __FILE__ ) ) . '/po/' );

define('GP_VERSION', '1.0');

require_once dirname(__FILE__).'/wp-oauth.php';

/**
 * GeePress Core:
 */
function gp_activate(){
	oauth_activate();
	
	// require PHP 5
	if (version_compare(PHP_VERSION, '5.0.0', '<')) {
		deactivate_plugins(basename(__FILE__)); // Deactivate ourself
		wp_die(__("Sorry, GeePress requires PHP 5 or higher. Ask your host how to enable PHP 5 as the default on your servers.", 'gp'));
	}

}
register_activation_hook(__FILE__, 'gp_activate');

function gp_include_oauth() {
    if( !class_exists('Google_Client') ) {
        require_once dirname(__FILE__) . '/google-api/Google_Client.php';
        require_once dirname(__FILE__) . '/google-api/contrib/Google_PlusService.php';
        require_once dirname(__FILE__) . '/google-api/contrib/Google_Oauth2Service.php';
    }
}

add_action('init','gp_init');
function gp_init() {

	if (session_id() == '') {
		session_start();
	}

	isset($_SESSION['gp-connected']) or
		$_SESSION['gp-connected'] = false;

	if(isset($_GET['oauth_token'])) {
		gp_oauth_confirm();
	}
}

function gp_app_options_defined() {
    return defined('GOOGLE_CLIENT_ID') && defined('GOOGLE_CLIENT_SECRET');
}

function gp_options($k=false) {
    $options = get_option('gp_options');

    if( !is_array($options) ) {
        add_option('gp_options', $options = array(
            'login' => true,
            'allow_comment' => false,
            'allow_comment_share' => false,
            'check_comment_share' => false,
        ));
    }

    $options = array_merge($options, gp_app_options());
    if( $k ) {
            $options = $options[$k];
    }
    return $options;
}

function gp_app_options() {
    $options = get_site_option('gp_app_options');

    if( !is_array($options) ) {
        add_site_option('gp_app_options', $options = array(
            'client_id' => '',
            'client_secret' => '',
        ));
    }

    if( gp_app_options_defined() ) {
        $options['client_id']     = GOOGLE_CLIENT_ID   ;
        $options['client_secret'] = GOOGLE_CLIENT_SECRET;
    }

    return $options;
}

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'gp_links', 10, 1);
function gp_links($links) {
	$links[] = '<a href="'.admin_url('options-general.php?page=gp').'">'.__('Settings', 'gp').'</a>';
        if( gp_app_options_defined() )
            $links[] = '<a href="'.admin_url('options-general.php?page=gpapp').'">'.__('App Settings', 'gp').'</a>';
	return $links;
}

function user_can_edit_gp_app_options() {
    return !gp_app_options_defined() &&
            ( is_multisite() ? is_super_admin() : current_user_can('manage_options') );
}

// add the admin options page
add_action('admin_menu', 'gp_admin_add_page');
function gp_admin_add_page() {
	global $wp_version;
	add_options_page(__('GeePress', 'gp'), __('GeePress', 'gp'), 'manage_options', 'gp', 'gp_options_page');
    if( (!is_multisite() || version_compare($wp_version, '3.1-dev', '<')) && user_can_edit_gp_app_options() ) {
        add_submenu_page((is_multisite()?'ms-admin':'options-general').'.php', __('GeePress App', 'gp'), __('GeePress App', 'gp'), 'manage_options', 'gpapp', 'gp_app_options_page');
    }
}
add_action('network_admin_menu', 'gp_network_admin_add_page');
function gp_network_admin_add_page() {
    if( is_multisite() && user_can_edit_gp_app_options() ) {
        add_submenu_page('settings.php', __('GeePress App', 'gp'), __('GeePress App', 'gp'), 'manage_options', 'gpapp', 'gp_app_options_page');
    }
}

// add the admin settings and such
add_action('admin_init', 'gp_admin_init',9);
function gp_admin_init(){

    $options = gp_options();

    if (empty($options['client_id']) || empty($options['client_secret'])) {
            add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>".sprintf(__('GeePress needs to be configured on its <a href="%s">app settings</a> page.', 'gp'), admin_url('options-general.php?page=gpapp'))."</p></div>';" ) );
    }
    wp_enqueue_script('jquery');
    register_setting( 'gp_options', 'gp_options', 'gp_options_validate' );

    if ( user_can_edit_gp_app_options() ) {
        register_setting( 'gp_app_options', 'gp_app_options' );
        add_filter('pre_update_option_gp_app_options','gp_update_app_options', 10, 2 );
	add_settings_section('gp_app_client', __('App Client Settings', 'gp'),
                'gp_app_client_callback', 'gpapp');
	add_settings_field('gp-client-id', __('Google Client ID', 'gp'),
                'gp_setting_client_id', 'gpapp', 'gp_app_client' );
	add_settings_field('gp-client-secret', __('Google Client Secret', 'gp'),
                'gp_setting_client_secret', 'gpapp', 'gp_app_client' );
    }
}

// display the admin options page
function gp_options_page() {
?>
    <div class="wrap">
        <h2><?php _e('GeePress', 'gp'); ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields('gp_options'); ?>
            <table><tr><td>
                <?php do_settings_sections('gp'); ?>
            </td></tr></table>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'gp') ?>" />
            </p>
        </form>
    </div>

<?php
}
function gp_app_options_page() {
    $updated = false;
    if( isset( $_POST['option_page'] ) && $_POST['option_page'] == 'gp_app_options' 
                    && wp_verify_nonce($_POST['_wpnonce'], 'gp_app_options') ) {
        // Save options...
        $options = $_POST['gp_app_options'];
        update_option('gp_app_options', $options);
        /*
        $url = add_query_arg('updated', 'true', gp_get_current_url());
        wp_redirect($url);
        die();
        */
        echo '<div id="message" class="updated"><p>'.__('Options saved.').'</p></div>';
    }
?>
    <div class="wrap">
        <h2><?php _e('GeePress App Options', 'gp'); ?></h2>
        <form method="post">
            <input type='hidden' name='option_page' value='gp_app_options' />
            <?php wp_nonce_field('gp_app_options'); ?>
            <table><tr><td>
                <?php do_settings_sections('gpapp'); ?>
            </td></tr></table>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'gp') ?>" />
            </p>
        </form>
        <p><?php _e('If you like this plugin, add me <a href="https://plus.google.com/101750106109339136418">@l0uy</a> for more updates.', 'gp'); ?></p>
    </div>
<?php
}

function gp_app_client_callback() {
	$options = gp_app_options();
	if (empty($options['client_id']) || empty($options['client_secret'])) {
?>
<p><?php _e('To connect your site to Google, you will need a Google Application. If you have already created one, please insert your Client ID and Client Secret below.', 'gp'); ?></p>
<p><strong><?php _e('Can&#39;t find your keys?', 'gp'); ?></strong></p>
<ol>
<li><?php _e('Get a list of your applications from here: <a target="_blank" href="https://code.google.com/apis/console">Google APIs Console</a>', 'gp'); ?></li>
<li><?php _e('Select the application (project) you want, then copy and paste the Client ID and Client Secret from the API Access page there.', 'gp'); ?></li>
</ol>

<p><?php _e('<strong>Haven&#39;t created an application yet?</strong> Don&#39;t worry, it&#39;s easy!', 'gp'); ?></p>
<ol>
<li><?php _e('Go to this link to create your application: <a target="_blank" href="https://code.google.com/apis/console">Google APIs Console</a>, then create a new project.', 'gp'); ?></li>
<li><?php _e('Go to API Access tab and click on "Create an OAuth 2.0 client ID..."', 'gp'); ?></li>

<li><?php _e('Important Settings:', 'gp'); ?><ol>
<li><?php _e('Application Type must be set to "Web application".', 'gp'); ?></li>
<li><?php printf(__('Site must be set to <code>%s</code>.', 'gp'), get_bloginfo('url').'/oauth/google/'); ?></li>
</ol>
</li>

<li><?php _e('The other application fields can be set up any way you like.', 'gp'); ?></li>

<li><?php _e('After creating the application, copy and paste the "Client ID" and "Client secret" from the API Access page.', 'gp'); ?></li>
</ol>
<?php
	}
}

function gp_setting_client_id() {
	if (defined('GOOGLE_CLIENT_ID')) return;
	$options = gp_app_options();
	echo "<input type='text' name='gp_app_options[client_id]' value='{$options['client_id']}' size='40' /> " . __('(required)', 'gp');
}

function gp_setting_client_secret() {
	if (defined('GOOGLE_CLIENT_SECRET')) return;
	$options = gp_app_options();
	echo "<input type='text' name='gp_app_options[client_secret]' value='{$options['client_secret']}' size='40' /> " . __('(required)', 'gp');
}

// validate our options
function gp_options_validate($input) {
        unset($input['client_id'], $input['client_secret']);
	$input = apply_filters('gp_validate_options',$input);
	return $input;
}
function gp_update_app_options($new, $old) {
    $output = array(
        'client_id'    => $new['client_id'],
        'client_secret' => $new['client_secret']
    );

    if( is_multisite() ) {
        if( $output != $old )
            update_site_option('gp_app_options', $output);
    }

    return $output;
}


// start wp-oauth
function gp_oauth_start() {
	$options = gp_options();
	if (empty($options['client_id']) || empty($options['client_secret'])) return false;
	gp_include_oauth();
	
	$client = new Google_Client();
	$client->setApplicationName(get_bloginfo('name'));
	$client->setClientId($options['client_id']);
	$client->setClientSecret($options['client_secret']);
	$client->setRedirectUri(get_bloginfo('url').'/oauth/google');
	
	$plus = new Google_PlusService($client);
	$oauth2 = new Google_Oauth2Service($client);
	
	if (isset($_GET['code'])) {
		$client->authenticate();
		
		$_SESSION['gp-connected'] = true;
		
		$_SESSION['gp_token'] = $client->getAccessToken();
		
		// this lets us do things actions on the return from google and such
		if ($_SESSION['gp_callback_action']) {
			do_action('gp_'.$_SESSION['gp_callback_action']);
			$_SESSION['gp_callback_action'] = ''; // clear the action
		}
		
		header('Location: ' . remove_query_arg('reauth', $_SESSION['gp_callback']));
		exit;
	}
	
	$_SESSION['gp_callback'] = $_GET['loc'];
	$_SESSION['gp_callback_action'] = $_GET['gpaction'];

	$url = $client->createAuthUrl();

	wp_redirect($url);
	exit;
}
add_action('oauth_start_google', 'gp_oauth_start');


// get the user credentials from google
function gp_get_profile($force_check = false) {

	if(!$force_check && !$_SESSION['gp-connected']) return false;

	// cache the results in the session so we don't do this over and over
	if (!$force_check && isset($_SESSION['gp_credentials']) && $_SESSION['gp_credentials'] ) return $_SESSION['gp_credentials'];

	$_SESSION['gp_credentials'] = gp_do_request('people/me');
	
	return $_SESSION['gp_credentials'];
}

// json is assumed for this, so don't add .xml or .json to the request URL
function gp_do_request($url, $args = array(), $type = NULL) {

	if (isset($args['token'])) {
		$token = $args['token'];
		unset($args['token']);
	} else {
		$token = isset($_SESSION['gp_token']) ? $_SESSION['gp_token'] : false;
	}
	
	$options = gp_options();
	if (empty($options['client_id']) || empty($options['client_secret']) || !$token )
		return false;

	gp_include_oauth();
	
	$client = new Google_Client();
	$client->setApplicationName(get_bloginfo('name'));
	$client->setClientId($options['client_id']);
	$client->setClientSecret($options['client_secret']);
	$client->setRedirectUri(get_bloginfo('url').'/oauth/google');
	
	$plus = new Google_PlusService($client);
	$oauth2 = new Google_Oauth2Service($client);
	
	$client->setAccessToken($token);
	
	$parts = explode('/', $url);
	
	switch($parts[0]) {
		case 'people':
			return $plus->people->get($parts[1]);
		break;
		case 'logout':
			return $client->revokeToken();
		break;
		case 'userinfo':
			return $oauth2->userinfo->get();
		break;
		case 'moments':
			if( $parts[1] == 'insert' ) {
				return $plus->moments->insert(
						  $args['userId']
						, 'vault'
						, $args['body']
						//, $args['optParams']
					);
			}
		break;
	}
	
	return false;
}

function gp_get_connect_button($action='', $image ='sign-in-with-google-smaller') {
	$image = apply_filters('gp_connect_button_image', $image, $action);
	$imgsrc = apply_filters('gp_connect_button_image_src', plugins_url() . '/gp/images/'.$image.'.png', $image, $action);
	return apply_filters('gp_get_connect_button',
		'<a href="' . oauth_link('google', array(
				'gpaction' => $action,
				'loc' => gp_get_current_url()
				) ) . '" title="'.__('Sign in with Google+', 'gp').'">'.
			'<img src="'.$imgsrc.'" alt="'.__('Sign in with Google+', 'gp').'" style="border:none;" />'.
		'</a>', $action, $image);
}

function gp_get_current_url() {
	// build the URL in the address bar
	$requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
	$requested_url .= $_SERVER['HTTP_HOST'];
	$requested_url .= $_SERVER['REQUEST_URI'];
	return $requested_url;
}

require 'gp-login.php';
require 'gp-comments.php';
