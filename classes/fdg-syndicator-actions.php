<?php
if ( ! defined( 'ABSPATH' ) ) die;
class FDG_Syndicator_Actions {

    private $requests;

    private $api;
    public function __construct($requests, $api) {
        $this->requests = $requests;
        $this->api = $api;

        $this->run_actions();
    }

    private function run_actions()
    {
        add_action('save_post', [$this, 'process_meta_fields'], 50, 3);
        add_action('save_post', [$this, 'send_post_content'], 100, 3);

        add_action('edit_term', [$this, 'send_term_content'], 100, 3);
    }

    public function send_term_content($term_id, $tt_id, $taxonomy) {
        var_dump(func_get_args());
        $termData = get_term($term_id, $taxonomy);
        if ($termData && !is_wp_error($termData)) {
            $termMeta = get_term_meta($term_id);
            var_dump($termData);
            var_dump($termMeta);


            die;
        }
    }

    public function process_meta_fields($post_id, $post, $update)
    {
        if ($post->post_title == 'Auto Draft' ||
            $post->post_name == 'auto-draft' ||
            $post->post_status == 'auto-draft' ||
            in_array($post->post_type, ['revision', 'nav_menu_item', 'wp_template', 'wp_template_part'])) return false;

        $postSyndicationData = [
            'syndication' => isset($_REQUEST['enable_syndication']) ? true : false,
            'syndication_type' => isset($_REQUEST['syndication_type']) ? $_REQUEST['syndication_type'] : '',
            'syndication_flow' => isset($_REQUEST['syndication_flow']) ? $_REQUEST['syndication_flow'] : 0,
            'remote_post' => [
                'id' => $_REQUEST['attached-post-id'],
                'name' => $_REQUEST['attached-post-name'],
                'slug' => $_REQUEST['attached-post-slug'],
            ]
        ];
        update_post_meta($post_id, 'syndication_data', json_encode($postSyndicationData));
        return true;
    }

    public function send_post_content($post_id, $post, $update)
    {
        error_log('post type: ' . $post->post_type);
        $syndicationOptions = get_post_meta($post_id, 'syndication_data', true);

        if ($syndicationOptions['syndication_flow'] == 0) {

            $dataToUpdate = $this->format_post_data($post_id, $post);

            $this->requests->trigger_post_sync_request($dataToUpdate);
        }

    }

    public function format_post_data($post_id, $post)
    {
        $dataToUpdate = [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_name' => $post->post_name,
            'post_excerpt' => $post->post_excerpt,
            'post_content' => $post->post_content,
            'post_status' => $post->post_status,
            'post_password' => $post->post_password,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'post_parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'update_post' => 0
        ];

        $metaData = get_post_meta($post_id);

        $assembledMeta = [];

        foreach ($metaData as $metaKey => $metaValue) {
            if ($metaKey == 'syndication_data') {
                $tempData = json_decode($metaValue[0], true);
                if (isset($tempData['remote_post'])) {
                    $dataToUpdate['update_post'] = $tempData['remote_post']['id'];
                }
            }
            $assembledMeta[$metaKey] = $metaValue[0];
        }

        $dataToUpdate['meta_data'] = $assembledMeta;


        return $dataToUpdate;
    }
}