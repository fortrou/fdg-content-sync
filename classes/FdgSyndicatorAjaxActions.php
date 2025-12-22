<?php
namespace FdgSync;
use FdgSync\FdgSyndicatorRequests;
use FdgSync\FdgSyndicatorActions;
use FdgSync\FdgSyndicatorQueue;

if ( ! defined( 'ABSPATH' ) ) die;
class FdgSyndicatorAjaxActions {

    private $requests;

    public function __construct() {
        $this->requests = new FdgSyndicatorRequests();
        $this->run_actions();


    }

    public function run_actions()
    {
        add_action('wp_ajax_fdg_sync_search_posts', [$this, 'search_posts']);
        add_action('wp_ajax_nopriv_fdg_sync_search_posts', [$this, 'search_posts']);

        add_action('wp_ajax_fdg_direct_sync_post', [$this, 'direct_sync_post']);
    }

    public function direct_sync_post()
    {
        $post_id = $_POST['origin_post'];
        $post = get_post($_POST['origin_post']);

        $actionHolder = new FdgSyndicatorActions();
        $actionHolder->prepare_post_content($post_id, $post);

        FdgSyndicatorQueue::instance()->run_worker();

        wp_send_json_success([
            'message' => "Process started, status will be updated automatically. <br>Don't close the page",
        ]);
    }

    public function search_posts()
    {
        $post_title = $_POST['search'];

        $data = $this->requests->trigger_search_request(htmlspecialchars($post_title), htmlspecialchars($_POST['postType']));

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