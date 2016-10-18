<?php
	/**
	 * Contains the main method
	 *
	 */

class Live_Comment_Preview {

	/**
	 * An associative array of Avatar settings.
	 * Array keys include - avatar_size, avatar_default, avatar_rating.
	 *
	 * @var array $avatar_settings
	 */
	protected $avatar_settings;

	/**
	 * Avatar rating to identify image appropriateness.
	 *
	 * @var const DEFAULT_USER_NAME
	 */
	const DEFAULT_USER_NAME = 'Anonymous';

	/**
	 * Default Avatar size.
	 *
	 * @var int DEFAULT_AVATAR_SIZE
	 */
	const DEFAULT_AVATAR_SIZE = 32;

	/**
	 * Default Avatar to display.
	 *
	 * @var string DEFAULT_AVATAR_TO_DISPLAY
	 */
	const DEFAULT_AVATAR_TO_DISPLAY = 'mm';

	/**
	 * Default Avatar rating.
	 *
	 * @var string DEFAULT_AVATAR_RATING
	 */
	const DEFAULT_AVATAR_RATING = 'g';

	/**
	 * Indicate if Live comment preview div tag has been added.
	 *
	 * @var bool $live_preview_div_added
	 */
	public $live_preview_div_added;

	/**
	 * Plugin's main file
	 *
	 * @var string $plugin_file
	 */
	public $plugin_file;

	/**
	 * Initialize defaults.
	 *
	 * @var bool $live_preview_div_added
	 */
	function __construct( $file ) {

		/*
		 * Gravatar specific defaults.
		 * Refer https://en.gravatar.com/site/implement/images/ for Gravatar default values.
		 */
		$this->avatar_settings = array(
			"avatar_size"    => self::DEFAULT_AVATAR_SIZE,
			"avatar_default" => self::DEFAULT_AVATAR_TO_DISPLAY,
			"avatar_rating"  => self::DEFAULT_AVATAR_RATING,
		);

		// UI defaults.
		$this->live_preview_div_added = false;

		// Plugin's main file.
		$this->plugin_file = $file;
	}

	/**
	 * Invoke methods to display the live comment preview.
	 *
	 * @return void
	 */
	function lcp_init() {

		// Adds UI element to display the comment preview.
		add_action( 'comment_form', array( $this, 'add_ui_element' ) );

		// Get Avatar options from Db.
		$this->get_avatar_options();

		/*
		 * Include necessary assets such as Javascript files, stylesheets.
		 * This should be done before invoking localize_vars() method.
		 */
		$this->lcp_include_assets();

		// Localize the data to be accessed from Javascript
		$this->localize_vars();
	}

