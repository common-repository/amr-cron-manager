<?php /*
Plugin Name: amr cron manager
Plugin URI: https://wpusersplugin.com/downloads/amr-cron-manager/
Author URI: http://webdesign.anmari.com
Description: Overview of cron with action check and arguments, plus ability to delete comprehensively, and reschedule
Author: anmari
Contributors: anmari
Version: 2.3
Text Domain: amr-cron-manager
Domain Path: /languages/

License:
 Released under the GPL license
  http://www.gnu.org/copyleft/gpl.html

	Changelog:
	2010 10 05 by anmari:
**	* set blog time for status message
**	* actually check the cron again after unscheduling to see if was delete.  If not it may have had a parameter - this is one way encrypted, so user entry required to delete those individually
	* - else ALL with that hook needs to be deleted.
**       * fix wordpress bug where clear all is not working if there are arguments (or maybe at all?)
**       *  add run now button instead of waiting for wp_hook to be run
*/


define( 'AMR_CRON_VERSION',		'2.3' );

class amr_cron_manager {
	var $plugin_dir, $plugin_file;
	
	public $dateformat, $utctzobj, $tzobj;

	/*
	* Constructor
	*/

	function CronDashboard() {
	global $tzobj;
		$this->__construct();
		$utctzobj = timezone_open('UTC');
		$tzobj = $utctzobj;
		if ($tz = get_option ('timezone_string') ) $tzobj = timezone_open($tz);
	}

	function __construct() {
		global $tzobj;
		load_plugin_textdomain('amr-cron-manager', false, dirname( plugin_basename( __FILE__ ) ) . 'lang');
		add_action('admin_menu', array($this, 'add_admin_menu'));
		$sp = '&\nb\sp;';
		$this->dateformat = 'D'.$sp.'d'.$sp.'M'.$sp.'y';	
		$this->datetimeformat = $this->dateformat.', H:i:s e'; //'l, j M y';//get_option('date_format');
	}

	function set_startdate() {
	global $tzobj;
				
		$gmt_time = (int) microtime(true);
		
		for ($i = 1; $i <= 8; $i++) {
			$blogtime = $this->convert_UTC_timestamp_to_blog($gmt_time,$tzobj);
		    $nextdates[$gmt_time] = date_i18n($this->dateformat,$blogtime); 
			$gmt_time = $gmt_time + (60*60*24);
		}

		$html = '';//'<br />';
		//<label for="startdate">'._('Set next run:').'</label>';
		$html .= '<select name="startdate">';
		foreach ($nextdates as $ts => $datestring) { // no selected date as this is always a moving target 
			$html .= "<option value=\"" . $ts . "\"";
			$html .= ">" .$datestring. "</option>";
			
		}
		$html .= '</select>';

	return($html);
	}
	
	function set_starttime() {
	global $tzobj;
		$gmt_time = (int) microtime(true);
		$timestamp = $this->convert_UTC_timestamp_to_blog($gmt_time, $tzobj);
		$time = date('H:i',$timestamp);
		$html = '';
		$html .= '<input type="text" size="5" name="starttime" value="'.$time.'">';

	return($html);
	}

	function add_admin_menu($s) {
		global $wp_version;

		add_submenu_page(
			version_compare($wp_version, "2.7", ">=") ? 'tools.php' : 'edit.php', //parent
			'amr-cron' , //page title
			__('Cron Manager', 'amr-cron-manager') , //Menu title
			'administrator' , //capability
			'amr-cron-manager', //dirname(__FILE__) ,   //menu slug
			array(&$this,'wp_cron_menu')  //function
		);
		return $s;
	}

	function convert_blog_timestamp_to_UTC($timestamp,$tzobj) {
		$date = date_create('now', $tzobj);
		$utctzobj = timezone_open('UTC');
		$date->format('U');
		$date->setTimezone($utctzobj);
		return($date->format('U'));
	}

	function convert_UTC_timestamp_to_blog($timestamp,$tzobj) {
		$date = date_create('@'.$timestamp);
		$date->setTimezone($tzobj);
		$offset = $date->getOffset();  
		$newtimestamp = $timestamp+$offset; 
		return($newtimestamp);
	}		
	
	function cronmgr_settings() {
		//amr_cron_license_page();   not really needed yet - add if lots of feature requests
	}

