<?php
if ( ! defined( 'ABSPATH' ) ) die;

require_once FDG_CONTENT_SYNDICATOR_PLUGIN_PATH . 'classes/fdg-syndicator-api.php';
require_once FDG_CONTENT_SYNDICATOR_PLUGIN_PATH . 'classes/fdg-syndicator-requests.php';
require_once FDG_CONTENT_SYNDICATOR_PLUGIN_PATH . 'classes/fdg-syndicator-actions.php';
require_once FDG_CONTENT_SYNDICATOR_PLUGIN_PATH . 'classes/fdg-syndicator-ajax-actions.php';
class FDG_App {

    private $api;

    private $requests;

    private $actions;

    private $ajax;

    public function __construct() {
        $this->init();
        $this->run_actions();
    }

    public function init()
    {

        $this->api = new FDG_Syndicator_API();
        $this->requests = new FDG_Syndicator_Requests();
        $this->actions = new FDG_Syndicator_Actions($this->requests, $this->api);
        $this->ajax = new FDG_Syndicator_Ajax_Actions($this->requests);
    }

    public function run_actions()
    {
        add_action( 'admin_init', [$this, 'init_settings_page'] );
        add_action( 'admin_menu', [$this, 'add_main_options_page'] );
        add_action( 'init', [$this, 'gutenberg_register_meta'] );
        add_action('add_meta_boxes', [$this, 'custom_settings_metabox']);
    }

    public function custom_settings_metabox() {
        $postTypes = get_post_types(array('public' => true), 'names');
        foreach ($postTypes as $type) {
            add_meta_box(
                'syndicator_options',
                'Syndicator Settings',
                [$this, 'classic_editor_sync_options'],
                $type,
                'side',
                'high'
            );
        }
    }

    public function classic_editor_sync_options($post) {
        require_once FDG_CONTENT_SYNDICATOR_PLUGIN_PATH . "/templates/single-post-syndicator-options.php";
    }


    public function gutenberg_register_meta() {
        register_post_meta('post', 'my_custom_field', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function add_main_options_page() {
        add_menu_page(
            'FDG Solutions',
            'FDG Options',
            'manage_options',
            'fdgsolutions',
            [$this, 'main_options_page_html']
        );
    }

    public function main_options_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error( 'wporg_messages', 'wporg_message', __( 'Settings Saved', 'wporg' ), 'updated' );
        }
        settings_errors( 'wporg_messages' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'fdgsolutions' );
                do_settings_sections( 'fdgsolutions' );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
    }

    public function init_settings_page() {
        register_setting( 'fdgsolutions', 'fdg_syndicator_options' );

        add_settings_section(
            'fdg_syndicator_options',
            'Syndicator settings', [$this, 'syndicator_settings_callback'],
            'fdgsolutions'
        );

        add_settings_field(
            'syndicator_settings_field',
            __( 'FDG Solutions', 'wporg' ),
            [$this, 'syndicator_settings_fields'],
            'fdgsolutions',
            'fdg_syndicator_options'
        );
    }

    public function syndicator_settings_callback( $args ) {
        ?>
        <p id="<?php echo esc_attr( $args['id'] ); ?>">Lets setup your flow</p>
        <?php
    }

    public function syndicator_settings_fields( $args ) {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option( 'fdg_syndicator_options' );
        require_once FDG_CONTENT_SYNDICATOR_PLUGIN_PATH . "templates/settings-tpl.php";
    }

    public static function get_syndication_flows()
    {
        return [
            0 => 'manual',
            1 => 'automatic',
        ];
    }

    public static function parse_options($base = true)
    {
        $returnSet = [];
        $options = get_option( 'fdg_syndicator_options');

        if ($base) {
            $returnSet = [
                'syndication' => isset($options['syndication_enabled']) ? $options['syndication_enabled'] : false,
                'syndication_flow' => isset($options['syndication_flow']) ? $options['syndication_flow'] : 0,
                'syndication_type' => 'slug',
            ];
        }

        return $returnSet;
    }
}
