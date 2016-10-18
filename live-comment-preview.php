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
load_template( 'class-live-comment-preview.php' );

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
