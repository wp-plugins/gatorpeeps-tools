<?php
/*
Plugin Name: Gatorpeeps Tools
Plugin URI: http://afrigator.com/peeps/tools
Description: A complete integration between your WordPress blog and <a href="http://gatorpeeps.com">Gatorpeeps</a>. Bring your peeps into your blog and pass your blog posts to Gatorpeeps. Based on Alex King's <a target="_blank" href="http://alexking.org/projects/wordpress">Twitter Tools</a> plugin.
Version: 2.0.0
Author: Afrigator
Author URI: http://afrigator.com
*/

// Copyright (c) 2009 Afrigator Internet. All rights reserved.
// Copyright (c) 2007-2008 Alex King. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// This is an add-on for WordPress
// http://wordpress.org/
//
// Thanks to John Ford ( http://www.aldenta.com ) for his contributions.
// Thanks to Dougal Campbell ( http://dougal.gunters.org ) for his contributions.
// Thanks to Silas Sewell ( http://silas.sewell.ch ) for his contributions.
// Thanks to Greg Grubbs for his contributions.
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

load_plugin_textdomain('gatorpeeps-tools');

if (!defined('PLUGINDIR')) {
	define('PLUGINDIR','wp-content/plugins');
}

if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'gatorpeeps-tools.php')) {
	define('PEEPS_FILE', trailingslashit(ABSPATH.PLUGINDIR).'gatorpeeps-tools.php');
}
else if (is_file(trailingslashit(ABSPATH.PLUGINDIR).'gatorpeeps-tools/gatorpeeps-tools.php')) {
	define('PEEPS_FILE', trailingslashit(ABSPATH.PLUGINDIR).'gatorpeeps-tools/gatorpeeps-tools.php');
}

if (!function_exists('is_admin_page')) {
	function is_admin_page() {
		if (function_exists('is_admin')) {
			return is_admin();
		}
		if (function_exists('check_admin_referer')) {
			return true;
		}
		else {
			return false;
		}
	}
}

if (!function_exists('wp_prototype_before_jquery')) {
	function wp_prototype_before_jquery( $js_array ) {
		if ( false === $jquery = array_search( 'jquery', $js_array ) )
			return $js_array;
	
		if ( false === $prototype = array_search( 'prototype', $js_array ) )
			return $js_array;
	
		if ( $prototype < $jquery )
			return $js_array;
	
		unset($js_array[$prototype]);
	
		array_splice( $js_array, $jquery, 0, 'prototype' );
	
		return $js_array;
	}
	
	add_filter( 'print_scripts_array', 'wp_prototype_before_jquery' );
}

define('PEEPS_API_POST_STATUS', 'http://afrigator.com/api/statuses/update.json');
define('PEEPS_API_USER_TIMELINE', 'http://afrigator.com/api/statuses/user_timeline.json');
define('PEEPS_API_STATUS_SHOW', 'http://afrigator.com/api/statuses/show/###ID###.json');
define('PEEPS_PROFILE_URL', 'http://gatorpeeps.com/###USERNAME###');
define('PEEPS_STATUS_URL', 'http://gatorpeeps.com/comments/###STATUS###');
define('PEEPS_HASHTAG_URL', 'http://gatorpeeps.com/community/###HASHTAG###');