	function unschedule ($hook,$timestamp,$args) {
		global $wp_filter, $tzobj, $utctzobj;

			$blogtime = $this->convert_UTC_timestamp_to_blog($timestamp,$tzobj );

			wp_clear_scheduled_hook( $hook, $args ); 
			// args dont always go if they were int not string
			foreach ($args as $i=>$v) {
				if (is_numeric($v)) $args[$i] = (int) $v;
			}
			wp_unschedule_event($timestamp, $hook, $args );  //no return value
			
			// check if it was actually deleted
			$crons = _get_cron_array();
			if ( isset( $crons[$timestamp][$hook] ) ) {
				$key = md5(serialize($args)); var_dump($key);
				var_dump($args);
				var_dump($crons[$timestamp][$hook]);
					$note = __('Still some left - maybe these have arguments?','amr-cron-manager')
					." ".$hook." (".$timestamp.")";
					$note .=  __('Try using Really Delete','amr-cron-manager');
				}	
			else 
				$note = sprintf(__('Unscheduled %s for %s','amr-cron-manager'),$hook,date_i18n('d-M H:i',$blogtime)
				//.' ('.$timestamp.')'
				);

			$note = '<div id="message" class="updated fade"><p>'.$note.'</p></div>'.PHP_EOL;
			return $note;
	}
	
	function reschedule ($hook,$timestamp,$args) {
		global $wp_filter, $tzobj, $utctzobj;
					//check_admin_referer('cron');
			
			$startstamp = (int) $_POST['startdate'];  // will be a gmt timestamp in server time?
			
			$gmt_time = $startstamp;
			$blogtime = $this->convert_UTC_timestamp_to_blog($startstamp,$tzobj );

			$newdate = date_create('@'.$gmt_time);		
			date_timezone_set($newdate,$tzobj );
			//zap the hours, nis and secs, we will put them back in with time from the start time
			date_time_set($newdate,0,0);
			$hour = (int) substr($_POST['starttime'],0,2);  
			$mins = (int) substr($_POST['starttime'],3,2);  
			$unixtime = $newdate->format('U'); // in UTC format but in timezone time
			$unixtime = $unixtime  + (60*60*$hour) + (60*$mins);
			// convert blog time to utc time
			$newdate = date_create('@'.$unixtime);  // we still in blogtime actually
			date_timezone_set($newdate,$utctzobj ); // now we convert to UTC / GMT
			
			$gmt_time = $newdate->format('U'); // in UTC format and utc time

			wp_unschedule_event($timestamp, $hook, $args );  //no return value
		
			$didit = wp_schedule_event($gmt_time , $_POST['recurrence'], $hook, $args);
			
			$blogtime = $this->convert_UTC_timestamp_to_blog($unixtime,$tzobj );
			
			if (!is_null($didit)) { //False on failure, null when complete with scheduling event.
				$note = sprintf(__('Wordpress failed to reschedule %s for %s:','amr-cron-manager'),$hook,date_i18n('d-M H:i',$blogtime));
			}
			else {
				$note = sprintf(__('Rescheduled %s for %s:','amr-cron-manager'),$hook,date_i18n('d-M H:i',$blogtime).' ('.$gmt_time.')');
			}
			// Note snuff
			$note = '<div id="message" class="updated fade"><p>'.$note.'</p></div>'.PHP_EOL;
			return $note;
	}

