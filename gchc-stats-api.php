<?php
/*
    Plugin Name: GCHC Stats API
    Plugin URI: https://healthcaresuccess.com
    Description: Display resident and staff statistics
    Author: Eddie Olivas
    Version: 1.0
    Author URI: https://healthcaresuccess.com
*/
 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Upon plugin activation create a row in the wp_options table for the location
register_activation_hook( __FILE__, 'gchc_stats_activation' );
function gchc_stats_activation() {
    if ( !get_option('covid_stats_HTML') ) {
        add_option('covid_stats_HTML', '', false);
    }
    if ( !get_option('gchc_stats_location_id') ) {
        add_option( 'gchc_stats_location_id', '', false);
    }

    check_stats_for_updates();
}

// Upon plugin deactivation delete wp_options table for each location
register_deactivation_hook( __FILE__, 'gchc_stats_deactivation' );
function gchc_stats_deactivation() {
    delete_option('covid_stats_HTML');
    delete_option('gchc_stats_location_id');
}

// Shortcode [show_stats id=LOCATION_ID] pulls the HTML from the options table for the location specified in the id
function stats_api_func( $atts ) {
    $response = get_option('covid_stats_HTML');
    return $response;
}
add_shortcode( 'show_stats', 'stats_api_func' );

// Connects to the stats API Node app to check for updated stats
add_action('gchc_check_stats', 'check_stats_for_updates');
function check_stats_for_updates() {
    $locationId = get_option('gchc_stats_location_id');
    $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJfaWQiOiI1ZWJlZTkzMmZlMjk2MzJkOGRjY2JjMWQiLCJpYXQiOjE1ODk1Njk4NDJ9.dzIUdzSRuiPJZLordCmcvqaJi3_qCfyy11_lO9rltfg';
    $options = array('http' => array(
        'method'  => 'GET',
        'header' => 'Authorization: Bearer '.$token
    ));
    $context  = stream_context_create($options);

    $api_url = 'https://gchc-stats-api.herokuapp.com/locations/' . $locationId;
    $response = file_get_contents($api_url, false, $context);

    if ( $response !== get_option('covid_stats_HTML') ) {
        update_option('covid_stats_HTML', $response, false);
        // This might not be necessary
        wp_cache_flush();
    }
}

// Add an every 30 minute schedule to the cron schedules
add_filter( 'cron_schedules', 'add_thirty_minute_cron_interval' );
function add_thirty_minute_cron_interval( $schedules ) { 
    $schedules['thirty_minutes'] = array(
        'interval' => 1800,
        'display'  => esc_html__( 'Every Thirty minutes' ), );
    return $schedules;
}

// If there's no scheduled cron event to check the stats, schedule one in 30 minutes
if ( ! wp_next_scheduled( 'gchc_check_stats' ) ) {
    wp_schedule_event( time(), 'thirty_minutes', 'gchc_check_stats' );
}

function gchc_stats_register_settings() {
    register_setting( 'gchc_stats_options_group', 'gchc_stats_location_id' );
 }
 add_action( 'admin_init', 'gchc_stats_register_settings' );

 function gchc_stats_register_options_page() {
    add_options_page('GCHC Stats Options', 'GCHC Stats', 'manage_options', 'gchc_stats', 'gchc_stats_options_page');
  }
  add_action('admin_menu', 'gchc_stats_register_options_page');

function gchc_stats_options_page() {
?>
    <div>
    <?php screen_icon(); ?>
    <h2>GCHC Stats Options</h2>
    <form method="post" action="options.php">
    <?php settings_fields( 'gchc_stats_options_group' ); ?>
    <p>Please enter the location options here.</p>
    <table>
    <tr valign="top">
    <th scope="row"><label for="gchc_stats_location_id">Location ID</label></th>
    <td><input type="text" id="gchc_stats_location_id" name="gchc_stats_location_id" value="<?php echo get_option('gchc_stats_location_id'); ?>" /></td>
    </tr>
    </table>
    <?php  submit_button(); ?>
    </form>
    </div>
    <?php
}

// When the location ID is updated run check_stats_for_updates
add_action('update_option_gchc_stats_location_id', 'check_stats_for_updates');