	/**
	 * Includes assets such as Javascript files, stylesheets.
	 *
	 * @return void
	 */
	function lcp_include_assets() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'live-cp-md5', plugin_dir_url( $this->plugin_file ) . 'assets/js/md5.js' );
		wp_enqueue_script( 'live-comment-preview', plugin_dir_url( $this->plugin_file ).'assets/js/live-comment-preview.js' );
		wp_enqueue_style( 'live-comment-preview', plugins_url( '/assets/css/live-comment-preview.css', $this->plugin_file ) );
	}

	/**
	 * Localizes the data to be accessed from Javascript.
	 *
	 * @return void
	 */
	function localize_vars() {

		// Set User specific data.
		$js_vars                  = $this->user_js_vars();

		// Set Comment preview template data.
		$js_vars['template']      = $this->template();

		/*
		 * If user template (comment-preview.php) is used then,
		 * match the comment field IDs here.
		 */
		$js_vars['commentID']     = 'comment';
		$js_vars['authorID']      = 'author';
		$js_vars['urlID']         = 'url';
		$js_vars['emailID']       = 'email';

		// Set Avatar specific data.
		$js_vars['avatar_size']   = $this->avatar_settings["avatar_size"];
		$js_vars['avatar_rating'] = $this->avatar_settings["avatar_rating"];

		// Localize data to be accessed via Javascript.
		wp_localize_script( 'live-comment-preview', 'ya_live_cp', $js_vars );
	}

	/**
	 * Sets variables to use in Javascript.
	 *
	 * @link https://core.trac.wordpress.org/browser/tags/3.1.3/wp-includes/kses.php#L397
	 *
	 * @global array $allowedtags White listed html tags to be allowed in comments.
	 *
	 * @return array
	 */
	function user_js_vars() {

		global $allowedtags;

		$local_js = array();

		// Whitelisted HTML tags to be used in comments.
		$local_js['allowed_tags'] = $allowedtags;

		// Current logged in user information.
		$current_user = wp_get_current_user();

		if ( $current_user->ID ) {
			$url = $this->get_gravatar( $current_user->user_email, $this->avatar_settings );

			$local_js['display_name']   = addslashes( $current_user->display_name );
			$local_js['user_gravatar']  = addslashes( $url );
			$local_js['user_url']       = addslashes( $current_user->user_url );
		} else {
			$local_js['default_name']   = addslashes( self::DEFAULT_USER_NAME );
			$local_js['avatar_default'] = $this->avatar_default;
		}
		return $local_js;
	}

	/**
	 * Identifies and returns path to user template if exists.
	 *
	 * If user template does not exists, returns path to theme template.
	 *
	 * @return array
	 */
	function template_file() {
		$files = array(
			'user'  => get_stylesheet_directory() . '/comment-preview.php',
			'theme' => get_stylesheet_directory() . '/comments.php',
		);

		foreach ( $files as $index=>$file ) {
			if ( file_exists( $file ) ) {
				return array( $index, $file );
			}
		}
	}

	/**
	 * Gets either user's or theme's HTML template to preview the comment.
	 *
	 * @return string
	 */
	function get_html() {
		$template = $this->template_file();
		$template_function = $template[0].'_template';
		$file = $template[1];
		$html = $this->{$template_function}( $file );
		return $html;
	}

	/**
	 * Gets the comment template from the User.
	 *
	 * @access private
	 *
	 * @param string $file Path to the user's HTML template.
	 * @return string
	 */
	private function user_template( $file ) {
		if ( empty( $file ) || ! file_exists( $file ) ) {
			return;
		}

		ob_start();
		include( $file );
		$template = ob_get_clean();

		// Get avatar size.
		if ( preg_match( '@<img(.*?)class=.avatar(.*?)>@s', $template, $matches ) ) {
				$img_tag = $matches[0];

				if ( preg_match( '@width=.([0-9]+)@', $img_tag, $matches ) ) {
						$this->avatar_settings["avatar_size"] = $matches[1];
				}
		}

		return $template;
	}

	/**
	 * Gets the comment template from theme.
	 *
	 * @access private
	 *
	 * @param string $file Path to the theme's comment template.
	 * @return string
	 */
	private function theme_template( $file ) {

		if ( empty( $file ) || ! file_exists( $file ) ) {
				return;
		}

		global $wp_query, $comments, $comment, $post;

		if ( ! empty( $post ) ){
			$post->comment_status = 'open';
		}

		if ( ! is_object( $comment ) ) {
				$comment = new stdClass();
		}

		/*
		 * Fetch comment list
		 * refer https://codex.wordpress.org/Function_Reference/wp_list_comments
		 */
		$comment->comment_ID = 'lcp';
		$comment->comment_content = 'COMMENT_CONTENT';
		$comment->comment_author = 'COMMENT_AUTHOR';
		$comment->comment_parent = 0;
		$comment->comment_date = time();
		$comment->comment_type = '';
		$comment->user_id = 0;
		$comment->comment_author_url = 'COMMENT_AUTHOR_URL';
		$comment->comment_post_ID = 0;
		$comment->comment_approved = 1;

		$wp_query->comment = $comment;
		$wp_query->comments = $comments = array( $comment );
		$wp_query->current_comment = -1;
		$wp_query->comment_count = 1;

		ob_start();
		include( $file );
		$template = ob_get_clean();

		if ( preg_match( '@<ol(.*?)class=.(commentlist|comment-list)(.*)</ol>@s', $template, $matches ) ) {
			$template = $matches[0];

			$template = preg_replace( '@http://COMMENT_AUTHOR_URL@', 'COMMENT_AUTHOR_URL', $template );

			if ( preg_match( '@<img(.*?)class=.avatar(.*?)>@s', $template, $matches ) ) {
					$img_tag = $matches[0];

				if ( preg_match( '@width=.([0-9]+)@', $img_tag, $matches ) ) {
						$this->avatar_settings["avatar_size"] = $matches[1];
				}

				$new_img_tag = preg_replace( '@src=("|\')(.*?)("|\')@', 'src=$1AVATAR_URL$3', $img_tag );
				$template = str_replace( $img_tag, $new_img_tag, $template );
			}
		}

		return $template;
	}

	/**
	 * Creates a HTML template to preview the comment.
	 *
	 * This template will be used when both theme and user template are not found.
	 *
	 * @return string $template
	 */
	function create_template() {

		// Get the template from the specified file.
		$template = file_get_contents( plugin_dir_url( $this->plugin_file ) . 'include/default-comment-template.html' );

		// Replace the hardcoded strings into their respective values.
		$template = preg_replace( '/AVATAR_SIZE/', $this->avatar_settings["avatar_size"], $template );
		$template = preg_replace( '/ISO_8601_DATE_FORMAT/', date( 'c' ), $template );
		$template = preg_replace( '/DEFAULT_DATE_FORMAT/', date( 'F j, Y \a\t g:i a' ), $template );
		return $template;
	}

	/**
	 * Gets the HTML template to preview the comment.
	 *
	 * @return string
	 */
	function template() {

		$html = $this->get_html();

		/*
		 * In case the other two methods (user & theme) didn't work out,
		 * then create and set the template
		 */
		if ( empty( $html ) ){
				$html = $this->create_template();
		}

		return $html;
	}

	/**
	 * Adds UI element to display comment preview.
	 *
	 * @param int $post_id The ID of the post where the comment form was rendered.
	 * @return void
	 */
	function add_ui_element( $post_id ) {

		if ( false == $this->live_preview_div_added ) {
			/*
			 * We don't want this included in every page
			 * so we add it here instead of using the wphead filter.
			 */
			echo '<div id="comment-preview"></div>';
			$this->live_preview_div_added = true;
		}

		return $post_id;
	}

	/**
	 * Get Avatar options from Db.
	 *
	 * @return void
	 */
	protected function get_avatar_options() {
		/**
		 * @todo Check if Show Avatars option has been enabled. This could be an enhancement.
		 */
		// $this->is_show_avatars = get_option( 'show_avatars' );
		$this->avatar_settings["avatar_rating"]  = get_option( 'avatar_rating' );
		$this->avatar_settings["avatar_default"] = get_option( 'avatar_default' );
	}

	/**
	 * Get either a Gravatar URL or complete image tag for a specified email address.
	 *
	 * @link https://gravatar.com/site/implement/images/php/
	 *
	 * @param string $email          The email address.
	 * @param string $avatar_settings {
	 *     @type int    $avatar_size    Size of the Avatar.
	 *     @type string $avatar_default Avatar to display.
	 *     @type string $avatar_rating  Avatar rating.
	 * }
	 * @param bool   $img  Optional. True to return a complete IMG tag, False for just the URL.
	 * @param array  $atts Optional. Additional key/value attributes to include in the IMG tag.
	 * @return string                String containing either just a URL or a complete image tag.
	 */
	protected function get_gravatar( $email, $avatar_settings, $img = false, $atts = array() ) {

		$url = 'https://www.gravatar.com/avatar/';
		if ( '' === trim( $email ) ) {
			$url .= '00000000000000000000000000000000';
		} else {
			$url .= md5( strtolower( trim( $email ) ) );
		}
		$url .= '?s=' . $avatar_settings["avatar_size"] .'&d=' . $avatar_settings["avatar_default"] . '&r='. $avatar_settings["avatar_rating"];

		if ( $img ) {
			$url = '<img src="' . $url . '"';
			foreach ( $atts as $key => $val )
				$url .= ' ' . $key . '="' . $val . '"';
			$url .= ' />';
		}

		return $url;
	}
}

?>
