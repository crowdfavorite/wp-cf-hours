<?php
/*
Plugin Name: CF Hours 
Plugin URI: http://crowdfavorite.com 
Description: For use with the Crowd Favorite Billing system.  To help track hours and report based on those hours
Version: 1.0
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/

// Constants
define('CFHR_DIR',trailingslashit(realpath(dirname(__FILE__))));

register_activation_hook(__FILE__, 'cfhr_activation');

// 	ini_set('display_errors', '1'); ini_set('error_reporting', E_ALL);

function cfhr_admin_menu() {
	add_submenu_page(
		'tools.php',
		__('CF Hours'),
		__('CF Hours'),
		10,
		'cf-hours',
		'cfhr_settings'
	);
}
add_action('admin_menu', 'cfhr_admin_menu');

function cfhr_request_handler() {
	if (!empty($_GET['cf_action'])) {
		switch ($_GET['cf_action']) {
			case 'cfhr-js':
				cfhr_js();
				die();
				break;
			case 'cfhr-css':
				cfhr_css();
				die();
				break;
		}
	}
	else if (!empty($_POST['cf_action'])) {
		switch ($_POST['cf_action']) {
			case 'cfhr_submit':
				if (is_array($_POST['cfhr']) && !empty($_POST['cfhr'])) {
					cfhr_save_options($_POST['cfhr']);
					wp_redirect(admin_url('tools.php?page=cf-hours&cfhr=settings&updated=true'));
					die();
				}
				break;
			case 'cfhr-files':
				$folder = '/';
				if (!empty($_POST['folder'])) {
					$folder = strip_tags($_POST['folder']);
				}
				cfhr_files($folder);
				die();
				break;
			case 'cfhr-scrape':
				$folder = '/';
				if (!empty($_POST['folder'])) {
					$folder = strip_tags($_POST['folder']);
				}
				cfhr_scrape_files($folder);
				die();
				break;
			case 'cfhr-folders':
				$folder = '/';
				if (!empty($_POST['folder'])) {
					$use_cache = false;
					if (!empty($_POST['cache']) && $_POST['cache'] == '1') {
						$use_cache = true;
					}
					$folder = strip_tags($_POST['folder'], $use_cache);
				}
				cfhr_folders($folder);
				die();
				break;
			case 'cfhr-client-project':
				if (!empty($_POST['client'])) {
					echo cfhr_get_dropdown_project(stripslashes($_POST['client']));
				}
				die();
				break;
			case 'cfhr_vacation':
				$vacation = array();
				if (is_array($_POST['cfhr_vacation']) && !empty($_POST['cfhr_vacation'])) {
					$vacation = stripslashes_deep($_POST['cfhr_vacation']);
				}
				cfhr_save_vacation($vacation);
				wp_redirect(admin_url('tools.php?page=cf-hours&cfhr=settings&updated=true'));
				die();
				break;
			case 'cfhr_sick':
				$sick = array();
				if (is_array($_POST['cfhr_sick']) && !empty($_POST['cfhr_sick'])) {
					$sick = stripslashes_deep($_POST['cfhr_sick']);
				}
				cfhr_save_sick($sick);
				wp_redirect(admin_url('tools.php?page=cf-hours&cfhr=settings&updated=true'));
				die();
				break;

		}
	}

	global $paycheckstart, $paycheckend, $lastpaycheckstart, $lastpaycheckend, $holidays, $vacation, $sick, $off_holidays;
	global $secondpaycheckstart, $secondpaycheckend, $thirdpaycheckstart, $thirdpaycheckend, $fourthpaycheckstart, $fourthpaycheckend;
	
	
	$cfhr = get_option('cfhr_options');

	$paycheckstart 					= $cfhr['paycheck_start'];
	$paycheckend 					= $cfhr['paycheck_end'];
	$lastpaycheckstart 				= $cfhr['last_paycheck_start'];
	$lastpaycheckend 				= $cfhr['last_paycheck_end'];
	$secondpaycheckstart 			= $cfhr['second_paycheck_start'];
	$secondpaycheckend 				= $cfhr['second_paycheck_end'];
	$thirdpaycheckstart 			= $cfhr['third_paycheck_start'];
	$thirdpaycheckend 				= $cfhr['third_paycheck_end'];
	
	$off_holidays = array(
		'2011-01-01',
		'2011-05-30',
		'2011-07-04',
		'2011-09-05',
		'2011-11-24',
		'2011-12-23',
		'2011-12-30'
	);
	$vacation = get_option('cfhr_vacation');
	if (!is_array($vacation)) {
		$vacation = array();
	}
	$sick = get_option('cfhr_sick');
	if (!is_array($sick)) {
		$sick = array();
	}
	$holidays = array_merge($off_holidays, $vacation, $sick);
	sort($holidays);
}
add_action('init', 'cfhr_request_handler', 1);

function cfhr_activation() {
	global $wpdb;
	$results = $wpdb->get_results("SHOW TABLES LIKE 'cfhr_data';");
	
	if (is_array($results) && empty($results)) {
		$insert = $wpdb->query("
			CREATE TABLE IF NOT EXISTS `cfhr_data` (
			`id` int(10) NOT NULL AUTO_INCREMENT,
			`item_date` date NOT NULL,
			`client` varchar(250) NOT NULL,
			`project` varchar(250) NOT NULL,
			`category` varchar(250) NOT NULL,
			`description` text NOT NULL,
			`billed` float NOT NULL,
			`total` float NOT NULL,
			PRIMARY KEY (`id`))
		");
	}
}

function cfhr_save_options($options = array()) {
	if (!is_array($options) || empty($options)) { return; }

	if (is_array($options) && !empty($options)) {
		$values = get_option('cfhr_options');
		foreach ($options as $key => $value) {
			if ($key == 'pword' && empty($value)) { continue; }
			$values[strip_tags($key)] = strip_tags($value);
		}
		update_option('cfhr_options', $values);
	}
}

function cfhr_save_vacation($vacation = array()) {
	$save_vacation = array();
	if (is_array($vacation) && !empty($vacation)) {
		foreach ($vacation as $key => $item) {
			$save_vacation[] = $item['date'];
		}
		sort($save_vacation);
	}
	update_option('cfhr_vacation', $save_vacation);
}

function cfhr_save_sick($sick = array()) {
	$save_sick = array();
	if (is_array($sick) && !empty($sick)) {
		foreach ($sick as $key => $item) {
			$save_sick[] = $item['date'];
		}
		sort($save_sick);
	}
	update_option('cfhr_sick', $save_sick);
}

function cfhr_save_paycheck($paycheck = array()) {
	
}

function cfhr_js() {
	header('Content-type: text/javascript');
	?>
	;(function($) {
		$(function() {
			$("#cfhr-show-data").click(function() {
				var folder = $("#cfhr-folder").val();
				$.post('<?php echo admin_url(); ?>', {
					cf_action: 'cfhr-files',
					folder: folder
				}, function(data) {
					$("#cfhr-data").html(data);
				});
				return false;
			});
			$("#cfhr-scrape-data").click(function() {
				var folder = $("#cfhr-folder").val();
				$.post('<?php echo admin_url(); ?>', {
					cf_action: 'cfhr-scrape',
					folder: folder
				}, function(data) {
					alert('complete');
				});
				return false;
			});
			$("#cfhr-get-folders").click(function() {
				var folder = $("#cfhr-folder").val();
				$.post('<?php echo admin_url(); ?>', {
					cf_action: 'cfhr-folders',
					folder: folder
				}, function(data) {
					$("#cfhr-data").html(data);
				});
				return false;
			});
			$("#cfhr-get-cache-folders").click(function() {
				var folder = $("#cfhr-folder").val();
				$.post('<?php echo admin_url(); ?>', {
					cf_action: 'cfhr-folders',
					folder: folder,
					cache: 1
				}, function(data) {
					$("#cfhr-data").html(data);
				});
				return false;
			});
			$("#cfhr-clear-data").click(function() {
				$("#cfhr-data").html('');
				return false;
			});
			
			$("#cfhr-clients").live('change', function() {
				var _this = $(this);
				var client = _this.val();
				
				$.post('<?php echo admin_url(); ?>', {
					cf_action: 'cfhr-client-project',
					client: client
				}, function(data) {
					$("#cfhr-projects").remove();
					$("#cfhr-targeted-reporting-selection").append(data);
				});
				return false;
			});
			
			$("#cfhr-vacation-new").click(function(e) {
				var id = new Date().valueOf();
				var item = id.toString();
				var html = $("#cfhr-vacation-new table tbody").html().replace(/###ITEM###/g, item);
				$("#cfhr-vacation table tbody").append(html);
				e.preventDefault();
			});

			$("#cfhr-sick-new").click(function(e) {
				var id = new Date().valueOf();
				var item = id.toString();
				var html = $("#cfhr-sick-new table tbody").html().replace(/###ITEM###/g, item);
				$("#cfhr-sick table tbody").append(html);
				e.preventDefault();
			});
			
			$(".cfhr-vacation-remove").click(function(e) {
				if (confirm('Are you sure you want to delete this?')) {
					var id = $(this).attr('id').replace('cfhr-vacation-remove-', '');
					$("#cfhr-vacation-item-"+id).remove();
				}
				e.preventDefault();
			});

			$(".cfhr-sick-remove").click(function(e) {
				if (confirm('Are you sure you want to delete this?')) {
					var id = $(this).attr('id').replace('cfhr-sick-remove-', '');
					$("#cfhr-sick-item-"+id).remove();
				}
				e.preventDefault();
			});
		});
	})(jQuery);
	<?php
	die();
}

function cfhr_css() {
	header('Content-type: text/css');
	?>
	/*
	.cfhr-level {
		margin-left:20px;
	}
	*/
	#cfhr-data {
		border:1px solid black;
		width:100%;
		height:400px;
		overflow:auto;
	}
	
	.cfhr-totals {
		float:left;
		font-weight:normal;
		margin-right:25px;
	}
	
	.cfhr-today {
		float:left;
	}
	.cfhr-dates {
		float:right;
		font-weight:normal;
		text-align:right;
	}
	.day-total-row {
		background-color:#CCCCCC;
		font-weight:bold;
	}
	
	.cfhr-head {
		margin:0 0 10px 0;
	}
	
	.cfhr-head-left {
		float:left;
		width:50%;
		text-align:left;
	}

	.cfhr-head-right {
		float:right;
		width:50%;
		text-align:right;
	}
	
	.cfhr-head-label {
		text-align:center;
		font-weight:bold;
	}
	
	.cfhr-label {
		float:left;
		text-align:left;
		width:195px;
	}
	.cfhr-value {
		float:left;
		text-align:right;
		width:50px;
	}
	.cfhr-value-bold {
		font-weight:bold;
	}
	.cfhr-value-total {
		border-bottom:1px solid #000000;
	}
	.cfhr-extended {
		margin-bottom:20px;
	}
	.cfhr-holiday {
		margin-top:20px;
	}
	<?php
	die();
}
// Add the CSS and JS files to the proper page in the admin
if (!empty($_GET['page']) && strpos($_GET['page'], 'cf-hours') !== false) {
	wp_enqueue_script('jquery');
	wp_enqueue_script('cfhr-js', admin_url('?cf_action=cfhr-js'), array('jquery'));
	wp_enqueue_style('cfhr-css', admin_url('?cf_action=cfhr-css'));
}

