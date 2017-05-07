<?php
/*
Plugin Name: Crony Cronjob Manager
Plugin URI: http://scottkclark.com/
Description: Create and Manage Cronjobs in WP by loading Scripts via URLs, including Scripts, running Functions, and/or running PHP code. This plugin utilizes the wp_cron API.
Version: 0.4.7
Author: Scott Kingsley Clark
Author URI: http://scottkclark.com/
*/

global $wpdb;
define('CRONY_TBL', $wpdb->prefix . 'crony_');
define('CRONY_VERSION', '047');
define('CRONY_URL', WP_PLUGIN_URL . '/crony');
define('CRONY_DIR', WP_PLUGIN_DIR . '/crony');

add_action('admin_init', 'crony_init');
add_action('admin_menu', 'crony_menu');

add_action('crony', 'crony', 10, 1);
add_filter('cron_schedules', 'crony_schedules', 10, 2);

if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'crony') !== false) {
    add_action('wp_admin_ui_post_save', 'crony_add_job', 10, 2);
    add_action('wp_admin_ui_post_delete', 'crony_remove_job', 10, 2);
}

function crony_install_update() {

	global $wpdb;

	// check version
	$version = intval( get_option( 'crony_version' ) );

	if ( empty( $version ) || $version == 10 ) {
		// thx pods ;)
		$sql = file_get_contents( CRONY_DIR . '/assets/dump.sql' );

		$sql_explode = preg_split( "/;\n/", str_replace( 'wp_', $wpdb->prefix, $sql ) );

		if ( count( $sql_explode ) == 1 ) {
			$sql_explode = preg_split( "/;\r/", str_replace( 'wp_', $wpdb->prefix, $sql ) );
		}

		for ( $i = 0, $z = count( $sql_explode ); $i < $z; $i ++ ) {
			$wpdb->query( $sql_explode[ $i ] );
		}

		$version = CRONY_VERSION;

		delete_option( 'crony_version' );
		add_option( 'crony_version', CRONY_VERSION );
	}

	if ( $version == 11 || $version == 12 ) {
		$wpdb->query( "ALTER TABLE `" . CRONY_TBL . "jobs` ADD COLUMN `email` varchar(255) AFTER `schedule`" );
		$wpdb->query( "ALTER TABLE `" . CRONY_TBL . "jobs` ADD COLUMN `last_run` datetime AFTER `email`" );
		$wpdb->query( "ALTER TABLE `" . CRONY_TBL . "jobs` ADD COLUMN `next_run` datetime AFTER `last_run`" );
		// in case these fail (already exist) then on refresh it won't cause issues
		$wpdb->query( "ALTER TABLE `" . CRONY_TBL . "jobs` ADD COLUMN `script` varchar(255) AFTER `next_run`" );
		$wpdb->query( "ALTER TABLE `" . CRONY_TBL . "jobs` ADD COLUMN `function` varchar(255) AFTER `script`" );
		$wpdb->query( "ALTER TABLE `" . CRONY_TBL . "jobs` ADD COLUMN `phpcode` longtext AFTER `function`" );
	}

	if ( $version < 30 ) {
		$wpdb->query( $wpdb->prepare( "UPDATE `" . CRONY_TBL . "jobs` SET `phpcode` = CONCAT(%s,`phpcode`) WHERE `phpcode` != ''", "<?php\n" ) );
		$wpdb->query( "DROP TABLE IF EXISTS `" . CRONY_TBL . "logs`" );
		$wpdb->query( "CREATE TABLE `" . CRONY_TBL . "logs` (`id` int(10) NOT NULL AUTO_INCREMENT,`crony_id` int(10) NOT NULL,`output` longtext NOT NULL,`real_time` datetime NOT NULL,`start` datetime NOT NULL,`end` datetime NOT NULL,PRIMARY KEY (`id`)) DEFAULT CHARSET=utf8" );
	}

	if ( $version < 31 ) {
		$wpdb->query( "ALTER TABLE `" . CRONY_TBL . "logs` CHANGE `real` `real_time` datetime" );
	}

	if ( $version < 40 ) {
		$wpdb->query( "ALTER TABLE `" . CRONY_TBL . "jobs` ADD COLUMN `url` varchar(255) AFTER `next_run`" );
	}

	if ( $version != CRONY_VERSION ) {
		delete_option( 'crony_version' );
		add_option( 'crony_version', CRONY_VERSION );
	}

}

