<?php
/*
  Plugin Name: Live Comment Preview
  Plugin URI: http://wordpress.org/extend/plugins/live-comment-preview/
  Description: Displays a preview of the user's comment as they type it.
  Author: Brad Touesnard
  Author URI: http://bradt.ca/
  Version: 2.0.2

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * The main plugin class.
 *
 */
class Live_Comment_Preview {

	/**
	 * Size of the Avatar to be displayed.
	 *
	 * @var int $avatar_size
	 */
	protected $avatar_size;

	/**
	 * Default Avatar to be shown if no image is associated.
	 *
	 * Default Avatar includes 404, mm, identicon, monsterid, wavatar, retro, blank.
	 *
	 * @var string $avatar_default
	 */
	protected $avatar_default;

	/**
	 * Avatar rating to identify image appropriateness.
	 *
	 * @var string $avatar_rating
	 */
	protected $avatar_rating;

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
	 * Initialize defaults.
	 *
	 * @var bool $live_preview_div_added
	 */
	function __construct() {

		/*
		 * Gravatar specific defaults.
		 * Refer https://en.gravatar.com/site/implement/images/ for Gravatar default values.
		 */
		$this->avatar_size    = self::DEFAULT_AVATAR_SIZE;
		$this->avatar_default = self::DEFAULT_AVATAR_TO_DISPLAY;
		$this->avatar_rating  = self::DEFAULT_AVATAR_RATING;

		// UI defaults.
		$this->live_preview_div_added = false;
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
		wp_enqueue_script( 'live-cp-md5', plugin_dir_url( __FILE__ ) . 'assets/js/md5.js' );
		wp_enqueue_script( 'live-comment-preview', plugin_dir_url( __FILE__ ).'assets/js/live-comment-preview.js' );
		wp_enqueue_style( 'live-comment-preview', plugins_url( '/assets/css/live-comment-preview.css', __FILE__ ) );
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
		$js_vars['avatar_size']   = $this->avatar_size;
		$js_vars['avatar_rating'] = $this->avatar_rating;

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
			$url = $this->get_gravatar( $current_user->user_email, $this->avatar_size, $this->avatar_default, $this->avatar_rating );

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
						$this->avatar_size = $matches[1];
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
						$this->avatar_size = $matches[1];
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

		$template = '
		<ol class="comment-list">
			<li id="comment-lcp" class="comment">
				<article id="div-comment-lcp" class="comment-body">
					<footer class="comment-meta">
						<div class="comment-author vcard">
							<img src="AVATAR_URL" alt="" class="avatar avatar-' . $this->avatar_size . '" width="' . $this->avatar_size . '" height="' . $this->avatar_size . '"/>
							<b class="fn"><a href="#" rel="external nofollow" class="url">COMMENT_AUTHOR</a></b>
							<span class="says">says:</span>
						</div>
						<div class="comment-metadata">
							<a href="/#comment-lcp">
								<time datetime="' . date( 'c' ) . ' "> ' . date( 'F j, Y \a\t g:i' ) . ' </time>
							</a>
						</div>
					</footer>
					<div class="comment-content">
						COMMENT_CONTENT
					</div>
				</article>
			</li>
		</ol>';

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

		$this->avatar_rating   = get_option( 'avatar_rating' );
		$this->avatar_default  = get_option( 'avatar_default' );
	}

	/**
	 * Get either a Gravatar URL or complete image tag for a specified email address.
	 *
	 * @link https://gravatar.com/site/implement/images/php/
	 *
	 * @param string $email          The email address.
	 * @param string $avatar_size    Size in pixels, defaults to 80px [ 1 - 2048 ].
	 * @param string $avatar_default Default imageset to use.
	 * @param string $avatar_rating  Maximum rating (inclusive) [ g | pg | r | x ].
	 * @param bool   $img  Optional. True to return a complete IMG tag, False for just the URL.
	 * @param array  $atts Optional. Additional key/value attributes to include in the IMG tag.
	 * @return string                String containing either just a URL or a complete image tag.
	 */
	protected function get_gravatar( $email, $avatar_size, $avatar_default, $avatar_rating, $img = false, $atts = array() ) {

		$url = 'https://www.gravatar.com/avatar/';
		if ( '' === trim( $email ) ) {
			$url .= '00000000000000000000000000000000';
		} else {
			$url .= md5( strtolower( trim( $email ) ) );
		}
		$url .= "?s=$avatar_size&d=$avatar_default&r=$avatar_rating";

		if ( $img ) {
			$url = '<img src="' . $url . '"';
			foreach ( $atts as $key => $val )
				$url .= ' ' . $key . '="' . $val . '"';
			$url .= ' />';
		}

		return $url;
	}
}

/**
 * Hooks the plugin class on `init`.
 *
 * @global Live_Comment_Preview $live_comment_preview
 *
 * @return void
 */
function live_comment_preview_setup() {
	global $live_comment_preview;

	if ( class_exists( 'Live_Comment_Preview' ) && ! isset( $live_comment_preview ) ) {
		$live_comment_preview = new Live_Comment_Preview();

		// Invoke the lcp_init() method to display the comment preview.
		$live_comment_preview->lcp_init();
	}
}
add_action( 'init', 'live_comment_preview_setup' );