function cfhr_settings() {
	$cfhr = get_option('cfhr_options');
	
	$vacation = get_option('cfhr_vacation');
	$sick = get_option('cfhr_sick');
	
	if (!is_array($cfhr) || empty($cfhr)) {
		$_GET['cfhr'] = 'settings';
	}
	
	?>
	<div class="wrap">
		<?php echo screen_icon().'<h2>'.__('CF Hours').'</h2>'; ?>
		<div class="cfhr-nav">
			<ul class="subsubsub">
				<li><a href="<?php echo admin_url('tools.php?page=cf-hours'); ?>" id="cfhr-reporting"<?php echo (empty($_GET['cfhr']) ? 'class="current"' : ''); ?>><?php _e('Reporting'); ?></a> |</li>
				<li><a href="<?php echo admin_url('tools.php?page=cf-hours&cfhr=totals'); ?>" id="cfhr-totals-reporting"<?php echo ($_GET['cfhr'] == 'totals' ? 'class="current"' : ''); ?>><?php _e('Totals Reporting'); ?></a> |</li>
				<li><a href="<?php echo admin_url('tools.php?page=cf-hours&cfhr=scrape'); ?>" id="cfhr-scrape"<?php echo ($_GET['cfhr'] == 'scrape' ? 'class="current"' : ''); ?>><?php _e('Scrape'); ?></a> |</li>
				<li><a href="<?php echo admin_url('tools.php?page=cf-hours&cfhr=settings'); ?>" id="cfhr-settings"<?php echo ($_GET['cfhr'] == 'settings' ? 'class="current"' : ''); ?>><?php _e('Settings'); ?></a></li>
				<?php /*<li><a href="<?php echo admin_url('?page=cf-hours&cfhr=targeted'); ?>" id="cfhr-targeted-reporting"<?php echo ($_GET['cfhr'] == 'targeted' ? 'class="current"' : ''); ?>><?php _e('Targeted Reporting'); ?></a></li>*/ ?>
			</ul>
		</div>
		<div class="clear"></div>
		<?php
		switch ($_GET['cfhr']) {
			case 'totals':
				?>
				<div class="cfhr-totals-reporting">
					<?php
					echo '<h3>'.__('Totals Reporting').'</h3>';
					cfhr_hours_reporting();
					?>
				</div>
				<?php
				break;
			case 'scrape':
				?>
				<div class="cfhr-scrape">
					<?php echo '<h3>'.__('Files/Scraping').'</h3>'; ?>
					<input type="button" id="cfhr-scrape-data" class="button-primary" value="<?php _e('Scrape Files'); ?>" />
					<input type="button" id="cfhr-show-data" class="button" value="<?php _e('Show Files'); ?>" />
					<input type="button" id="cfhr-get-folders" class="button" value="<?php _e('Show Folders'); ?>" />
					<input type="button" id="cfhr-get-cache-folders" class="button" value="<?php _e('Show Cached Folders'); ?>" />
					<input type="button" id="cfhr-clear-data" class="button" value="<?php _e('Clear Files'); ?>" />
					<input type="text" id="cfhr-folder" value="/HoursReports/pay-periods/2011-paychecks/" />
					<div id="cfhr-data"></div>
				</div>
				<?php
				break;
			case 'settings':
				?>
				<div class="cfhr-settings">
					<?php 
					echo '<h3>'.__('Settings').'</h3>'; 
					cfhr_options();
					?>
				</div>
				<?php
				break;
			case 'targeted':
				?>
				<div class="cfhr-targeted-reporting">
					<?php 
					echo '<h3>'.__('Targeted Reporting').'</h3>'; 
					cfhr_targeted_reporting();
					?>
				</div>
				<?php
				break;
			default:
				?>
				<div class="cfhr-reporting">
					<?php 
					echo '<h3>'.__('Reporting').'</h3>'; 
					cfhr_reporting();
					?>
				</div>
				<?php
				break;
		}
		?>
	</div>
	<?php
}

function cfhr_load_api() {
	global $dropbox;
	if (is_a($dropbox, 'Dropbox_API')) { return; }
	// Get the saved Dropbox info
	$cfhr = get_option('cfhr_options');
	// Load the API files
	include CFHR_DIR.'Dropbox/autoload.php';
	// Get a session going to help with the load
	session_start();
	// Startup the Dropbox OAuth PHP API
	$oauth = new Dropbox_OAuth_PHP($cfhr['consumerkey'], $cfhr['consumersecret']);
	// Startup the Dropbox API with the OAuth info
	$dropbox = new Dropbox_API($oauth);
	// Get the Tokens from the API
	$tokens = $dropbox->getToken($cfhr['uname'], $cfhr['pword']); 
	// Set the Tokens for use with the API
	$oauth->setToken($tokens);
}

function cfhr_files($folder = '/') {
	print('<pre>');
	print_r(cfhr_get_files($folder));
	print('</pre>');
}

function cfhr_get_files($folder = '/') {
	global $dropbox;
	cfhr_load_api();
	return cfhr_get_folder_files($folder);
}

function cfhr_folders($path = '/', $use_cache = false) {
	echo cfhr_get_folders($path, $use_cache);
}

function cfhr_get_folders($path = '/', $use_cache = false) {
	global $dropbox;
	cfhr_load_api();
	
	$folders = array();
	$text = '';

	if ($use_cache) {
		$folders = get_option('cfhr_folders');
	}
	else {
		$meta = $dropbox->getMetaData($path);

		if (is_array($meta['contents']) && !empty($meta['contents'])) {
			foreach ($meta['contents'] as $key => $file) {
				if ($file['is_dir']) {
					$folders[] = $file['path'];
					$folders = cfhr_get_nested_folders($file['path'], $folders);
				}
			}
		}
	}
	
	if (is_array($folders) && !empty($folders)) {
		update_option('cfhr_folders', $folders);
		foreach ($folders as $folder) {
			$text .= 'Folder: <input type="text" value="'.$folder.'" size="100" /><br />';
		}
	}
	
	return $text;
}