function crony_init() {

	crony_install_update();

	global $current_user;
	// thx gravity forms, great way of integration with members!
	$capabilities = crony_capabilities();
	if ( function_exists( 'members_get_capabilities' ) ) {
		add_filter( 'members_get_capabilities', 'crony_get_capabilities' );
		if ( current_user_can( "crony_full_access" ) ) {
			$current_user->remove_cap( "crony_full_access" );
		}
		$is_admin_with_no_permissions = current_user_can( "administrator" ) && ! crony_current_user_can_any( crony_capabilities() );
		if ( $is_admin_with_no_permissions ) {
			$role = get_role( "administrator" );
			foreach ( $capabilities as $cap ) {
				$role->add_cap( $cap );
			}
		}
	} else {
		$crony_full_access = current_user_can( "administrator" ) ? "crony_full_access" : "";
		$crony_full_access = apply_filters( "crony_full_access", $crony_full_access );

		if ( ! empty( $crony_full_access ) ) {
			$current_user->add_cap( $crony_full_access );
		}
	}
}

function crony_menu ()
{
    global $wpdb;

    $has_full_access = current_user_can('crony_full_access');

    if(!$has_full_access&&(current_user_can('administrator')||is_super_admin()))
        $has_full_access = true;

    $min_cap = crony_current_user_can_which(crony_capabilities());

    if(empty($min_cap))
        $min_cap = 'crony_full_access';

    add_menu_page('Cronjobs', 'Cronjobs', $has_full_access ? 'read' : $min_cap, 'crony', null, CRONY_URL.'/assets/icons/16.png');
    add_submenu_page('crony', 'Manage Cronjobs', 'Manage Cronjobs', $has_full_access ? 'read' : 'crony_manage', 'crony', 'crony_manage');
    add_submenu_page('crony', 'View Schedule', 'View Schedule', $has_full_access ? 'read' : 'crony_view', 'crony-view', 'crony_view');
    add_submenu_page('crony', 'View Logs', 'View Logs', $has_full_access ? 'read' : 'crony_view_logs', 'crony-logs', 'crony_view_logs');
    add_submenu_page('crony', 'Settings', 'Settings', $has_full_access ? 'read' : 'crony_settings', 'crony-settings', 'crony_settings');
}
function crony_get_capabilities ($caps)
{
    return array_merge($caps,crony_capabilities());
}
function crony_capabilities ()
{
    return array('crony_full_access','crony_settings','crony_manage');
}
function crony_current_user_can_any ($caps)
{
    if(!is_array($caps))
        return current_user_can($caps) || current_user_can("crony_full_access");
    foreach($caps as $cap)
    {
        if(current_user_can($cap))
            return true;
    }
    return current_user_can("crony_full_access");
}
function crony_current_user_can_which ($caps)
{
    foreach($caps as $cap)
    {
        if(current_user_can($cap))
            return $cap;
    }
    return "";
}