	function wp_cron_menu() {
		global $wp_filter, $tzobj, $utctzobj;

		$tabs['dashboard'] 	= __('Dashboard', 'amr-cron-manager');
//		$tabs['settings'] 	= __('Settings','amr-cron-manager');
		$tabs['help'] 	= __('Help','amr-cron-manager');
	
	if (isset($_GET['tab']) and ($_GET['tab'] == 'help')){  //nlr
				$this->do_tabs ($tabs,'help');
				$this->cron_help();
				return;
			}
	elseif (isset($_GET['tab']) and ($_GET['tab'] == 'settings')){  //nlr
				$this->do_tabs ($tabs,'settings');
				$this->cronmgr_settings();
				return;
			}	
		else {
			$this->do_tabs ($tabs,'dashboard');	
		}	
		
		if (isset($_POST['_wpnonce'] )){
			check_admin_referer('cron');
		}	
		if (isset($_POST['deleteoption'])) {
			delete_option('cron');
		}

		$note = '';
		$out = PHP_EOL.' <!-- begin -->';

		$utctzobj = timezone_open('UTC');
		$tzobj = $utctzobj;
		
		if ($tz = get_option ('timezone_string') ) 
			$tzobj = timezone_open($tz);
			
		$schedules = wp_get_schedules();
		if (isset($_POST['args'])) 
			$args = $_POST['args'];
		else 	
			$args = array();
		
		if (isset($_POST['timestamp'])) // the timestamp of the job, needed to unschedule
			$timestamp = (int) $_POST['timestamp'];
		else 
			$timestamp = current_time('timestamp');

		$hook = '';
		if (!empty($_POST['hook'])) 
			$hook = sanitize_text_field($_POST['hook']); // sanitise?
			
		
		if (isset($_POST['runnow'])) {
			$note = $this->reschedule($hook,$timestamp,$args);
		}
		elseif (isset($_POST['delete'])) {
			$note = $this->unschedule ($hook,$timestamp,$args);
		}

		else if (isset($_POST['reallydelete'])) {
				$crons = _get_cron_array();
				foreach ($crons as $timestamp => $cron) {
					if ( isset( $cron[$hook] ) ) 
						unset ($crons[$timestamp][$hook]);
					if (empty ($crons[$timestamp])) 
						unset ($crons[$timestamp]);
				}
				_set_cron_array($crons);
				$note = $hook." - ".__('all instances deleted we hope! ','amr-cron-manager')
				.__('Note some jobs are automatically recreated by wp or their plugin.','amr-cron-manager');

			// Note snuff
			$note = '<div id="message" class="updated fade"><p>'.$note.'</p></div>'.PHP_EOL;
		}

		$now = (int) current_time('timestamp'); // in blogtime
		$gmt_time = (int) microtime( true ); 
		//$now = $this->convert_UTC_timestamp_to_blog($now,$tzobj);
		
		$out .= '<div class="wrap">'.PHP_EOL;

		$out .= '<h2>'.__('Overview of cron tasks','amr-cron-manager');
		$out .= ' - '.date_i18n($this->datetimeformat,$now)
		//.' ('.$gmt_time.')'
		.'</h2>'.PHP_EOL;
		$out .= $this->cron_actions();
		$out .= $this->show_cron_schedules('l d,F Y, H:i:s');
		$out .= '<br/>'.PHP_EOL;

		$out .= "</div>";

		// Output
		echo $note.$out.PHP_EOL;
	}

	function _get_cron_array() {
		if ( function_exists('_get_cron_array') ) {
			return _get_cron_array();
		} else {
			$cron = get_option('cron');
			return ( is_array($cron) ? $cron : false );
		}
	}
	
	function cron_actions() {
		$ans = '<form action="" method="post">'.PHP_EOL;
		$ans .= wp_nonce_field('cron');
		$ans .= '<a class="button-primary" target="_blank" href="'
		.get_bloginfo('wpurl').'/wp-cron.php'.'" title="'
		.__('Trigger the cron, else it has to wait for someone to request a page.').' '.__('Any jobs that are due or overdue will run.').' '. __('Any debug(?) output will show.','amr-cron-manager').'" >'
		.__('Trigger the cron now!','amr-cron-manager').'</a> &nbsp; '.PHP_EOL;
		$ans .= '<input class="button"  title="Delete the cron option - do not panic - the wordpress ones will recreate themselves" name="deleteoption" type="submit" value="'.__('Delete all jobs','amr-cron-manager').'"/> &nbsp; '.PHP_EOL;
		$ans .= '<a class="button" href="" title="'.__('Refresh to check status').'" >'.__('Refresh').'</a> &nbsp; '.PHP_EOL;

		$ans .= '</form>';

		return $ans;
	}
	
