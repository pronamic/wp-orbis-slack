<?php
/*
Plugin Name: Orbis Slack
Plugin URI: https://www.pronamic.eu/plugins/orbis-slack/
Description: Orbis Slack integrates Orbis with Slack.

Version: 1.0.0
Requires at least: 3.0

Author: Pronamic
Author URI: https://www.pronamic.eu/

Text Domain: orbis
Domain Path: /languages/

License: GPL

GitHub URI: https://github.com/wp-orbis/wp-orbis-slack
*/

class OrbisSlackPlugin {
	public function __construct( $file ) {
		$this->file = $file;
	}

	public function setup() {
		// Actions
		add_action( 'init', array( $this, 'init' ) );

		add_action( 'orbis_slack_notify', array( $this, 'send_projects_notifications' ) );

		add_filter( 'slack_get_events', array( $this, 'slack_get_events' ) );
	}

	public function init() {
		if ( ! wp_next_scheduled( 'orbis_slack_notify' ) ) {
			$timestamp = get_gmt_from_date( date( 'Y-m-d H:i:s', strtotime( '10:00' ) ), 'U' );

			wp_schedule_event( $timestamp, 'daily', 'orbis_slack_notify' );
		}

		if ( filter_has_var( INPUT_GET, 'orbis_slack_test' ) ) {
			$this->test();
		}
	}

	public function test() {
		$query = new WP_Query( array(
			'post_type'                 => 'orbis_project',
			'posts_per_page'            => 10,
			'orbis_project_is_finished' => false,
			'orderby'                   => 'last_comment_date',
			'order'                     => 'ASC',
			'author__not_in'            => array(
				19,
			),
			'date_query'                => array(
				array(
					'column' => 'last_comment_date',
					'before' => '1 week ago',
				),
			),
			'meta_query'                => array(
				array(
					'key'     => '_orbis_project_is_finished',
					'compare' => 'NOT EXISTS'
				),
			),
		) );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				do_action( 'orbis_post_requires_comment', \get_post() );
			}
		}			
	}
	/**
	 * Slack events.
	 *
	 * @see http://gedex.web.id/wp-slack/
	 * @see https://github.com/gedex/wp-slack/blob/0.5.1/includes/event-manager.php#L57-L167
	 * @see https://github.com/gedex/wp-slack-edd/blob/0.1.0/slack-edd.php
	 */
	public function slack_get_events( $events ) {
		$events['orbis_post_requires_comment'] = array(
			'action'      => 'orbis_post_requires_comment',
			'description' => __( 'When an Orbis post requires a comment.', 'orbis-slack' ),
			'message'     => function( $post ) {
				$message = sprintf(
					__( 'Orbis post <%s|%s> requires a comment.', 'orbis-slack' ),
					get_permalink( $post ),
					get_the_title( $post )
				);

				return $message;
			},
		);

		return $events;
	}

	public function send_projects_notifications() {
		$this->test();
	}
}

global $orbis_slack_plugin;

$orbis_slack_plugin = new OrbisSlackPlugin( __FILE__ );

$orbis_slack_plugin->setup();
