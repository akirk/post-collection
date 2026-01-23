<?php
/**
 * Template Loader for Plugins.
 *
 * @package   Gamajo_Template_Loader
 * @author    Gary Jones
 * @link      http://github.com/GaryJones/Gamajo-Template-Loader
 * @copyright 2013 Gary Jones
 * @license   GPL-2.0-or-later
 * @version   1.3.1
 */

namespace PostCollection;

use Friends\Template_Loader;

/**
 * Template loader.
 *
 * Originally based on functions in Easy Digital Downloads (thanks Pippin!).

 * @package Post_Collection
 * @author  Alex Kirk
 */
class Post_Collection_Template_Loader extends Template_Loader {
	/**
	 * Reference to the root directory path of this plugin.
	 *
	 * Can either be a defined constant, or a relative reference from where the subclass lives.
	 *
	 * e.g. YOUR_PLUGIN_TEMPLATE or plugin_dir_path( dirname( __FILE__ ) ); etc.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $plugin_directory = POST_COLLECTION_PLUGIN_DIR;
}
