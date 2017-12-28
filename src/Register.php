<?php

namespace WPQueueTasks;
/**
 * Class Register
 */
class Register {

	/**
	 * Sets up all of the functionality to run in the proper hook
	 */
	public function run() {

		// Registers the "queue" taxonomy
		add_action( 'init', [ $this, 'register_taxonomy' ] );

		// Registers the "task" post type
		add_action( 'init', [ $this, 'register_post_type' ] );

		// Process the queue
		add_action( 'shutdown', [ $this, 'process_queue' ], 999 );

	}

	/**
	 * Registers the post-queue taxonomy
	 *
	 * @access public
	 * @return void
	 */
	public function register_taxonomy() {

		$labels = [
			'name'                       => __( 'Task queues', 'wp-queue-tasks' ),
			'singular_name'              => _x( 'Task queue', 'taxonomy general name', 'wp-queue-tasks' ),
			'search_items'               => __( 'Search task queues', 'wp-queue-tasks' ),
			'popular_items'              => __( 'Popular task queues', 'wp-queue-tasks' ),
			'all_items'                  => __( 'All task queues', 'wp-queue-tasks' ),
			'parent_item'                => __( 'Parent task queue', 'wp-queue-tasks' ),
			'parent_item_colon'          => __( 'Parent task queue:', 'wp-queue-tasks' ),
			'edit_item'                  => __( 'Edit task queue', 'wp-queue-tasks' ),
			'update_item'                => __( 'Update task queue', 'wp-queue-tasks' ),
			'add_new_item'               => __( 'New task queue', 'wp-queue-tasks' ),
			'new_item_name'              => __( 'New task queue', 'wp-queue-tasks' ),
			'separate_items_with_commas' => __( 'Separate task queues with commas', 'wp-queue-tasks' ),
			'add_or_remove_items'        => __( 'Add or remove task queues', 'wp-queue-tasks' ),
			'choose_from_most_used'      => __( 'Choose from the most used task queues', 'wp-queue-tasks' ),
			'not_found'                  => __( 'No task queues found.', 'wp-queue-tasks' ),
			'menu_name'                  => __( 'Task queues', 'wp-queue-tasks' ),
		];

		$args = [
			'hierarchical'          => false,
			'public'                => true,
			'show_in_nav_menus'     => true,
			'show_ui'               => true,
			'show_admin_column'     => false,
			'query_var'             => true,
			'rewrite'               => false,
			'labels'                => $labels,
			'capabilities'          => [
				'manage_terms' => 'read',
				'edit_terms'   => 'read',
				'delete_terms' => 'read',
				'assign_terms' => 'read',
			],
			'show_in_rest'          => true,
			'rest_base'             => 'task-queue',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'show_in_graphql'       => true,
			'graphql_single_name'   => 'queue',
			'graphql_plural_name'   => 'queues',
		];

		register_taxonomy( 'task-queue', 'wpqt-task', $args );

	}

	/**
	 * Registers the task post type
	 *
	 * @access public
	 * @return void
	 */
	public function register_post_type() {

		$labels = [
			'name'               => __( 'Queue Tasks', 'wp-queue-tasks' ),
			'singular_name'      => __( 'Queue Task', 'wp-queue-tasks' ),
			'all_items'          => __( 'All Queue Tasks', 'wp-queue-tasks' ),
			'new_item'           => __( 'New Queue Task', 'wp-queue-tasks' ),
			'add_new'            => __( 'Add New', 'wp-queue-tasks' ),
			'add_new_item'       => __( 'Add New Queue Task', 'wp-queue-tasks' ),
			'edit_item'          => __( 'Edit Queue Task', 'wp-queue-tasks' ),
			'view_item'          => __( 'View Queue Task', 'wp-queue-tasks' ),
			'search_items'       => __( 'Search Queue Tasks', 'wp-queue-tasks' ),
			'not_found'          => __( 'No Queue Tasks found', 'wp-queue-tasks' ),
			'not_found_in_trash' => __( 'No Queue Tasks found in trash', 'wp-queue-tasks' ),
			'parent_item_colon'  => __( 'Parent Queue Task', 'wp-queue-tasks' ),
			'menu_name'          => __( 'Queue Tasks', 'wp-queue-tasks' ),
		];

		$args = [
			'labels'                => $labels,
			'public'                => true,
			'hierarchical'          => false,
			'show_ui'               => false,
			'show_in_nav_menus'     => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'show_in_menu'          => true,
			'supports'              => [ 'title', 'editor' ],
			'has_archive'           => false,
			'rewrite'               => false,
			'query_var'             => true,
			'capabilities'          => [
				'edit_posts'    => 'read',
				'publish_posts' => 'read',
			],
			'show_in_rest'          => true,
			'rest_base'             => 'wpqt-task',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'show_in_graphql'       => true,
			'graphql_single_name'   => 'task',
			'graphql_plural_name'   => 'tasks',
		];

		register_post_type( 'wpqt-task', $args );

	}

