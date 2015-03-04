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

class liveCommentPreview {

        public $livePreviewDivAdded = false;

        function init() {

                add_action( 'comment_form', array( $this, 'ui' ) );
                $this->avatar_size = 32;
                $this->avatar_default = get_option( 'avatar_default' );
                $this->avatar_rating = get_option( 'avatar_rating' );
                
        }

        function localize_vars() {
                
                $js_vars = $this->js_vars();
                $js_vars['template'] = $this->template();
                
                wp_localize_script('live-comment-preview', 'ya_live_cp', $js_vars);
                wp_enqueue_script('live-cp-md5', plugin_dir_url( __FILE__).'md5.js');
                wp_enqueue_script('live-comment-preview', plugin_dir_url( __FILE__).'live-comment-review.js');

        }
        
        function js_vars(){
                global $user_ID, $user_identity, $allowedtags;
                
                $local_js = array();
                
                // If you have changed the ID's on your form field elements
                // You should make them match here
                $local_js['commentID'] = 'comment';
                $local_js['authorID'] = 'author';
                $local_js['urlID'] = 'url';
                $local_js['emailID'] = 'email';

                // Default name
                if ( $user_ID ) {
                        $local_js['default_name'] = add_slashes($user_identity);

                        $user = get_userdata( $user_ID );
                        if ( $user ) {
                                $local_js['user_gravatar'] = add_slashes('http://www.gravatar.com/avatar/' . md5( strtolower( $user->user_email ) ));
                        }
                } else {
                        $local_js['default_name'] = add_slashes('Anonymous');
                }
                
                $local_js['allowedtags'] = $allowedtags;
                
                return $local_js;

        }
        
        function template_file(){
                $files = array(
                        'user' => TEMPLATEPATH . '/comment-preview.php',
                        'theme' => TEMPLATEPATH . '/comments.php'
                );
                
                foreach($files as $index=>$file){
                        if(file_exists($file)){
                                return array( $index, $file );
                        }
                }
                
        }
        
        function get_html(){
                $template = $this->template_file();
                $template_function = $template[0].'_template';
                $file = $template[1];
                $html = $this->$template_function($file);
                return $html;
        }
        
        private function user_template($file){
                if(empty($file)){
                        return;
                }
                
                if ( !file_exists( $file ) ) {
                        return;
                }
                
                ob_start();
                include($file);
                $template = ob_get_clean();

                // Get avatar size
                if ( preg_match( '@<img(.*?)class=.avatar(.*?)>@s', $template, $matches ) ) {
                        $img_tag = $matches[ 0 ];

                        if ( preg_match( '@width=.([0-9]+)@', $img_tag, $matches ) ) {
                                $this->avatar_size = $matches[ 1 ];
                        }
                }
                
                return $template;
        }
        
        private function theme_template($file){
                if(empty($file)){
                        return;
                }
                
                if ( !file_exists( $file ) ) {
                        return;
                }
                
                global $wp_query, $comments, $comment, $post;

                $post->comment_status = 'open';

                if ( !is_object( $comment ) ) {
                        $comment = new stdClass();
                }

                $comment->comment_ID = 'lcp';
                $comment->comment_content = 'COMMENT_CONTENT';
                $comment->comment_author = 'COMMENT_AUTHOR';
                $comment->comment_parent = 0;
                $comment->comment_date = time();
                $comment->comment_type = '';
                $comment->user_id = 0;
                $comment->comment_author_url = '';
                $comment->comment_post_ID = 0;
                $comment->comment_approved = 1;

                $wp_query->comment = $comment;
                $wp_query->comments = $comments = array( $comment );
                $wp_query->current_comment = -1;
                $wp_query->comment_count = 1;

                ob_start();
                include($file);
                $template = ob_get_clean();
                if ( preg_match( '@<ol(.*?)class=.commentlist(.*)</ol>@s', $html, $matches ) ) {
                        $template = $matches[ 0 ];

                        $template = preg_replace( '@http://COMMENT_AUTHOR_URL@', 'COMMENT_AUTHOR_URL', $template );

                        if ( preg_match( '@<img(.*?)class=.avatar(.*?)>@s', $template, $matches ) ) {
                                $img_tag = $matches[ 0 ];

                                if ( preg_match( '@width=.([0-9]+)@', $img_tag, $matches ) ) {
                                        $this->avatar_size = $matches[ 1 ];
                                }

                                $new_img_tag = preg_replace( '@src=("|\')(.*?)("|\')@', 'src=$1AVATAR_URL$3', $img_tag );
                                $template = str_replace( $img_tag, $new_img_tag, $template );

                        }
                }
                
                return $template;
        }
        
        function create_template(){
                $template = '
                <ol class="commentlist">
                        <li id="comment-preview">
                                <img src="' . $this->avatar_default . '" alt="" class="avatar avatar-' . $this->avatar_size . '" width="' . $this->avatar_size . '" height="' . $this->avatar_size . '"/>
                                <cite>COMMENT_AUTHOR</cite> Says:
                                <div aria-live="polite">
                                COMMENT_CONTENT
                                </div>
                        </li>
                </ol>';
                
                return $template;
        }

        
        function template( ){
                
                $html = $this->get_html($this->avatar_size, $this->avatar_default);
                
                if ( !$this->avatar_default )
                        $this->avatar_default = 'mystery';

                if ( is_ssl() ) {
                        $host = 'https://secure.gravatar.com';
                } else {
                        if ( !empty( $email ) )
                                $host = sprintf( "http://%d.gravatar.com", ( hexdec( $email_hash{0} ) % 2 ) );
                        else
                                $host = 'http://0.gravatar.com';
                }

                if ( 'mystery' == $this->avatar_default ){
                        $this->avatar_default = "$host/avatar/ad516503a11cd5ca435acc9bb6523536"; // ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
                }elseif ( 'blank' == $this->avatar_default ){
                        $this->avatar_default = includes_url( 'images/blank.gif' );
                }elseif ( 'gravatar_default' == $this->avatar_default ){
                        $this->avatar_default = "$host/avatar/";
                }
                // Just in case the other two methods didn't work out
                if(empty($html)){
                        $html = $this->create_template();
                }
                
                return $html;
        }

        function ui( $post_id ) {

                if ( false == $this->livePreviewDivAdded ) {
                        // We don't want this included in every page 
                        // so we add it here instead of using the wphead filter
                        //echo '<script src="' . get_option( 'home' ) . '/?live-comment-preview.js" type="text/javascript"></script>';
                        echo '<div id="commentPreview"></div>';
                        $this->livePreviewDivAdded = true;
                }

                return $post_id;
        }

}

/*
 * will have wp_enqueue_script now
if ( stristr( $_SERVER[ 'REQUEST_URI' ], 'live-comment-preview.js' ) ) {
        add_action( 'template_redirect', 'lcp_output_js' );
}
*/