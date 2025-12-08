<?php

function myplugin_enqueue_assets() {
    wp_enqueue_script(
        'myplugin-gutenberg-panel',
        FDG_CONTENT_SYNDICATOR_PLUGIN_URL . 'dist/gutenberg-panel.bundle.js', // Путь к вашему JavaScript файлу
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose'],
    );

    wp_enqueue_style(
        'myplugin-gutenberg-panel-style',
        FDG_CONTENT_SYNDICATOR_PLUGIN_URL . '/assets/build/css/index.css', // Путь к вашему CSS файлу
        [],
        filemtime(get_template_directory() . 'css/gutenberg-panel.css')
    );
}
add_action('enqueue_block_editor_assets', 'myplugin_enqueue_assets');

add_action('admin_enqueue_scripts', 'fd_syndicator_admin_scripts');
function fd_syndicator_admin_scripts() {
    wp_enqueue_script(
        'fdsyndicator_scripts',
        FDG_CONTENT_SYNDICATOR_PLUGIN_URL . '/assets/js/main.js',
        ['jquery'],
        filemtime(FDG_CONTENT_SYNDICATOR_PLUGIN_URL . 'assets/js/main.js')
    );
    wp_localize_script( 'fdsyndicator_scripts', 'fdgsyncajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ), // URL для обработки AJAX-запросов
        'nonce'    => wp_create_nonce( 'my_ajax_nonce' ) // Защита через nonce
    ));
    wp_enqueue_style(
        'fdsyndicator-style',
        FDG_CONTENT_SYNDICATOR_PLUGIN_URL . '/assets/build/css/index.css',
        [],
        filemtime(FDG_CONTENT_SYNDICATOR_PLUGIN_URL . '/assets/build/css/index.css')
    );
}