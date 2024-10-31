<?php

namespace NMGR\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Schedule an event to run automatically using wp_cron.
 * How to use:
 * - Extend the class with the class of the event to run
 * - Instantiate the class when wp loads by calling the init() method.
 * - Also when wp loads or in a specific wp action, execute the run() method to manually execute the event.
 * - If the event executes automatically, no need to call the run() method.
 *
 * -- In the child class:
 * --- (prefix) IMPORTANT: set the prefix
 * --- (recurrence) If the task is recurring e.g. hourly or daily, set the $recurrence property.
 * --- (timestamp) If you want to set a custom time when the task should be first run, set the $timestamp property.
 * --- (task) IMPORTANT: declare the task() method to define the task that should be run during the cron
 *     on each item.
 * --- (get_batch_data) Declare the get_batch_data() method to collate the 'list of items'
 *     that will be looped through for task() to run on each of them.
 */
abstract class Scheduler {

	/**
	 * Start time of current process.
	 */
	private $start_time = 0;

	/**
	 * Unique prefix to identify all events for this plugin
	 * (Important to set to prevent conflicts with events scheduled by other plugins with the same name as this)
	 * @var string
	 */
	protected $prefix;

	/**
	 * Unique hook to identify the specific event defined by the child class.
	 * (Auto) The class sets this automatically so no need to set it except if you want.
	 * @var string
	 */
	protected $hook;

	/**
	 * When you want the event to first run. Default is current time (time())
	 * (Auto) The class sets this automatically so no need to set it  except if you want.
	 * @var int
	 */
	protected $timestamp;

	/**
	 * How frequently the event should be run e.g. hourly, weekly
	 *
	 * This value can be a boolean (true), or a key {string} in wp_get_schedules() that
	 * represents an actual recurrence.
	 *
	 * If the value is boolean true, the default custom value 'nmeri_cron_recurrence' which represents
	 * a weekly schedule is used. This value has been been set in this class for convenience to prevent
	 * child class from having to filter the default wp cron schedules to set the the typical recurrence
	 * periods for average cron jobs which is weekly. Also wp < 5.4 doesn't have the 'weekly' cron schedule
	 * and so the value is present to support wp installations < 5.4.
	 *
	 * If you set a custom string value in the child class e.g. 'twice_weekly', you should use the
	 * 'cron_schedules' filter to add this value to wp_get_schedules() for the event to run.
	 * In this case the filter should be run before the child class is called, or be run in the
	 * child class constructor before the parent class constructor is called.
	 *
	 * @var boolean|string
	 */
	protected $recurrence;
	private $default_recurrence = 'nmeri_cron_recurrence';

	/**
	 * The hook used to schedule unfinished tasks
	 * (Auto) The class sets this automatically so no need to set it  except if you want.
	 * @var string
	 */
	private $task_hook;
	protected $file;

	/**
	 * Whether to cache the batch data as a transient for quick retrieval. Default true.
	 * @var boolean
	 */
	protected $cache_data = true;

	/**
	 * Whether the data is being processed in batches. Default false.
	 *
	 * If true, get_batch_data() is called repeatedly after each batch is completed
	 * until there is no data retrieved. This flag is useful for example when get_batch_data()
	 * returns a limited number of results (batch) from the database to be processed at a time,
	 * rather than all results at once.
	 * It is another way of ensuring that the scheduler runs within the server performance limit.
	 *
	 * @var boolean
	 */
	protected $batch_processing = false;

	public function __construct() {
		$this->hook = $this->hook ?? $this->get_event_name();
		$this->timestamp = $this->timestamp ?? time();
		$this->task_hook = $this->hook . '_task';

		if ( true === $this->recurrence ) {
			$this->recurrence = isset( wp_get_schedules()[ 'weekly' ] ) ? 'weekly' : $this->default_recurrence;
		}
	}

	/**
	 * WP Cron runs on init so we must initialize scheduler class on init and do everything
	 * afterwards.
	 * This function should be called when wp loads typically outside of any condition
	 * so that the actions registered in initialize() would always be ready to fire
	 * when the scheduled cron event starts running.
	 */
	public function init() {
		add_action( 'init', [ $this, 'initialize' ], 30 );
	}

	public function initialize() {
		// Doesn't hurt if we call this multiple times so call it here first for convenience.
		add_filter( 'cron_schedules', array( $this, 'set_cron_schedule' ) );
		add_action( $this->hook, [ $this, 'do_task' ] );
		add_action( $this->task_hook, [ $this, 'do_task' ] );

		if ( $this->file && is_admin() ) {
			register_deactivation_hook( $this->file, [ $this, 'clear_autoscheduled' ] );
		}

		$this->autoschedule();

		return $this;
	}