function crony_settings ()
{
	global $wpdb;

    $tables = $wpdb->get_results( 'SHOW TABLES LIKE "' . CRONY_TBL . '%"', ARRAY_N );

	// Force reset if tables don't exist
    if ( count( $tables ) < 2 ) {
	    $_POST['reset'] = 1;
    }

    if (isset($_POST['clear-logs']) && !empty($_POST['clear-logs'])) {
        crony_empty_log();
?>
        <div id="message" class="updated fade"><p>Crony Logs have been removed</p></div>
<?php
    }
    elseif (isset($_POST['reset']) && !empty($_POST['reset'])) {
        crony_reset();
?>
        <div id="message" class="updated fade"><p>All Crony Jobs, Logs, and Settings have been removed</p></div>
<?php
    }
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo CRONY_URL; ?>/assets/icons/32.png);"><br /></div>
    <h2>Crony Cronjob Manager - Settings</h2>
    <div style="height:20px;"></div>
    <link type="text/css" rel="stylesheet" href="<?php echo CRONY_URL; ?>/assets/admin.css" />
    <form method="post" action="">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label>Reset Crony Logs</label></th>
                <td>
                    <input name="clear-logs" type="submit" id="clear" value=" Clear Now " onclick="return confirm('Are you sure?');" />
                    <span class="description">This will remove all Crony Logs</span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label>Reset Crony</label></th>
                <td>
                    <input name="reset" type="submit" id="reset" value=" Reset Now " onclick="return confirm('Are you sure?');" />
                    <span class="description">This will remove all Crony Jobs, Logs, and Settings</span>
                </td>
            </tr><!--
            <tr valign="top">
                <th scope="row"><label for=""></label></th>
                <td>
                    <input name="" type="text" id="" value="0" class="small-text" />
                    <span class="description"></span>
                </td>
            </tr>-->
        </table><!--
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="  Save Changes  " />
        </p>-->
    </form>
