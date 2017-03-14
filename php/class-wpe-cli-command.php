<?php

class WPE_CLI_Command extends WP_CLI_Command {

	protected $base_url = 'https://my.wpengine.com/installs';

	/**
	 * Runs a wp-cli command on WP Engine installs.
	 *
	 * Documentation is incomplete as I'm currently fighting with
	 * the PHPDoc parser to allow unlimited arguments
	 *
	 * ## EXAMPLES
	 *
	 *     # Get the WordPress version of your install
	 *     $ wp wpe cli myinstall core version
	 *     Success: 4.7.2
	 *
	 *     # Update all of your install's plugins on staging
	 *     $ wp wpe cli myinstall theme update --all --staging
	 *     Success: Updated 1 of 3 themes
	 *
	 * @when after_wp_load
	 */
	public function cli( $args, $assoc_args ) {
		$install = array_shift( $args );

		$environment = \WP_CLI\Utils\get_flag_value( $assoc_args, 'staging' ) ? 'staging' : 'production';

		unset( $assoc_args['staging'] );

		if ( empty( $args ) ) {
			WP_CLI::error( 'Please provide a command to execute' );
		}

		// rebuild the user's added command
		$command  = '';
		$command .= \WP_CLI\Utils\args_to_str( $args );
		$command .= \WP_CLI\Utils\assoc_args_to_str( $assoc_args );

		$url = "{$this->base_url}/{$install}/wp_cli?environment={$environment}";

		$post_args = array(
			'body' => array(
				'command' => $command,
				),
			);

		$res = $this->send_post_request( $url, $post_args );

		$json_res = json_decode( $res['body'] );

		if ( $json_res && ! empty( $json_res->response ) ) {
			WP_CLI::log( $json_res->response );
		}
	}

	/**
	 * Clear the cache on a WP Engine install
	 *
	 * ## OPTIONS
	 *
	 * <install>
	 * : The WP Engine install to run the command on.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear the cache on myinstall production site
	 *     $ wp wpe flush myinstall
	 *     Success: Cache flushed!
	 *
	 * @when after_wp_load
	 * @alias clear-cache
	 */
	public function flush( $args, $assoc_args ) {
		$install = array_shift( $args );

		$url = "{$this->base_url}/{$install}/utilities/clear_cache";

		$this->send_post_request( $url );

		WP_CLI::success( 'Cache flushed!' );
	}

	/**
	 * Trigger a backup on my WP Engine install
	 *
	 * ## OPTIONS
	 *
	 * <install>
	 * : The WP Engine install to run the command on.
	 *
	 * [--staging]
	 * : Run the command on the staging environment.
	 *
	 * [--message=<value>]
	 * : The description to give to the backup.
	 *
	 * [--emails=<value>]
	 * : A comma separated list of emails that should be notified when the backup completes.
	 *
	 * ## EXAMPLES
	 *
	 *     # Trigger a backup of my install
	 *     $ wp wpe backup myinstall
	 *     Success: Backup triggered! This can take a while! You will be notified at ryan.hoover@wpengine.com when the checkpoint has completed.
	 *
	 *     # Trigger a backup of my staging site with a custom message and emails to alert
	 *     $ wp wpe backup myinstall --staging --message="Backing up staging" --emails="thisis@me.com, thatis@you.com"
	 *     Success: Backup triggered! This can take a while! You will be notified at ryan.hoover@wpengine.com when the checkpoint has completed.
	 *
	 * @when after_wp_load
	 * @alias checkpoint-create
	 */
	public function backup( $args, $assoc_args ) {
		$install = array_shift( $args );

		$environment = \WP_CLI\Utils\get_flag_value( $assoc_args, 'staging' ) ? 'staging' : 'production';

		$url = "{$this->base_url}/{$install}/backup_points";

		$post_args = array(
			'body' => array(
				'checkpoint' => array(
					'environment' => $environment,
					'comment' => \WP_CLI\Utils\get_flag_value( $assoc_args, 'message', 'Triggered by wpe-cli' ),
					'notification_emails' => \WP_CLI\Utils\get_flag_value( $assoc_args, 'emails' ),
					),
				'commit' => "Create {$environment} backup",
				),
			);

		$this->send_post_request( $url, $post_args );

		WP_CLI::success( 'Backup triggered! This can take a while! You will be notified at ryan.hoover@wpengine.com when the checkpoint has completed.' );
	}

	protected function get_default_post_args() {

		$config = $this->get_config();

		$cookies = array();

		$cookies[] = new WP_Http_Cookie( [ 'name' => '__ar_v4', 'value' => $config['ar_v4'] ] );
		$cookies[] = new WP_Http_Cookie( [ 'name' => '_session_id', 'value' => $config['session_id'] ] );

		$post_args = array(
			'timeout' => 30,
			'headers' => array(
				'X-CSRF-Token' => $config['token'],
				),
			'cookies' => $cookies,
			);

		return $post_args;
	}

	protected function send_post_request( $url, $post_args = array() ) {
		$post_args = array_merge( $this->get_default_post_args(), $post_args );

		$res = wp_remote_post( $url, $post_args );

		// If the response is a WP_ERROR, then something went very wrong
		if ( is_wp_error( $res ) ) {
			WP_CLI::error( 'Something went wrong' . PHP_EOL . print_r( $res, true ) );
			return;
		}

		// If the response code is not in the 200s, something went wrong
		if ( 300 <= $res['response']['code'] ) {
			WP_CLI::error( 'Got an invalid response: ' . $res['response']['code'] . ' ' . $res['response']['message'] );
			return;
		}

		return $res;
	}

	protected function get_config() {
		$full_config = \WP_CLI::get_configurator()->to_array();

		$config = ! empty( $full_config[1]['wpe-cli'] ) ? $full_config[1]['wpe-cli'] : [];

		if ( empty( $config ) ) {
			WP_CLI::error( 'Please set the wpe-cli config values in your config.yml file' );
		}

		return $config;
	}
}

WP_CLI::add_command( 'wpe', 'WPE_CLI_Command' );
