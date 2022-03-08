<?php
/**
 * Plugin Name: Minecraft AuthMe插件Wordpress集成
 * Plugin URI: https://www.henrychang.ca/authme-reloaded-wordpress-plugin/
 * Description: 一个提供网站Minecraft AuthMe的Wordpress集成插件
 * Version: 1.0
 * Author: hdlineage, Dreamdawn Network
 * Author URI: https://www.henrychang.ca
 */
add_shortcode('minecraft_authme', 'minecraft_authme_main');
require_once('AuthMeController.php');
require_once('Sha256.php');

function minecraft_authme_main() {	
	$content = '';
	ob_start();
	?>
		<link href="<?php echo plugin_dir_url( __FILE__ );?>resources/mui.min.css" rel="stylesheet" type="text/css" />
		<script src="<?php echo plugin_dir_url( __FILE__ );?>resources/mui.min.js"></script>
		<style>		
			.mc_authme_form{
				width: 100%;
				margin-left: auto;
				margin-right: auto;
			}
			.mc_authme_msg{
				width: 100%;
				margin-left: auto;
				margin-right: auto;
			}			
		</style>	
	<?php
	$content .= ob_get_contents();
	ob_end_clean();			
	
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
	
	$content .= '<div class="mui--text-center mui-panel mc_authme_msg">';
	
	if ($post_data['action'] === 'Log in') {
		$query_results = process_login($post_data, $authme_controller);
		$content .= $query_results['msg'];
	} else if ($post_data['action'] === 'Register') {
		$query_results = process_register($post_data, $authme_controller);
		$content .= $query_results['msg'];
	} else{
		$content .= "<h3>".$options['welcome']."</h3>";
	}
	
	$content .= '</div>';
	
	if (!$query_results['status'])
	{
		ob_start();
		?>
		
	<form class="mui-form mc_authme_form" method="post">
		  <legend><?php echo $options['form-title'];?></legend>
		  <div class="mui-textfield mui-textfield--float-label">
				<input type="text" value="<?php echo $post_data['username'];?>" name="username" />
				<label>用户名(Username)</label>
		  </div>
		  <div class="mui-textfield mui-textfield--float-label">
				<input type="password" value="<?php echo $post_data['password'];?>" name="password" />
				<label>密码(Password)</label>
		  </div>
		  <div class="mui-textfield mui-textfield--float-label">
				<input type="password" value="<?php echo $post_data['repass'];?>" name="repass" />
				<label>确认密码(Confirm Password)</label>
		  </div>
		  <div class="mui-textfield mui-textfield--float-label">
				<input type="email" value="<?php echo $post_data['email'];?>" name="email" />
				<label>电子邮件地址(Email)</label>
		  </div>
		  <div class="mui-textfield mui-textfield--float-label">
				<input type="text" value="<?php echo $post_data['invCode'];?>" name="invCode" />
				<label>邀请码(Invitation Code)</label>
		  </div>
		  <div class="mui--text-center">
				<input type="hidden" name="action" value="Register" />
				<button type="submit"  class="mui-btn mui-btn--raised mui-btn--primary" >提交(Submit)</button>
		  </div>
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
        $msg = '<h3 style="color:#bf2321;">Error: 你需要完成ReCaptcha人机验证！.</h3>';
    } else if ($controller->isUserRegistered($post_data['username'])) {
        $msg = '<h3 style="color:#bf2321;">Error: 此用户已存在！请检查输入是否有误</h3>';
    } else if (preg_match('/\s/',$post_data['username']) || strlen($post_data['username']) > 16 || $post_data['username']==''){
		$msg = '<h3 style="color:#bf2321;">Error: 提供的用户名无效！</h3><h4>提示：不能包含空格，最长为16个字符。</h4>';
	} else if (strlen($post_data['password']) < 6 || $post_data['password']==''){
		$msg = '<h3 style="color:#bf2321;">Error: 提供的密码无效！</h3><h4>提示：至少需要6个字符。</h4>';
	} else if (!is_email_valid($post_data['email'])) {
        $msg = '<h3 style="color:#bf2321;">Error: 提供的电子邮件地址无效！</h3>';
    } else if ($post_data['repass'] != $post_data['password']) {
        $msg = '<h3 style="color:#bf2321;">Error: 请确认密码并重试</h3>';
    } else if ($post_data['invCode'] != $post_data['invitation']) {
        $msg = '<h3 style="color:#bf2321;">Error: 输入的邀请码错误！</h3>';
    } else {        
        $register_success = $controller->register($post_data['username'], $post_data['password'], $post_data['email']);
        if ($register_success) {
			$status = true;
            $msg = '			
					<h3 style="color:#3ca33e;">欢迎, '.htmlspecialchars($post_data['username']).'! <br/>注册已完成.</h3>
					<h4>你现在可以登录到服务器了！</h4>';  
			if($options['email'] != '')
				wp_mail($options['email'], "有新玩家注册了！", 'Minecraft Authme的提醒: <br/>    '.$post_data['username'].' 使用这个邮箱注册了: '.$post_data['email']);
        } else {
           $msg = '<h3 style="color:#bf2321;">Error: 很遗憾，注册时出现了一些问题，请联系管理员。</h3>';
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
			$msg = '<h3>欢迎，'.htmlspecialchars($user).'</h3>';
			$msg .= '你已成功登录，欢迎回来！';	
		} else {
			$status = false;
			$msg = '<h3 style="color:#bf2321">错误的用户名或密码 </h3>';
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