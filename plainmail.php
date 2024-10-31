<?php 
/*
Plugin Name: Wordpress Plain Mail
Description: Plaintext email form for Wordpress
Version: 1.2
Author: Kenneth Rapp <kennethrapp1@gmail.com>
License: MIT
*/
require_once(dirname(__FILE__).'/plainmailCsrfguard.php');
require_once(dirname(__FILE__).'/plainmailErrorhandler.php');
require_once(dirname(__FILE__).'/plainmailSession.php');

if(!function_exists('wp_get_current_user')) {
	require_once(ABSPATH.'wp-includes/pluggable.php');
}

if(!function_exists('esc_attr')){
	require_once(ABSPATH.'wp-includes/formatting.php');
}


class Plainmail{

	private $Settings = array();

	function __set($key, $value){
		if(!array_key_exists($key, $this->Settings)){
			$this->Settings[$key] = $value;
		}
	}

	function __get($key){
		if(array_key_exists($key, $this->Settings)){
			return $this->Settings[$key];
		}
		return null;
	}

	function __construct(){
		
		$this->plugin_name = plugin_basename(__FILE__);

		$this->CSRF  = new PlainMailCSRFGuard();
		$this->Error = new PlainMailErrorHandler();

		$this->options =array(
			'mailto'      => false,
			'header'      => '',
			'description' => '',
			'header_size' => 5
		);

		$this->whitelist = array(
			'name'    => 'the_name', 
			'email'   => 'the_email', 
			'subject' => 'the_subject', 
			'message' => 'the_message'
		);

		register_activation_hook($this->plugin_name, array($this, 'install'));
		register_deactivation_hook($this->plugin_name, array($this, 'uninstall'));

		add_shortcode('plainmail', array($this, 'shortcode'));

		wp_register_style('css_plainmail', plugins_url('style.css', __FILE__ ));
		wp_enqueue_style('css_plainmail');
		
	}

	/* display the mail form */
	private function mailform($csrfName, $csrfVal){

		?>

		<div class="plainmail-form">
		<form action="<?php echo get_permalink(); ?>" method="post">

		<?php
		foreach($this->whitelist as $key=>$val){
			if($key != 'message'){
			$key = esc_attr($key); 
			$val = esc_attr($val); 
		?>
		<label for ="<?php echo esc_attr($val); ?>"><?php echo esc_attr($key); ?></label>
		<input type="text" name="<?php echo esc_attr($val); ?>" id="<?php echo esc_attr($val); ?>">
		<?php
			}

			else{
		?>

			<label class="message" for ="<?php echo esc_attr($val); ?>">
			<?php echo esc_attr($key); ?>
			</label>
			<textarea type="text" name="<?php echo esc_attr($val); ?>" id="<?php echo esc_attr($val) ?>">
			</textarea>
		
			<input type="hidden" name="CSRFName" value="<?php echo esc_attr($csrfName); ?>">
			<input type="hidden" name="CSRFToken" value="<?php echo esc_attr($csrfVal); ?>">
			<input type="submit" value="send mail">
			</form>
			</div>
			<?php
			}
		}
	}

	/* run the shortcode. Content no longer does anything.  */
	function shortcode($atts, $content){

		$this->shortcode_atts = shortcode_atts($this->options, $atts);

		if(isset($this->shortcode_atts['mailto'])){

			/* 	if they're not on the admin page, and posting, and the shortcode is running, then
				run the sendmail code. */

			if( (false === is_admin()) && ($_SERVER['REQUEST_METHOD'] === 'POST') ){
				$this->send_mail($this->shortcode_atts);
			} 

			/* 	wordpress seems to want to overwrite the action, or provide one if I don't, making
				it difficult to add a form directly in the editor.  */	

			$form = '<form action="'.get_permalink().'" method="post">';

			if($this->Error->HasErrors()){ 
				echo '<div class="errorstack">';
				echo $this->Error->ShowErrors();
				echo '</div>';
			}

			// if we can build the csrf tokens, display the form.
			if($csrf = $this->CSRF->makeFields('plainmail.')){ 

				// display the header if it exists
				if(strlen($this->shortcode_atts['header'])){ 

					if(filter_var($this->shortcode_atts['header_size'], FILTER_VALIDATE_INT, array(
						'options' => array(
							'min_size'=>1,
							'max_size'=>5,
							'default' =>1 
							)
						)
					)){
						// display the header if the header attribute
						// is valid (int 1 - 5)
						?><h<?php 
							echo (int)($this->shortcode_atts['header_size']);
						?>>
						<?php 
							echo esc_attr($this->shortcode_atts['header']); 
						?>
						</h<?php 
							echo (int)($this->shortcode_atts['header_size']);
						?>><?php
					}

					// otherwise default to h5
					else{
					 ?><h5><?php echo esc_attr($this->shortcode_atts['header']); ?></h5><?php
					}
				}

				// display the content. Note that any linebreaks will be escaped as <br> tags
				if(strlen($content)){
					echo esc_attr($content);
				}

				$this->mailform($csrf[0], $csrf[1]);
			}
		}
	}

