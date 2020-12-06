<?php
/**
 * Plugin Name: Minecraft AuthMe
 * Plugin URI: http://henrychang.ca
 * Description: Wordpress plugin for Minecraft Authme Integration
 * Version: 1.0
 * Author: hdlineage
 * Author URI: http://henrychang.ca
 */
add_shortcode('minecraft_authme', 'minecraft_authme_main');
require_once('AuthMeController.php');
require_once('Sha256.php');

function minecraft_authme_main() {    
	
	$content = '';
	$authme_controller = new Sha256();
	$options = get_option( 'minecraft_authme_options' );
	
	$post_data = array();
	$post_data['action'] = get_from_post_or_empty('action');
	$post_data['username'] = get_from_post_or_empty('username');
	$post_data['password']= get_from_post_or_empty('password');
	$post_data['repass'] = get_from_post_or_empty('repass');
	$post_data['email'] = get_from_post_or_empty('email');
	$post_data['invCode'] = get_from_post_or_empty('invCode');
	$post_data['invitation'] = Authme_INV_CODE;	
	
	$query_results = array(
		'status' => false,
		'msg' => '',
	);
	
	
	if ($post_data['action'] === 'Log in') {
		$query_results = process_login($post_data, $authme_controller);
		$content = $query_results['msg'];
	} else if ($post_data['action'] === 'Register') {
		$query_results = process_register($post_data, $authme_controller);
		$content = $query_results['msg'];
	}

	
	if (!$query_results['status'])
	{
		ob_start();
		?>		
	
	<form method="post">
		<table style="width:80%;" align="center">
			<tr><td>Username</td><td ><input type="text" value="<?php echo $post_data['username'];?>" name="username" /></td></tr>
			<tr><td>Password</td><td><input type="password" value="<?php echo $post_data['password'];?>" name="password" /></td></tr>
			<tr><td>Confirm Password</td><td><input type="password" value="<?php echo $post_data['repass'];?>" name="repass" /></td></tr>
			<tr><td>Email</td><td><input type="text" value="<?php echo $post_data['email'];?>" name="email" /></td></tr>
			<tr><td>Invitation Code</td><td><input type="text" value="<?php echo $post_data['invCode'];?>" name="invCode" /></td></tr>
			<tr>		 
				<td colspan="2">
					<input type="hidden" name="action" value="Register"/>
					<input type="submit" name="action" value="Register">
				</td>
			</tr>
		</table>
		<?php if($options['captcha']) do_action('google_invre_render_widget_action'); ?>
		</form>
		<?php
		$content .= ob_get_contents();
		ob_end_clean();			
	}
	
	return $content;
}

function process_register($post_data, AuthMeController $controller) {
	$status = false;
	$msg = '';
	$options = get_option( 'minecraft_authme_options' );
	
    if (!apply_filters('google_invre_is_valid_request_filter', true) && $options['captcha']) {
        $msg = '<h3 style="color:#bf2321">Error: Request denied due to spam activity.</h3>';
    } else if ($controller->isUserRegistered($post_data['username'])) {
        $msg = '<h3 style="color:#bf2321">Error: This user already exists.</h3>';
    } else if (preg_match('/\s/',$post_data['username']) || strlen($post_data['username']) > 16 || $post_data['username']==''){
		$msg = '<h3 style="color:#bf2321">Error: Invalid choice of username.</h3><h4>Cannot contain spaces. Maximum 16 Characters.</h4>';
	} else if (strlen($post_data['password']) < 6 || $post_data['password']==''){
		$msg = '<h3 style="color:#bf2321">Error: Invalid choice of password.</h3><h4>Minimum 6 Characters.</h4>';
	} else if (!is_email_valid($post_data['email'])) {
        $msg = '<h3 style="color:#bf2321">Error: The supplied email is invalid.</h3>';
    } else if ($post_data['repass'] != $post_data['password']) {
        $msg = '<h3 style="color:#bf2321">Error: Please confirm passwords and try again</h3>';
    } else if ($post_data['invCode'] != $post_data['invitation']) {
        $msg = '<h3 style="color:#bf2321">Error: The supplied invitation code is invalid.</h3>';
    } else {        
        $register_success = $controller->register($post_data['username'], $post_data['password'], $post_data['email']);
        if ($register_success) {
			$status = true;
            $msg = '<h3 style="color:#3ca33e">Welcome, '.htmlspecialchars($post_data['username']).'! <br/>Registration completed.</h3><h4>You may now use the account to login at minecraft.henrychang.ca</h4>';  
			if($options['email'] != '')
				wp_mail($options['email'], "New Minecraft Player Registration", 'Minecraft Authme notification: <br/>    '.$post_data['username'].' has just registered with email: '.$post_data['email']);
        } else {
           $msg = '<h3 style="color:#bf2321">Error: Unfortunately, there was an error during the registration.</h3>';
        }
    }
   return array(
		'status' => $status,
		'msg' => $msg,
	);
}

function process_login($user, $pass, AuthMeController $controller) {
		$status = false;
		$msg = '';
		if ($controller->checkPassword($user, $pass)) {			
			$status = true;
			$msg = '<h3>Hello, '.htmlspecialchars($user).'</h3>';
			$msg .= 'Successful login. Nice to have you back!';	
		} else {
			$status = false;
			$msg = '<h3 style="color:#bf2321">Error Invalid username or password. </h3>';
		}
		return array(
			'status' => $status,
			'msg' => $msg,
		);
} 
 
function get_from_post_or_empty($index_name) {
    return trim(
        filter_input(INPUT_POST, $index_name, FILTER_UNSAFE_RAW, FILTER_REQUIRE_SCALAR | FILTER_FLAG_STRIP_LOW)
            ?: '');
}

function is_email_valid($email) {
    return trim($email) === ''
        ? true // accept no email
        : filter_var($email, FILTER_VALIDATE_EMAIL);
}