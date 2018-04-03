<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Email notification to administrator when a new job is submitted.
 *
 * @package wp-job-manager
 * @since 1.31.0
 * @extends WP_Job_Manager_Email
 */
class WP_Job_Manager_Email_Admin_New_Job extends WP_Job_Manager_Email {
	/**
	 * Get the email subject.
	 *
	 * @return string
	 */
	public function get_subject() {
		$args = $this->get_args();

		/**
		 * @var WP_Post $job
		 */
		$job = $args['job'];
		return sprintf( __( 'New Job Listing Submitted: %s', 'wp-job-manager' ), $job->post_title );
	}

	/**
	 * Get `From:` address header value. Can be simple email or formatted `Firstname Lastname <email@example.com>`.
	 *
	 * @return string|bool Email from value or false to use WordPress' default.
	 */
	public function get_from() {
		return false;
	}

	/**
	 * Get array or comma-separated list of email addresses to send message.
	 *
	 * @return string|array
	 */
	public function get_to() {
		return get_option( 'admin_email', false );
	}

	/**
	 * Get the rich text version of the email content.
	 *
	 * @return string
	 */
	public function get_rich_content() {
		// TODO: Implement get_rich_content() method.
	}

	/**
	 * Checks the arguments and returns whether the email notification is properly set up.
	 *
	 * @return bool
	 */
	public function is_valid() {
		$args = $this->get_args();
		return isset( $args['job'] )
				&& $args['job'] instanceof WP_Post
				&& $this->get_to();
	}

}