	// by Ben Gillbanks @ binarymoon.co.uk
	function is_it_spam($content){

	// innocent until proven guilty
		$isSpam = FALSE;
		
		$content = (array) $content;
		
		if (function_exists('akismet_init')) {
			
			$wpcom_api_key = get_option('wordpress_api_key');
			
			if (!empty($wpcom_api_key)) {
			
				global $akismet_api_host, $akismet_api_port;

				// set remaining required values for akismet api
				$content['user_ip'] = preg_replace( '/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR'] );
				$content['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
				$content['referrer'] = $_SERVER['HTTP_REFERER'];
				$content['blog'] = get_option('home');
				
				if (empty($content['referrer'])) {
					$content['referrer'] = get_permalink();
				}
				
				$queryString = '';
				
				foreach ($content as $key => $data) {
					if (!empty($data)) {
						$queryString .= $key . '=' . urlencode(stripslashes($data)) . '&';
					}
				}
				
				$response = akismet_http_post($queryString, $akismet_api_host, '/1.1/comment-check', $akismet_api_port);
				
				if ($response[1] == 'true') {
					update_option('akismet_spam_count', get_option('akismet_spam_count') + 1);
					$isSpam = TRUE;
				}
				
			}
			
		}
	
		return $isSpam;
	}


	/* send an email. */
	function send_mail($atts){

		global $wpdb;
		
		$m = array();
		
		$mail = array();
		
		/* 	Wordpress sometimes deletes or unsets $_POST so we'll check the
			media stream. */
		if($f = file_get_contents("php://input")){

			parse_str($f, $m);

			if(!count($m)){
				return false;
			}

			//die(var_dump($m));
			
			if ( !isset($m['CSRFToken'], $m['CSRFName']) ){
				$this->Error->Stack('CSRF Token not set');
			}
			else if(!$this->CSRF->validateToken('plainmail.', $m['CSRFName'], $m['CSRFToken'])){ 
				$this->Error->Stack("csrf timeout.");
			}

			// remove csrf tokens from the array or else the check later will fail.
			unset($m['CSRFToken'], $m['CSRFName']);

			/* parse the input array. match the field names against what's in the whitelist.
			build the resulting mail array from this list. If for some crazy reason $m is
			still empty then this will still fail safe. */

			foreach($m as $key=>$val){

				//echo "$key, $val";

				if(in_array($key, $this->whitelist)){

					// don't run the header checks against the message since there's not header injection
					// vulnerability there. 

					if(strlen(trim($val))){

						if($key !== $this->whitelist['message']){ 

							$field = str_ireplace(array("\r", "\n", "%0a", "%0d"), '', stripslashes($val));
							$field = str_ireplace(array("Content-Type:", "bcc:","to:","cc:"), '', $field);

							if($field){
								$mail[ $key ] = $field;
							}
						}
						else{
							$mail[ $key ] = wordwrap($val, 70, "\r\n");
						}
					}
					else if($required_field = array_search($key, $this->whitelist)){
						$this->Error->Stack($required_field." is a required field.");
					}
				}
			}
		}

		/* revalidate - length of the whitelist should match the mail array */
		if(count($mail) === count($this->whitelist)){

			// validate their email is an email
			if(filter_var($mail[$this->whitelist['email']], FILTER_VALIDATE_EMAIL)){

				// check the session for matching post
				if(isset($_SESSION['plainmail.lastpost.hash'])){
					$h = $_SESSION['plainmail.lastpost.hash'];
					
					if(md5(implode(null, $mail)) === $h){
						$this->Error->Stack("Sorry... it looks like you already sent that message.");
					}
					else{
						unset($_SESSION['plainmail.lastpost.hash']);
					}
				}

				// validate against Akismet
				$akismet_payload = array(
					'comment_author' 		=> $mail[ $this->whitelist['name'] ],
					'comment_author_email' 	=> $mail[ $this->whitelist['email'] ],
					'comment_author_url'	=> '',
					'comment_content'		=> $mail[ $this->whitelist['subject'] ].'\n\r'.$mail[ $this->whitelist['message'] ]
				);

				// returns true if akismet flags it
				if($this->is_it_spam($akismet_payload)){
					$this->Error->Stack("Your message or IP has been caught in the spam filter.");
				}

				// set up headers.
				$headers = array(
					"MIME-Version: 1.0;",
					"Content-type: text/plain; charset=utf-8;",
					"Content-Transfer-Encoding: 8-bit;",
					"From: ".$mail[$this->whitelist['name']]." <".$mail[$this->whitelist['email']].">",
					"Subject: ".$mail[$this->whitelist['subject']],
					"Date: ".date("Y.m.d H:i:s")
				);

				// THEY CAN HAS EMAIL?
				$id = get_user_by('login', $wpdb->escape($atts['mailto']));
				
				$mailto = $wpdb->get_var(  $wpdb->prepare(
					"SELECT mailto FROM ".$wpdb->prefix."plainmail_recipients where user_id = %d",
					$id->ID
					) 
				);

				// only the pure of heart may pass. 
				if(!$this->Error->HasErrors() && (is_email($mailto)) ){
					
					if(mail($mailto, 
						$mail[$this->whitelist['subject']], 
						$mail[$this->whitelist['message']], 
						implode("\r\n", $headers)
					)){ 

						$_SESSION['plainmail.lastpost.time'] = time();
						$_SESSION['plainmail.lastpost.hash'] = md5(implode(null, $mail));

						$this->Error->flush();
						$this->Error->Stack("Your message was sent!");
					}

					// add a new entry in the headers table
					$wpdb->insert(
						$wpdb->prefix.'plainmail_headers',
							array(
								'user_id'	=> $id->ID,
								'from_addr' => $wpdb->escape($mail[$this->whitelist['email']]),
								'title' 	=> $wpdb->escape($mail[$this->whitelist['subject']]),
						    	'time'   	=> time(),
						    	'destroy'	=> (time()+86400)
						),
						array('%d', '%s', '%s', '%d', '%d')
					);

				}						
		
			}
			else{
				$this->Error->Stack("provided email was invalid");
			}
		} // validation count failed. 
	}

