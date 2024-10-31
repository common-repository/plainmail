<?php 

require_once(dirname(__FILE__).'/plainmailCsrfguard.php');

class PlainmailAdmin{

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

	function __construct($userinfo){

		$this->user = $userinfo;
				
		if(current_user_can('manage_options')){	

			$this->csrf = new PlainmailCSRFGuard();
			global $wpdb;

		// process POST - validate token, validate user
			if(isset($_POST['CSRFName'], $_POST['CSRFToken']) 
			&& $this->csrf->validateToken('plainmail_admin.', $_POST['CSRFName'], $_POST['CSRFToken'])){ 
	    	
	    	if(isset($_POST['plainmail_user']) 
	    		&& ($_POST['plainmail_user'] == $this->user->ID)){    	
		    		
		    		// email change
			    	if(isset($_POST['plainmail_mailto'])){
			    		
			    		$sanitized_email = sanitize_email($_POST['plainmail_mailto']);
		    			
		    			if(is_email($sanitized_email)){ 		    				
		    				
		    				$wpdb->update(
								$wpdb->prefix.'plainmail_recipients',
								array('mailto' => $wpdb->escape($sanitized_email), 'time'  => time()),
								array('user_id' => $this->user->ID)
							);
			    		}
			    	}

			    	// header deletion
			    	if(isset($_POST['plainmail_delete']) 
			    		&& is_array($_POST['plainmail_delete']) 
			    		&& count($_POST['plainmail_delete'])){
		    			
		    			$delete = array_filter(array_values(array_filter($_POST['plainmail_delete'], 'ctype_digit')));
		    			
		    			if(count($delete)){
		    				
		    				foreach($delete as $id){
		    					
		    					$wpdb->delete($wpdb->prefix.'plainmail_headers', array( 
		    						'id' 	  => intval($id),
		    						'user_id' => $this->user->ID 
		    						), array( '%d', '%d' ) 
		    					);
		    				}
		    			}
			    	}

			    }
			}
		}
	}
	
	function AdminMenu(){
		add_options_page('Plainmail Settings', 'Plainmail', 'administrator', 'plainmail', array($this, 'AdminInterface'));
	}

	function time_elapsed_string($ptime){

	    $etime = time() - $ptime;

	    if ($etime < 1){
	        return '0 seconds';
	    }

	    $a = array( 12 * 30 * 24 * 60 * 60  =>  'year',
	                30 * 24 * 60 * 60       =>  'month',
	                24 * 60 * 60            =>  'day',
	                60 * 60                 =>  'hour',
	                60                      =>  'minute',
	                1                       =>  'second'
	    );

	    foreach ($a as $secs => $str){
	        $d = $etime / $secs;
	        if ($d >= 1){
	            $r = round($d);
	            return $r . ' ' . $str . ($r > 1 ? 's' : '') . ' ago';
	        }
	    }
	}


	function AdminInterface(){

		global $wpdb;
	
		if (current_user_can( 'manage_options' ) 
			&& ($csrf = $this->csrf->makeFields('plainmail_admin.'))){	

			// delete old headers
			$wpdb->query($wpdb->prepare(
				"DELETE FROM ".$wpdb->prefix."plainmail_headers where destroy < %d and user_id = %d ",
				array( time(), $this->user->ID ) 
				)
			);

			 $this->mailto = $wpdb->get_var(  $wpdb->prepare(
				"SELECT mailto FROM ".$wpdb->prefix."plainmail_recipients where user_id = %d ",
					$this->user->ID
				) 
			); 

			 $this->headers = $wpdb->get_results( $wpdb->prepare(
			 	"SELECT * FROM ".$wpdb->prefix."plainmail_headers where user_id = %d ORDER BY time DESC LIMIT 50",
			 		$this->user->ID
			 	), ARRAY_A
			 );

		 	?>

			<div class="wrap">
			<?php screen_icon(); ?>
			<h2>Plainmail Settings</h2>
			<hr>

	    	<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
	    	Add an email below for your username. The shortcode below will display a form to send email to the
	    	address you provide. 

	    	<h3>[plainmail mailto="<?php echo esc_attr($this->user->user_login); ?>"]</h3>
			
			<b>Mailto:</b>
				
				<input type="hidden" name="plainmail_user" value="<?php echo esc_attr($this->user->ID); ?>">
				<input type="hidden" name="CSRFName" value="<?php echo esc_attr($csrf[0]); ?>">
				<input type="hidden" name="CSRFToken" value="<?php echo esc_attr($csrf[1]); ?>">
			
				<input type="text" name="plainmail_mailto" value="<?php
				 if(is_email($this->mailto)){ 
	    			echo esc_attr($this->mailto);
	    		}
	    		else{ 
	    			echo esc_attr($this->user->user_email); 
	    		} ?>">

			<?php submit_button("update email") ?>
			 
			<?php

			// display headers
			if(count($this->headers)){
				
				?>

				<h4>Recent Messages</h4>
				
				(will be deleted after 24 hours, maximum of 50 shown)
				
				<table class="plainmail-items">
					<thead>
					<tr class="plainmail-header plainmail-row">
						<th>Time</th><th>Email</th><th>Title</th><th>Delete</th>
					</tr>
					<tr>
					</thead>
					<tbody>
				<?php
				foreach($this->headers as $header){
					?>
					<tr class="plainmail-row">
					<td><?php echo esc_attr($this->time_elapsed_string($header['time'])); ?></td>
					<td><?php echo esc_attr(substr($header['from_addr'], 0, 50)); ?></td>
					<td><?php echo esc_attr(substr($header['title'], 0, 50)); ?></td>
					<td><input type="checkbox" name="plainmail_delete[]" value="<?php echo intval($header['id']); ?>">
					</tr>
					<?php
				}
				?></tbody></table>
				<?php submit_button("delete"); ?>
				</form><?php
			}
		} // end user can manage options
		else{
			wp_die();
		}
	}
}