	/**
	 * Whether the event recurs
	 * @return mixed
	 */
	public function recurs() {
		return $this->recurrence;
	}

	// Run only events that are scheduled to recur
	public function autoschedule() {
		if ( $this->recurs() ) {
			$this->run();
		}
	}

	// Clear autoscheduled event
	public function clear_autoscheduled() {
		if ( $this->recurs() ) {
			$this->unschedule();
		}
	}

	public function run() {
		if ( $this->recurrence ) {
			$this->schedule();
		} else {
			$this->schedule_task();
		}
	}

	/**
	 * Schedule the recurring event to run
	 * This is the main cron job that should run probably weekly or fortnightly.
	 */
	public function schedule() {
		if ( !wp_next_scheduled( $this->hook ) ) {
			wp_schedule_event( $this->timestamp, $this->recurrence, $this->hook );
		}
	}

	/**
	 * Unschedule the main cron job
	 */
	public function unschedule() {
		wp_unschedule_hook( $this->hook );
	}

	/**
	 * Schedule the task to run
	 * This is the actual task in the main cron job that should run continuously until it is completed.
	 */
	protected function schedule_task() {
		if ( !wp_next_scheduled( $this->task_hook ) ) {
			wp_schedule_single_event( time(), $this->task_hook );
		}
	}

	public function set_cron_schedule( $schedules ) {
		if ( $this->recurrence === $this->default_recurrence ) {
			$schedules[ $this->recurrence ] = array(
				'interval' => WEEK_IN_SECONDS,
				'display' => __( 'Once Weekly' ),
			);
		}
		return $schedules;
	}

	protected function get_name() {
		return (new \ReflectionClass( $this ) )->getShortName();
	}

	protected function get_event_name() {
		return $this->prefix . '_' . $this->get_name();
	}

	protected function get_cache_name() {
		return $this->hook . '_cache';
	}

	public function do_task() {
		$data = $this->get_data();
		$this->start_timer();
		$this->start_running_task();

		foreach ( $data as $key => $value ) {
			$this->task( $value );
			unset( $data[ $key ] );

			if ( $this->time_exceeded() || $this->memory_exceeded() ) {
				break;
			}
		}

		if ( !empty( $data ) ) {
			if ( $this->cache_data ) {
				set_transient( $this->get_cache_name(), $data );
			}
			$this->schedule_task();
		} else {
			if ( $this->cache_data ) {
				delete_transient( $this->get_cache_name() );
			}

			if ( $this->continue_batch_processing() ) {
				$this->schedule_task();
			} else {
				$this->end_running_task();
				$this->complete();
			}
		}
	}

	protected function continue_batch_processing() {
		return $this->batch_processing && !empty( $this->get_batch_data() );
	}

	private function get_data() {
		$batch_data = $this->get_batch_data();
		$cache = $this->cache_data ? get_transient( $this->get_cache_name() ) : false;
		return ( array ) (false === $cache ? $batch_data : $cache);
	}

	private function start_timer() {
		$this->start_time = time(); // Set start time of current process.
	}

	/**
	 * Check whether the current task is already running
	 */
	public function is_task_running() {
		return get_transient( $this->hook . '_task_running' );
	}

	/**
	 * Set a flag in the database to show the task is running
	 */
	protected function start_running_task() {
		set_transient( $this->hook . '_task_running', microtime() );
	}

	/**
	 * Remove the flag in the database that shows the task is running
	 */
	protected function end_running_task() {
		delete_transient( $this->hook . '_task_running' );
	}

	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @return bool
	 */
	protected function memory_exceeded() {
		$memory_limit = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		return ( $current_memory >= $memory_limit ) ? true : false;
	}

	/**
	 * Get memory limit
	 *
	 * @return int
	 */
	protected function get_memory_limit() {
		$memory_limit = function_exists( 'ini_get' ) ? ini_get( 'memory_limit' ) : '128M';

		if ( !$memory_limit || -1 === $memory_limit ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}

		return intval( $memory_limit ) * 1024 * 1024;
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30s is common on shared hosting.
	 *
	 * @return bool
	 */
	protected function time_exceeded() {
		return time() >= ($this->start_time + 20); // 20 seconds
	}

	/**
	 * This function is called when the task has been fully completed
	 * Use it to perform any other operations
	 */
	protected function complete() {

	}

	/**
	 * The array of data to be processed during the even
	 * @return array
	 */
	abstract protected function get_batch_data();

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * batch item.
	 *
	 * @param mixed $item Item to iterate over.
	 *
	 * @return mixed
	 */
	abstract protected function task( $item );

}
