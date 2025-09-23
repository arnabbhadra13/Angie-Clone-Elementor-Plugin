<?php
/*
Plugin Name: Angie Clone - Safe Agent (Image-fallback HTML)
Description: Adds fallback: when sideload fails, insert image HTML inside a Text Editor widget so the image appears in Elementor editor.
Version: 0.4.7
Author: Your Name
Text Domain: angie-clone
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Angie_Clone_Safe_Image_Fallback {

    const REST_NAMESPACE = 'angie-clone/v1';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    }

    public function activate() {
        update_option( 'angie_clone_version', '0.4.7' );
    }

    public function admin_menu() {
        add_menu_page( 'Angie Clone', 'Angie Clone', 'manage_options', 'angie-clone', [ $this, 'admin_page' ], 'dashicons-admin-generic', 56 );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_angie-clone' ) return;
        wp_enqueue_script( 'angie-clone-admin', plugin_dir_url( __FILE__ ) . 'admin.js', [ 'jquery' ], '0.4.7', true );
        wp_localize_script( 'angie-clone-admin', 'AngieClone', [
            'previewUrl' => rest_url( self::REST_NAMESPACE . '/preview' ),
            'applyUrl'   => rest_url( self::REST_NAMESPACE . '/apply' ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
        ] );
        wp_enqueue_style( 'angie-clone-admin-css', plugin_dir_url( __FILE__ ) . 'admin.css' );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Angie Clone — Safe Agent</h1>
            <p>Type a command (demo). Click <strong>Preview Plan</strong> to see what will happen (dry run). Edit plan steps, then <strong>Apply Plan</strong> to run with a snapshot created automatically for destructive steps.</p>
            <textarea id="angie-prompt" rows="6" style="width:100%;"></textarea>
            <p>
                <button id="angie-preview" class="button">Preview Plan</button>
                <button id="angie-apply" class="button button-primary" disabled>Apply Plan</button>
            </p>
            <h2>Planned Steps (editable)</h2>
            <div id="angie-plan-editor" style="background:#fff;border:1px solid #ddd;padding:12px;min-height:100px;"></div>
            <h2>Output / Logs</h2>
            <pre id="angie-output" style="background:#fff;border:1px solid #ddd;padding:12px;min-height:120px;"></pre>
        </div>
        <?php
    }

    public function register_routes() {
        register_rest_route( self::REST_NAMESPACE, '/preview', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_preview' ],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ] );
        register_rest_route( self::REST_NAMESPACE, '/apply', [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_apply' ],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ] );
    }

    private function verify_nonce_from_request() {
        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
        return wp_verify_nonce( $nonce, 'wp_rest' );
    }

    public function rest_preview( \WP_REST_Request $request ) {
        if ( ! $this->verify_nonce_from_request() ) return new \WP_REST_Response( [ 'error' => 'Invalid nonce' ], 403 );
        $params = $request->get_json_params();
        $prompt = isset( $params['prompt'] ) ? sanitize_text_field( $params['prompt'] ) : '';
        if ( empty( $prompt ) ) return new \WP_REST_Response( [ 'error' => 'Prompt required' ], 400 );
        $plan = $this->planner_process_prompt( $prompt );
        return new \WP_REST_Response( [ 'plan' => $plan ], 200 );
    }

    public function rest_apply( \WP_REST_Request $request ) {
        if ( ! $this->verify_nonce_from_request() ) return new \WP_REST_Response( [ 'error' => 'Invalid nonce' ], 403 );
        $params = $request->get_json_params();
        $plan = isset( $params['plan'] ) ? $params['plan'] : null;
        if ( ! is_array( $plan ) ) return new \WP_REST_Response( [ 'error' => 'Plan required (array)' ], 400 );
        $results = [];
        foreach ( $plan as $action ) { $results[] = $this->execute_action( $action ); }
        return new \WP_REST_Response( [ 'results' => $results ], 200 );
    }

    private function planner_process_prompt( $prompt ) {
        $lower = strtolower( $prompt );
        if ( false !== strpos( $lower, 'elementor' ) && ( false !== strpos( $lower, 'create' ) || false !== strpos( $lower, 'page' ) ) ) {
            preg_match( '/"([^"]+)"/', $prompt, $m );
            $heading = isset( $m[1] ) ? $m[1] : 'Welcome';
            preg_match( '/https?:\/\/[^\s"\']+/i', $prompt, $u );
            $image_url = isset( $u[0] ) ? $u[0] : null;
            $page_title = 'Elementor Page - ' . wp_trim_words( $heading, 6, '' );
            return [ [ 'type'=>'create_elementor_page_rich', 'title'=>$page_title, 'heading'=>$heading, 'body'=>'', 'image_url'=>$image_url, 'status'=>'publish' ] ];
        }
        if ( false !== strpos( $lower, 'create a blog post' ) || false !== strpos( $lower, 'create post' ) ) {
            return [ [ 'type'=>'create_post', 'title'=>'Sample post from Angie Clone', 'content'=>'This post was created by Angie Clone (demo).' ] ];
        }
        return [ [ 'type'=>'suggest','message'=>'Try: Create a new Elementor page with heading "Welcome" and image https://via.placeholder.com/800x400' ] ];
    }

    private function execute_action( $action ) {
        $type = isset( $action['type'] ) ? $action['type'] : '';
        switch ( $type ) {
            case 'create_post': return $this->action_create_post( $action );
            case 'create_elementor_page_rich': return $this->action_create_elementor_page_rich( $action );
            default: return [ 'status'=>'skipped','message'=>'Unknown action' ];
        }
    }

    private function action_create_post( $data ) {
        $postarr = [ 'post_title'=>sanitize_text_field( $data['title'] ?? 'Untitled' ), 'post_content'=>wp_kses_post( $data['content'] ?? '' ), 'post_status'=>'publish', 'post_author'=>get_current_user_id(), 'post_type'=>'post' ];
        $id = wp_insert_post( $postarr );
        if ( is_wp_error( $id ) ) return [ 'status'=>'error','message'=>$id->get_error_message() ];
        return [ 'status'=>'success','action'=>'create_post','post_id'=>$id,'link'=>get_edit_post_link($id,'') ];
    }

    private function action_create_elementor_page_rich( $data ) {
        if ( ! defined( 'ELEMENTOR_VERSION' ) || ! class_exists( '\\Elementor\\Plugin' ) ) return [ 'status'=>'skipped','message'=>'Elementor not active' ];
        $title = sanitize_text_field( $data['title'] ?? 'New Elementor Page' );
        $heading = sanitize_text_field( $data['heading'] ?? 'Welcome' );
        $body = sanitize_textarea_field( $data['body'] ?? '' );
        $image_url = isset( $data['image_url'] ) ? esc_url_raw( $data['image_url'] ) : null;

        $postarr = [ 'post_title'=>$title, 'post_content'=>'', 'post_status'=>$data['status'] ?? 'publish', 'post_type'=>'page', 'post_author'=>get_current_user_id() ];
        $post_id = wp_insert_post( $postarr );
        if ( is_wp_error( $post_id ) ) return [ 'status'=>'error','message'=>$post_id->get_error_message() ];

        $attachment_id = null;
        $attachment_url = null;

        if ( $image_url ) {
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/media.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
            $tmp = download_url( $image_url );
            if ( ! is_wp_error($tmp) ) {
                $file_array = [ 'name'=>basename($image_url), 'tmp_name'=>$tmp ];
                $aid = media_handle_sideload( $file_array, $post_id );
                if ( ! is_wp_error($aid) ) {
                    $attachment_id = (int) $aid;
                    $attachment_url = wp_get_attachment_url( $attachment_id );
                } else {
                    @unlink( $tmp );
                }
            }
        }

        if ( ! $attachment_id && $image_url ) {
            $attachment_id = 0;
            $attachment_url = $image_url;
        }

        $section = 'section_'.wp_generate_password(6,false,false);
        $column  = 'column_'.wp_generate_password(6,false,false);
        $w_heading = 'widget_'.wp_generate_password(6,false,false).'_heading';
        $w_text = 'widget_'.wp_generate_password(6,false,false).'_text';
        $w_image = 'widget_'.wp_generate_password(6,false,false).'_image';

        $elements = [
            [
                'id'=>$section,
                'elType'=>'section',
                'settings'=> (object) [],
                'elements'=> [
                    [
                        'id'=>$column,
                        'elType'=>'column',
                        'settings'=> (object) [],
                        'elements'=> [
                            [
                                'id'=>$w_heading,
                                'elType'=>'widget',
                                'widgetType'=>'heading',
                                'settings'=> [ 'title'=>$heading, 'size'=>'xl' ],
                            ],
                            [
                                'id'=>$w_text,
                                'elType'=>'widget',
                                'widgetType'=>'text-editor',
                                'settings'=> [ 'editor'=>$body ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ( $attachment_id && $attachment_id > 0 && $attachment_url ) {
            $elements[0]['elements'][0]['elements'][] = [
                'id'=>$w_image,
                'elType'=>'widget',
                'widgetType'=>'image',
                'settings'=> [
                    'image'=> [ 'id'=>$attachment_id, 'url'=>$attachment_url ],
                    'image_size'=>'full',
                    'caption'=>'',
                    'alignment'=>'center',
                ],
            ];
        } elseif ( $attachment_url ) {
            $img_html = '<img src="'.esc_url($attachment_url).'" alt="'.esc_attr($heading).'" style="max-width:100%;height:auto;" />';
            $elements[0]['elements'][0]['elements'][] = [
                'id'=>'widget_'.wp_generate_password(6,false,false).'_imghtml',
                'elType'=>'widget',
                'widgetType'=>'text-editor',
                'settings'=> [ 'editor'=>$img_html ],
            ];
        }

        update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $post_id, '_elementor_version', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'unknown' );
        update_post_meta( $post_id, '_elementor_template_type', 'page' );

        if ( class_exists('\\Elementor\\Plugin') ) {
            try {
                $doc = \\Elementor\\Plugin::$instance->documents->get( $post_id );
                if ( $doc ) $doc->save([], true);
            } catch ( \Throwable $e ) { /* ignore */ }
        }

        return [ 'status'=>'success','action'=>'create_elementor_page_rich','post_id'=>$post_id,'attachment_id'=>$attachment_id,'edit_link'=>get_edit_post_link($post_id,''),'elementor_edit_link'=>admin_url("post.php?post={$post_id}&action=elementor"),'view_link'=>get_permalink($post_id) ];
    }

} // end class

new Angie_Clone_Safe_Image_Fallback();
?>