	/**
	 * Queries all of the queue's and decides if we should process them. If the queue needs to be process
	 * it will post a request to process them asynchronously. We are processing the queue async, because it
	 * will give us a fresh thread to do it, and avoid timeouts.
	 *
	 * @access public
	 * @return void
	 */
	public function process_queue() {

		$queues = get_terms( [ 'taxonomy' => 'task-queue' ] );
		global $wpqt_queues;

		if ( ! empty( $queues ) && is_array( $queues ) ) {
			foreach ( $queues as $queue ) {

				// If the term has no associated queue, bail.
				if ( empty( $wpqt_queues[ $queue->name ] ) ) {
					continue;
				}

				// If the queue is already being processed, bail.
				if ( false !== self::is_queue_process_locked( $queue->name ) ) {
					continue;
				}

				// If the queue doesn't have enough items, or is set to process at a certain interval, bail.
				if ( false === $this->should_process( $queue->name, $queue->term_id, $queue->count ) ) {
					continue;
				}

				// Lock the queue process so another process can't pick it up.
				// The queue will be unlocked in Processor::process_queue
				self::lock_queue_process( $queue->name );

				if ( ! empty( $wpqt_queues[ $queue->name ] ) && 'async' === $wpqt_queues[ $queue->name ]->processor ) {
					// Post to the async task handler to process this specific queue
					$this->post_to_processor( $queue->name, $queue->term_id );
				} else {
					$this->schedule_cron( $queue->name, $queue->term_id );
				}

			}
		}

	}

	/**
	 * Determines whether or not the queue should be processed
	 *
	 * @param string $queue_name Name of the queue being processed
	 * @param int $queue_id Term ID for the queue being processed
	 * @param int $queue_count The amount of tasks attached to the queue
	 *
	 * @access private
	 * @return bool
	 */
	private function should_process( $queue_name, $queue_id, $queue_count ) {

		global $wpqt_queues;

		$current_queue_settings = $wpqt_queues[ $queue_name ];

		// If there aren't enough items in this queue, bail.
		if ( $current_queue_settings->minimum_count > $queue_count ) {
			return false;
		}

		// Check to see if the queue has an update interval, and compare it to the current time to see if it's
		// time to run again.
		if ( false !== $current_queue_settings->update_interval ) {
			$last_ran = get_term_meta( $queue_id, 'wpqt_queue_last_run', true );
			if ( '' !== $last_ran && ( $last_ran + $current_queue_settings->update_interval ) > time() ) {
				return false;
			}
		}

		return true;

	}

	/**
	 * Handle the post request to the async handler
	 *
	 * @param string $queue_name Name of the queue to process
	 * @param int $queue_id Term ID of the queue to process
	 *
	 * @access private
	 * @return void
	 */
	private function post_to_processor( $queue_name, $queue_id ) {

		$request_args = [
			'timeout' => 0.01,
			'blocking' => false,
			'body' => [
				'action' => 'wpqt_process_' . $queue_name,
				'queue_name' => $queue_name,
				'term_id' => $queue_id,
			],
		];

		$url = admin_url( 'admin-post.php' );
		wp_safe_remote_post( $url, $request_args );

	}

	private function schedule_cron( $queue_name, $queue_id ) {
		wp_schedule_single_event( time(), 'wpqt_run_processor', [ 'queue_name' => $queue_name, 'term_id' => $queue_id ] );
	}

	/**
	 * Sets a transient to prevent a queue from being updated, it it's already being processed.
	 * Using transients, because they are backed my memcache, therefore faster to read/write to.
	 *
	 * @uses set_transient()
	 *
	 * @param string $queue_name Name of the queue to set a lock for
	 * @access public
	 * @return void
	 */
	public static function lock_queue_process( $queue_name ) {
		// Set the expiration to 5 minutes, just in case something goes wrong processing the queue,
		// it doesn't just stay locked forever.
		set_transient( 'wpqt_queue_lock_' . $queue_name, 'locked', 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Delete's the queue lock transient to allow other processes to process the queue
	 *
	 * @uses delete_transient()
	 *
	 * @param string $queue_name Name of the queue to unlock
	 * @access public
	 * @return void
	 */
	public static function unlock_queue_process( $queue_name ) {
		delete_transient( 'wpqt_queue_lock_' . $queue_name );
	}

	/**
	 * Checks to see if the queue is already being processed
	 *
	 * @uses get_transient()
	 *
	 * @param string $queue_name Name of the queue to check for a lock
	 * @access public
	 * @return mixed
	 */
	public static function is_queue_process_locked( $queue_name ) {
		return get_transient( 'wpqt_queue_lock_' . $queue_name );
	}

}
