<?php
/**
 * Post Collection User Query
 *
 * This wraps \WP_User_Query to generate instances of User.
 *
 * @package Post_Collection
 */

namespace PostCollection;

/**
 * User_Query class for the Post Collection plugin.
 *
 * @since 3.0
 *
 * @package Post_Collection
 * @author Alex Kirk
 */
class User_Query extends \WP_User_Query {
	/**
	 * Whether to cache the retrieved users.
	 *
	 * @var boolean
	 */
	public static $cache = true;

	/**
	 * List of found User objects.
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Total number of found users for the current query.
	 *
	 * @var int
	 */
	private $total_users = 0;

	/**
	 * Execute the query and ensure that we populate User objects.
	 */
	public function query() {
		parent::query();
		foreach ( parent::get_results() as $k => $user ) {
			$this->results[ $k ] = new User( $user );
			$this->total_users += 1;
		}
	}

	/**
	 * Return the user results.
	 *
	 * @return array Array of User objects.
	 */
	public function get_results() {
		return $this->results;
	}

	/**
	 * Returns the total number of users for the current query.
	 *
	 * @return int Number of total users.
	 */
	public function get_total() {
		return $this->total_users;
	}
}