	function show_cron_schedules($datetime_format = '') {

		$utctzobj = timezone_open('UTC');
		if ($tz = get_option ('timezone_string') ) 
			$tzobj = timezone_open($tz);
		else 
			$tzobj = $utctzobj;

		$ans = '';
		$timeslots = $this->_get_cron_array();  // in UTC time
		//$now = (int) current_time('timestamp'); // in UTC time because wp always in UTC
		$gmt_now = microtime( true ); // gmt time
		//$now = $this->convert_UTC_timestamp_to_blog($now,$tzobj);
		//$now = time();
		
		if ( empty($timeslots) ) {
			$ans .= '<div style="margin:.5em 0;width:100%;">';
			$ans .= __('Nothing scheduled','amr-cron-manager');
			$ans .= '</div>'.PHP_EOL;
		}
		else {
			$count = 1;
			$ans .= '<p>'.count($timeslots).' '.__('Timeslots:','amr-cron-manager').'</p>';
			$ans .= '<table class="widefat" style="width: 100%;">';
			$ans .= '<thead><tr><th>'.__('Date&nbsp;and&nbsp;Time').'</th><th>Job</th><th>Action by priority?</th>'
			.'<th>Frequency</th><th>Arguments</th><th>Actions: Run sooner or delete?</th></tr></thead>';
			$ans .= '<tbody>';
			$alt = ' alt ';
			foreach ( $timeslots as $time => $tasks ) {
				if (!empty($alt)) $alt='';
				else $alt = ' alt ';
				$gmt_time = $time; // save  
				$blogtimestamp = $this->convert_UTC_timestamp_to_blog ($gmt_time, $tzobj); // timeis in UTC

				$timetext = '<tr class="'.$alt.'"><td rowspan="'.count($tasks).'"><strong>'		
				.date_i18n($this->datetimeformat,$blogtimestamp)
				.'</strong>'
				//. '('.$gmt_time.')'
				.'<br />';
				if ($gmt_time < $gmt_now ) {
					$timetext .= __('ASAP','amr-cron-manager');
				}
				else {
					$timetext .= sprintf(__('In %s time','amr-cron-manager')
					, human_time_diff( $gmt_time ,$gmt_now));
				}
				$timetext .= '<br />';
				$timetext .= sprintf(_n('%s task','%s tasks',count($tasks), 'amr-cron-manager'),count($tasks));
				$timetext .= '</td>'.PHP_EOL;
				$taskcount = 1;
				foreach ($tasks as $procname => $task) {
					if ($taskcount > 1) $taskline = '<tr>';//<td>&nbsp;</td>';
					else $taskline = $timetext;
					$taskline .= '<td>'.$procname.'</td>';
					$taskcount++;
					if (has_action( $procname )) {
						$action = '&nbsp;<span style="color:green;" >&#8730;</span>'.__('exists','amr-cron-manager');
						if (isset($GLOBALS['wp_filter'][$procname])) {
							foreach ($GLOBALS['wp_filter'][$procname] as $priority => $prioritisedtasks) {
								foreach ($prioritisedtasks as $functions_to_do) {
									foreach ($functions_to_do as $func) {
										if (!($func == 1)) {
											if (is_array($func)) { // probably object and function 
												if (is_object($func[0])) {
													$info = get_class($func[0]).'::'.$func[1];
												}
												else $info = print_r($func,true);
												$action .= '<br/>'.$priority.'&nbsp;'.$info;
											}
											else
												$action .= '<br/>'.$priority.'&nbsp;'.$func;
										}
									}
								}
							}
						}

					}
					else $action = '<span style="color:red;">X</span>'.__('missing','amr-cron-manager');
					$taskline .= ' <td>'. $action.'</td>';
					$taskwithargcount = 1;
					foreach ($task as $md5key => $taskdetails) {
						if ($taskwithargcount > 1) 
							$taskwithargs = '<td> </td>';
						else 
							$taskwithargs = $taskline;
						$uns = '';
						if (!empty($taskdetails['schedule'])) $schedule = $taskdetails['schedule'];
						else $schedule = 'single run';
//
						$uns = ' &nbsp; <input title ="Will delete ALL recurences with this name." name="reallydelete" type="submit" value="'.__('Really Delete','amr-cron-manager').'"/>'.PHP_EOL;
//
						$taskwithargs .= ' <td>'. $schedule.'</td>';
						$argsline = '';
						$butargs  = '';
						if (!empty($taskdetails['args'])) {
//							$key = md5(serialize($taskdetails['args']));
//							$args = (serialize(esc_attr($taskdetails['args'])));

							foreach ($taskdetails['args'] as $i => $value) {
								$argsline .= $i.'='. $value;
								$butargs .= '<input type="hidden" name="args['.$i.']" value="'.esc_attr($value).'"/>'.PHP_EOL;
								}
						}
						else {
							$key = '';
							$argsline .= '&nbsp;';
							$args = '';
						}
						$taskwithargs .= ' <td>'. $argsline.'</td>';

						// Add in delete button for each entry.
						$but = '';
						$but .= '<form style="width: 400px;" action="" method="post">'.PHP_EOL;
						$but .= wp_nonce_field('cron');
						$but .= $butargs;
						$but .= '<input type="hidden" name="hook" value="'.$procname.'"/>'.PHP_EOL;
						$but .= '<input type="hidden" name="timestamp" value="'.$gmt_time.'"/>'.PHP_EOL;
						$but .= '<input type="hidden" name="recurrence" value="'.$taskdetails['schedule'].'"/>'.PHP_EOL;
						$but .= $this->set_startdate();
						$but .= $this->set_starttime();
											
						//$but .= '<input type="hidden" name="args" value="'.($args).'"/>'.PHP_EOL;
						//$but .= '<input type="hidden" name="timeintz" value="'.$timeintz->format('Y-m-d H:i:s').'"/>'.PHP_EOL;
						$but .= '<br />'.PHP_EOL.'<input class="button-primary" title ="Reschedule for time above. Remember you still need to trigger the cron with at least a page refresh." name="runnow" type="submit" value="'
						.__('Reschedule for the time above.','amr-cron-manager').'"/>'.PHP_EOL;
						//$but .= __("To run now, leave date and time, and click reschedule.",'amr-cron-manager');
						$but .= '<br /><br /><input title ="Delete just this instance" name="delete" type="submit" value="'.__('Delete','amr-cron-manager').'"/>'.PHP_EOL;

						$but .= $uns;	
						$but .= '</form>'.PHP_EOL;

						$taskwithargs .= '<td>'.$but."</td></tr>\n";
						$count++;
						$ans .= $taskwithargs;
					}

				}
			}
			$ans .= '</tbody></table>';
			unset($timeslots);
		}



		return $ans;
	}

