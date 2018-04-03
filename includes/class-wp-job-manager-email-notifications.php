<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Base class for WP Job Manager's email notification system.
 *
 * @package wp-job-manager
 * @since 1.31.0
 */
class WP_Job_Manager_Email_Notifications {
	/**
	 * @var array
	 */
	private static $deferred_notifications = array();

	/**
	 * Sets up initial hooks.
	 *
	 * @since  1.31.0
	 * @static
	 */
	public static function init() {
		add_action( 'job_manager_send_notification', array( __CLASS__, 'schedule_notification' ), 10, 2 );
		add_action( 'shutdown', array( __CLASS__, 'send_deferred_notifications' ) );
	}

	/**
	 * Gets list of email notifications handled by WP Job Manager core.
	 * @return array
	 */
	private static function core_email_notifications() {
		return array(
			'admin_notice_new_listing' => array(
				'class'             => 'WP_Job_Manager_Email_Admin_New_Job',
				'name'              => __( 'Admin Notice of New Listing', 'wp-job-listing' ),
				'default_enabled'   => true,
			),
		);
	}

	/**
	 * Sets up an email notification to be sent at the end of the script's execution.
	 *
	 * @param string $notification
	 * @param array  $args
	 */
	final public static function schedule_notification( $notification, $args = array() ) {
		self::$deferred_notifications[] = array( $notification, $args );
	}

	/**
	 * Sends all notifications collected during execution.
	 *
	 * Do not call manually.
	 *
	 * @access private
	 */
	final public static function send_deferred_notifications() {
		$email_notifications = self::get_email_notifications( true );

		foreach ( self::$deferred_notifications as $email ) {
			if (
				! is_string( $email[0] )
				|| ! isset( $email_notifications[ $email[0] ] )
			) {
				continue;
			}

			$class_name = $email_notifications[ $email[0] ]['class'];
			$email_args = is_array( $email[1] ) ? $email[1] : array();

			self::send_email( $email[0], new $class_name( $email_args ) );
		}
	}

	/**
	 * Gets a list of all email notifications that WP Job Manager handles.
	 *
	 * @param bool $enabled_notifications_only
	 * @return array
	 */
	final public static function get_email_notifications( $enabled_notifications_only = false ) {
		/**
		 * Retrieves all email notifications to be sent.
		 *
		 * @since 1.31.0
		 *
		 * @param array $email_notifications All the email notifications to be registered.
		 */
		$email_notifications = apply_filters( 'job_manager_email_notifications', self::core_email_notifications() );

		foreach ( $email_notifications as $email_key => $email_config ) {
			if (
				! self::is_email_notification_valid( $email_key, $email_config )
				|| ( $enabled_notifications_only && ! self::is_email_notification_enabled( $email_key ) )
			) {
				unset( $email_notifications[ $email_key ] );
			}
		}
		return $email_notifications;
	}

	/**
	 * Confirms an email notification's configuration is valid.
	 *
	 * @access private
	 *
	 * @param string $email_key
	 * @param array  $email_config
	 * @return bool
	 */
	private static function is_email_notification_valid( $email_key, $email_config ) {
		return is_string( $email_key )
				&& is_array( $email_config )
				&& isset( $email_config['class'] )
				&& isset( $email_config['name'] )
				&& is_string( $email_config['class'] )
				&& class_exists( $email_config['class'] )
				&& is_subclass_of( $email_config['class'], 'WP_Job_Manager_Email' );
	}

	/**
	 * Checks if a particular notification is enabled or not.
	 *
	 * @access private
	 *
	 * @param string $email_notification_key
	 * @return bool
	 */
	private static function is_email_notification_enabled( $email_notification_key ) {
		// @todo To be implemented.
		return true;
	}

	/**
	 * Sends an email notification.
	 *
	 * @access private
	 *
	 * @param string               $email_notification_key
	 * @param WP_Job_Manager_Email $email
	 * @return bool
	 */
	private static function send_email( $email_notification_key, WP_Job_Manager_Email $email ) {
		if ( ! $email->is_valid() ) {
			return false;
		}

		$fields = array( 'to', 'from', 'subject', 'rich_content', 'plain_content', 'attachments' );
		$args = array();
		foreach ( $fields as $field ) {
			$method = 'get_' . $field;
			$args[ $field ] = apply_filters( 'job_manager_email_' . $email_notification_key . '_' . $field, $email->$method() );
		}

		$headers = array();
		if ( ! empty( $args['from'] ) ) {
			$headers[] = 'From: ' . $args['from'];
		}

		if ( ! self::send_as_plain_text() ) {
			$headers[] = 'Content-Type: text/html';
		}

		$content = self::get_email_content( $email_notification_key, $args );


		return wp_mail( $args['to'], $args['subject'], $content, $headers, $args['attachments'] );
	}

	private static function get_email_content( $email_notification_key, $args ) {
		$plain_text = self::send_as_plain_text();

		ob_start();

		do_action( 'job_manager_email_header', $email_notification_key, $args, $plain_text );

		if ( $plain_text ) {
			echo wptexturize( $args['plain_content'] );
		} else {
			echo wpautop( wptexturize( $args['rich_text'] ) );
		}

		do_action( 'job_manager_email_footer', $email_notification_key, $args, $plain_text );

		$content = ob_get_clean();
		return $content;
	}

	/**
	 * Checks if we should send emails using plain text.
	 *
	 * @return bool
	 */
	private static function send_as_plain_text() {
		return apply_filters( 'job_manager_emails_send_as_plain_text', false );
	}
}