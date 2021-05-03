<?php
/*
  Plugin Name: Fdmedia Dev Test
  Plugin URI: #
  Description: Display data about wordpress specific plugin.
  Version: 1.0
  Author: Melberth Bontilao
  License: GPLv2+
  Text Domain: fdmedia-dev-test
*/

if ( !defined( 'ABSPATH' ) ) exit;

define( 'FDT_URL', plugin_dir_url( __FILE__ ) );
define( 'FDT_DIR', plugin_dir_path( __FILE__ ) );

add_action('plugins_loaded', 'fdmedia_init', 0);

function fdmedia_init() {
	FdmediaDevTest::instance();
}

register_activation_hook( __FILE__, array( FdmediaDevTest::instance(), 'fdmedia_activate' ) );
register_deactivation_hook( __FILE__, array( FdmediaDevTest::instance(), 'fdmedia_deactivate' ) );

class FdmediaDevTest {
	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'fdmedia-dev-test' ), '1.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'fdmedia-dev-test' ), '1.0' );
	}

	public function __construct()
	{	
		// Scheduling
		add_filter('cron_schedules', array($this, 'fdmedia_cron_schedules'));
		add_action( 'fdmedia_cron_10mins', array($this, 'fdmedia_store_api_data') );

		// Shortcode
		// [fdmedia_dev_test slug=yourvalue]
		add_shortcode( 'fdmedia_dev_test', array($this, 'fdmedia_get_data') );

		// CSS/JS Enqueue Scripts
		add_action( 'wp_enqueue_scripts', array($this, 'fdmedia_enque_scripts') );

		// For checking purposes
		// URL yourdomain.com/wp-admin/admin-ajax.php?action=fdmedia_check_fetch
		add_action("wp_ajax_fdmedia_check_fetch", array($this, 'fdmedia_check_api_fetch'));
		add_action("wp_ajax_nopriv_fdmedia_check_fetch", array($this, 'fdmedia_check_api_fetch'));

		// URL yourdomain.com/wp-admin/admin-ajax.php?action=fdmedia_check_save
		add_action("wp_ajax_fdmedia_check_save", array($this, 'fdmedia_store_api_data'));
		add_action("wp_ajax_nopriv_fdmedia_check_save", array($this, 'fdmedia_store_api_data'));
	}

	public function fdmedia_activate()
	{
		if ( ! wp_next_scheduled( 'fdmedia_cron_10mins' ) ) {
		    wp_schedule_event( time(), '10min', 'fdmedia_cron_10mins' );
		}
	}

	public function fdmedia_deactivate()
	{
		if( wp_next_scheduled( 'fdmedia_cron_10mins' ) ){
	        wp_clear_scheduled_hook( 'fdmedia_cron_10mins' );
	    }
	}

	public function fdmedia_cron_schedules($schedules){
		if(!isset($schedules["10min"])){
			$schedules["10min"] = array(
				'interval' => 10*60,
				'display' => __('Once every 10 minutes')
			);
		}
		return $schedules;
	}

	public function fdmedia_enque_scripts()
	{
		// Styles
		wp_enqueue_style( 'fdmedia-style', FDT_URL . 'assets/css/fdmedia-style.css' );

		// JS SCripts
		wp_enqueue_script( 'fdmedia_script', FDT_URL . 'assets/js/fdmedia-scripts.js', array(), '1.0.0', true );
		wp_enqueue_script( 'fdmedia_script' );
		wp_localize_script( 'fdmedia_script', 'fdmedia_object', array( 'ajax_url' => admin_url('admin-ajax.php')) );
	}

	public function fdmedia_check_api_fetch()
	{
		$slug = $_REQUEST['slug'];
		$fdmedia_data = get_transient( $slug );
		$data = json_decode( $fdmedia_data );

		echo "<pre>";
		print_r($data);
		echo "</pre>";
		exit;
	}

	public function fdmedia_store_api_data()
	{
		$request = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/affiliate-coupons.json' );
		if( is_wp_error( $request ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body );

		set_transient( $data->slug, $body, 10 * MINUTE_IN_SECONDS );
		exit;
	}

	public function fdmedia_get_data($atts)
	{
		$att = shortcode_atts( array(
			'slug' => 'fdmedia-dev-test',
		), $atts );

		$fdmedia_data = get_transient( $att['slug'] );
		$data = json_decode( $fdmedia_data );

		ob_start();

		?>
		<div id="fdmedia-box">
		<?php
		if(!empty($data)) {

			$pluginname = !empty($data->name)?$data->name:'no name';
			$pluginurl = !empty($data->homepage)?$data->homepage:'no url';
			$pluginversion = !empty($data->version)?$data->version:'0.0';
			$pluginratings = !empty($data->ratings)?$data->ratings:array();

			$ratings = 0;
			if(!empty($pluginratings)) {
				$total = 0;
				$i = 0;
				foreach ($pluginratings as $pluginrating) {
					$total += $pluginrating;
					$i++;
				}
				$ratings = round( $total / $i, 1 );
			}
		?>
			<div class="fdmedia-basic-info">
				<p><strong>Plugin Name: </strong> <?php echo $pluginname; ?></p>
				<p><strong>Plugin URL: </strong> <?php echo $pluginurl; ?></p>
				<p><strong>Plugin Version: </strong> <?php echo $pluginversion; ?></p>
				<p><strong>Current Rating: </strong> <?php echo $ratings; ?></p>
			</div>
			<p><strong>Screenshots</strong></p>
			<div class="fdmedia-images">
				<?php 
				if(!empty($data->screenshots)) {
					foreach ($data->screenshots as $screenshot) {
				?>
				<a href="<?php echo $screenshot->src; ?>"><img src="<?php echo $screenshot->src; ?>" alt="<?php echo $screenshot->caption; ?>" /></a>
				<?php
					}
				}
				?>
			</div>
		<?php
		} else {
		?>
			<div class="fdmedia-message">
				<p>This plugin is not exists. Please try again!</p>
			</div>
		<?php
		}
		?>
		</div>
		<?php

		$output_string = ob_get_contents();
		ob_end_clean();
		return $output_string;
	}
}