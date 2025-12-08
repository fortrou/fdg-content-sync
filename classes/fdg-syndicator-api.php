<?php
if ( ! defined( 'ABSPATH' ) ) die;
class FDG_Syndicator_Api {
    public function __construct() {
        add_action('rest_api_init', [$this, 'run_rest_routes']);
    }

    public function run_rest_routes()
    {
        register_rest_route('fdg_syndicator/v1', '/posts/moderate', array(
            'methods' => 'POST',
            'callback' => [$this, 'modify_post'],
            'permission_callback' => '__return_true'
        ));

        register_rest_route('fdg_syndicator/v1', '/posts/search/(?P<title>[^\/]+)', array(
            'methods' => 'GET',
            'callback' => [$this, 'search_post_by_title'],
            'permission_callback' => '__return_true'
        ));
    }

    public function modify_post($request) {
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        global $wpdb;



        $arrayToProduce = [
            'post_title' => $data['post_title'],
            'post_name' => $data['post_name'],
            'post_excerpt' => $data['post_excerpt'],
            'post_content' => $data['post_content'],
            'post_status' => $data['post_status'],
            'comment_status' => $data['comment_status'],
            'ping_status' => $data['ping_status'],
            'post_password' => $data['post_password'],
            'menu_order' => $data['menu_order'],
            'meta_input' => $data['meta_data']
        ];

        if ($data['update_post'] != 0) {
            $arrayToProduce['ID'] = $data['update_post'];
            $postID = wp_update_post($arrayToProduce);
        } else {
            $postID = wp_insert_post($arrayToProduce);
        }

        if (is_wp_error($postID)) {
            return [
                'status' => false,
                'message' => $postID->get_error_message()
            ];
        }

        return [
            'status' => true,
            'post' => $postID
        ];
    }

    public function search_post_by_title($request)
    {
        $title = urldecode($request->get_param('title'));
        global $wpdb;
        $returnList = [];
        $context = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE post_type NOT IN ('revision', 'nav_menu_item', 'wp_template', 'wp_template_part') AND ( post_title LIKE '%$title%' OR post_name LIKE '%$title%') ORDER BY post_title, post_name ASC LIMIT 0, 15", ARRAY_A);

        if (!empty($context)) {
            foreach ($context as $post) {
                $returnList[] = [
                    'id' => $post['ID'],
                    'title' => $post['post_title'],
                    'name' => $post['post_name'],
                ];
            }
        }

        $counter = $wpdb->get_results("SELECT COUNT(*) as counter FROM {$wpdb->prefix}posts WHERE post_type NOT IN ('revision', 'nav_menu_item', 'wp_template', 'wp_template_part') AND ( post_title LIKE '%$title%' OR post_name LIKE '%$title%') ORDER BY post_title, post_name ASC", ARRAY_A);
        $counter = $counter[0]['counter'];
        return [
            'list' => $returnList,
            'counter' => ceil($counter / 15)
        ];
    }

    public function check_if_record_exists($item_id, $type = 'post')
    {
        global $wpdb;
        $field = 'post_id';
        if ($type == 'post') {
            $context = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_key = 'origin_post_id' AND meta_value = {$item_id}", ARRAY_A);
        } else if ($type == 'term') {
            $field = 'term_id';
            $context = $wpdb->get_results("SELECT * FROM {$wpdb->termmeta} WHERE meta_key = 'origin_term_id' AND meta_value = {$item_id}", ARRAY_A);
        }
        if (!empty($context)) {
            return $context[0][$field];
        } else {
            return false;
        }
    }

    public function is_api_request()
    {
        $request_uri = $_SERVER['REQUEST_URI'];
        return false !== strpos($request_uri, '/wp-json/');
    }

    public function check_if_outer_request($post_id, $post, $update)
    {
        if ($post->post_title == 'Auto Draft' || empty($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return false;
        $sourceHeader = isset($_SERVER['HTTP_X_REFERRER']) ? $_SERVER['HTTP_X_REFERRER'] : '';
        if ($sourceHeader == '' || $sourceHeader != 'RemoteSyndicatorRequest') {

            if (empty(get_post_meta($post_id, 'edit_blocked_from_api', true))) {
                update_post_meta($post_id, 'edit_blocked_from_api', 'locked ' . date('Y-m-d H:i'));
            }
        }
    }
}