function cfhr_get_nested_folders($path = '/', $folders = array(), $level = 0) {
	if ($level >= 10) { pp($folders,'dying folders'); die(); }
	global $dropbox;
	
	$meta = $dropbox->getMetaData($path);
	
	if (is_array($meta['contents']) && !empty($meta['contents'])) {
		foreach ($meta['contents'] as $key => $file) {
			if ($file['is_dir']) {
				$folders[] = $file['path'];
				$folders = cfhr_get_nested_folders($file['path'], $folders);
				$level++;
			}
		}
	}
	return $folders;
}

function cfhr_get_folder_files($path = '/', $count = 0, $recursive = false) {
	global $dropbox;
	// Get all of the files from the Dropbox
	$meta = $dropbox->getMetaData($path);
	$files = array();
	
	if (is_array($meta['contents']) && !empty($meta['contents'])) {
		foreach ($meta['contents'] as $key => $file) {
			if (is_array($file) && !empty($file)) {
				if (strpos($file['path'], 'phone') !== false) { continue; }
				if ($file['is_dir']) { continue; }
				$files[] = $file['path'];
				
				// $dashes = ' &mdash; ';
				// if ($count > 1) {
				// 	for ($i = 1; $i <= $count; $i++) {
				// 		$dashes .= ' &mdash; ';
				// 	}
				// }
				// echo '<div class="cfhr-level">'.$dashes.' File: '.str_replace($path, '', $file['path']);
				// // pp($file, 'file');
				// if ($file['is_dir']) {
				// 	$count++;
				// 	cfhr_get_folder_files($file['path'], $count);
				// }
				// echo '</div>';
				// echo "\tFile: ".$file['path'].'<br />';
				// pp($file, 'file');
				// echo 'File: '.$file['path'].'<br />';

				// if ($file['mime_type'] == 'text/csv') {
				// 	$content = $dropbox->getFile($file['path']);
				// 	$csv = explode("\n", $content);
				// 	if (is_array($csv) && !empty($csv)) {
				// 		foreach ($csv as $csv_key => $line) {
				// 			if (!empty($line)) {
				// 				$csv[$csv_key] = explode(',', $line);
				// 			}
				// 			else {
				// 				unset($csv[$csv_key]);
				// 			}
				// 		}
				// 	}
				// 	print('csv<pre>');
				// 	print_r($csv);
				// 	print('</pre>');
				// 	die();
				// }
			}
		}
	}
	return $files;
}

function cfhr_get_file_contents($file = '') {
	if (empty($file)) { return ''; }
	global $dropbox;
	
	return $dropbox->getFile($file);
}