	// install this plugin
	function install(){

		if(current_user_can('activate_plugins')){

			$is_installed = get_option('PLAINMAIL_INSTALLED');

			if(empty($is_installed)){ 
		
				require_once(ABSPATH.'wp-admin/includes/upgrade.php');
				require_once(dirname(__FILE__).'/PlainmailInstall.php');
				
				$installer = new PlainmailInstall();
				$installer->PlainmailInstall();
			}
		}
	}

	// remove this plugin
	function uninstall(){

		if(current_user_can('delete_plugins') && get_option('PLAINMAIL_INSTALLED') == '1'){

			require_once(ABSPATH.'wp-admin/includes/upgrade.php');
			require_once(dirname(__FILE__).'/PlainmailInstall.php');
				
			$installer = new PlainmailInstall();
			$installer->PlainmailRemove();
		
		}	
	}
}

// start the session handler. 	
new PlainmailSession();

// start the plugin.

if(session_id()){ 
	$TM = new Plainmail();
}

/* add the hook for the administrator panel, if the user is logged in. */
if(current_user_can('manage_options')){
	require_once(dirname(__FILE__).'/plainmailAdmin.php');
	if(($userinfo = get_userdata(get_current_user_id())) && is_admin()){
		$admin = new PlainmailAdmin($userinfo);
		add_action('admin_menu', array($admin, 'AdminMenu'));
	}
}