	function do_tabs ($tabs, $current_tab) {
	// check for tabs  
	    // display the icon and page title  
    echo '<div id="icon-options-general" class="icon32"><br /></div>';  
	if ($tabs !='') {  	
		// wrap each in anchor html tags  
		$links = array();  
		foreach( $tabs as $tab => $name ) {  
			// set anchor class  
			$class      = ($tab == $current_tab ? 'nav-tab nav-tab-active' : 'nav-tab');  
			$page       = $_GET['page'];  
			// the link  
			$links[]    = "<a class='$class' href='?page=$page&tab=$tab'>$name</a>";  
		}    
		echo PHP_EOL.'<h2 class="nav-tab-wrapper">';  
			foreach ( $links as $link ) {  
				echo $link;  
			}  
		echo '</h2>'.PHP_EOL;  
		} 
	}
	
	function cron_help() {
	?><p>
	<a target="new" href="http://code.tutsplus.com/articles/insights-into-wp-cron-an-introduction-to-scheduling-tasks-in-wordpress--wp-23119">
	<?php _e('A cron guide','amr-cron-manager'); ?></a>
		&nbsp; 
	<a href="http://www.smashingmagazine.com/2013/10/16/schedule-events-using-wordpress-cron/" target="new"><?php _e('Another cron guide','amr-cron-manager'); ?></a>

	</p>
	<h3>Needs Visitor Site Traffic</h3><p>
	A key point about default wordpress cron is that it only runs if there is traffic on the website.   So if you are testing at low traffic hours you may not see a cron job run when you think it should.   If you want more immediate and reliable scheduled action please google 'wordpress cron' - there is a lot of content out there about wordpress cron and alternate ways to run it.  For most sites wordpress default cron is adequate.</p>
	<h3>Help - Job with scheduled time in the past????</h3>
	<p>See the point above. Click on 'Trigger cron now', then go back to dashboard and refresh the dashboard. </p>
	<h3>Run now = Reschedule to time just past</h3>
	<p>To run a job 'now', leave the date and time proposed as is (they will be in the past by the time you have looked at the screen.   Then click reschedule.  That will reschedule that job for that time and the job should run.  You may have to click 'trigger cron now'.
	The trigger cron now page is useful when you are debugging cron jobs.  You may temporarily issue output and it will be visible if the cron runs on that 'trigger cron' page load.
	</p>
	<h3>Job rescheduled for now/soon shows in future</h3>
	<p>It probably rescheduled and ran, and then setup the next run as per it's frequency.</p>
	<h3>Refresh the cron dashboard</h3>
	<p>If you have triggered the cron and think a job should have run, then refresh the cron dashboard to see.  It will either 
	<ol><li>still be there with a time in the future</li>
	<li>moved to a much more future timeslot (recurring jobs)</li>
	<li>disappeared - it was a one off job and it ran</li></ol>
	<h3>No action?</h3>
	<p>If a job has no action it will do nothing.  It is just there, an entry that keeps getting rescheduled.   Maybe the plugin that created it got deleted? Someplugins don't know to clean up after themselves.   Maybe it is just temporarily inactive? Maybe it's a bug in the creating plugin?   If you don't think you need it, delete it.  
	</p>
	<h3>Undeleting</h3>
	<p>The only way to do this is either maybe wordpress or the creating plugin will automaticaly bring the job back to life, or you ay have to reactivate the creating plugin.  Yes that means deactivate then activate again.   
	</p>
	<h3>Deleting a job - zombies that won't die</h3>
	<p>Some plugins check whether their jobs are running and if not, they recreate them. 
	</p>

	<?php
	}
}

if (is_admin()) new amr_cron_manager();


?>