function peeps_install() {
	global $wpdb;

	$peeps_install = new gatorpeeps_tools;
	$wpdb->peeps = $wpdb->prefix.'gp_gatorpeeps';
	$charset_collate = '';
	if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
		if (!empty($wpdb->charset)) {
			$charset_collate .= " DEFAULT CHARACTER SET $wpdb->charset";
		}
		if (!empty($wpdb->collate)) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}
	}
	$result = $wpdb->query("
		CREATE TABLE `$wpdb->peeps` (
		`id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`gp_id` VARCHAR( 255 ) NOT NULL ,
		`gp_text` VARCHAR( 255 ) NOT NULL ,
		`gp_reply_username` VARCHAR( 255 ) DEFAULT NULL ,
		`gp_reply_peep` VARCHAR( 255 ) DEFAULT NULL ,
		`gp_created_at` DATETIME NOT NULL ,
		`modified` DATETIME NOT NULL ,
		INDEX ( `gp_id` )
		) $charset_collate
	");
	foreach ($peeps_install->options as $option) {
		add_option('peeps_'.$option, $peeps_install->$option);
	}
	add_option('peeps_update_hash', '');
}
register_activation_hook(PEEPS_FILE, 'peeps_install');

class gatorpeeps_tools {
	function gatorpeeps_tools() {
		$this->options = array(
			'gatorpeeps_username'
			, 'gatorpeeps_password'
			, 'create_blog_posts'
			, 'create_digest'
			, 'create_digest_weekly'
			, 'digest_daily_time'
			, 'digest_weekly_time'
			, 'digest_weekly_day'
			, 'digest_title'
			, 'digest_title_weekly'
			, 'blog_post_author'
			, 'blog_post_category'
			, 'blog_post_tags'
			, 'notify_gatorpeeps'
			, 'sidebar_peeps_count'
			, 'peeps_from_sidebar'
			, 'give_tt_credit'
			, 'exclude_reply_peeps'
			, 'last_peeps_download'
			, 'doing_peeps_download'
			, 'doing_digest_post'
			, 'install_date'
			, 'js_lib'
			, 'digest_peeps_order'
			, 'notify_gatorpeeps_default'
		);
		$this->gatorpeeps_username = '';
		$this->gatorpeeps_password = '';
		$this->create_blog_posts = '0';
		$this->create_digest = '0';
		$this->create_digest_weekly = '0';
		$this->digest_daily_time = null;
		$this->digest_weekly_time = null;
		$this->digest_weekly_day = null;
		$this->digest_title = __("Gatorpeeps Updates for %s", 'gatorpeeps-tools');
		$this->digest_title_weekly = __("Gatorpeeps Weekly Updates for %s", 'gatorpeeps-tools');
		$this->blog_post_author = '1';
		$this->blog_post_category = '1';
		$this->blog_post_tags = '';
		$this->notify_gatorpeeps = '0';
		$this->notify_gatorpeeps_default = '0';
		$this->sidebar_peeps_count = '3';
		$this->peeps_from_sidebar = '1';
		$this->give_tt_credit = '1';
		$this->exclude_reply_peeps = '0';
		$this->install_date = '';
		$this->js_lib = 'jquery';
		$this->digest_peeps_order = 'ASC';
		// not included in options
		$this->update_hash = '';
		$this->peeps_prefix = 'New blog post';
		$this->peeps_format = $this->peeps_prefix.': %s %s';
		$this->last_digest_post = '';
		$this->last_peeps_download = '';
		$this->doing_peeps_download = '0';
		$this->doing_digest_post = '0';
		$this->version = '1.0';
	}
	
	function upgrade() {
		global $wpdb;
		$wpdb->peeps = $wpdb->prefix.'gp_gatorpeeps';

		$col_data = $wpdb->get_results("
			SHOW COLUMNS FROM $wpdb->peeps
		");
		$cols = array();
		foreach ($col_data as $col) {
			$cols[] = $col->Field;
		}
		// 1.2 schema upgrade
		if (!in_array('gp_reply_username', $cols)) {
			$wpdb->query("
				ALTER TABLE `$wpdb->peeps`
				ADD `gp_reply_username` VARCHAR( 255 ) DEFAULT NULL
				AFTER `gp_text`
			");
		}
		if (!in_array('gp_reply_peep', $cols)) {
			$wpdb->query("
				ALTER TABLE `$wpdb->peeps`
				ADD `gp_reply_peep` VARCHAR( 255 ) DEFAULT NULL
				AFTER `gp_reply_username`
			");
		}
	}

	function get_settings() {
		foreach ($this->options as $option) {
			$this->$option = get_option('peeps_'.$option);
		}
	}
	
	// puts post fields into object propps
	function populate_settings() {
		foreach ($this->options as $option) {
			if (isset($_POST['peeps_'.$option])) {
				$this->$option = stripslashes($_POST['peeps_'.$option]);
			}
		}
	}
	
	// puts object props into wp option storage
	function update_settings() {
		if (current_user_can('manage_options')) {
			$this->sidebar_peeps_count = intval($this->sidebar_peeps_count);
			if ($this->sidebar_peeps_count == 0) {
				$this->sidebar_peeps_count = '3';
			}
			foreach ($this->options as $option) {
				update_option('peeps_'.$option, $this->$option);
			}
			if (empty($this->install_date)) {
				update_option('peeps_install_date', current_time('mysql'));
			}
			$this->initiate_digests();
			$this->upgrade();
		}
	}
	
	// figure out when the next weekly and daily digests will be
	function initiate_digests() {
		$next = ($this->create_digest) ? $this->calculate_next_daily_digest() : null;
		$this->next_daily_digest = $next;
		update_option('peeps_next_daily_digest', $next);
		
		$next = ($this->create_digest_weekly) ? $this->calculate_next_weekly_digest() : null;
		$this->next_weekly_digest = $next;
		update_option('peeps_next_weekly_digest', $next);
	}
	
	function calculate_next_daily_digest() {
		$optionDate = strtotime($this->digest_daily_time);
		$hour_offset = date("G", $optionDate);
		$minute_offset = date("i", $optionDate);
		$next = mktime($hour_offset, $minute_offset, 0);
		
		// may have to move to next day
		$now = time();
		while($next < $now) {
			$next += 60 * 60 * 24;
		}
		return $next;
	}
	
	function calculate_next_weekly_digest() {
		$optionDate = strtotime($this->digest_weekly_time);
		$hour_offset = date("G", $optionDate);
		$minute_offset = date("i", $optionDate);
		
		$current_day_of_month = date("j");
		$current_day_of_week = date("w");
		$current_month = date("n");
		
		// if this week's day is less than today, go for next week
		$nextDay = $current_day_of_month - $current_day_of_week + $this->digest_weekly_day;
		$next = mktime($hour_offset, $minute_offset, 0, $current_month, $nextDay);
		if ($this->digest_weekly_day <= $current_day_of_week) {
			$next = strtotime('+1 week', $next);
		}
		return $next;
	}
	
	function ping_digests() {
		// still busy
		if (get_option('peeps_doing_digest_post') == '1') {
			return;
		}
		// check all the digest schedules
		if ($this->create_digest == 1) {
			$this->ping_digest('peeps_next_daily_digest', 'peeps_last_digest_post', $this->digest_title, 60 * 60 * 24 * 1);
		}
		if ($this->create_digest_weekly == 1) {
			$this->ping_digest('peeps_next_weekly_digest', 'peeps_last_digest_post_weekly', $this->digest_title_weekly, 60 * 60 * 24 * 7);
		}
		return;
	}
	
	function ping_digest($nextDateField, $lastDateField, $title, $defaultDuration) {

		$next = get_option($nextDateField);
		
		if ($next) {		
			$next = $this->validateDate($next);
			$rightNow = time();
			if ($rightNow >= $next) {
				$start = get_option($lastDateField);
				$start = $this->validateDate($start, $rightNow - $defaultDuration);
				if ($this->do_digest_post($start, $next, $title)) {
					update_option($lastDateField, $rightNow);
					update_option($nextDateField, $next + $defaultDuration);
				} else {
					update_option($lastDateField, null);
				}
			}
		}
	}
	
	function validateDate($in, $default = 0) {
		if (!is_numeric($in)) {
			// try to convert what they gave us into a date
			$out = strtotime($in);
			// if that doesn't work, return the default
			if (!is_numeric($out)) {
				return $default;
			}
			return $out;	
		}
		return $in;
	}

	function do_digest_post($start, $end, $title) {
		
		if (!$start || !$end) return false;

		// flag us as busy
		update_option('peeps_doing_digest_post', '1');
		remove_action('publish_post', 'peeps_notify_gatorpeeps', 99);
		remove_action('publish_post', 'peeps_store_post_options', 1, 2);
		remove_action('save_post', 'peeps_store_post_options', 1, 2);
		// see if there's any peeps in the time range
		global $wpdb;
		
		$startGMT = gmdate("Y-m-d H:i:s", $start);
		$endGMT = gmdate("Y-m-d H:i:s", $end);
		
		// build sql
		$conditions = array();
		$conditions[] = "gp_created_at >= '{$startGMT}'";
		$conditions[] = "gp_created_at <= '{$endGMT}'";
		$conditions[] = "gp_text NOT LIKE '$this->peeps_prefix%'";
		if ($this->exclude_reply_peeps) {
			$conditions[] = "gp_text NOT LIKE '@%'";
		}
		$where = implode(' AND ', $conditions);
		
		$sql = "
			SELECT * FROM {$wpdb->peeps}
			WHERE {$where}
			GROUP BY gp_id
			ORDER BY gp_created_at {$this->digest_peeps_order}
		";

		$peeps = $wpdb->get_results($sql);

		if (count($peeps) > 0) {
		
			$peeps_to_post = array();
			foreach ($peeps as $data) {
				$peep = new peeps_peep;
				$peep->gp_text = $data->gp_text;
				$peep->gp_reply_peep = $data->gp_reply_peep;
				if (!$peep->peeps_is_post_notification() || ($peep->peeps_is_reply() && $this->exclude_reply_peeps)) {
					$peeps_to_post[] = $data;
				}
			}

			if (count($peeps_to_post) > 0) {
				$content = '<ul class="peeps_peeps_digest">'."\n";
				foreach ($peeps_to_post as $peep) {
					$content .= '	<li>'.peeps_peeps_display($peep, 'absolute').'</li>'."\n";
				}
				$content .= '</ul>'."\n";
				if ($this->give_tt_credit == '1') {
					$content .= '<p class="peeps_credit">Powered by <a href="http://gatorpeeps.com">Gatorpeeps Tools</a>.</p>';
				}

				$post_data = array(
					'post_content' => $wpdb->escape($content),
					'post_title' => $wpdb->escape(sprintf($title, date('Y-m-d'))),
					'post_date' => date('Y-m-d H:i:s', $end),
					'post_category' => array($this->blog_post_category),
					'post_status' => 'publish',
					'post_author' => $wpdb->escape($this->blog_post_author)
				);

				$post_id = wp_insert_post($post_data);

				add_post_meta($post_id, 'peeps_peeped', '1', true);
				wp_set_post_tags($post_id, $this->blog_post_tags);
			}

		}
		add_action('publish_post', 'peeps_notify_gatorpeeps', 99);
		add_action('publish_post', 'peeps_store_post_options', 1, 2);
		add_action('save_post', 'peeps_store_post_options', 1, 2);
		update_option('peeps_doing_digest_post', '0');
		return true;
	}
	
	function peeps_download_interval() {
		return 600;
	}
	
	function do_peep($peep = '') {
		if (empty($this->gatorpeeps_username) 
			|| empty($this->gatorpeeps_password) 
			|| empty($peep)
			|| empty($peep->gp_text)
		) {
			return;
		}
		require_once(ABSPATH.WPINC.'/class-snoopy.php');
		$snoop = new Snoopy;
		$snoop->agent = 'Gatorpeeps Tools http://gatorpeeps.com';
		$snoop->rawheaders = array(
			'X-Gatorpeeps-Client' => 'Gatorpeeps Tools'
			, 'X-Gatorpeeps-Client-Version' => $this->version
			, 'X-Gatorpeeps-Client-URL' => 'http://afrigator.com/twitter/gatorpeeps-tools.xml'
		);
		$snoop->user = $this->gatorpeeps_username;
		$snoop->pass = $this->gatorpeeps_password;
		$snoop->submit(
			PEEPS_API_POST_STATUS
			, array(
				'status' => $peep->gp_text
				, 'source' => 'gatorpeeps'
			)
		);
		if (strpos($snoop->response_code, '200')) {
			update_option('peeps_last_peeps_download', strtotime('-28 minutes'));
			return true;
		}
		return false;
	}
	
	function do_blog_post_peep($post_id = 0) {
// this is only called on the publish_post hook
		if ($this->notify_gatorpeeps == '0'
			|| $post_id == 0
			|| get_post_meta($post_id, 'peeps_peeped', true) == '1'
			|| get_post_meta($post_id, 'peeps_notify_gatorpeeps', true) == 'no'
		) {
			return;
		}
		$post = get_post($post_id);
		// check for an edited post before TT was installed
		if ($post->post_date <= $this->install_date) {
			return;
		}
		// check for private posts
		if ($post->post_status == 'private') {
			return;
		}
		$peep = new peeps_peep;
		
		// get short url through gatorurl.com (replaced fopen function with curl due to servers like Dreamhost not supporting it - 06-05-2009)
		function get_content($url)
		{
		    $ch = curl_init();
		
		    curl_setopt ($ch, CURLOPT_URL, $url);
		    curl_setopt ($ch, CURLOPT_HEADER, 0);
		
		    ob_start();
		
		    curl_exec ($ch);
		    curl_close ($ch);
		    $string = ob_get_contents();
		
		    ob_end_clean();
		   
		    return $string;    
		}
		
		$gatorurl_api = "http://gatorurl.com/api/rest.php?url=";
		$api_handle = $gatorurl_api . get_permalink($post_id);
		
		$gator_url = get_content("".$api_handle."");
		
		$url = apply_filters('peeps_blog_post_url', $gator_url);
		$peep->gp_text = sprintf(__($this->peeps_format, 'gatorpeeps-tools'), $post->post_title, $url);
		$this->do_peep($peep);
		add_post_meta($post_id, 'peeps_peeped', '1', true);
	}
	
	function do_peeps_post($peep) {
		global $wpdb;
		remove_action('publish_post', 'peeps_notify_gatorpeeps', 99);
		$data = array(
			'post_content' => $wpdb->escape(peeps_make_clickable($peep->gp_text))
			, 'post_title' => $wpdb->escape(trim_add_elipsis($peep->gp_text, 30))
			, 'post_date' => get_date_from_gmt(date('Y-m-d H:i:s', $peep->gp_created_at))
			, 'post_category' => array($this->blog_post_category)
			, 'post_status' => 'publish'
			, 'post_author' => $wpdb->escape($this->blog_post_author)
		);
		$post_id = wp_insert_post($data);
		add_post_meta($post_id, 'peeps_gatorpeeps_id', $peep->gp_id, true);
		wp_set_post_tags($post_id, $this->blog_post_tags);
		add_action('publish_post', 'peeps_notify_gatorpeeps', 99);
	}
}

class peeps_peep {
	function peeps_peep(
		$gp_id = ''
		, $gp_text = ''
		, $gp_created_at = ''
		, $gp_reply_username = null
		, $gp_reply_peep = null
	) {
		$this->id = '';
		$this->modified = '';
		$this->gp_created_at = $gp_created_at;
		$this->gp_text = $gp_text;
		$this->gp_reply_username = $gp_reply_username;
		$this->gp_reply_peep = $gp_reply_peep;
		$this->gp_id = $gp_id;
	}
	
	function twdate_to_time($date) {
		$parts = explode(' ', $date);
		$date = strtotime($parts[1].' '.$parts[2].', '.$parts[5].' '.$parts[3]);
		return $date;
	}
	
	function peeps_post_exists() {
		global $wpdb;
		$test = $wpdb->get_results("
			SELECT *
			FROM $wpdb->postmeta
			WHERE meta_key = 'peeps_gatorpeeps_id'
			AND meta_value = '".$wpdb->escape($this->gp_id)."'
		");
		if (count($test) > 0) {
			return true;
		}
		return false;
	}
	
	function peeps_is_post_notification() {
		global $peeps;
		if (substr($this->gp_text, 0, strlen($peeps->peeps_prefix)) == $peeps->peeps_prefix) {
			return true;
		}
		return false;
	}
	
	function peeps_is_reply() {
// Gatorpeeps data changed - users still expect anything starting with @ is a reply
//		return !empty($this->gp_reply_peep);
		return (substr($this->gp_text, 0, 1) == '@');
	}
	
	function add() {
		global $wpdb, $peeps;
		$wpdb->query("
			INSERT
			INTO $wpdb->peeps
			( gp_id
			, gp_text
			, gp_reply_username
			, gp_reply_peep
			, gp_created_at
			, modified
			)
			VALUES
			( '".$wpdb->escape($this->gp_id)."'
			, '".$wpdb->escape($this->gp_text)."'
			, '".$wpdb->escape($this->gp_reply_username)."'
			, '".$wpdb->escape($this->gp_reply_peep)."'
			, '".date('Y-m-d H:i:s', $this->gp_created_at)."'
			, NOW()
			)
		");
		do_action('peeps_add_peep', $this);
		if ($peeps->create_blog_posts == '1' && !$this->peeps_post_exists() && !$this->peeps_is_post_notification() && (!$peeps->exclude_reply_peeps || !$this->peeps_is_reply())) {
			$peeps->do_peeps_post($this);
		}
	}
}

function peeps_api_status_show_url($id) {
	return str_replace('###ID###', $id, PEEPS_API_STATUS_SHOW);
}

function peeps_profile_url($username) {
	return str_replace('###USERNAME###', $username, PEEPS_PROFILE_URL);
}

function peeps_profile_link($username, $prefix = '', $suffix = '') {
	return $prefix.'<a href="'.peeps_profile_url($username).'">'.$username.'</a>'.$suffix;
}

function peeps_hashtag_url($hashtag) {
	$hashtag = urlencode($hashtag);
	return str_replace('###HASHTAG###', $hashtag, PEEPS_HASHTAG_URL);
}

function peeps_hashtag_link($hashtag, $prefix = '', $suffix = '') {
	return $prefix.'<a href="'.peeps_hashtag_url($hashtag).'">'.htmlspecialchars($hashtag).'</a>'.$suffix;
}

function peeps_status_url($username, $status) {
	return str_replace(
		array(
			'###USERNAME###'
			, '###STATUS###'
		)
		, array(
			$username
			, $status
		)
		, PEEPS_STATUS_URL
	);
}

function peeps_login_test($username, $password) {
	require_once(ABSPATH.WPINC.'/class-snoopy.php');
	$snoop = new Snoopy;
	$snoop->agent = 'Gatorpeeps Tools http://gatorpeeps.com';
	$snoop->user = $username;
	$snoop->pass = $password;
	$snoop->fetch(PEEPS_API_USER_TIMELINE);
	if (strpos($snoop->response_code, '200')) {
		return __("Login succeeded, you're good to go.", 'gatorpeeps-tools');
	} else {
		$json = new Services_JSON();
		$results = $json->decode($snoop->results);
		return sprintf(__('Sorry, login failed. Error message from Gatorpeeps: %s', 'gatorpeeps-tools'), $results->error);
	}
}


function peeps_ping_digests() {
	global $peeps;
	$peeps->ping_digests();
}

function peeps_update_peeps() {
	global $peeps;
	// let the last update run for 10 minutes
	if (time() - intval(get_option('peeps_doing_peeps_download')) < $peeps->peeps_download_interval()) {
		return;
	}
	// wait 10 min between downloads
	if (time() - intval(get_option('peeps_last_peeps_download')) < $peeps->peeps_download_interval()) {
		return;
	}
	update_option('peeps_doing_peeps_download', time());
	global $wpdb, $peeps;
	if (empty($peeps->gatorpeeps_username) || empty($peeps->gatorpeeps_password)) {
		update_option('peeps_doing_peeps_download', '0');
		return;
	}
	require_once(ABSPATH.WPINC.'/class-snoopy.php');
	$snoop = new Snoopy;
	$snoop->agent = 'Gatorpeeps Tools http://gatorpeeps.com';
	$snoop->user = $peeps->gatorpeeps_username;
	$snoop->pass = $peeps->gatorpeeps_password;
	$snoop->fetch(PEEPS_API_USER_TIMELINE);
	if (!strpos($snoop->response_code, '200')) {
		update_option('peeps_doing_peeps_download', '0');
		return;
	}

	$data = $snoop->results;
	
	// hash results to see if they're any different than the last update, if so, return
	$hash = md5($data);
	if ($hash == get_option('peeps_update_hash')) {
		update_option('peeps_last_peeps_download', time());
		update_option('peeps_doing_peeps_download', '0');
		return;
	}
	$json = new Services_JSON();
	$peeps_arr = $json->decode($data);
	
	if (is_array($peeps_arr) && count($peeps_arr) > 0) {
		$peeps_ids = array();
		foreach ($peeps_arr as $peep) {
			$peeps_ids[] = $wpdb->escape($peep->id);
		}
		$existing_ids = $wpdb->get_col("
			SELECT gp_id
			FROM $wpdb->peeps
			WHERE gp_id
			IN ('".implode("', '", $peeps_ids)."')
		");
		$new_peeps = array();
		foreach ($peeps_arr as $peep_data) {
			if (!$existing_ids || !in_array($peep_data->id, $existing_ids)) {
				$peep = new peeps_peep(
					$peep_data->id
					, $peep_data->text
				);
				$peep->gp_created_at = $peep->twdate_to_time($peep_data->created_at);
				if (!empty($peep_data->in_reply_to_status_id)) {
					$peep->gp_reply_peep = $peep_data->in_reply_to_status_id;
					$url = peeps_api_status_show_url($peep_data->in_reply_to_status_id);
					$snoop->fetch($url);
					if (strpos($snoop->response_code, '200') !== false) {
						$data = $snoop->results;
						$status = $json->decode($data);
						$peep->gp_reply_username = $status->user->screen_name;
					}
				}
				// make sure we haven't downloaded someone else's peeps - happens sometimes due to Gatorpeeps hiccups
				if (strtolower($peep_data->user->screen_name) == strtolower($peeps->gatorpeeps_username)) {
					$new_peeps[] = $peep;
				}
			}
		}
		foreach ($new_peeps as $peep) {
			$peep->add();
		}
	}
	peeps_reset_peeps_checking($hash, time());
}


function peeps_reset_peeps_checking($hash = '', $time = 0) {
	if (!current_user_can('manage_options')) {
		return;
	}
	update_option('peeps_update_hash', $hash);
	update_option('peeps_last_peeps_download', $time);
	update_option('peeps_doing_peeps_download', '0');
}

function peeps_notify_gatorpeeps($post_id) {
	global $peeps;
	$peeps->do_blog_post_peep($post_id);
}
add_action('publish_post', 'peeps_notify_gatorpeeps', 99);

function peeps_sidebar_peeps() {
	global $wpdb, $peeps;
	if ($peeps->exclude_reply_peeps) {
		$where = "AND gp_text NOT LIKE '@%' ";
	}
	else {
		$where = '';
	}
	$peeps_results = $wpdb->get_results("
		SELECT *
		FROM $wpdb->peeps
		WHERE gp_text NOT LIKE '$peeps->peeps_prefix%'
		$where
		GROUP BY gp_id
		ORDER BY gp_created_at DESC
		LIMIT $peeps->sidebar_peeps_count
	");
	$output = '<div class="peeps_peeps">'."\n"
		.'	<ul>'."\n";
	if (count($peeps_results) > 0) {
		foreach ($peeps_results as $peep) {
			$output .= '		<li>'.peeps_peeps_display($peep).'</li>'."\n";
		}
	}
	else {
		$output .= '		<li>'.__('No peeps available at the moment.', 'gatorpeeps-tools').'</li>'."\n";
	}
	if (!empty($peeps->gatorpeeps_username)) {
  		$output .= '		<li class="peeps_more_updates"><a href="'.peeps_profile_url($peeps->gatorpeeps_username).'">More updates...</a></li>'."\n";
	}
	$output .= '</ul>';
	if ($peeps->peeps_from_sidebar == '1' && !empty($peeps->gatorpeeps_username) && !empty($peeps->gatorpeeps_password)) {
  		$output .= peeps_peeps_form('input', 'onsubmit="akttPostPeep(); return false;"');
		  $output .= '	<p id="peeps_peeps_posted_msg">'.__('Posting peep...', 'gatorpeeps-tools').'</p>';
	}
	if ($peeps->give_tt_credit == '1') {
		$output .= '<p class="peeps_credit">Powered by <a href="http://gatorpeeps.com">Gatorpeeps Tools</a>.</p>';
	}
	$output .= '</div>';
	print($output);
}

function peeps_latest_peep() {
	global $wpdb, $peeps;
	$peeps_results = $wpdb->get_results("
		SELECT *
		FROM $wpdb->peeps
		WHERE gp_text NOT LIKE '$peeps->peeps_prefix%'
		GROUP BY gp_id
		ORDER BY gp_created_at DESC
		LIMIT 1
	");
	if (count($peeps_results) == 1) {
		foreach ($peeps_results as $peep) {
			$output = peeps_peeps_display($peep);
		}
	}
	else {
		$output = __('No peeps available at the moment.', 'gatorpeeps-tools');
	}
	print($output);
}

function peeps_peeps_display($peep, $time = 'relative') {
	global $peeps;
	$output = peeps_make_clickable(wp_specialchars($peep->gp_text));
	if (!empty($peep->gp_reply_username)) {
		$output .= 	' <a href="'.peeps_status_url($peep->gp_reply_username, $peep->gp_reply_peep).'">'.sprintf(__('in reply to %s', 'gatorpeeps-tools'), $peep->gp_reply_username).'</a>';
	}
	switch ($time) {
		case 'relative':
			$time_display = peeps_relativeTime($peep->gp_created_at);
			break;
		case 'absolute':
			$time_display = '#';
			break;
	}
	$output .= ' <a href="'.peeps_status_url($peeps->gatorpeeps_username, $peep->gp_id).'">'.$time_display.'</a>';
	return $output;
}

function peeps_make_clickable($peep) {
	$peep .= ' ';
	$peep = preg_replace_callback(
			'/@([a-zA-Z0-9_]{1,15})([) ])/'
			, create_function(
				'$matches'
				, 'return peeps_profile_link($matches[1], \'@\', $matches[2]);'
			)
			, $peep
	);
	$peep = preg_replace_callback(
		'/\#([a-zA-Z0-9_]{1,15}) /'
		, create_function(
			'$matches'
			, 'return peeps_hashtag_link($matches[1], \'#\', \' \');'
		)
		, $peep
	);
	
	if (function_exists('make_chunky')) {
		return make_chunky($peep);
	}
	else {
		return make_clickable($peep);
	}
}

function peeps_peeps_form($type = 'input', $extra = '') {
	$output = '';
	if (current_user_can('publish_posts')) {
		$output .= '
<form action="'.get_bloginfo('wpurl').'/index.php" method="post" id="peeps_peeps_form" '.$extra.'>
	<fieldset>
		';
		switch ($type) {
			case 'input':
				$output .= '
		<p><input type="text" size="20" maxlength="140" id="peeps_peeps_text" name="peeps_peeps_text" onkeyup="akttCharCount();" /></p>
		<input type="hidden" name="peeps_action" value="peeps_post_peeps_sidebar" />
		<script type="text/javascript">
		//<![CDATA[
		function akttCharCount() {
			var count = document.getElementById("peeps_peeps_text").value.length;
			if (count > 0) {
				document.getElementById("peeps_char_count").innerHTML = 140 - count;
			}
			else {
				document.getElementById("peeps_char_count").innerHTML = "";
			}
		}
		setTimeout("akttCharCount();", 500);
		document.getElementById("peeps_peeps_form").setAttribute("autocomplete", "off");
		//]]>
		</script>
				';
				break;
			case 'textarea':
				$output .= '
		<p><textarea type="text" cols="60" rows="5" maxlength="140" id="peeps_peeps_text" name="peeps_peeps_text" onkeyup="akttCharCount();"></textarea></p>
		<input type="hidden" name="peeps_action" value="peeps_post_peeps_admin" />
		<script type="text/javascript">
		//<![CDATA[
		function akttCharCount() {
			var count = document.getElementById("peeps_peeps_text").value.length;
			if (count > 0) {
				document.getElementById("peeps_char_count").innerHTML = (140 - count) + "'.__(' characters remaining', 'gatorpeeps-tools').'";
			}
			else {
				document.getElementById("peeps_char_count").innerHTML = "";
			}
		}
		setTimeout("akttCharCount();", 500);
		document.getElementById("peeps_peeps_form").setAttribute("autocomplete", "off");
		//]]>
		</script>
				';
				break;
		}
		$output .= '
		<p>
			<input type="submit" id="peeps_peeps_submit" name="peeps_peeps_submit" value="'.__('Post Peep!', 'gatorpeeps-tools').'" />
			<span id="peeps_char_count"></span>
		</p>
		<div class="clear"></div>
	</fieldset>
</form>
		';
	}
	return $output;
}

function peeps_widget_init() {
	if (!function_exists('register_sidebar_widget')) {
		return;
	}
	function peeps_widget($args) {
		extract($args);
		$options = get_option('peeps_widget');
		$title = $options['title'];
		if (empty($title)) {
		}
		echo $before_widget . $before_title . $title . $after_title;
		peeps_sidebar_peeps();
		echo $after_widget;
	}
	register_sidebar_widget(array(__('Gatorpeeps Tools', 'gatorpeeps-tools'), 'widgets'), 'peeps_widget');
	
	function peeps_widget_control() {
		$options = get_option('peeps_widget');
		if (!is_array($options)) {
			$options = array(
				'title' => __("What I'm Doing...", 'gatorpeeps-tools')
			);
		}
		if (isset($_POST['peeps_action']) && $_POST['peeps_action'] == 'peeps_update_widget_options') {
			$options['title'] = strip_tags(stripslashes($_POST['peeps_widget_title']));
			update_option('peeps_widget', $options);
			// reset checking so that sidebar isn't blank if this is the first time activating
			peeps_reset_peeps_checking();
			peeps_update_peeps();
		}

		// Be sure you format your options to be valid HTML attributes.
		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		
		// Here is our little form segment. Notice that we don't need a
		// complete form. This will be embedded into the existing form.
		print('
			<p style="text-align:right;"><label for="peeps_widget_title">' . __('Title:') . ' <input style="width: 200px;" id="peeps_widget_title" name="peeps_widget_title" type="text" value="'.$title.'" /></label></p>
			<p>'.__('Find additional Gatorpeeps Tools options on the <a href="options-general.php?page=gatorpeeps-tools.php">Gatorpeeps Tools Options page</a>.', 'gatorpeeps-tools').'
			<input type="hidden" id="peeps_action" name="peeps_action" value="peeps_update_widget_options" />
		');
	}
	register_widget_control(array(__('Gatorpeeps Tools', 'gatorpeeps-tools'), 'widgets'), 'peeps_widget_control', 300, 100);

}
add_action('widgets_init', 'peeps_widget_init');

function peeps_init() {
	global $wpdb, $peeps;
	$peeps = new gatorpeeps_tools;

	$wpdb->peeps = $wpdb->prefix.'gp_gatorpeeps';

	$peeps->get_settings();
	if (($peeps->last_peeps_download + $peeps->peeps_download_interval()) < time()) {
		add_action('shutdown', 'peeps_update_peeps');
		add_action('shutdown', 'peeps_ping_digests');
	}
	if (is_admin() || $peeps->peeps_from_sidebar) {
		switch ($peeps->js_lib) {
			case 'jquery':
				wp_enqueue_script('jquery');
				break;
			case 'prototype':
				wp_enqueue_script('prototype');
				break;
		}
	}
	global $wp_version;
	if (isset($wp_version) && version_compare($wp_version, '2.5', '>=') && empty ($peeps->install_date)) {
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>Please update your <a href=\"".get_bloginfo('wpurl')."/wp-admin/options-general.php?page=gatorpeeps-tools.php\">Gatorpeeps Tools settings</a>.</p></div>';" ) );
	}
}
add_action('init', 'peeps_init');

function peeps_head() {
	global $peeps;
	if ($peeps->peeps_from_sidebar) {
		print('
			<link rel="stylesheet" type="text/css" href="'.get_bloginfo('wpurl').'/index.php?peeps_action=peeps_css" />
			<script type="text/javascript" src="'.get_bloginfo('wpurl').'/index.php?peeps_action=peeps_js"></script>
		');
	}
}
add_action('wp_head', 'peeps_head');

function peeps_head_admin() {
	print('
		<link rel="stylesheet" type="text/css" href="'.get_bloginfo('wpurl').'/index.php?peeps_action=peeps_css_admin" />
		<script type="text/javascript" src="'.get_bloginfo('wpurl').'/index.php?peeps_action=peeps_js_admin"></script>
	');
}
add_action('admin_head', 'peeps_head_admin');

function peeps_request_handler() {
	global $wpdb, $peeps;
	if (!empty($_GET['peeps_action'])) {
		switch($_GET['peeps_action']) {
			case 'peeps_update_peeps':
				peeps_update_peeps();
				wp_redirect(get_bloginfo('wpurl').'/wp-admin/options-general.php?page=gatorpeeps-tools.php&peeps-updated=true');
				die();
				break;
			case 'peeps_reset_peeps_checking':
				peeps_reset_peeps_checking();
				wp_redirect(get_bloginfo('wpurl').'/wp-admin/options-general.php?page=gatorpeeps-tools.php&peep-checking-reset=true');
				die();
				break;
			case 'peeps_js':
				remove_action('shutdown', 'peeps_ping_digests');
				header("Content-type: text/javascript");
				switch ($peeps->js_lib) {
					case 'jquery':
?>
function akttPostPeep() {
	var peeps_field = jQuery('#peeps_peeps_text');
	var peeps_text = peeps_field.val();
	if (peeps_text == '') {
		return;
	}
	var peeps_msg = jQuery("#peeps_peeps_posted_msg");
	jQuery.post(
		"<?php bloginfo('wpurl'); ?>/index.php"
		, {
			peeps_action: "peeps_post_peeps_sidebar"
			, peeps_peeps_text: peeps_text
		}
		, function(data) {
			peeps_msg.html(data);
			akttSetReset();
		}
	);
	peeps_field.val('').focus();
	jQuery('#peeps_char_count').html('');
	jQuery("#peeps_peeps_posted_msg").show();
}
function akttSetReset() {
	setTimeout('akttReset();', 2000);
}
function akttReset() {
	jQuery('#peeps_peeps_posted_msg').hide();
}
<?php
						break;
					case 'prototype':
?>
function akttPostPeep() {
	var peeps_field = $('peeps_peeps_text');
	var peeps_text = peeps_field.value;
	if (peeps_text == '') {
		return;
	}
	var peeps_msg = $("peeps_peeps_posted_msg");
	var akttAjax = new Ajax.Updater(
		peeps_msg,
		"<?php bloginfo('wpurl'); ?>/index.php",
		{
			method: "post",
			parameters: "peeps_action=peeps_post_peeps_sidebar&peeps_peeps_text=" + peeps_text,
			onComplete: akttSetReset
		}
	);
	peeps_field.value = '';
	peeps_field.focus();
	$('peeps_char_count').innerHTML = '';
	peeps_msg.style.display = 'block';
}
function akttSetReset() {
	setTimeout('akttReset();', 2000);
}
function akttReset() {
	$('peeps_peeps_posted_msg').style.display = 'none';
}
<?php
						break;
				}
				die();
				break;
			case 'peeps_css':
				remove_action('shutdown', 'peeps_ping_digests');
				header("Content-Type: text/css");
?>
#peeps_peeps_form {
	margin: 0;
	padding: 5px 0;
}
#peeps_peeps_form fieldset {
	border: 0;
}
#peeps_peeps_form fieldset #peeps_peeps_submit {
	float: right;
	margin-right: 10px;
}
#peeps_peeps_form fieldset #peeps_char_count {
	color: #666;
}
#peeps_peeps_posted_msg {
	background: #ffc;
	display: none;
	margin: 0 0 5px 0;
	padding: 5px;
}
#peeps_peeps_form div.clear {
	clear: both;
	float: none;
}
<?php
				die();
				break;
			case 'peeps_js_admin':
				remove_action('shutdown', 'peeps_ping_digests');
				header("Content-Type: text/javascript");
				switch ($peeps->js_lib) {
					case 'jquery':
?>
function akttTestLogin() {
	var result = jQuery('#peeps_login_test_result');
	result.show().addClass('peeps_login_result_wait').html('<?php _e('Testing...', 'gatorpeeps-tools'); ?>');
	jQuery.post(
		"<?php bloginfo('wpurl'); ?>/index.php"
		, {
			peeps_action: "peeps_login_test"
			, peeps_gatorpeeps_username: jQuery('#peeps_gatorpeeps_username').val()
			, peeps_gatorpeeps_password: jQuery('#peeps_gatorpeeps_password').val()
		}
		, function(data) {
			result.html(data).removeClass('peeps_login_result_wait');
			setTimeout('akttTestLoginResult();', 5000);
		}
	);
};

function akttTestLoginResult() {
	jQuery('#peeps_login_test_result').fadeOut('slow');
};

(function($){

	jQuery.fn.timepicker = function(){
	
		var hrs = new Array();
		for(var h = 1; h <= 12; hrs.push(h++));

		var mins = new Array();
		for(var m = 0; m < 60; mins.push(m++));

		var ap = new Array('am', 'pm');

		function pad(n) {
			n = n.toString();
			return n.length == 1 ? '0' + n : n;
		}
	
		this.each(function() {

			var v = $(this).val();
			if (!v) v = new Date();

			var d = new Date(v);
			var h = d.getHours();
			var m = d.getMinutes();
			var p = (h >= 12) ? "pm" : "am";
			h = (h > 12) ? h - 12 : h;

			var output = '';

			output += '<select id="h_' + this.id + '" class="timepicker">';				
			for (var hr in hrs){
				output += '<option value="' + pad(hrs[hr]) + '"';
				if(parseInt(hrs[hr], 10) == h || (parseInt(hrs[hr], 10) == 12 && h == 0)) output += ' selected';
				output += '>' + pad(hrs[hr]) + '</option>';
			}
			output += '</select>';
	
			output += '<select id="m_' + this.id + '" class="timepicker">';				
			for (var mn in mins){
				output += '<option value="' + pad(mins[mn]) + '"';
				if(parseInt(mins[mn], 10) == m) output += ' selected';
				output += '>' + pad(mins[mn]) + '</option>';
			}
			output += '</select>';				
	
			output += '<select id="p_' + this.id + '" class="timepicker">';				
			for(var pp in ap){
				output += '<option value="' + ap[pp] + '"';
				if(ap[pp] == p) output += ' selected';
				output += '>' + ap[pp] + '</option>';
			}
			output += '</select>';
			
			$(this).after(output);
			
			var field = this;
			$(this).siblings('select.timepicker').change(function() {
				var h = parseInt($('#h_' + field.id).val(), 10);
				var m = parseInt($('#m_' + field.id).val(), 10);
				var p = $('#p_' + field.id).val();
	
				if (p == "am") {
					if (h == 12) {
						h = 0;
					}
				} else if (p == "pm") {
					if (h < 12) {
						h += 12;
					}
				}
				
				var d = new Date();
				d.setHours(h);
				d.setMinutes(m);
				
				$(field).val(d.toUTCString());
			}).change();

		});

		return this;
	};
	
	jQuery.fn.daypicker = function() {
		
		var days = new Array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
		
		this.each(function() {
			
			var v = $(this).val();
			if (!v) v = 0;
			v = parseInt(v, 10);
			
			var output = "";
			output += '<select id="d_' + this.id + '" class="daypicker">';				
			for (var i = 0; i < days.length; i++) {
				output += '<option value="' + i + '"';
				if (v == i) output += ' selected';
				output += '>' + days[i] + '</option>';
			}
			output += '</select>';
			
			$(this).after(output);
			
			var field = this;
			$(this).siblings('select.daypicker').change(function() {
				$(field).val( $(this).val() );
			}).change();
		
		});
		
	};
	
	jQuery.fn.forceToggleClass = function(classNames, bOn) {
		return this.each(function() {
			jQuery(this)[ bOn ? "addClass" : "removeClass" ](classNames);
		});
	};
	
})(jQuery);

jQuery(function() {

	// add in the time and day selects
	jQuery('form#gp_gatorpeepstools input.time').timepicker();
	jQuery('form#gp_gatorpeepstools input.day').daypicker();
	
	// togglers
	jQuery('.time_toggle .toggler').change(function() {
		var theSelect = jQuery(this);
		theSelect.parent('.time_toggle').forceToggleClass('active', theSelect.val() === "1");
	}).change();
	
});
<?php
						break;
					case 'prototype':
?>
function akttTestLogin() {
	var username = $('peeps_gatorpeeps_username').value;
	var password = $('peeps_gatorpeeps_password').value;
	var result = $('peeps_login_test_result');
	result.className = 'peeps_login_result_wait';
	result.innerHTML = '<?php _e('Testing...', 'gatorpeeps-tools'); ?>';
	var akttAjax = new Ajax.Updater(
		result,
		"<?php bloginfo('wpurl'); ?>/index.php",
		{
			method: "post",
			parameters: "peeps_action=peeps_login_test&peeps_gatorpeeps_username=" + username + "&peeps_gatorpeeps_password=" + password,
			onComplete: akttTestLoginResult
		}
	);
}
function akttTestLoginResult() {
	$('peeps_login_test_result').className = 'peeps_login_result';
	Fat.fade_element('peeps_login_test_result');
}
<?php
						break;
				}
				die();
				break;
			case 'peeps_css_admin':
				remove_action('shutdown', 'peeps_ping_digests');
				header("Content-Type: text/css");
?>
#peeps_peeps_form {
	margin: 0;
	padding: 5px 0;
}
#peeps_peeps_form fieldset {
	border: 0;
}
#peeps_peeps_form fieldset textarea {
	width: 95%;
}
#peeps_peeps_form fieldset #peeps_peeps_submit {
	float: right;
	margin-right: 50px;
}
#peeps_peeps_form fieldset #peeps_char_count {
	color: #666;
}
#ak_readme {
	height: 300px;
	width: 95%;
}
#gp_gatorpeepstools .options {
	overflow: hidden;
	border: none;
}
#gp_gatorpeepstools .option {
	overflow: hidden;
	border-bottom: dashed 1px #ccc;
	padding-bottom: 9px;
	padding-top: 9px;
}
#gp_gatorpeepstools .option label {
	display: block;
	float: left;
	width: 200px;
	margin-right: 24px;
	text-align: right;
}
#gp_gatorpeepstools .option span {
	display: block;
	float: left;
	margin-left: 230px;
	margin-top: 6px;
	clear: left;
}
#gp_gatorpeepstools select,
#gp_gatorpeepstools input {
	float: left;
	display: block;
	margin-right: 6px;
}
#gp_gatorpeepstools p.submit {
	overflow: hidden;
}
#gp_gatorpeepstools .option span {
	color: #666;
	display: block;
}
#gp_gatorpeepstools #peeps_login_test_result {
	display: inline;
	padding: 3px;
}
#gp_gatorpeepstools fieldset.options .option span.peeps_login_result_wait {
	background: #ffc;
}
#gp_gatorpeepstools fieldset.options .option span.peeps_login_result {
	background: #CFEBF7;
	color: #000;
}
#gp_gatorpeepstools .timepicker,
#gp_gatorpeepstools .daypicker {
	display: none;
}
#gp_gatorpeepstools .active .timepicker,
#gp_gatorpeepstools .active .daypicker {
	display: block
}
<?php
				die();
				break;
		}
	}
	if (!empty($_POST['peeps_action'])) {
		switch($_POST['peeps_action']) {
			case 'peeps_update_settings':
				$peeps->populate_settings();
				$peeps->update_settings();
				wp_redirect(get_bloginfo('wpurl').'/wp-admin/options-general.php?page=gatorpeeps-tools.php&updated=true');
				die();
				break;
			case 'peeps_post_peeps_sidebar':
				if (!empty($_POST['peeps_peeps_text']) && current_user_can('publish_posts')) {
					$peep = new peeps_peep();
					$peep->gp_text = stripslashes($_POST['peeps_peeps_text']);
					if ($peeps->do_peep($peep)) {
						die(__('Peep posted.', 'gatorpeeps-tools'));
					}
					else {
						die(__('Peep post failed.', 'gatorpeeps-tools'));
					}
				}
				break;
			case 'peeps_post_peeps_admin':
				if (!empty($_POST['peeps_peeps_text']) && current_user_can('publish_posts')) {
					$peep = new peeps_peep();
					$peep->gp_text = stripslashes($_POST['peeps_peeps_text']);
					if ($peeps->do_peep($peep)) {
						wp_redirect(get_bloginfo('wpurl').'/wp-admin/post-new.php?page=gatorpeeps-tools.php&peep-posted=true');
					}
					else {
						wp_die(__('Oops, your peep was not posted. Please check your username and password and that Gatorpeeps is up and running happily.', 'gatorpeeps-tools'));
					}
					die();
				}
				break;
			case 'peeps_login_test':
				$test = @peeps_login_test(
					@stripslashes($_POST['peeps_gatorpeeps_username'])
					, @stripslashes($_POST['peeps_gatorpeeps_password'])
				);
				die(__($test, 'gatorpeeps-tools'));
				break;
		}
	}
}
add_action('init', 'peeps_request_handler', 10);

function peeps_admin_peeps_form() {
	global $peeps;
	if ( $_GET['peep-posted'] ) {
		print('
			<div id="message" class="updated fade">
				<p>'.__('Peep posted.', 'gatorpeeps-tools').'</p>
			</div>
		');
	}
	print('
		<div class="wrap" id="peeps_write_peep">
	');
	if (empty($peeps->gatorpeeps_username) || empty($peeps->gatorpeeps_password)) {
		print('
<p>Please enter your <a href="http://gatorpeeps.com">Gatorpeeps</a> account information in your <a href="options-general.php?page=gatorpeeps-tools.php">Gatorpeeps Tools Options</a>.</p>		
		');
	}
	else {
		print('
			<h2>'.__('Write Peep', 'gatorpeeps-tools').'</h2>
			<p>This will create a new \'peep\' in <a href="http://gatorpeeps.com">Gatorpeeps</a> using the account information in your <a href="options-general.php?page=gatorpeeps-tools.php">Gatorpeeps Tools Options</a>.</p>
			'.peeps_peeps_form('textarea').'
		');
	}
	print('
		</div>
	');
}

function peeps_options_form() {
	global $wpdb, $peeps;

	$categories = get_categories('hide_empty=0');
	$cat_options = '';
	foreach ($categories as $category) {
// WP < 2.3 compatibility
		!empty($category->term_id) ? $cat_id = $category->term_id : $cat_id = $category->cat_ID;
		!empty($category->name) ? $cat_name = $category->name : $cat_name = $category->cat_name;
		if ($cat_id == $peeps->blog_post_category) {
			$selected = 'selected="selected"';
		}
		else {
			$selected = '';
		}
		$cat_options .= "\n\t<option value='$cat_id' $selected>$cat_name</option>";
	}

	$authors = get_users_of_blog();
	$author_options = '';
	foreach ($authors as $user) {
		$usero = new WP_User($user->user_id);
		$author = $usero->data;
		// Only list users who are allowed to publish
		if (! $usero->has_cap('publish_posts')) {
			continue;
		}
		if ($author->ID == $peeps->blog_post_author) {
			$selected = 'selected="selected"';
		}
		else {
			$selected = '';
		}
		$author_options .= "\n\t<option value='$author->ID' $selected>$author->user_nicename</option>";
	}
	
	$js_libs = array(
		'jquery' => 'jQuery'
		, 'prototype' => 'Prototype'
	);
	$js_lib_options = '';
	foreach ($js_libs as $js_lib => $js_lib_display) {
		if ($js_lib == $peeps->js_lib) {
			$selected = 'selected="selected"';
		}
		else {
			$selected = '';
		}
		$js_lib_options .= "\n\t<option value='$js_lib' $selected>$js_lib_display</option>";
	}
	$digest_peeps_orders = array(
		'ASC' => 'Oldest first (Chronological order)'
		, 'DESC' => 'Newest first (Reverse-chronological order)'
	);
	$digest_peeps_order_options = '';
	foreach ($digest_peeps_orders as $digest_peeps_order => $digest_peeps_order_display) {
		if ($digest_peeps_order == $peeps->digest_peeps_order) {
			$selected = 'selected="selected"';
		}
		else {
			$selected = '';
		}
		$digest_peeps_order_options .= "\n\t<option value='$digest_peeps_order' $selected>$digest_peeps_order_display</option>";
	}	
	$yes_no = array(
		'create_blog_posts'
		, 'create_digest'
		, 'create_digest_weekly'
		, 'notify_gatorpeeps'
		, 'notify_gatorpeeps_default'
		, 'peeps_from_sidebar'
		, 'give_tt_credit'
		, 'exclude_reply_peeps'
	);
	foreach ($yes_no as $key) {
		$var = $key.'_options';
		if ($peeps->$key == '0') {
			$$var = '
				<option value="0" selected="selected">'.__('No', 'gatorpeeps-tools').'</option>
				<option value="1">'.__('Yes', 'gatorpeeps-tools').'</option>
			';
		}
		else {
			$$var = '
				<option value="0">'.__('No', 'gatorpeeps-tools').'</option>
				<option value="1" selected="selected">'.__('Yes', 'gatorpeeps-tools').'</option>
			';
		}
	}
	if ( $_GET['peeps-updated'] ) {
		print('
			<div id="message" class="updated fade">
				<p>'.__('Peeps updated.', 'gatorpeeps-tools').'</p>
			</div>
		');
	}
	if ( $_GET['peep-checking-reset'] ) {
		print('
			<div id="message" class="updated fade">
				<p>'.__('Peep checking has been reset.', 'gatorpeeps-tools').'</p>
			</div>
		');
	}
	print('
			<div class="wrap" id="peeps_options_page">
				<h2>'.__('Gatorpeeps Tools Options', 'gatorpeeps-tools').'</h2>
				<form id="gp_gatorpeepstools" name="gp_gatorpeepstools" action="'.get_bloginfo('wpurl').'/wp-admin/options-general.php" method="post">
					<input type="hidden" name="peeps_action" value="peeps_update_settings" />
					<fieldset class="options">
						<div class="option">
							<label for="peeps_gatorpeeps_username">'.__('Gatorpeeps Username', 'gatorpeeps-tools').'/'.__('Password', 'gatorpeeps-tools').'</label>
							<input type="text" size="25" name="peeps_gatorpeeps_username" id="peeps_gatorpeeps_username" value="'.$peeps->gatorpeeps_username.'" autocomplete="off" />
							<input type="password" size="25" name="peeps_gatorpeeps_password" id="peeps_gatorpeeps_password" value="'.$peeps->gatorpeeps_password.'" autocomplete="off" />
							<input type="button" name="peeps_login_test" id="peeps_login_test" value="'.__('Test Login Info', 'gatorpeeps-tools').'" onclick="akttTestLogin(); return false;" />
							<span id="peeps_login_test_result"></span>
						</div>
						<div class="option">
							<label for="peeps_notify_gatorpeeps">'.__('Enable option to create a peep when you post in your blog?', 'gatorpeeps-tools').'</label>
							<select name="peeps_notify_gatorpeeps" id="peeps_notify_gatorpeeps">'.$notify_gatorpeeps_options.'</select>
						</div>
						<div class="option">
							<label for="peeps_notify_gatorpeeps_default">'.__('Set this on by default?', 'gatorpeeps-tools').'</label>
							<select name="peeps_notify_gatorpeeps_default" id="peeps_notify_gatorpeeps_default">'.$notify_gatorpeeps_default_options.'</select><span>'							.__('Also determines peeping for posting via XML-RPC', 'gatorpeeps-tools').'</span>
						</div>
						<div class="option">
							<label for="peeps_create_blog_posts">'.__('Create a blog post from each of your peeps?', 'gatorpeeps-tools').'</label>
							<select name="peeps_create_blog_posts" id="peeps_create_blog_posts">'.$create_blog_posts_options.'</select>
						</div>
						<div class="option time_toggle">
							<label>'.__('Create a daily digest blog post from your peeps?', 'gatorpeeps-tools').'</label>
							<select name="peeps_create_digest" class="toggler">'.$create_digest_options.'</select>
							<input type="hidden" class="time" id="peeps_digest_daily_time" name="peeps_digest_daily_time" value="'.$peeps->digest_daily_time.'" />
						</div>
						<div class="option">
							<label for="peeps_digest_title">'.__('Title for daily digest posts:', 'gatorpeeps-tools').'</label>
							<input type="text" size="30" name="peeps_digest_title" id="peeps_digest_title" value="'.$peeps->digest_title.'" />
							<span>'.__('Include %s where you want the date. Example: Peeps on %s', 'gatorpeeps-tools').'</span>
						</div>
						<div class="option time_toggle">
							<label>'.__('Create a weekly digest blog post from your peeps?', 'gatorpeeps-tools').'</label>
							<select name="peeps_create_digest_weekly" class="toggler">'.$create_digest_weekly_options.'</select>
							<input type="hidden" class="time" name="peeps_digest_weekly_time" id="peeps_digest_weekly_time" value="'.$peeps->digest_weekly_time.'" />
							<input type="hidden" class="day" name="peeps_digest_weekly_day" value="'.$peeps->digest_weekly_day.'" />
						</div>
						<div class="option">
							<label for="peeps_digest_title_weekly">'.__('Title for weekly digest posts:', 'gatorpeeps-tools').'</label>
							<input type="text" size="30" name="peeps_digest_title_weekly" id="peeps_digest_title_weekly" value="'.$peeps->digest_title_weekly.'" />
							<span>'.__('Include %s where you want the date. Example: Peeps on %s', 'gatorpeeps-tools').'</span>
						</div>
						<div class="option">
							<label for="peeps_digest_peeps_order">'.__('Order of peeps in digest?', 'gatorpeeps-tools').'</label>
							<select name="peeps_digest_peeps_order" id="peeps_digest_peeps_order">'.$digest_peeps_order_options.'</select>
						</div>
						<div class="option">
							<label for="peeps_blog_post_category">'.__('Category for peep posts:', 'gatorpeeps-tools').'</label>
							<select name="peeps_blog_post_category" id="peeps_blog_post_category">'.$cat_options.'</select>
						</div>
						<div class="option">
							<label for="peeps_blog_post_tags">'.__('Tag(s) for your peep posts:', 'gatorpeeps-tools').'</label>
							<input name="peeps_blog_post_tags" id="peeps_blog_post_tags" value="'.$peeps->blog_post_tags.'">
							<span>'._('Separate multiple tags with commas. Example: peeps, twitter').'</span>
						</div>
						<div class="option">
							<label for="peeps_blog_post_author">'.__('Author for peep posts:', 'gatorpeeps-tools').'</label>
							<select name="peeps_blog_post_author" id="peeps_blog_post_author">'.$author_options.'</select>
						</div>
						<div class="option">
							<label for="peeps_exclude_reply_peeps">'.__('Exclude @reply peeps in your sidebar, digests and created blog posts?', 'gatorpeeps-tools').'</label>
							<select name="peeps_exclude_reply_peeps" id="peeps_exclude_reply_peeps">'.$exclude_reply_peeps_options.'</select>
						</div>
						<div class="option">
							<label for="peeps_sidebar_peeps_count">'.__('Peeps to show in sidebar:', 'gatorpeeps-tools').'</label>
							<input type="text" size="3" name="peeps_sidebar_peeps_count" id="peeps_sidebar_peeps_count" value="'.$peeps->sidebar_peeps_count.'" />
							<span>'.__('Numbers only please.', 'gatorpeeps-tools').'</span>
						</div>
						<div class="option">
							<label for="peeps_peeps_from_sidebar">'.__('Create peeps from your sidebar?', 'gatorpeeps-tools').'</label>
							<select name="peeps_peeps_from_sidebar" id="peeps_peeps_from_sidebar">'.$peeps_from_sidebar_options.'</select>
						</div>
						<div class="option">
							<label for="peeps_js_lib">'.__('JS Library to use?', 'gatorpeeps-tools').'</label>
							<select name="peeps_js_lib" id="peeps_js_lib">'.$js_lib_options.'</select>
						</div>
						<div class="option">
							<label for="peeps_give_tt_credit">'.__('Give Gatorpeeps Tools credit?', 'gatorpeeps-tools').'</label>
							<select name="peeps_give_tt_credit" id="peeps_give_tt_credit">'.$give_tt_credit_options.'</select>
						</div>
					</fieldset>
					<p class="submit">
						<input type="submit" name="submit" value="'.__('Update Gatorpeeps Tools Options', 'gatorpeeps-tools').'" />
					</p>
				</form>
				<h2>'.__('Update Peeps', 'gatorpeeps-tools').'</h2>
				<form name="gp_gatorpeepstools_updatepeeps" action="'.get_bloginfo('wpurl').'/wp-admin/options-general.php" method="get">
					<p>'.__('Use this button to manually update your peeps.', 'gatorpeeps-tools').'</p>
					<p class="submit">
						<input type="submit" name="submit-button" value="'.__('Update Peeps', 'gatorpeeps-tools').'" />
						<input type="submit" name="reset-button" value="'.__('Reset Peep Checking', 'gatorpeeps-tools').'" onclick="document.getElementById(\'peeps_action_2\').value = \'peeps_reset_peeps_checking\';" />
						<input type="hidden" name="peeps_action" id="peeps_action_2" value="peeps_update_peeps" />
					</p>
				</form>
				<h2>'.__('Other Gatorpeeps Tools', 'gatorpeeps-tools').'</h2>
				<p>'.__('For more WordPress and other useful plugins please visit the <a href="http://afrigator.com/peeps/tools">Gatorpeeps Tools page</a>.', 'gatorpeeps-tools').'</p>
				<iframe id="ak_readme" src="http://afrigator.com/peeps/tools"></iframe>
			</div>
	');
}

function peeps_post_options() {
	global $peeps, $post;
	if ($peeps->notify_gatorpeeps) {
		echo '<div class="postbox">
			<h3>Gatorpeeps Tools</h3>
			<div class="inside">
			<p>Notify Gatorpeeps about this post?
			';
		$notify = get_post_meta($post->ID, 'peeps_notify_gatorpeeps', true);
		if ($notify == '') {
			switch ($peeps->notify_gatorpeeps_default) {
				case '1':
					$notify = 'yes';
					break;
				case '0':
					$notify = 'no';
					break;
			}
		}
		if ($notify == 'no') {
			$yes = '';
			$no = 'checked="checked"';
		}
		else {
			$yes = 'checked="checked"';
			$no = '';
		}
		echo '
		<input type="radio" name="peeps_notify_gatorpeeps" id="peeps_notify_gatorpeeps_yes" value="yes" '.$yes.' /> <label for="peeps_notify_gatorpeeps_yes">Yes</label> &nbsp;&nbsp;
		<input type="radio" name="peeps_notify_gatorpeeps" id="peeps_notify_gatorpeeps_no" value="no" '.$no.' /> <label for="peeps_notify_gatorpeeps_no">No</label>
		';
		echo '
			</p>
			</div><!--.inside-->
			</div><!--.postbox-->
		';
	}
}
add_action('edit_form_advanced', 'peeps_post_options');

function peeps_store_post_options($post_id, $post = false) {
	global $peeps;
	$post = get_post($post_id);
	if (!$post || $post->post_type == 'revision') {
		return;
	}

	$notify_meta = get_post_meta($post_id, 'peeps_notify_gatorpeeps', true);
	$posted_meta = $_POST['peeps_notify_gatorpeeps'];

	$save = false;
	if (!empty($posted_meta)) {
		$posted_meta == 'yes' ? $meta = 'yes' : $meta = 'no';
		$save = true;
	}
	else if (empty($notify_meta)) {
		$peeps->notify_gatorpeeps_default ? $meta = 'yes' : $meta = 'no';
		$save = true;
	}
	else {
		$save = false;
	}
	
	if ($save) {
		if (!update_post_meta($post_id, 'peeps_notify_gatorpeeps', $meta)) {
			add_post_meta($post_id, 'peeps_notify_gatorpeeps', $meta);
		}
	}
}
add_action('draft_post', 'peeps_store_post_options', 1, 2);
add_action('publish_post', 'peeps_store_post_options', 1, 2);
add_action('save_post', 'peeps_store_post_options', 1, 2);

function peeps_menu_items() {
	if (current_user_can('manage_options')) {
		add_options_page(
			__('Gatorpeeps Tools Options', 'gatorpeeps-tools')
			, __('Gatorpeeps Tools', 'gatorpeeps-tools')
			, 10
			, basename(__FILE__)
			, 'peeps_options_form'
		);
	}
	if (current_user_can('publish_posts')) {
		add_submenu_page(
			'post-new.php'
			, __('New Peep', 'gatorpeeps-tools')
			, __('Peep', 'gatorpeeps-tools')
			, 2
			, basename(__FILE__)
			, 'peeps_admin_peeps_form'
		);
	}
}
add_action('admin_menu', 'peeps_menu_items');

function peeps_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__);
	if ($file == $plugin_file) {
		$settings_link = '<a href="options-general.php?page='.$plugin_file.'">'.__('Settings', 'gatorpeeps-tools').'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'peeps_plugin_action_links', 10, 2);

if (!function_exists('trim_add_elipsis')) {
	function trim_add_elipsis($string, $limit = 100) {
		if (strlen($string) > $limit) {
			$string = substr($string, 0, $limit)."...";
		}
		return $string;
	}
}

if (!function_exists('ak_gmmktime')) {
	function ak_gmmktime() {
		return gmmktime() - get_option('gmt_offset') * 3600;
	}
}

/**
Relative Time format (updated 04-05-2009)
*/

function plural($num) {
	if ($num != 1)
	return "s";
}

function peeps_relativeTime ($date) {
	$diff = time() - strtotime($date);
	if ($diff<60)
	return $diff . " second" . plural($diff) . " ago";
	$diff = round($diff/60);
	if ($diff<60)
	return $diff . " minute" . plural($diff) . " ago";
	$diff = round($diff/60);
	if ($diff<24)
	return $diff . " hour" . plural($diff) . " ago";
	$diff = round($diff/24);
	if ($diff<7)
	return $diff . " day" . plural($diff) . " ago";
	$diff = round($diff/7);
	if ($diff<4)
	return $diff . " week" . plural($diff) . " ago";
	return "on " . date("F j, Y", strtotime($date));
}
if (!class_exists('Services_JSON')) {

// PEAR JSON class

/**
* Converts to and from JSON format.
*
* JSON (JavaScript Object Notation) is a lightweight data-interchange
* format. It is easy for humans to read and write. It is easy for machines
* to parse and generate. It is based on a subset of the JavaScript
* Programming Language, Standard ECMA-262 3rd Edition - December 1999.
* This feature can also be found in  Python. JSON is a text format that is
* completely language independent but uses conventions that are familiar
* to programmers of the C-family of languages, including C, C++, C#, Java,
* JavaScript, Perl, TCL, and many others. These properties make JSON an
* ideal data-interchange language.
*
* This package provides a simple encoder and decoder for JSON notation. It
* is intended for use with client-side Javascript applications that make
* use of HTTPRequest to perform server communication functions - data can
* be encoded into JSON notation for use in a client-side javascript, or
* decoded from incoming Javascript requests. JSON format is native to
* Javascript, and can be directly eval()'ed with no further parsing
* overhead
*
* All strings should be in ASCII or UTF-8 format!
*
* LICENSE: Redistribution and use in source and binary forms, with or
* without modification, are permitted provided that the following
* conditions are met: Redistributions of source code must retain the
* above copyright notice, this list of conditions and the following
* disclaimer. Redistributions in binary form must reproduce the above
* copyright notice, this list of conditions and the following disclaimer
* in the documentation and/or other materials provided with the
* distribution.
*
* THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
* WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
* MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
* NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
* INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
* BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
* OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
* ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
* TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
* USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
* DAMAGE.
*
* @category
* @package     Services_JSON
* @author      Michal Migurski <mike-json@teczno.com>
* @author      Matt Knapp <mdknapp[at]gmail[dot]com>
* @author      Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
* @copyright   2005 Michal Migurski
* @version     CVS: $Id: JSON.php,v 1.31 2006/06/28 05:54:17 migurski Exp $
* @license     http://www.opensource.org/licenses/bsd-license.php
* @link        http://pear.php.net/pepr/pepr-proposal-show.php?id=198
*/

/**
* Marker constant for Services_JSON::decode(), used to flag stack state
*/
define('SERVICES_JSON_SLICE',   1);

/**
* Marker constant for Services_JSON::decode(), used to flag stack state
*/
define('SERVICES_JSON_IN_STR',  2);

/**
* Marker constant for Services_JSON::decode(), used to flag stack state
*/
define('SERVICES_JSON_IN_ARR',  3);

/**
* Marker constant for Services_JSON::decode(), used to flag stack state
*/
define('SERVICES_JSON_IN_OBJ',  4);

/**
* Marker constant for Services_JSON::decode(), used to flag stack state
*/
define('SERVICES_JSON_IN_CMT', 5);

/**
* Behavior switch for Services_JSON::decode()
*/
define('SERVICES_JSON_LOOSE_TYPE', 16);

/**
* Behavior switch for Services_JSON::decode()
*/
define('SERVICES_JSON_SUPPRESS_ERRORS', 32);

/**
* Converts to and from JSON format.
*
* Brief example of use:
*
* <code>
* // create a new instance of Services_JSON
* $json = new Services_JSON();
*
* // convert a complexe value to JSON notation, and send it to the browser
* $value = array('foo', 'bar', array(1, 2, 'baz'), array(3, array(4)));
* $output = $json->encode($value);
*
* print($output);
* // prints: ["foo","bar",[1,2,"baz"],[3,[4]]]
*
* // accept incoming POST data, assumed to be in JSON notation
* $input = file_get_contents('php://input', 1000000);
* $value = $json->decode($input);
* </code>
*/
class Services_JSON
{
   /**
    * constructs a new JSON instance
    *
    * @param    int     $use    object behavior flags; combine with boolean-OR
    *
    *                           possible values:
    *                           - SERVICES_JSON_LOOSE_TYPE:  loose typing.
    *                                   "{...}" syntax creates associative arrays
    *                                   instead of objects in decode().
    *                           - SERVICES_JSON_SUPPRESS_ERRORS:  error suppression.
    *                                   Values which can't be encoded (e.g. resources)
    *                                   appear as NULL instead of throwing errors.
    *                                   By default, a deeply-nested resource will
    *                                   bubble up with an error, so all return values
    *                                   from encode() should be checked with isError()
    */
    function Services_JSON($use = 0)
    {
        $this->use = $use;
    }

   /**
    * convert a string from one UTF-16 char to one UTF-8 char
    *
    * Normally should be handled by mb_convert_encoding, but
    * provides a slower PHP-only method for installations
    * that lack the multibye string extension.
    *
    * @param    string  $utf16  UTF-16 character
    * @return   string  UTF-8 character
    * @access   private
    */
    function utf162utf8($utf16)
    {
        // oh please oh please oh please oh please oh please
        if(function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
        }

        $bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

        switch(true) {
            case ((0x7F & $bytes) == $bytes):
                // this case should never be reached, because we are in ASCII range
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0x7F & $bytes);

            case (0x07FF & $bytes) == $bytes:
                // return a 2-byte UTF-8 character
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0xC0 | (($bytes >> 6) & 0x1F))
                     . chr(0x80 | ($bytes & 0x3F));

            case (0xFFFF & $bytes) == $bytes:
                // return a 3-byte UTF-8 character
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0xE0 | (($bytes >> 12) & 0x0F))
                     . chr(0x80 | (($bytes >> 6) & 0x3F))
                     . chr(0x80 | ($bytes & 0x3F));
        }

        // ignoring UTF-32 for now, sorry
        return '';
    }

   /**
    * convert a string from one UTF-8 char to one UTF-16 char
    *
    * Normally should be handled by mb_convert_encoding, but
    * provides a slower PHP-only method for installations
    * that lack the multibye string extension.
    *
    * @param    string  $utf8   UTF-8 character
    * @return   string  UTF-16 character
    * @access   private
    */
    function utf82utf16($utf8)
    {
        // oh please oh please oh please oh please oh please
        if(function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
        }

        switch(strlen($utf8)) {
            case 1:
                // this case should never be reached, because we are in ASCII range
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return $utf8;

            case 2:
                // return a UTF-16 character from a 2-byte UTF-8 char
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr(0x07 & (ord($utf8{0}) >> 2))
                     . chr((0xC0 & (ord($utf8{0}) << 6))
                         | (0x3F & ord($utf8{1})));

            case 3:
                // return a UTF-16 character from a 3-byte UTF-8 char
                // see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                return chr((0xF0 & (ord($utf8{0}) << 4))
                         | (0x0F & (ord($utf8{1}) >> 2)))
                     . chr((0xC0 & (ord($utf8{1}) << 6))
                         | (0x7F & ord($utf8{2})));
        }

        // ignoring UTF-32 for now, sorry
        return '';
    }

   /**
    * encodes an arbitrary variable into JSON format
    *
    * @param    mixed   $var    any number, boolean, string, array, or object to be encoded.
    *                           see argument 1 to Services_JSON() above for array-parsing behavior.
    *                           if var is a strng, note that encode() always expects it
    *                           to be in ASCII or UTF-8 format!
    *
    * @return   mixed   JSON string representation of input var or an error if a problem occurs
    * @access   public
    */
    function encode($var)
    {
        switch (gettype($var)) {
            case 'boolean':
                return $var ? 'true' : 'false';

            case 'NULL':
                return 'null';

            case 'integer':
                return (int) $var;

            case 'double':
            case 'float':
                return (float) $var;

            case 'string':
                // STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT
                $ascii = '';
                $strlen_var = strlen($var);

               /*
                * Iterate over every character in the string,
                * escaping with a slash or encoding to UTF-8 where necessary
                */
                for ($c = 0; $c < $strlen_var; ++$c) {

                    $ord_var_c = ord($var{$c});

                    switch (true) {
                        case $ord_var_c == 0x08:
                            $ascii .= '\b';
                            break;
                        case $ord_var_c == 0x09:
                            $ascii .= '\t';
                            break;
                        case $ord_var_c == 0x0A:
                            $ascii .= '\n';
                            break;
                        case $ord_var_c == 0x0C:
                            $ascii .= '\f';
                            break;
                        case $ord_var_c == 0x0D:
                            $ascii .= '\r';
                            break;

                        case $ord_var_c == 0x22:
                        case $ord_var_c == 0x2F:
                        case $ord_var_c == 0x5C:
                            // double quote, slash, slosh
                            $ascii .= '\\'.$var{$c};
                            break;

                        case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
                            // characters U-00000000 - U-0000007F (same as ASCII)
                            $ascii .= $var{$c};
                            break;

                        case (($ord_var_c & 0xE0) == 0xC0):
                            // characters U-00000080 - U-000007FF, mask 110XXXXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c, ord($var{$c + 1}));
                            $c += 1;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xF0) == 0xE0):
                            // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}));
                            $c += 2;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xF8) == 0xF0):
                            // characters U-00010000 - U-001FFFFF, mask 11110XXX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}));
                            $c += 3;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xFC) == 0xF8):
                            // characters U-00200000 - U-03FFFFFF, mask 111110XX
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}),
                                         ord($var{$c + 4}));
                            $c += 4;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ord_var_c & 0xFE) == 0xFC):
                            // characters U-04000000 - U-7FFFFFFF, mask 1111110X
                            // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                            $char = pack('C*', $ord_var_c,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}),
                                         ord($var{$c + 4}),
                                         ord($var{$c + 5}));
                            $c += 5;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;
                    }
                }

                return '"'.$ascii.'"';

            case 'array':
               /*
                * As per JSON spec if any array key is not an integer
                * we must treat the the whole array as an object. We
                * also try to catch a sparsely populated associative
                * array with numeric keys here because some JS engines
                * will create an array with empty indexes up to
                * max_index which can cause memory issues and because
                * the keys, which may be relevant, will be remapped
                * otherwise.
                *
                * As per the ECMA and JSON specification an object may
                * have any string as a property. Unfortunately due to
                * a hole in the ECMA specification if the key is a
                * ECMA reserved word or starts with a digit the
                * parameter is only accessible using ECMAScript's
                * bracket notation.
                */

                // treat as a JSON object
                if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1))) {
                    $properties = array_map(array($this, 'name_value'),
                                            array_keys($var),
                                            array_values($var));

                    foreach($properties as $property) {
                        if(Services_JSON::isError($property)) {
                            return $property;
                        }
                    }

                    return '{' . join(',', $properties) . '}';
                }

                // treat it like a regular array
                $elements = array_map(array($this, 'encode'), $var);

                foreach($elements as $element) {
                    if(Services_JSON::isError($element)) {
                        return $element;
                    }
                }
				
				return '[' . join(',', $elements) . ']';

            case 'object':
                $vars = get_object_vars($var);

                $properties = array_map(array($this, 'name_value'),
                                        array_keys($vars),
                                        array_values($vars));

                foreach($properties as $property) {
                    if(Services_JSON::isError($property)) {
                        return $property;
                    }
                }

                return '{' . join(',', $properties) . '}';

            default:
                return ($this->use & SERVICES_JSON_SUPPRESS_ERRORS)
                    ? 'null'
                    : new Services_JSON_Error(gettype($var)." can not be encoded as JSON string");
        }
    }

   /**
    * array-walking function for use in generating JSON-formatted name-value pairs
    *
    * @param    string  $name   name of key to use
    * @param    mixed   $value  reference to an array element to be encoded
    *
    * @return   string  JSON-formatted name-value pair, like '"name":value'
    * @access   private
    */
    function name_value($name, $value)
    {
        $encoded_value = $this->encode($value);

        if(Services_JSON::isError($encoded_value)) {
            return $encoded_value;
        }

        return $this->encode(strval($name)) . ':' . $encoded_value;
    }

   /**
    * reduce a string by removing leading and trailing comments and whitespace
    *
    * @param    $str    string      string value to strip of comments and whitespace
    *
    * @return   string  string value stripped of comments and whitespace
    * @access   private
    */
    function reduce_string($str)
    {
        $str = preg_replace(array(

                // eliminate single line comments in '// ...' form
                '#^\s*//(.+)$#m',

                // eliminate multi-line comments in '/* ... */' form, at start of string
                '#^\s*/\*(.+)\*/#Us',

                // eliminate multi-line comments in '/* ... */' form, at end of string
                '#/\*(.+)\*/\s*$#Us'

            ), '', $str);

        // eliminate extraneous space
        return trim($str);
    }

   /**
    * decodes a JSON string into appropriate variable
    *
    * @param    string  $str    JSON-formatted string
    *
    * @return   mixed   number, boolean, string, array, or object
    *                   corresponding to given JSON input string.
    *                   See argument 1 to Services_JSON() above for object-output behavior.
    *                   Note that decode() always returns strings
    *                   in ASCII or UTF-8 format!
    * @access   public
    */
    function decode($str)
    {
        $str = $this->reduce_string($str);

        switch (strtolower($str)) {
            case 'true':
                return true;

            case 'false':
                return false;

            case 'null':
                return null;

            default:
                $m = array();

                if (is_numeric($str)) {
                    // Lookie-loo, it's a number

                    // This would work on its own, but I'm trying to be
                    // good about returning integers where appropriate:
                    // return (float)$str;

                    // Return float or int, as appropriate
                    return ((float)$str == (integer)$str)
                        ? (integer)$str
                        : (float)$str;

                } elseif (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] == $m[2]) {
                    // STRINGS RETURNED IN UTF-8 FORMAT
                    $delim = substr($str, 0, 1);
                    $chrs = substr($str, 1, -1);
                    $utf8 = '';
                    $strlen_chrs = strlen($chrs);

                    for ($c = 0; $c < $strlen_chrs; ++$c) {

                        $substr_chrs_c_2 = substr($chrs, $c, 2);
                        $ord_chrs_c = ord($chrs{$c});

                        switch (true) {
                            case $substr_chrs_c_2 == '\b':
                                $utf8 .= chr(0x08);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\t':
                                $utf8 .= chr(0x09);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\n':
                                $utf8 .= chr(0x0A);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\f':
                                $utf8 .= chr(0x0C);
                                ++$c;
                                break;
                            case $substr_chrs_c_2 == '\r':
                                $utf8 .= chr(0x0D);
                                ++$c;
                                break;

                            case $substr_chrs_c_2 == '\\"':
                            case $substr_chrs_c_2 == '\\\'':
                            case $substr_chrs_c_2 == '\\\\':
                            case $substr_chrs_c_2 == '\\/':
                                if (($delim == '"' && $substr_chrs_c_2 != '\\\'') ||
                                   ($delim == "'" && $substr_chrs_c_2 != '\\"')) {
                                    $utf8 .= $chrs{++$c};
                                }
                                break;

                            case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $c, 6)):
                                // single, escaped unicode character
                                $utf16 = chr(hexdec(substr($chrs, ($c + 2), 2)))
                                       . chr(hexdec(substr($chrs, ($c + 4), 2)));
                                $utf8 .= $this->utf162utf8($utf16);
                                $c += 5;
                                break;

                            case ($ord_chrs_c >= 0x20) && ($ord_chrs_c <= 0x7F):
                                $utf8 .= $chrs{$c};
                                break;

                            case ($ord_chrs_c & 0xE0) == 0xC0:
                                // characters U-00000080 - U-000007FF, mask 110XXXXX
                                //see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 2);
                                ++$c;
                                break;

                            case ($ord_chrs_c & 0xF0) == 0xE0:
                                // characters U-00000800 - U-0000FFFF, mask 1110XXXX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 3);
                                $c += 2;
                                break;

                            case ($ord_chrs_c & 0xF8) == 0xF0:
                                // characters U-00010000 - U-001FFFFF, mask 11110XXX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 4);
                                $c += 3;
                                break;

                            case ($ord_chrs_c & 0xFC) == 0xF8:
                                // characters U-00200000 - U-03FFFFFF, mask 111110XX
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 5);
                                $c += 4;
                                break;

                            case ($ord_chrs_c & 0xFE) == 0xFC:
                                // characters U-04000000 - U-7FFFFFFF, mask 1111110X
                                // see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
                                $utf8 .= substr($chrs, $c, 6);
                                $c += 5;
                                break;

                        }

                    }

                    return $utf8;

                } elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
                    // array, or object notation

                    if ($str{0} == '[') {
                        $stk = array(SERVICES_JSON_IN_ARR);
                        $arr = array();
                    } else {
                        if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                            $stk = array(SERVICES_JSON_IN_OBJ);
                            $obj = array();
                        } else {
                            $stk = array(SERVICES_JSON_IN_OBJ);
                            $obj = new stdClass();
                        }
                    }

                    array_push($stk, array('what'  => SERVICES_JSON_SLICE,
                                           'where' => 0,
                                           'delim' => false));

                    $chrs = substr($str, 1, -1);
                    $chrs = $this->reduce_string($chrs);

                    if ($chrs == '') {
                        if (reset($stk) == SERVICES_JSON_IN_ARR) {
                            return $arr;

                        } else {
                            return $obj;

                        }
                    }

                    //print("\nparsing {$chrs}\n");

                    $strlen_chrs = strlen($chrs);

                    for ($c = 0; $c <= $strlen_chrs; ++$c) {

                        $top = end($stk);
                        $substr_chrs_c_2 = substr($chrs, $c, 2);

                        if (($c == $strlen_chrs) || (($chrs{$c} == ',') && ($top['what'] == SERVICES_JSON_SLICE))) {
                            // found a comma that is not inside a string, array, etc.,
                            // OR we've reached the end of the character list
                            $slice = substr($chrs, $top['where'], ($c - $top['where']));
                            array_push($stk, array('what' => SERVICES_JSON_SLICE, 'where' => ($c + 1), 'delim' => false));
                            //print("Found split at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                            if (reset($stk) == SERVICES_JSON_IN_ARR) {
                                // we are in an array, so just push an element onto the stack
                                array_push($arr, $this->decode($slice));

                            } elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
                                // we are in an object, so figure
                                // out the property name and set an
                                // element in an associative array,
                                // for now
                                $parts = array();
                                
                                if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
                                    // "name":value pair
                                    $key = $this->decode($parts[1]);
                                    $val = $this->decode($parts[2]);

                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                } elseif (preg_match('/^\s*(\w+)\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
                                    // name:value pair, where name is unquoted
                                    $key = $parts[1];
                                    $val = $this->decode($parts[2]);

                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                }

                            }

                        } elseif ((($chrs{$c} == '"') || ($chrs{$c} == "'")) && ($top['what'] != SERVICES_JSON_IN_STR)) {
                            // found a quote, and we are not inside a string
                            array_push($stk, array('what' => SERVICES_JSON_IN_STR, 'where' => $c, 'delim' => $chrs{$c}));
                            //print("Found start of string at {$c}\n");

                        } elseif (($chrs{$c} == $top['delim']) &&
                                 ($top['what'] == SERVICES_JSON_IN_STR) &&
                                 ((strlen(substr($chrs, 0, $c)) - strlen(rtrim(substr($chrs, 0, $c), '\\'))) % 2 != 1)) {
                            // found a quote, we're in a string, and it's not escaped
                            // we know that it's not escaped becase there is _not_ an
                            // odd number of backslashes at the end of the string so far
                            array_pop($stk);
                            //print("Found end of string at {$c}: ".substr($chrs, $top['where'], (1 + 1 + $c - $top['where']))."\n");

                        } elseif (($chrs{$c} == '[') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // found a left-bracket, and we are in an array, object, or slice
                            array_push($stk, array('what' => SERVICES_JSON_IN_ARR, 'where' => $c, 'delim' => false));
                            //print("Found start of array at {$c}\n");

                        } elseif (($chrs{$c} == ']') && ($top['what'] == SERVICES_JSON_IN_ARR)) {
                            // found a right-bracket, and we're in an array
                            array_pop($stk);
                            //print("Found end of array at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        } elseif (($chrs{$c} == '{') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // found a left-brace, and we are in an array, object, or slice
                            array_push($stk, array('what' => SERVICES_JSON_IN_OBJ, 'where' => $c, 'delim' => false));
                            //print("Found start of object at {$c}\n");

                        } elseif (($chrs{$c} == '}') && ($top['what'] == SERVICES_JSON_IN_OBJ)) {
                            // found a right-brace, and we're in an object
                            array_pop($stk);
                            //print("Found end of object at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        } elseif (($substr_chrs_c_2 == '/*') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // found a comment start, and we are in an array, object, or slice
                            array_push($stk, array('what' => SERVICES_JSON_IN_CMT, 'where' => $c, 'delim' => false));
                            $c++;
                            //print("Found start of comment at {$c}\n");

                        } elseif (($substr_chrs_c_2 == '*/') && ($top['what'] == SERVICES_JSON_IN_CMT)) {
                            // found a comment end, and we're in one now
                            array_pop($stk);
                            $c++;

                            for ($i = $top['where']; $i <= $c; ++$i)
                                $chrs = substr_replace($chrs, ' ', $i, 1);

                            //print("Found end of comment at {$c}: ".substr($chrs, $top['where'], (1 + $c - $top['where']))."\n");

                        }

                    }

                    if (reset($stk) == SERVICES_JSON_IN_ARR) {
                        return $arr;

                    } elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
                        return $obj;

                    }

                }
        }
    }

    /**
     * @todo Ultimately, this should just call PEAR::isError()
     */
    function isError($data, $code = null)
    {
        if (class_exists('pear')) {
            return PEAR::isError($data, $code);
        } elseif (is_object($data) && (get_class($data) == 'services_json_error' ||
                                 is_subclass_of($data, 'services_json_error'))) {
            return true;
        }

        return false;
    }
}

if (class_exists('PEAR_Error')) {

    class Services_JSON_Error extends PEAR_Error
    {
        function Services_JSON_Error($message = 'unknown error', $code = null,
                                     $mode = null, $options = null, $userinfo = null)
        {
            parent::PEAR_Error($message, $code, $mode, $options, $userinfo);
        }
    }

} else {

    /**
     * @todo Ultimately, this class shall be descended from PEAR_Error
     */
    class Services_JSON_Error
    {
        function Services_JSON_Error($message = 'unknown error', $code = null,
                                     $mode = null, $options = null, $userinfo = null)
        {

        }
    }

}

}

?>