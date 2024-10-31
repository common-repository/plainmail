<?php 

class PlainmailInstall{

	private $sql = array(

		"recipients" => "CREATE TABLE IF NOT EXISTS %s ( 
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id mediumint(0) NOT NULL DEFAULT 0,
		mailto varchar(255)  NOT NULL,
		time int(10) NOT NULL,
		UNIQUE KEY id (id) )",

		"headers" => "CREATE TABLE IF NOT EXISTS %s (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id mediumint(0) NOT NULL default 0,
		from_addr varchar(255) NOT NULL,
		title varchar(255) NOT NULL,
		time int(10) NOT NULL,
		destroy int(10) NOT NULL,
		UNIQUE KEY ID (id) )"
	);

	// remove db tables and options
	function PlainmailRemove(){
		global $wpdb;
	 	
	 	delete_option('PLAINMAIL_INSTALLED');

		foreach($this->sql as $key=>$val){ 
			$table_name = $wpdb->prefix."plainmail_".$key;
			$schema = "DROP TABLE IF EXISTS ".$wpdb->escape($table_name);
			$wpdb->query($schema);
		}

	}

	// create db tables and options
	function PlainmailInstall(){
		global $wpdb;
		$user = wp_get_current_user();

		foreach($this->sql as $key=>$val){
			$table_name = $wpdb->prefix."plainmail_".$key;
			$schema = sprintf($val, $table_name);
			$wpdb->query($schema);
		}

		// add the current user as a default
		$wpdb->insert(
			$wpdb->prefix.'plainmail_recipients',
			array(
				'user_id'=> $user->ID,
				'mailto' => $user->user_email,
			    'time'   => time()
			),
			array('%d', '%s', '%s')
		);

		add_option('PLAINMAIL_INSTALLED', '1');
				
	}
	
}