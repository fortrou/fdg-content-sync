<?php
if ( ! defined( 'ABSPATH' ) ) die;
class FDG_Syndicator_Ajax_Actions {

    private $requests;

    public function __construct($requests) {
        $this->requests = $requests;
        $this->run_actions();


    }

    public function run_actions()
    {
        add_action('wp_ajax_fdg_sync_search_posts', [$this, 'search_posts']);
        add_action('wp_ajax_nopriv_fdg_sync_search_posts', [$this, 'search_posts']);
    }

    public function search_posts()
    {
        $post_title = $_POST['search'];

        $data = $this->requests->trigger_search_request(htmlspecialchars($post_title));

        $postsLayout = '';

        foreach ($data['list'] as $post) {
            $postsLayout .= sprintf('<li data-id="%s" data-name="%s" data-slug="%s">%s</li>', $post['id'], $post['title'], $post['name'], $post['title']);
        }
        $pages = '';

        if ($data['counter'] > 0) {
            for ($i = 1; $i <= $data['counter']; $i++) {
                $pages .= sprintf('<li class="page-item" data-page="%s">%s</li>', $i, $i);
            }
        }

        $pages = '<ul class="pagination">' . $pages . '</ul>';
        wp_send_json_success([
            'list' => $postsLayout,
            'pages' => $pages
        ]);
        wp_die();
    }
}