</div>
<?php
}
function crony_manage ()
{
    require_once CRONY_DIR.'/wp-admin-ui/Admin.class.php';
    $columns = array('name','disabled'=>array('Disabled?','type'=>'bool'),'start'=>array('type'=>'datetime'),'next_run'=>array('label'=>'Next Run On','custom_input'=>'crony_date_input','type'=>'datetime','comments'=>'This is the date to run the next Cronjob on','comments_top'=>true),'last_run'=>array('label'=>'Last Run On','type'=>'datetime'),'schedule'=>array('custom_display'=>'crony_schedule_display','custom_input'=>'crony_schedule_input'),'created'=>array('label'=>'Date Created','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    unset($columns['start']);
    unset($form_columns['last_run']);
    $form_columns['start'] = array('label'=>'Start On','custom_input'=>'crony_date_input','type'=>'datetime','comments'=>'This is the date to start allowing Cronjobs to run, set to the future to delay and override Next Run date','comments_top'=>true);
    $form_columns['email'] = array('label'=>'E-mail Notifications','comments'=>'Enter the E-mail Address you would like notifications / output to be sent to','comments_top'=>true);
    $form_columns['url'] = array('label'=>'URL to Load','comments'=>'URL of a Page or Script to be loaded','comments_top'=>true);
    $form_columns['script'] = array('label'=>'Script to Include','comments'=>'Path of Script to be included','comments_top'=>true);
    $form_columns['function'] = array('label'=>'Function to Run');
    $form_columns['phpcode'] = array('label'=>'Custom PHP to Run','type'=>'desc','comments'=>'<strong>NOTE:</strong> You must put your &lt;?php tag to initiate PHP where you want it used','comments_top'=>true);
    $form_columns['created']['date_touch_on_create'] = true;
    $form_columns['created']['display'] = false;
    $form_columns['updated']['date_touch'] = true;
    $form_columns['updated']['display'] = false;
    $admin = new WP_Admin_UI(array('css'=>CRONY_URL.'/assets/admin.css','item'=>'Cronjob','items'=>'Cronjobs','table'=>CRONY_TBL.'jobs','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>CRONY_URL.'/assets/icons/32.png'));
    $admin->go();
}
function crony_schedule_display ($column,$data,$obj)
{
    $schedules = wp_get_schedules();
    return $schedules[$column]['display'];
}
function crony_schedule_input ($column,$attributes,$obj)
{
    $schedules = wp_get_schedules();
    $interval = array();
    foreach ($schedules as $key => $value)
    {
        $interval[$key]  = $value['interval'];
    }
    array_multisort($interval,SORT_NUMERIC,$schedules);
?>
<select name="<?php echo $column; ?>">
<?php
    foreach($schedules as $id=>$schedule)
    {
?>
    <option value="<?php echo $id; ?>"<?php echo ($obj->row[$column]==$id?' SELECTED':''); ?>><?php echo $schedule['display']; ?></option>
<?php
    }
?>
</select>
<?php
}
function crony_date_input ($column,$attributes,$obj)
{
    $obj->row[$column] = empty($obj->row[$column]) ? date_i18n("Y-m-d H:i:s") : $obj->row[$column];
    if(!isset($obj->date_input))
    {
        $obj->date_input = true;
?>
<script type="text/javascript" src="<?php echo CRONY_URL; ?>/assets/date_input.js"></script>
<link type="text/css" rel="stylesheet" href="<?php echo CRONY_URL; ?>/assets/date_input.css" />
<script type="text/javascript">
jQuery(function() {
    jQuery(".wp_admin_ui input.date").date_input();
});
</script>
<?php } ?>
<input type="text" name="<?php echo esc_attr( $column ); ?>" value="<?php echo esc_attr( $obj->row[$column] ); ?>" class="regular-text date" />
<?php
}
function crony_view ()
{
    require_once CRONY_DIR.'/wp-admin-ui/Admin.class.php';
    $admin = new WP_Admin_UI();
    $cronjobs = _get_cron_array();
    $schedules = wp_get_schedules();
    if(isset($_GET['crony_cronjob_remove'])&&!empty($_GET['crony_cronjob_remove']))
    {
        $remove = explode(':',$_GET['crony_cronjob_remove']);
        $removal_timestamp = $remove[0];
        $removal_function = $remove[1];
        $removal_key = $remove[2];
        $error = false;
        if(isset($cronjobs[$removal_timestamp]))
        {
            if(isset($cronjobs[$removal_timestamp][$removal_function])&&isset($cronjobs[$removal_timestamp][$removal_function][$removal_key]))
            {
                wp_unschedule_event($removal_timestamp,$removal_function,$cronjobs[$removal_timestamp][$removal_function][$removal_key]['args']);
                unset($cronjobs[$removal_timestamp][$removal_function][$removal_key]);
                if(empty($cronjobs[$removal_timestamp][$removal_function]))
                    unset($cronjobs[$removal_timestamp][$removal_function]);
                if(empty($cronjobs[$removal_timestamp]))
                    unset($cronjobs[$removal_timestamp]);
            }
            else
            {
                $error = '<strong>Error:</strong> Cronjob not found.';
            }
        }
        else
        {
            $error = '<strong>Error:</strong> Cronjob not found.';
        }
        if(false===$error)
        {
            $admin->message('Cronjob removed.');
        }
        else
        {
            $admin->error($error);
        }
    }
    $interval = array();
    foreach($schedules as $schedule=>$info)
    {
        $interval[$schedule]  = $info['interval'];
    }
    array_multisort($interval,SORT_NUMERIC,$schedules);
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo CRONY_URL; ?>/assets/icons/32.png);"><br /></div>
    <h2>View Scheduled Cronjobs</h2>
    <div style="height:20px;"></div>
    <link type="text/css" rel="stylesheet" href="<?php echo CRONY_URL; ?>/assets/admin.css" />
    <h3>Schedule Cronjobs</h3>
    <table class="widefat fixed">
        <thead>
            <tr>
                <th scope="col">Function</th>
                <th scope="col">Schedule</th>
                <th scope="col">Next Run</th>
                <th scope="col">Arguments</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
<?php
    foreach($cronjobs as $timestamp=>$cron)
    {
        $cron = (array) $cron;
        foreach($cron as $function=>$events)
        {
            $events = (array) $events;
            foreach($events as $key=>$event)
            {
?>
            <tr>
                <th scope="row"><?php echo esc_html( $function ); ?></th>
                <td><?php echo ($event['schedule']?esc_html($schedules[$event['schedule']]['display']):'<em>One-off event</em>'); ?></td>
                <td><?php echo get_date_from_gmt(date_i18n('Y-m-d H:i:s', $timestamp, true), 'M j, Y @ h:ia'); ?></td>
                <td>
<?php
                if ('crony' == $function && !empty($event['args'])) {
                    $crony_id = (int) $event['args'][0];
?>
                    <a href="admin.php?page=crony&action=edit&id=<?php echo (int) $crony_id; ?>"><?php echo esc_html( crony_get_job_name($crony_id) ); ?></a>
<?php
                }
                elseif(!empty($event['args']))
                {
?>
                    <ul>
<?php
                    foreach($event['args'] as $arg=>$val)
                    {
?>
                        <li><strong>[Arg <?php echo esc_html( $arg ); ?>]:</strong> <?php echo esc_html( $val ); ?></li>
<?php
                    }
?>
                    </ul>
<?php
                }
                else
                {
                    echo '<em>N/A</em>';
                }
?>
                </td>
                <td>
                    <input type="button" class="button" value=" Remove " onclick="crony_remove_job(this);" />
                    <input type="hidden" class="crony-identifier" value="<?php echo esc_attr( $timestamp.':'.$function.':'.$key ); ?>" />
                </td>
            </tr>
<?php
            }
        }
    }
?>
        </tbody>
    </table>
    <script type="text/javascript">
        function crony_remove_job (that) {
            if(confirm('Are you sure you want to remove this Cronjob?\n\nPlease Note: Whatever scheduled this job may add it back automatically.')) {
                var crony_identifier = jQuery(that).parent().find('.crony-identifier').val();
                jQuery(that).parent().parent().slideUp('fast');
                document.location = '<?php echo $admin->var_update(array('page'=>$_GET['page']),false,false,true); ?>&crony_cronjob_remove='+encodeURI(crony_identifier);
            }
        }
    </script>
    <div style="height:20px;"></div>
    <h3>Available Schedules</h3>
    <table class="widefat fixed">
        <thead>
            <tr>
                <th scope="col">Schedule</th>
                <th scope="col">Interval</th>
            </tr>
        </thead>
        <tbody>
<?php
    foreach($schedules as $id=>$schedule)
    {
?>
            <tr>
                <th scope="row"><?php echo esc_html( $schedule['display'] ); ?></th>
                <td><?php echo human_time_diff(0,(int)$schedule['interval']); ?></td>
            </tr>
<?php
    }
?>
        </tbody>
    </table>
</div>
<?php
}
function crony_view_logs ()
{
    require_once CRONY_DIR.'/wp-admin-ui/Admin.class.php';
    $columns = array('crony_id'=>array('label'=>'Cronjob','custom_display'=>'crony_cronjob_name','custom_view'=>'crony_cronjob_name'),'real_time'=>array('label'=>'Scheduled Time','type'=>'datetime'),'start'=>array('label'=>'Start Time','type'=>'datetime'),'end'=>array('label'=>'End Time','type'=>'datetime'));
    $view_columns = $columns;
    $view_columns['output'] = array('label'=>'Cronjob Output','custom_view'=>'crony_cronjob_output');
    $admin = new WP_Admin_UI(array('css'=>CRONY_URL.'/assets/admin.css','item'=>'Cronjob Log','items'=>'Cronjob Logs','table'=>CRONY_TBL.'logs','columns'=>$columns,'view_columns'=>$view_columns,'icon'=>CRONY_URL.'/assets/icons/32.png','readonly'=>true,'view'=>true));
    $admin->go();
}
function crony_cronjob_name ($id)
{
    global $wpdb;
    return @current($wpdb->get_col("SELECT `name` FROM `".CRONY_TBL."jobs` WHERE `id`=".$wpdb->_real_escape($id)));
}
function crony_cronjob_output ($output)
{
    if(0<strlen($output))
    {
        return '<textarea rows="10" cols="50">'.esc_textarea( $output ).'</textarea>';
    }
    return 'N/A';
}
function crony_get_job_name ($id) {
    global $wpdb;
    $row = @current($wpdb->get_results('SELECT `name` FROM `' . CRONY_TBL . 'jobs` WHERE `id`=' . $wpdb->_real_escape($id), ARRAY_A));
    if (false === $row)
        return '<em>Job not found</em>';
    return $row['name'];
}
function crony ($id) {
    global $wpdb;
    $row = @current($wpdb->get_results('SELECT * FROM `' . CRONY_TBL . 'jobs` WHERE `disabled`=0 AND `id`=' . $wpdb->_real_escape($id), ARRAY_A));
    if (false === $row)
        return false;
    $start = current_time('mysql');
    $real = $row['next_run'];
    ob_start();
    if (0 < strlen($row['url']) && false !== @parse_url($row['url']))
        echo wp_remote_retrieve_body( wp_remote_post($row['url'], array('sslverify' => apply_filters('https_local_ssl_verify', true))) );
    if (0 < strlen($row['script']))
        include_once $row['script'];
    if (0 < strlen($row['function']) && function_exists("{$row['function']}"))
        echo $row['function']();
    if (0 < strlen($row['phpcode'])) {
        eval('?>' . $row['phpcode']);
    }
    $output = ob_get_clean();
    $schedules = wp_get_schedules();
    $last_run = date_i18n('Y-m-d H:i:s');
    $next_run = date_i18n('Y-m-d H:i:s', current_time('timestamp') + $schedules[$row['schedule']]['interval']);
    $wpdb->query("UPDATE `" . CRONY_TBL . "jobs` SET `last_run` = '$last_run', `next_run` = '$next_run' WHERE `id`=" . $wpdb->_real_escape($id));
    if (!empty($row['email']))
        wp_mail($row['email'], '[' . get_bloginfo('sitename') . '] Cronjob Run: ' . $row['name'], 'The following was output (if any) from the cronjob <strong>' . $row['name'] . '</strong> that was run on ' . date_i18n('m/d/Y h:i:sa') . '<br /><br />' . "\r\n\r\n" . $output, "Content-Type: text/html");
    $end = current_time('mysql');
    crony_add_log($id, $output, $real, $start, $end);
    if (0 < strlen($output))
        return $output;
    return true;
}
function crony_schedules ($schedules) {
    if (!isset($schedules['twicehourly']))
        $schedules['twicehourly'] = array( 'interval' => 1800, 'display' => __('Twice Hourly') );
    if (!isset($schedules['weekly']))
        $schedules['weekly'] = array( 'interval' => 604800, 'display' => __('Once Weekly') );
    if (!isset($schedules['twiceweekly']))
        $schedules['twiceweekly'] = array( 'interval' => 302400, 'display' => __('Twice Weekly') );
    if (!isset($schedules['monthly']))
        $schedules['monthly'] = array( 'interval' => 2628002, 'display' => __('Once Monthly') );
    if (!isset($schedules['twicemonthly']))
        $schedules['twicemonthly'] = array( 'interval' => 1314001, 'display' => __('Twice Monthly') );
    if (!isset($schedules['yearly']))
        $schedules['yearly'] = array( 'interval' => 31536000, 'display' => __('Once Yearly') );
    if (!isset($schedules['twiceyearly']))
        $schedules['twiceyearly'] = array( 'interval' => 15768012, 'display' => __('Twice Yearly') );
    if (!isset($schedules['fouryearly']))
        $schedules['fouryearly'] = array( 'interval' => 7884006, 'display' => __('Four Times Yearly') );
    if (!isset($schedules['sixyearly']))
        $schedules['sixyearly'] = array( 'interval' => 5256004, 'display' => __('Six Times Yearly') );
    return apply_filters('crony_schedules',$schedules);
}

function crony_add_job ($args, $obj) {
    if ($obj[0]->table != CRONY_TBL . 'jobs')
        return false;
    if (!isset($args[3]) || false === $args[3] || !isset($args[2]) || empty($args[2]))
        return false;
    crony_remove_job($args, $obj);
    if ($args[2]['disabled'] == 1)
        return true;
    $timestamp = mysql2date('U', get_gmt_from_date($args[2]['next_run']));
    $recurrence = $args[2]['schedule'];
    return wp_schedule_event($timestamp, $recurrence, 'crony', array($args[1]));
}

function crony_remove_job ($args, $obj) {
    if ($obj[0]->table != CRONY_TBL . 'jobs')
        return false;

    $timestamp = false;
    $key = md5(serialize(array($args[1])));

    $schedules = _get_cron_array();
    foreach ($schedules as $ts => $schedule)
    {
        if (isset($schedule['crony']) && isset($schedule['crony'][$key])) {
            $timestamp = $ts;
            wp_unschedule_event($timestamp, 'crony', array($args[1]));
        }
    }

    if (false === $timestamp)
        return false;
    return true;
}

function crony_add_log ($id, $output, $real, $start, $end) {
    global $wpdb;

    // Drop any older logs
    $wpdb->query( $wpdb->prepare( "DELETE FROM `" . CRONY_TBL . "logs` WHERE `end` < %s", array( date_i18n( 'Y-m-d H:i:s', strtotime( '-2 weeks' ) ) ) ) );

    return $wpdb->query($wpdb->prepare("INSERT INTO `" . CRONY_TBL . "logs` (`crony_id`, `output`, `real_time`, `start`, `end`) VALUES (%d, %s, %s, %s, %s)", array($id, $output, $real, $start, $end)));
}

function crony_empty_log () {

    global $wpdb;
    return $wpdb->query("TRUNCATE `" . CRONY_TBL . "logs`");

}

function crony_reset () {

    global $wpdb;

	$tables = $wpdb->get_results( 'SHOW TABLES LIKE "' . CRONY_TBL . '%"', ARRAY_N );

	$remove_jobs = false;

	// Force reset if tables don't exist
	if ( 2 == count( $tables ) ) {
		$remove_jobs = true;
	} elseif ( ! empty( $tables[ 0 ][ 0 ] ) && CRONY_TBL . 'jobs' == $tables[ 0 ][ 0 ] ) {
		$remove_jobs = true;
	}

	// Get current crons
	$schedules = _get_cron_array();

	// Remove Existing Jobs
	if ( $remove_jobs ) {
		$jobs = $wpdb->get_col( 'SELECT `id` FROM `' . CRONY_TBL . 'jobs`' );

		foreach ( $jobs as $job ) {
			$key = md5( serialize( array( $job ) ) );
			foreach ( $schedules as $ts => $schedule ) {
				if ( isset( $schedule[ 'crony' ] ) && isset( $schedule[ 'crony' ][ $key ] ) ) {
					wp_unschedule_event( $ts, 'crony', array( $job ) );
				}
			}
		}

		$wpdb->query( "DROP TABLE IF EXISTS `" . CRONY_TBL . "jobs`" );
		$wpdb->query( "DROP TABLE IF EXISTS `" . CRONY_TBL . "logs`" );
	} else {
		// Remove general crony events
		foreach ( $schedules as $ts => $schedule ) {
			if ( isset( $schedule[ 'crony' ] ) ) {
				wp_unschedule_event( $ts, 'crony' );
			}
		}
	}

	// Delete option
	delete_option( 'crony_version' );

	crony_install_update();

}