function cfhr_options() {
	$cfhr = get_option('cfhr_options');
	
	$vacation = get_option('cfhr_vacation');
	$sick = get_option('cfhr_sick');
	
	?>
	<form action="<?php echo admin_url(); ?>" method="post" id="cfhr-form">
		<table class="widefat">
			<thead>
				<tr>
					<th scope="col" style="width:25%;"><?php _e('Option Name'); ?></th>
					<th scope="col"><?php _e('Option Value'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<td colspan="2">
						<input type="submit" class="button-primary" value="<?php _e('Submit'); ?>" />
						<input type="hidden" name="cf_action" value="cfhr_submit" />
					</td>
				</tr>
			</tfoot>
			<tbody>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('Consumer Key'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[consumerkey]" class="input widefat" value="<?php echo esc_attr($cfhr['consumerkey']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('Consumer Secret'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[consumersecret]" class="input widefat" value="<?php echo esc_attr($cfhr['consumersecret']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('Dropbox Username'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[uname]" class="input widefat" value="<?php echo esc_attr($cfhr['uname']); ?>" autocomplete="off" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('Dropbox Password (optional)'); ?>
						<br />
						<small><?php _e('After this is entered once, there is no need to enter again.')?></small>
					</td>
					<td>
						<input type="password" name="cfhr[pword]" class="input widefat" value="" autocomplete="off" />
					</td>
				</tr>
				<tr>
					<td colspan="2" style="text-align:left">
						<b><?php _e('PAYCHECK START/END DAYS')?></b>
						<br />
						<b><?php _e('All values should be formatted YYYY-MM-DD'); ?></b>
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('Current Paycheck End'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[paycheck_end]" class="input widefat" value="<?php echo esc_attr($cfhr['paycheck_end']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('Current Paycheck Start'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[paycheck_start]" class="input widefat" value="<?php echo esc_attr($cfhr['paycheck_start']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('Last Paycheck End'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[last_paycheck_end]" class="input widefat" value="<?php echo esc_attr($cfhr['last_paycheck_end']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('Last Paycheck Start'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[last_paycheck_start]" class="input widefat" value="<?php echo esc_attr($cfhr['last_paycheck_start']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('2nd Paycheck End'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[second_paycheck_end]" class="input widefat" value="<?php echo esc_attr($cfhr['second_paycheck_end']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('2nd Paycheck Start'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[second_paycheck_start]" class="input widefat" value="<?php echo esc_attr($cfhr['second_paycheck_start']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('3rd Paycheck End'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[third_paycheck_end]" class="input widefat" value="<?php echo esc_attr($cfhr['third_paycheck_end']); ?>" />
					</td>
				</tr>
				<tr>
					<td style="vertical-align: middle;">
						<?php _e('3rd Paycheck Start'); ?>
					</td>
					<td>
						<input type="text" name="cfhr[third_paycheck_start]" class="input widefat" value="<?php echo esc_attr($cfhr['third_paycheck_start']); ?>" />
					</td>
				</tr>
			</tbody>
		</table>
	</form>
	<div class="cfhr-vacation-input" style="float:left;width:50%;">
		<?php echo '<h3>'.__('Vacation').'</h3>'; ?>
		<form action="<?php echo admin_url(); ?>" method="post" id="cfhr-vacation">
			<table class="widefat" style="width:400px;">
				<thead>
					<tr>
						<th style="width:300px;"><?php _e('Date (YYYY-MM-DD)'); ?></th>
						<th style="text-align:center;"><?php _e('Delete'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th><?php _e('Date'); ?></th>
						<th style="text-align:center;"><?php _e('Delete'); ?></th>
					</tr>
					<tr>
						<td colspan="2">
							<input type="submit" class="button-primary" value="<?php _e('Submit'); ?>" />
							<button class="button" id="cfhr-vacation-new"><?php _e('Add New Date'); ?></button>
							<input type="hidden" name="cf_action" value="cfhr_vacation" />
						</td>
					</tr>
				</tfoot>
				<tbody>
					<?php
					if (is_array($vacation) && !empty($vacation)) {
						foreach ($vacation as $key => $day) {
							?>
							<tr id="cfhr-vacation-item-<?php echo $key; ?>">
								<td><input type="text" name="cfhr_vacation[<?php echo $key; ?>][date]" value="<?php echo $day; ?>" class="widefat" /></td>
								<td style="text-align:center;"><input type="button" id="cfhr-vacation-remove-<?php echo $key; ?>" class="cfhr-vacation-remove button" value="<?php _e('Delete'); ?>" /></td>
							</tr>
							<?php
						}
					}
					?>
				</tbody>
			</table>
		</form>
		<div id="cfhr-vacation-new" style="display:none;">
			<table>
				<tbody>
					<tr id="cfhr-vacation-item-###ITEM###">
						<td><input type="text" name="cfhr_vacation[###ITEM###][date]" value="" class="widefat" /></td>
						<td style="text-align:center;"><input type="button" id="cfhr-vacation-remove-###ITEM###" class="cfhr-vacation-remove button" value="<?php _e('Delete'); ?>" /></td>
					</tr>
				</tbody>
			</table>
		</div>						
	</div>
	<div class="cfhr-sick-input" style="float:left;width:50%;">
		<?php echo '<h3>'.__('Sick').'</h3>'; ?>
		<form action="<?php echo admin_url(); ?>" method="post" id="cfhr-sick">
			<table class="widefat" style="width:400px;">
				<thead>
					<tr>
						<th style="width:300px;"><?php _e('Date (YYYY-MM-DD)'); ?></th>
						<th style="text-align:center;"><?php _e('Delete'); ?></th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th><?php _e('Date'); ?></th>
						<th style="text-align:center;"><?php _e('Delete'); ?></th>
					</tr>
					<tr>
						<td colspan="2">
							<input type="submit" class="button-primary" value="<?php _e('Submit'); ?>" />
							<button class="button" id="cfhr-sick-new"><?php _e('Add New Date'); ?></button>
							<input type="hidden" name="cf_action" value="cfhr_sick" />
						</td>
					</tr>
				</tfoot>
				<tbody>
					<?php
					if (is_array($sick) && !empty($sick)) {
						foreach ($sick as $key => $day) {
							?>
							<tr id="cfhr-sick-item-<?php echo $key; ?>">
								<td><input type="text" name="cfhr_sick[<?php echo $key; ?>][date]" value="<?php echo $day; ?>" class="widefat" /></td>
								<td style="text-align:center;"><input type="button" id="cfhr-sick-remove-<?php echo $key; ?>" class="cfhr-sick-remove button" value="<?php _e('Delete'); ?>" /></td>
							</tr>
							<?php
						}
					}
					?>
				</tbody>
			</table>
		</form>
		<div id="cfhr-sick-new" style="display:none;">
			<table>
				<tbody>
					<tr id="cfhr-sick-item-###ITEM###">
						<td><input type="text" name="cfhr_sick[###ITEM###][date]" value="" class="widefat" /></td>
						<td style="text-align:center;"><input type="button" id="cfhr-sick-remove-###ITEM###" class="cfhr-sick-remove button" value="<?php _e('Delete'); ?>" /></td>
					</tr>
				</tbody>
			</table>
		</div>						
	</div>
	<div class="clear"></div>
	<?php
}

function cfhr_scrape_files($folder = '/') {
	cfhr_load_api();
	$content = '';

	if (strpos($folder, '.csv') !== false) {
		$content = cfhr_get_file_contents($folder);
	}
	else {
		$files = cfhr_get_files($folder);
		if (is_array($files) && !empty($files)) {
			foreach ($files as $file) {
				$content .= cfhr_get_file_contents($file);
			}
		}
	}

	$csv = explode("\n", $content);
	$data = array();
	if (is_array($csv) && !empty($csv)) {
		foreach ($csv as $csv_key => $line) {
			if (substr($line, 0, 6) == '"Date"' || empty($line)) { continue; }
			$data[] = explode('","', $line);
		}
	}
	
	$dates = get_option('cfhr_processed_dates');
	if (is_array($data) && !empty($data)) {
		global $wpdb;
		foreach ($data as $item) {
			if (is_array($item) && !empty($item)) {
				// 0 - Date, 1 - Name, 2 - Client, 3 - Project, 4 - Category, 5 - Description, 6 - Billed, 7 - Total
				
				$date			= str_replace(array('"', "'"),'',$item[0]);
				$name			= str_replace(array('"', "'"),'',$item[1]);
				$client			= str_replace(array('"', "'"),'',$item[2]);
				$project		= str_replace(array('"', "'"),'',$item[3]);
				$category		= str_replace(array('"', "'"),'',$item[4]);
				$description	= str_replace(array('"', "'"),'',$item[5]);
				$billed			= str_replace(array('"', "'"),'',$item[6]);
				$total			= str_replace(array('"', "'"),'',$item[7]);
				
				$wpdb->query("INSERT INTO cfhr_data (item_date, client, project, category, description, billed, total) VALUES ('".$date."','".$client."','".$project."','".$category."','".$description."','".$billed."','".$total."')");
				echo 'Inserted<br />';
			}
		}
	}
}

function cfhr_reporting() {
	global $wpdb, $paycheckstart, $paycheckend, $lastpaycheckstart, $lastpaycheckend, $off_holidays, $vacation, $sick, $holidays;
	
	$today = date('Y-m-d');
	$daynum = date('d');
	$weekstart = date('Y-m-d', strtotime('Last Monday'));
	$weekend = date('Y-m-d', strtotime('This Sunday'));

	$lastweekstart = date('Y-m-d', strtotime('Last Monday -1 week'));
	$lastweekend = date('Y-m-d', strtotime('This Sunday -1 week'));

	$paycheckstart_clean = date('F j, Y', strtotime($paycheckstart));
	$paycheckend_clean = date('F j, Y', strtotime($paycheckend));

	$startmonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y')));
	$endmonth = date('Y-m-d', mktime(0, 0, 0, date('m')+1, 0, date('Y')));
	$laststartmonth = date('Y-m-d', mktime(0, 0, 0, date('m')-1, 1, date('Y')));
	$lastendmonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 0, date('Y')));

	$startyear = date('Y-m-d', mktime(0, 0, 0, 1, 1, date('Y')));
	$endyear = date('Y-m-d', mktime(0, 0, 0, 1, 0, date('Y')+1));
	
	$lastdayentered_query = $wpdb->get_results("SELECT item_date FROM cfhr_data ORDER BY item_date DESC LIMIT 1");
	$lastdayentered = $lastdayentered_query[0]->item_date;

	$paycheck_working_days = getWorkingDays($paycheckstart, $paycheckend, $holidays);
	$paycheck_working_days_so_far = getWorkingDays($paycheckstart, $lastdayentered, $holidays);
	$paycheckhours = $wpdb->get_results("SELECT * FROM cfhr_data WHERE item_date >= '$paycheckstart' AND item_date <= '$paycheckend' ORDER BY item_date DESC");
	$paycheck_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$paycheckstart' AND item_date <= '$paycheckend' ORDER BY item_date DESC");
	$paycheck_working_totals = $paycheck_working_days*8;
	$paycheck_actual_working_days = count($paycheck_actual_working_days);

	$lastpaycheck_working_days = getWorkingDays($lastpaycheckstart, $lastpaycheckend, $holidays);
	$lastpaycheck_totals = $wpdb->get_results("SELECT SUM(total) as paychecktotal, SUM(billed) as paycheckbilled FROM cfhr_data WHERE item_date >= '$lastpaycheckstart' AND item_date <= '$lastpaycheckend'");
	$lastpaycheck_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$lastpaycheckstart' AND item_date <= '$lastpaycheckend' ORDER BY item_date DESC");
	$lastpaycheck_working_totals = $lastpaycheck_working_days*8;
	$lastpaycheck_actual_working_days = count($lastpaycheck_actual_working_days);

	$week_working_days = getWorkingDays($weekstart, $weekend, $holidays);
	$week_working_days_so_far = getWorkingDays($weekstart, $lastdayentered, $holidays);
	$week_totals = $wpdb->get_results("SELECT SUM(total) as weektotal, SUM(billed) as weekbilled FROM cfhr_data WHERE item_date >= '$weekstart' AND item_date <= '$weekend'");
	$week_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$weekstart' AND item_date <= '$weekend' ORDER BY item_date DESC");
	$week_working_totals = $week_working_days*8;
	$week_actual_working_days = count($week_actual_working_days);

	$lastweek_working_days = getWorkingDays($lastweekstart, $lastweekend, $holidays);
	$lastweek_totals = $wpdb->get_results("SELECT SUM(total) as weektotal, SUM(billed) as weekbilled FROM cfhr_data WHERE item_date >= '$lastweekstart' AND item_date <= '$lastweekend'");
	$lastweek_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$lastweekstart' AND item_date <= '$lastweekend' ORDER BY item_date DESC");
	$lastweek_working_totals = $lastweek_working_days*8;
	$lastweek_actual_working_days = count($lastweek_actual_working_days);

	$month_working_days = getWorkingDays($startmonth, $endmonth, $holidays);
	$month_working_days_so_far = getWorkingDays($startmonth, $lastdayentered, $holidays);
	$month_totals = $wpdb->get_results("SELECT SUM(total) as monthtotal, SUM(billed) as monthbilled FROM cfhr_data WHERE item_date >= '$startmonth' AND item_date <= '$endmonth'");
	$month_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$startmonth' AND item_date <= '$endmonth' ORDER BY item_date DESC");
	$month_working_totals = $month_working_days*8;
	$month_actual_working_days = count($month_actual_working_days);

	$lastmonth_working_days = getWorkingDays($laststartmonth, $lastendmonth, $holidays);
	$lastmonth_totals = $wpdb->get_results("SELECT SUM(total) as monthtotal, SUM(billed) as monthbilled FROM cfhr_data WHERE item_date >= '$laststartmonth' AND item_date <= '$lastendmonth'");
	$lastmonth_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$laststartmonth' AND item_date <= '$lastendmonth' ORDER BY item_date DESC");
	$lastmonth_working_totals = $lastmonth_working_days*8;
	$lastmonth_actual_working_days = count($lastmonth_actual_working_days);

	$year_working_days = getWorkingDays($startyear, $endyear, $holidays);
	$year_working_days_so_far = getWorkingDays($startyear, $lastdayentered, $holidays);
	$year_totals = $wpdb->get_results("SELECT SUM(total) as yeartotal, SUM(billed) as yearbilled FROM cfhr_data WHERE item_date >= '$startyear' AND item_date <= '$endyear'");
	$year_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$startyear' AND item_date <= '$endyear' ORDER BY item_date DESC");
	$year_working_totals = $year_working_days*8;
	$year_actual_working_days = count($year_actual_working_days);
	
	$totalpaycheck = 0;
	$totallastpaycheck = 0;
	$totalweek = 0;
	$totalmonth = 0;
	$totalyear = 0;
	$numitems = 0;
	$daterow = 0;
	$checkrow = 0;
	$dayhours = 0;
	$content = '';
	
	foreach ($paycheckhours as $item) {
		$daterow = $item->item_date;
		if ($checkrow == 0) {
			$checkrow = $item->item_date;
		}
		
		if ($daterow != $checkrow) {
			$content .= '
			<tr class="day-total-row">
				<td colspan="4">'.$checkrow.' -- END OF DAY</td>
				<td>'.$daybilledhours.'</td>
				<td>'.$dayhours.'</td>
			</tr>
			';
			$checkrow = $item->item_date;
			$dayhours = 0;
			$daybilledhours = 0;
		}

		$content .= '
		<tr>
			<td>'.$item->item_date.'</td>
			<td>'.$item->client.'</td>
			<td>'.$item->project.'</td>
			<td>'.$item->description.'</td>
			<td>'.$item->billed.'</td>
			<td>'.$item->total.'</td>
		</tr>
		';
		$totalpaycheck += floatval($item->total); 
		$daybilledhours += floatval($item->billed);
		$dayhours += floatval($item->total);
		$numitems++;
		
		if (count($paycheckhours) == $numitems) {
			$content .= '
			<tr class="day-total-row">
				<td colspan="4">'.$daterow.' -- END OF DAY</td>
				<td>'.$daybilledhours.'</td>
				<td>'.$dayhours.'</td>
			</tr>
			';
		}
	}
	foreach ($lastpaycheck_totals as $item) {
		$totallastpaycheck = $item->paychecktotal;
	}
	foreach ($week_totals as $week) {
		$totalweek = $week->weektotal;
	}
	foreach ($lastweek_totals as $week) {
		$totallastweek = $week->weektotal;
	}
	foreach ($month_totals as $month) {
		$totalmonth = $month->monthtotal;
	}
	foreach ($lastmonth_totals as $month) {
		$totallastmonth = $month->monthtotal;
	}
	foreach ($year_totals as $year) {
		$totalyear = $year->yeartotal;
	}
	
	$vacation_days = '';
	$count = 0;
	if (is_array($vacation) && !empty($vacation)) {
		foreach ($vacation as $date) {
			$date_parts = explode('-', $date);
			$vacation_days .= date('F j', mktime(0, 0, 0, $date_parts[1], $date_parts[2], $date_parts[0]));
			$count++;
			if ($count < count($vacation)) {
				$vacation_days .= ', ';
			}
		}
	}
	$sick_days = '';
	$count = 0;
	if (is_array($sick) && !empty($sick)) {
		foreach ($sick as $date) {
			$date_parts = explode('-', $date);
			$sick_days .= date('F j', mktime(0, 0, 0, $date_parts[1], $date_parts[2], $date_parts[0]));
			$count++;
			if ($count < count($sick)) {
				$sick_days .= ', ';
			}
		}
	}
	$holiday_days = '';
	$count = 0;
	if (is_array($off_holidays) && !empty($off_holidays)) {
		foreach ($off_holidays as $date) {
			$date_parts = explode('-', $date);
			$holiday_days .= date('F j', mktime(0, 0, 0, $date_parts[1], $date_parts[2], $date_parts[0]));
			$count++;
			if ($count < count($off_holidays)) {
				$holiday_days .= ', ';
			}
		}
	}

	?>
	<table class="widefat">
		<thead>
			<tr>
				<th colspan="7">
					<div class="cfhr-head">
						<div class="cfhr-head-left">
							Today: <?php echo $today; ?>
							<br />
							Paycheck Total Hours: <?php echo number_format($totalpaycheck, 2); ?>
						</div>
						<div class="cfhr-head-right">
							Dates: <?php echo $paycheckstart_clean.' - '.$paycheckend_clean; ?>
							<br />
							Items: <?php echo $numitems; ?>
						</div>
						<div class="clear"></div>
					</div>
					<div class="clear"></div>
					<div class="cfhr-totals">
						<div class="cfhr-label">Paycheck Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalpaycheck, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Paycheck Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($paycheck_working_days_so_far*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Paycheck Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalpaycheck-($paycheck_working_days_so_far*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Paycheck Hours Total:</div><div class="cfhr-value"><?php echo $paycheck_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Paycheck Days Worked:</div><div class="cfhr-value"><?php echo $paycheck_working_days_so_far; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Paycheck Days Expected:</div><div class="cfhr-value"><?php echo $paycheck_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-label">Week Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalweek, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Week Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($week_working_days_so_far*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Week Difference Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalweek-($week_working_days_so_far*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Week Hours Total:</div><div class="cfhr-value"><?php echo $week_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Week Days Worked:</div><div class="cfhr-value"><?php echo $week_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Week Days Expected:</div><div class="cfhr-value"><?php echo $week_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-label">Month Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalmonth, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Month Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($month_working_days_so_far*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Month Difference Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalmonth-($month_working_days_so_far*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Month Hours Total:</div><div class="cfhr-value"><?php echo $month_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Month Days Worked:</div><div class="cfhr-value"><?php echo $month_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Month Days Expected:</div><div class="cfhr-value"><?php echo $month_working_days; ?></div>
						<div class="clear cfhr-extended"></div>
					</div>
					<div class="clear cfhr-extended"></div>
					<div class="cfhr-totals">
						<div class="cfhr-label">Last Paycheck Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastpaycheck, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Last Paycheck Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($lastpaycheck_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Paycheck Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastpaycheck-($lastpaycheck_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Last Paycheck Days Worked:</div><div class="cfhr-value"><?php echo $lastpaycheck_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Paycheck Days Expected:</div><div class="cfhr-value"><?php echo $lastpaycheck_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-label">Last Week Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastweek, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Last Week Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($lastweek_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Week Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastweek-($lastweek_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Last Week Days Worked:</div><div class="cfhr-value"><?php echo $lastweek_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Week Days Expected:</div><div class="cfhr-value"><?php echo $lastweek_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-label">Last Month Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastmonth, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Last Month Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($lastmonth_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Month Difference Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastmonth-($lastmonth_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Last Month Hours Total:</div><div class="cfhr-value"><?php echo $lastmonth_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Month Days Worked:</div><div class="cfhr-value"><?php echo $lastmonth_actual_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="clear"></div>
					<div class="cfhr-holiday">
						<div class="cfhr-label">Holiday Days:</div><?php echo $holiday_days; ?>
					</div>
					<div class="clear"></div>
					<div class="cfhr-vacation">
						<div class="cfhr-label">Vacation Days:</div><?php echo $vacation_days; ?>
					</div>
					<div class="clear"></div>
					<div class="cfhr-sick">
						<div class="cfhr-label">Sick Days:</div><?php echo $sick_days; ?>
					</div>
					<div class="clear"></div>
				</th>
			</tr>
			<tr>
				<th>Date</th>
				<th style="width:125px;">Client</th>
				<th>Project</th>
				<th>Description</th>
				<th>Billed</th>
				<th>Total</th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th>Date</th>
				<th>Client</th>
				<th>Project</th>
				<th>Description</th>
				<th>Billed</th>
				<th>Total</th>
			</tr>
		</tfoot>
		<tbody>
			<?php echo $content; ?>
		</tbody>
	</table>
	<?php
}

function cfhr_targeted_reporting() {
	?>
	<div id="cfhr-targeted-reporting-selection">
		<?php echo cfhr_get_dropdown_clients(); ?>
	</div>
	<?php
}

function cfhr_hours_reporting() {
	date_default_timezone_set('America/Denver');
	global $wpdb, $paycheckstart, $paycheckend, $lastpaycheckstart, $lastpaycheckend, $off_holidays, $vacation, $sick, $holidays;
	global $secondpaycheckstart, $secondpaycheckend, $thirdpaycheckstart, $thirdpaycheckend, $fourthpaycheckstart, $fourthpaycheckend;
	$today = date('Y-m-d');
	$daynum = date('d');

	// Get the Dates of the last few weeks
	$weekstart = date('Y-m-d', strtotime('Last Monday'));
	$weekend = date('Y-m-d', strtotime('This Sunday'));
	$lastweekstart = date('Y-m-d', strtotime('Last Monday -1 week'));
	$lastweekend = date('Y-m-d', strtotime('This Sunday -1 week'));
	$secondweekstart = date('Y-m-d', strtotime('Last Monday -2 week'));
	$secondweekend = date('Y-m-d', strtotime('This Sunday -2 week'));
	$thirdweekstart = date('Y-m-d', strtotime('Last Monday -3 week'));
	$thirdweekend = date('Y-m-d', strtotime('This Sunday -3 week'));
	$fourthweekstart = date('Y-m-d', strtotime('Last Monday -4 week'));
	$fourthweekend = date('Y-m-d', strtotime('This Sunday -4 week'));
	
	// Get the Dates of the last few months
	$startmonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y')));
	$endmonth = date('Y-m-d', mktime(0, 0, 0, date('m')+1, 0, date('Y')));
	$laststartmonth = date('Y-m-d', mktime(0, 0, 0, date('m')-1, 1, date('Y')));
	$lastendmonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 0, date('Y')));
	$secondstartmonth = date('Y-m-d', mktime(0, 0, 0, date('m')-2, 1, date('Y')));
	$secondendmonth = date('Y-m-d', mktime(0, 0, 0, date('m')-1, 0, date('Y')));
	$thirdstartmonth = date('Y-m-d', mktime(0, 0, 0, date('m')-3, 1, date('Y')));
	$thirdendmonth = date('Y-m-d', mktime(0, 0, 0, date('m')-2, 0, date('Y')));

	// Get the Dates of the last few years
	$startyear = date('Y-m-d', mktime(0, 0, 0, 1, 1, date('Y')));
	$endyear = date('Y-m-d', mktime(0, 0, 0, 1, 0, date('Y')+1));
	$laststartyear = date('Y-m-d', mktime(0, 0, 0, 1, 1, date('Y')-1));
	$lastendyear = date('Y-m-d', mktime(0, 0, 0, 1, 0, date('Y')));
	
	$lastdayentered_query = $wpdb->get_results("SELECT item_date FROM cfhr_data ORDER BY item_date DESC LIMIT 1");
	$lastdayentered = $lastdayentered_query[0]->item_date;
	
	
	// BEGIN PAYCHECK DATA
	$paycheck_working_days = getWorkingDays($paycheckstart, $paycheckend, $holidays);
	$paycheck_working_days_so_far = getWorkingDays($paycheckstart, $lastdayentered, $holidays);
	$paycheckhours = $wpdb->get_results("SELECT * FROM cfhr_data WHERE item_date >= '$paycheckstart' AND item_date <= '$paycheckend' ORDER BY item_date DESC");
	$paycheck_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$paycheckstart' AND item_date <= '$paycheckend' ORDER BY item_date DESC");
	$paycheck_working_totals = $paycheck_working_days*8;
	$paycheck_actual_working_days = count($paycheck_actual_working_days);

	$lastpaycheck_working_days = getWorkingDays($lastpaycheckstart, $lastpaycheckend, $holidays);
	$lastpaycheck_totals = $wpdb->get_results("SELECT SUM(total) as paychecktotal, SUM(billed) as paycheckbilled FROM cfhr_data WHERE item_date >= '$lastpaycheckstart' AND item_date <= '$lastpaycheckend'");
	$lastpaycheck_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$lastpaycheckstart' AND item_date <= '$lastpaycheckend' ORDER BY item_date DESC");
	$lastpaycheck_working_totals = $lastpaycheck_working_days*8;
	$lastpaycheck_actual_working_days = count($lastpaycheck_actual_working_days);
	
	$secondpaycheck_working_days = getWorkingDays($secondpaycheckstart, $secondpaycheckend, $holidays);
	$secondpaycheck_totals = $wpdb->get_results("SELECT SUM(total) as paychecktotal, SUM(billed) as paycheckbilled FROM cfhr_data WHERE item_date >= '$secondpaycheckstart' AND item_date <= '$secondpaycheckend' ORDER BY item_date DESC");
	$secondpaycheck_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$secondpaycheckstart' AND item_date <= '$secondpaycheckend' ORDER BY item_date DESC");
	$secondpaycheck_working_totals = $secondpaycheck_working_days*8;
	$secondpaycheck_actual_working_days = count($secondpaycheck_actual_working_days);

	$thirdpaycheck_working_days = getWorkingDays($thirdpaycheckstart, $thirdpaycheckend, $holidays);
	$thirdpaycheck_totals = $wpdb->get_results("SELECT SUM(total) as paychecktotal, SUM(billed) as paycheckbilled FROM cfhr_data WHERE item_date >= '$thirdpaycheckstart' AND item_date <= '$thirdpaycheckend' ORDER BY item_date DESC");
	$thirdpaycheck_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$thirdpaycheckstart' AND item_date <= '$thirdpaycheckend' ORDER BY item_date DESC");
	$thirdpaycheck_working_totals = $thirdpaycheck_working_days*8;
	$thirdpaycheck_actual_working_days = count($thirdpaycheck_actual_working_days);
	
	$fourthpaycheck_working_days = getWorkingDays($fourthpaycheckstart, $fourthpaycheckend, $holidays);
	$fourthpaycheck_totals = $wpdb->get_results("SELECT SUM(total) as paychecktotal, SUM(billed) as paycheckbilled FROM cfhr_data WHERE item_date >= '$fourthpaycheckstart' AND item_date <= '$fourthpaycheckend' ORDER BY item_date DESC");
	$fourthpaycheck_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$fourthpaycheckstart' AND item_date <= '$fourthpaycheckend' ORDER BY item_date DESC");
	$fourthpaycheck_working_totals = $fourthpaycheck_working_days*8;
	$fourthpaycheck_actual_working_days = count($fourthpaycheck_actual_working_days);

	$totalpaycheck = 0;
	$totallastpaycheck = 0;
	$totalsecondpaycheck = 0;
	$totalthirdpaycheck = 0;
	$totalfouthpaycheck = 0;
	
	foreach ($paycheckhours as $item) {
		$totalpaycheck += floatval($item->total); 
		$daybilledhours += floatval($item->billed);
		$dayhours += floatval($item->total);
		$numitems++;
	}
	foreach ($lastpaycheck_totals as $item) {
		$totallastpaycheck = $item->paychecktotal;
	}
	foreach ($secondpaycheck_totals as $item) {
		$totalsecondpaycheck = $item->paychecktotal;
	}
	foreach ($thirdpaycheck_totals as $item) {
		$totalthirdpaycheck = $item->paychecktotal;
	}
	foreach ($fourthpaycheck_totals as $item) {
		$totalfourthpaycheck = $item->paychecktotal;
	}

	// END PAYCHECK DATA
	// BEGIN WEEK DATA
	
	$week_working_days = getWorkingDays($weekstart, $weekend, $holidays);
	$week_working_days_so_far = getWorkingDays($weekstart, $lastdayentered, $holidays);
	$week_totals = $wpdb->get_results("SELECT SUM(total) as weektotal, SUM(billed) as weekbilled FROM cfhr_data WHERE item_date >= '$weekstart' AND item_date <= '$weekend'");
	$week_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$weekstart' AND item_date <= '$weekend' ORDER BY item_date DESC");
	$week_working_totals = $week_working_days*8;
	$week_actual_working_days = count($week_actual_working_days);

	$lastweek_working_days = getWorkingDays($lastweekstart, $lastweekend, $holidays);
	$lastweek_totals = $wpdb->get_results("SELECT SUM(total) as weektotal, SUM(billed) as weekbilled FROM cfhr_data WHERE item_date >= '$lastweekstart' AND item_date <= '$lastweekend'");
	$lastweek_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$lastweekstart' AND item_date <= '$lastweekend' ORDER BY item_date DESC");
	$lastweek_working_totals = $lastweek_working_days*8;
	$lastweek_actual_working_days = count($lastweek_actual_working_days);

	$secondweek_working_days = getWorkingDays($secondweekstart, $secondweekend, $holidays);
	$secondweek_totals = $wpdb->get_results("SELECT SUM(total) as weektotal, SUM(billed) as weekbilled FROM cfhr_data WHERE item_date >= '$secondweekstart' AND item_date <= '$secondweekend'");
	$secondweek_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$secondweekstart' AND item_date <= '$secondweekend' ORDER BY item_date DESC");
	$secondweek_working_totals = $secondweek_working_days*8;
	$secondweek_actual_working_days = count($secondweek_actual_working_days);

	$thirdweek_working_days = getWorkingDays($thirdweekstart, $thirdweekend, $holidays);
	$thirdweek_totals = $wpdb->get_results("SELECT SUM(total) as weektotal, SUM(billed) as weekbilled FROM cfhr_data WHERE item_date >= '$thirdweekstart' AND item_date <= '$thirdweekend'");
	$thirdweek_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$thirdweekstart' AND item_date <= '$thirdweekend' ORDER BY item_date DESC");
	$thirdweek_working_totals = $thirdweek_working_days*8;
	$thirdweek_actual_working_days = count($thirdweek_actual_working_days);

	$fourthweek_working_days = getWorkingDays($fourthweekstart, $fourthweekend, $holidays);
	$fourthweek_totals = $wpdb->get_results("SELECT SUM(total) as weektotal, SUM(billed) as weekbilled FROM cfhr_data WHERE item_date >= '$fourthweekstart' AND item_date <= '$fourthweekend'");
	$fourthweek_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$fourthweekstart' AND item_date <= '$fourthweekend' ORDER BY item_date DESC");
	$fourthweek_working_totals = $fourthweek_working_days*8;
	$fourthweek_actual_working_days = count($fourthweek_actual_working_days);

	$totalweek = 0;
	$totallastweek = 0;
	$totalsecondweek = 0;
	$totalthirdweek = 0;
	$totalfourthweek = 0;

	foreach ($week_totals as $week) {
		$totalweek = $week->weektotal;
	}
	foreach ($lastweek_totals as $week) {
		$totallastweek = $week->weektotal;
	}
	foreach ($secondweek_totals as $week) {
		$totalsecondweek = $week->weektotal;
	}
	foreach ($thirdweek_totals as $week) {
		$totalthirdweek = $week->weektotal;
	}
	foreach ($fourthweek_totals as $week) {
		$totalfourthweek = $week->weektotal;
	}

	// END WEEK DATA
	// BEGIN MONTH DATA

	$month_working_days = getWorkingDays($startmonth, $endmonth, $holidays);
	$month_working_days_so_far = getWorkingDays($startmonth, $lastdayentered, $holidays);
	$month_totals = $wpdb->get_results("SELECT SUM(total) as monthtotal, SUM(billed) as monthbilled FROM cfhr_data WHERE item_date >= '$startmonth' AND item_date <= '$endmonth'");
	$month_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$startmonth' AND item_date <= '$endmonth' ORDER BY item_date DESC");
	$month_working_totals = $month_working_days*8;
	$month_actual_working_days = count($month_actual_working_days);

	$lastmonth_working_days = getWorkingDays($laststartmonth, $lastendmonth, $holidays);
	$lastmonth_totals = $wpdb->get_results("SELECT SUM(total) as monthtotal, SUM(billed) as monthbilled FROM cfhr_data WHERE item_date >= '$laststartmonth' AND item_date <= '$lastendmonth'");
	$lastmonth_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$laststartmonth' AND item_date <= '$lastendmonth' ORDER BY item_date DESC");
	$lastmonth_working_totals = $lastmonth_working_days*8;
	$lastmonth_actual_working_days = count($lastmonth_actual_working_days);

	$secondmonth_working_days = getWorkingDays($secondstartmonth, $secondendmonth, $holidays);
	$secondmonth_totals = $wpdb->get_results("SELECT SUM(total) as monthtotal, SUM(billed) as monthbilled FROM cfhr_data WHERE item_date >= '$secondstartmonth' AND item_date <= '$secondendmonth'");
	$secondmonth_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$secondstartmonth' AND item_date <= '$secondendmonth' ORDER BY item_date DESC");
	$secondmonth_working_totals = $secondmonth_working_days*8;
	$secondmonth_actual_working_days = count($secondmonth_actual_working_days);

	$thirdmonth_working_days = getWorkingDays($thirdstartmonth, $thirdendmonth, $holidays);
	$thirdmonth_totals = $wpdb->get_results("SELECT SUM(total) as monthtotal, SUM(billed) as monthbilled FROM cfhr_data WHERE item_date >= '$thirdstartmonth' AND item_date <= '$thirdendmonth'");
	$thirdmonth_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$thirdstartmonth' AND item_date <= '$thirdendmonth' ORDER BY item_date DESC");
	$thirdmonth_working_totals = $thirdmonth_working_days*8;
	$thirdmonth_actual_working_days = count($thirdmonth_actual_working_days);

	$fourthmonth_working_days = getWorkingDays($fourthstartmonth, $fourthendmonth, $holidays);
	$fourthmonth_totals = $wpdb->get_results("SELECT SUM(total) as monthtotal, SUM(billed) as monthbilled FROM cfhr_data WHERE item_date >= '$fourthstartmonth' AND item_date <= '$fourthendmonth'");
	$fourthmonth_actual_working_days = $wpdb->get_results("SELECT DISTINCT(item_date) FROM cfhr_data WHERE item_date >= '$fourthstartmonth' AND item_date <= '$fourthendmonth' ORDER BY item_date DESC");
	$fourthmonth_working_totals = $fourthmonth_working_days*8;
	$fourthmonth_actual_working_days = count($fourthmonth_actual_working_days);

	$totalmonth = 0;
	$totallastmonth = 0;
	$totalsecondmonth = 0;
	$totalthirdmonth = 0;
	$totalfourthmonth = 0;

	foreach ($month_totals as $month) {
		$totalmonth = $month->monthtotal;
	}
	foreach ($lastmonth_totals as $month) {
		$totallastmonth = $month->monthtotal;
	}
	foreach ($secondmonth_totals as $month) {
		$totalsecondmonth = $month->monthtotal;
	}
	foreach ($thirdmonth_totals as $month) {
		$totalthirdmonth = $month->monthtotal;
	}
	foreach ($fourthmonth_totals as $month) {
		$totalfourthmonth = $month->monthtotal;
	}

	// END MONTH DATA

	$numitems = 0;
	$daterow = 0;
	$checkrow = 0;
	$dayhours = 0;
	$content = '';
	
	$vacation_days = '';
	$count = 0;
	if (is_array($vacation) && !empty($vacation)) {
		foreach ($vacation as $date) {
			$date_parts = explode('-', $date);
			$vacation_days .= date('F j', mktime(0, 0, 0, $date_parts[1], $date_parts[2], $date_parts[0]));
			$count++;
			if ($count < count($vacation)) {
				$vacation_days .= ', ';
			}
		}
	}
	$sick_days = '';
	$count = 0;
	if (is_array($sick) && !empty($sick)) {
		foreach ($sick as $date) {
			$date_parts = explode('-', $date);
			$sick_days .= date('F j', mktime(0, 0, 0, $date_parts[1], $date_parts[2], $date_parts[0]));
			$count++;
			if ($count < count($sick)) {
				$sick_days .= ', ';
			}
		}
	}
	$holiday_days = '';
	$count = 0;
	if (is_array($off_holidays) && !empty($off_holidays)) {
		foreach ($off_holidays as $date) {
			$date_parts = explode('-', $date);
			$holiday_days .= date('F j', mktime(0, 0, 0, $date_parts[1], $date_parts[2], $date_parts[0]));
			$count++;
			if ($count < count($off_holidays)) {
				$holiday_days .= ', ';
			}
		}
	}

	?>
	<table class="widefat">
		<thead>
			<tr>
				<th colspan="7">
					<div class="cfhr-head">
						<div class="cfhr-head-left">
							Today: <?php echo $today; ?>
							<br />
							Paycheck Total Hours: <?php echo number_format($totalpaycheck, 2); ?>
						</div>
						<div class="cfhr-head-right">
						</div>
						<div class="clear"></div>
					</div>
					<div class="clear"></div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $paycheckend.' : '.$paycheckstart; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Paycheck Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalpaycheck, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Paycheck Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($paycheck_working_days_so_far*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Paycheck Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalpaycheck-($paycheck_working_days_so_far*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Paycheck Hours Total:</div><div class="cfhr-value"><?php echo $paycheck_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Paycheck Days Worked:</div><div class="cfhr-value"><?php echo $paycheck_working_days_so_far; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Paycheck Days Expected:</div><div class="cfhr-value"><?php echo $paycheck_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $lastpaycheckend.' : '.$lastpaycheckstart; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Paycheck Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastpaycheck, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Last Paycheck Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($lastpaycheck_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Paycheck Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastpaycheck-($lastpaycheck_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Last Paycheck Days Worked:</div><div class="cfhr-value"><?php echo $lastpaycheck_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Paycheck Days Expected:</div><div class="cfhr-value"><?php echo $lastpaycheck_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $secondpaycheckend.' : '.$secondpaycheckstart; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">2nd Paycheck Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalsecondpaycheck, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">2nd Paycheck Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($secondpaycheck_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">2nd Paycheck Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalsecondpaycheck-($secondpaycheck_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">2nd Paycheck Days Worked:</div><div class="cfhr-value"><?php echo $secondpaycheck_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">2nd Paycheck Days Expected:</div><div class="cfhr-value"><?php echo $secondpaycheck_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $thirdpaycheckend.' : '.$thirdpaycheckstart; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">3rd Paycheck Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalthirdpaycheck, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">3rd Paycheck Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($thirdpaycheck_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">3rd Paycheck Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalthirdpaycheck-($thirdpaycheck_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">3rd Paycheck Days Worked:</div><div class="cfhr-value"><?php echo $thirdpaycheck_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">3rd Paycheck Days Expected:</div><div class="cfhr-value"><?php echo $thirdpaycheck_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="clear cfhr-extended"></div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $weekend.' : '.$weekstart; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Week Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalweek, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Week Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($week_working_days_so_far*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Week Difference Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalweek-($week_working_days_so_far*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Week Hours Total:</div><div class="cfhr-value"><?php echo $week_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Week Days Worked:</div><div class="cfhr-value"><?php echo $week_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Week Days Expected:</div><div class="cfhr-value"><?php echo $week_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $lastweekend.' : '.$lastweekstart; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Week Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastweek, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Last Week Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($lastweek_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Week Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastweek-($lastweek_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Last Week Days Worked:</div><div class="cfhr-value"><?php echo $lastweek_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Week Days Expected:</div><div class="cfhr-value"><?php echo $lastweek_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $secondweekend.' : '.$secondweekstart; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">2nd Week Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalsecondweek, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">2nd Week Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($secondweek_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">2nd Week Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalsecondweek-($secondweek_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">2nd Week Days Worked:</div><div class="cfhr-value"><?php echo $secondweek_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">2nd Week Days Expected:</div><div class="cfhr-value"><?php echo $secondweek_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $thirdweekend.' : '.$thirdweekstart; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">3rd Week Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalthirdweek, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">3rd Week Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($thirdweek_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">3rd Week Difference:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalthirdweek-($thirdweek_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">3rd Week Days Worked:</div><div class="cfhr-value"><?php echo $thirdweek_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">3rd Week Days Expected:</div><div class="cfhr-value"><?php echo $thirdweek_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="clear cfhr-extended"></div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $endmonth.' : '.$startmonth; ?></div>
						<div class="cfhr-label">Month Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalmonth, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Month Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($month_working_days_so_far*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Month Difference Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalmonth-($month_working_days_so_far*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Month Hours Total:</div><div class="cfhr-value"><?php echo $month_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Month Days Worked:</div><div class="cfhr-value"><?php echo $month_actual_working_days; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Month Days Expected:</div><div class="cfhr-value"><?php echo $month_working_days; ?></div>
						<div class="clear cfhr-extended"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $lastendmonth.' : '.$laststartmonth; ?></div>
						<div class="cfhr-label">Last Month Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastmonth, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">Last Month Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($lastmonth_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Month Difference Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totallastmonth-($lastmonth_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">Last Month Hours Total:</div><div class="cfhr-value"><?php echo $lastmonth_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">Last Month Days Worked:</div><div class="cfhr-value"><?php echo $lastmonth_actual_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $secondendmonth.' : '.$secondstartmonth; ?></div>
						<div class="cfhr-label">2nd Month Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalsecondmonth, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">2nd Month Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($secondmonth_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">2nd Month Difference Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalsecondmonth-($secondmonth_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">2nd Month Hours Total:</div><div class="cfhr-value"><?php echo $secondmonth_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">2nd Month Days Worked:</div><div class="cfhr-value"><?php echo $secondmonth_actual_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="cfhr-totals">
						<div class="cfhr-head-label">Days: <?php echo $thirdendmonth.' : '.$thirdstartmonth; ?></div>
						<div class="cfhr-label">3rd Month Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalthirdmonth, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label cfhr-value-total">3rd Month Hours Expected:</div><div class="cfhr-value cfhr-value-total"><?php echo number_format($thirdmonth_working_days*8, 2); ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">3rd Month Difference Hours:</div><div class="cfhr-value cfhr-value-bold"><?php echo number_format($totalthirdmonth-($thirdmonth_working_days*8), 2); ?></div>
						<div class="clear cfhr-extended"></div>
						<div class="cfhr-label">3rd Month Hours Total:</div><div class="cfhr-value"><?php echo $thirdmonth_working_totals; ?></div>
						<div class="clear"></div>
						<div class="cfhr-label">3rd Month Days Worked:</div><div class="cfhr-value"><?php echo $thirdmonth_actual_working_days; ?></div>
						<div class="clear"></div>
					</div>
					<div class="clear cfhr-extended"></div>
					<div class="cfhr-holiday">
						<div class="cfhr-label">Holiday Days:</div><?php echo $holiday_days; ?>
					</div>
					<div class="clear"></div>
					<div class="cfhr-vacation">
						<div class="cfhr-label">Vacation Days:</div><?php echo $vacation_days; ?>
					</div>
					<div class="clear"></div>
					<div class="cfhr-sick">
						<div class="cfhr-label">Sick Days:</div><?php echo $sick_days; ?>
					</div>
					<div class="clear"></div>
				</th>
			</tr>
		</thead>
	</table>
	<?php
}

//The function returns the no. of business days between two dates and it skips the holidays
function getWorkingDays($startDate,$endDate,$holidays){
    //The total number of days between the two dates. We compute the no. of seconds and divide it to 60*60*24
    //We add one to inlude both dates in the interval.
    $days = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;

    $no_full_weeks = floor($days / 7);
    $no_remaining_days = fmod($days, 7);
	
    //It will return 1 if it's Monday,.. ,7 for Sunday
    $the_first_day_of_week = date("N",strtotime($startDate));
    $the_last_day_of_week = date("N",strtotime($endDate));
	
    //---->The two can be equal in leap years when february has 29 days, the equal sign is added here
    //In the first case the whole interval is within a week, in the second case the interval falls in two weeks.
    if ($the_first_day_of_week <= $the_last_day_of_week){
        if ($the_first_day_of_week <= 6 && 6 <= $the_last_day_of_week) $no_remaining_days--;
        if ($the_first_day_of_week <= 7 && 7 <= $the_last_day_of_week) $no_remaining_days--;
    }
    else{
        if ($the_first_day_of_week <= 6) {
        //In the case when the interval falls in two weeks, there will be a weekend for sure
            $no_remaining_days = $no_remaining_days - 2;
        }
		else if ($the_first_day_of_week == 7) {
			$no_remaining_days = $no_remaining_days - 1;
		}
    }

    //The no. of business days is: (number of weeks between the two dates) * (5 working days) + the remainder
	//---->february in none leap years gave a remainder of 0 but still calculated weekends between first and last day, this is one way to fix it
   $workingDays = $no_full_weeks * 5;

    if ($no_remaining_days > 0 )
    {
      $workingDays += $no_remaining_days;
    }

    //We subtract the holidays
    foreach($holidays as $holiday){
        $time_stamp=strtotime($holiday);
        //If the holiday doesn't fall in weekend
        if (strtotime($startDate) <= $time_stamp && $time_stamp <= strtotime($endDate) && date("N",$time_stamp) != 6 && date("N",$time_stamp) != 7)
            $workingDays--;
    }

    return $workingDays;
}

function cfhr_get_dropdown_clients() {
	global $wpdb;
	
	$clients = $wpdb->get_results("SELECT DISTINCT(client) FROM cfhr_data");
	$options = '';
	
	if (is_array($clients) && !empty($clients)) {
		foreach ($clients as $key => $client) {
			$options .= '<option value="'.esc_attr($client->client).'">'.$client->client.'</option>';
		}
	}

	if (!empty($options)) {
		return '
		<select name="cfhr-clients" id="cfhr-clients">
			<option value="0">--Select Client Name--</option>
			'.$options.'
		</select>
		';
	}
	return false;
}

function cfhr_get_dropdown_project($client = '') {
	if (empty($client)) { return 'select client'; }
	global $wpdb;
	
	$projects = $wpdb->get_results("SELECT DISTINCT(project) FROM cfhr_data WHERE client = '$client'");
	$options = '';
	
	if (is_array($projects) && !empty($projects)) {
		foreach ($projects as $key => $project) {
			$options .= '<option value="'.esc_attr($project->project).'">'.$project->project.'</option>';
		}
	}

	if (!empty($options)) {
		return '
		<select name="cfhr-projects" id="cfhr-projects">
			<option value="0">--Select Project Name--</option>
			'.$options.'
		</select>
		';
	}
	return false;
}














?>