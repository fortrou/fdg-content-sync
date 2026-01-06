<?php
namespace FdgSync;
use FdgSync\FdgSyndicatorRequests;
if ( ! defined( 'ABSPATH' ) ) die;
class FdgSyndicatorActions {

    private $requests;

    private $api;
    public function __construct() {
        $this->requests = new FdgSyndicatorRequests();
        $this->api = new FdgSyndicatorApi();

        $this->run_actions();
    }

    private function run_actions()
    {
        add_action('rest_after_insert', [$this, 'update_syndication_meta'], 10, 3);
        add_action('save_post', [$this, 'process_meta_fields'], 99, 3);
        //add_action('save_post', [$this, 'send_post_content'], 100, 3);

        add_action('edit_term', [$this, 'send_term_content'], 100, 3);

        // handling queue requests
        add_action('fdg_sync_queue_handle_process_content_scrape', [$this, 'handle_process_content_scrape'], 10, 3);
        add_action('fdg_sync_queue_handle_process_media_scrape', [$this, 'handle_process_media_scrape'], 10, 3);
        add_action('fdg_sync_queue_handle_copy_media', [$this, 'handle_copy_media'], 10, 3);
        add_action('fdg_sync_queue_handle_heartbeat_check', [$this, 'handle_heartbeat_check'], 10, 3);
        add_action('fdg_sync_queue_handle_zip_scan', [$this, 'handle_zip_scan'], 10, 3);
        add_action('fdg_sync_queue_handle_zip_simple', [$this, 'handle_zip_simple'], 10, 3);
        add_action('fdg_sync_queue_handle_zip_pcl', [$this, 'handle_zip_pcl'], 10, 3);
        add_action('fdg_sync_queue_handle_archive_post', [$this, 'handle_archive_post'], 10, 3);
        add_action('fdg_sync_queue_handle_push_archive_chunk', [$this, 'push_archive_chunk'], 10, 3);
    }

    public function handle_process_content_scrape($task, $payload)
    {
        global $wp_filesystem;

        if (! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $post_id = $payload['post_id'];
        $post = get_post($post_id);
        $dataToUpdate = $this->format_post_data($post_id, $post);

        $filesList = [];
        if (!empty($dataToUpdate['content_media'])) {
            foreach ($dataToUpdate['content_media'] as $key => $media) {
                $splitted = explode(':', $media);
                $mediaFile = get_attached_file($splitted[3]);
                $filesList[] = $mediaFile;
            }
        }

        if (!empty($dataToUpdate['meta_media'])) {
            foreach ($dataToUpdate['meta_media'] as $key => $media) {
                $mediaFile = get_attached_file($media['id']);
                $filesList[] = $mediaFile;
            }
        }

        $tempFolderName = 'post-' . $post_id . '-' . time();
        $uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/send/' . $tempFolderName;

        $wp_filesystem->mkdir($uploads_dir, FS_CHMOD_DIR);
        $filePut = $wp_filesystem->put_contents($uploads_dir . '/content.json', wp_json_encode($dataToUpdate, JSON_UNESCAPED_UNICODE), FS_CHMOD_FILE);

        FdgSyndicatorQueue::instance()->enqueue('process_media_scrape',
        [
            'post_id' => $post_id,
            'folder_name' => $tempFolderName,
            'files_list' => $filesList
        ],
        10, null, null, 5);
    }

    public function handle_process_media_scrape($task, $payload)
    {
        global $wp_filesystem;

        if (! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $folderName = $payload['folder_name'];
        $filesList = $payload['files_list'];
        $uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/send/' . $folderName;
        if (!empty($filesList)) {
            foreach ($filesList as $item) {
                $fileData = pathinfo($item);
                FdgSyndicatorQueue::instance()->enqueue('copy_media',
                [
                    'file' => $item,
                    'folder_name' => $uploads_dir . '/' . $fileData['basename'],
                ],
                10, null, null, 5);
            }
            FdgSyndicatorQueue::instance()->enqueue('heartbeat_check',
            [
                'files' => $filesList,
                'folder_name' => $folderName,
                'type' => 'media_check'
            ],
            10, null, null, 5);
        }
        

    }

    public function handle_heartbeat_check($task, $payload)
    {
        $type = $payload['type'];
        if ($type == 'media_check') {

            $folderName = $payload['folder_name'];
            FdgSyndicatorQueue::instance()->enqueue('zip_scan',
                [
                    'folder_name' => $folderName,
                ],
                10, null, null, 5);

        }
    }

    public function handle_zip_scan($task, $payload)
    {

        global $wp_filesystem;

        if (! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $folderName = $payload['folder_name'];
        $uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/send/' . $folderName;
        $zipPath = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/send/' . $folderName . '.zip';

        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploads_dir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            $filesAggregated = 0;
            $filesPull = [];
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filesize = ceil($file->getSize() / 8);
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($uploads_dir) + 1);
                    if (($filesAggregated + $filesize) > 22500 && empty($filesPull)) {
                        $filesPull[] = [
                            'filepath' => $filePath,
                            'relative' => $relativePath
                        ];
                        // release files
                        FdgSyndicatorQueue::instance()->enqueue('zip_simple',
                        [
                            'file' => $zipPath,
                            'filePull' => $filesPull,
                        ],
                        10, null, null, 5);
                        $filesAggregated = 0;
                        $filesPull = [];
                    } else {
                        if (($filesAggregated + $filesize) > 22500) {
                            // release files
                            FdgSyndicatorQueue::instance()->enqueue('zip_simple',
                            [
                                'file' => $zipPath,
                                'filePull' => $filesPull,
                            ],
                            10, null, null, 5);
                            $filesAggregated = 0;
                            $filesPull = [[
                                'filepath' => $filePath,
                                'relative' => $relativePath
                            ]];
                        } else {
                            $filesPull[] = [
                                'filepath' => $filePath,
                                'relative' => $relativePath
                            ];
                        }
                    }
                }
            }
            if (!empty($filesPull)) {
                FdgSyndicatorQueue::instance()->enqueue('zip_simple',
                [
                    'file' => $zipPath,
                    'filePull' => $filesPull,
                ],
                10, null, null, 5);
            }

            //$wp_filesystem->rmdir($uploads_dir, true);
        } else {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            $archive = new \PclZip($zipPath);

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploads_dir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            $filesToZip = [];
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $filesToZip[] = $filePath;
                }
            }

            $archive->create(implode(',', $filesToZip), PCLZIP_OPT_REMOVE_PATH, ABSPATH);
            $wp_filesystem->rmdir($uploads_dir, true);

        }
    }

    public function handle_zip_simple($task, $payload)
    {
        $zip = new \ZipArchive();
        $zip->open($payload['file'], \ZipArchive::CREATE);
        if (!empty($payload['filePull'])) {
            foreach ($payload['filePull'] as $file) {
                $zip->addFile($file['filepath'], $file['relative']);
            }
        }
        $zip->close();
    }

    public function handle_zip_pcl($task, $payload)
    {

    }

    public function handle_copy_media($task, $payload)
    {
        global $wp_filesystem;

        if (! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $file = $payload['file'];
        $folder = $payload['folder_name'];
        $imagePut = $wp_filesystem->copy($file, $folder);
    }

    public function handle_archive_post()
    {
        global $wp_filesystem;

        if (! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $zipPath = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/send/' . $tempFolderName . '.zip';
        if ($wp_filesystem->exists($zipPath)) {
            $size = filesize($zipPath);
            $chuncks = ceil($size / FDG_CONTENT_SYNDICATOR_CHUNK_SIZE);
            $setupChunks = [];
            if ($chuncks && $chuncks > 0) {
                for($i = 1; $i <= $chuncks; $i++) {
                    $setupChunks[] = FdgSyndicatorQueue::instance()->enqueue('sync_post_chunk',
                        [
                            'total_chuncks_amount' => $chuncks,
                            'post_archive_base' => $tempFolderName . '.zip',
                            'post_id' => $post_id,
                            'current_chunck' => $i,
                        ],
                        10, null, null, 5);
                }

                $setupChunks[] = FdgSyndicatorQueue::instance()->enqueue('sync_post_finalize',
                    [
                        'post_archive_base' => $tempFolderName . '.zip',
                        'post_id' => $post_id,
                    ],
                    20, null, null, 5);
            }
        }
    }

    public function push_archive_chunk()
    {

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

    public function update_syndication_meta($post, $request, $creating) {
        if ($this->skip_request($post->ID, $post)) return false;
        $type = $post->post_type;

        $ignore = ['revision', 'nav_menu_item', 'wp_template', 'wp_template_part'];
        if (in_array($type, $ignore)) {
            return;
        }

        $params = $request->get_params();

        if (!isset($params['sync_meta_nonce']) ||
            !wp_verify_nonce($params['sync_meta_nonce'], 'sync_meta_action')) {
            return;
        }

        $data = [
            'syndication'      => !empty($params['enable_syndication']),
            'syndication_type' => $params['syndication_type'] ?? '',
            'syndication_flow' => intval($params['syndication_flow'] ?? 0),
            'remote_post' => [
                'id'   => $params['attached-post-id'] ?? '',
                'name' => $params['attached-post-name'] ?? '',
                'slug' => $params['attached-post-slug'] ?? '',
            ]
        ];

        update_post_meta($post->ID, 'syndication_data', wp_json_encode($data));

    }


    public function process_meta_fields($post_id, $post)
    {
        if ($this->skip_request($post_id, $post)) return false;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_status === 'auto-draft') return;

        if (!isset($_POST['sync_meta_nonce']) ||
            !wp_verify_nonce($_POST['sync_meta_nonce'], 'sync_meta_action')) {
            return;
        }

        $data = [
            'syndication'      => isset($_POST['enable_syndication']),
            'syndication_type' => $_POST['syndication_type'] ?? '',
            'syndication_flow' => intval($_POST['syndication_flow'] ?? 0),
            'remote_post' => [
                'id'   => $_POST['attached-post-id'] ?? '',
                'name' => $_POST['attached-post-name'] ?? '',
                'slug' => $_POST['attached-post-slug'] ?? '',
            ]
        ];

        update_post_meta($post_id, 'syndication_data', wp_json_encode($data));
    }

    public function prepare_post_content($post_id, $post)
    {
        if ($this->skip_request($post_id, $post)) return false;
        global $wp_filesystem;

        if (! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $syndicationOptions = get_post_meta($post_id, 'syndication_data', true);
        if (!empty($syndicationOptions)) {
            $syndicationOptions = json_decode($syndicationOptions, true);
            if (isset($syndicationOptions['syndication_flow']) && $syndicationOptions['syndication_flow'] == 0) {

                FdgSyndicatorQueue::instance()->enqueue('process_content_scrape',
                [
                    'post_id' => $post_id,
                ],
                10, null, null, 5);

                $dataToUpdate = $this->format_post_data($post_id, $post);

                //var_dump($dataToUpdate);

                $filesList = [];
                if (!empty($dataToUpdate['content_media'])) {
                    foreach ($dataToUpdate['content_media'] as $key => $media) {
                        $splitted = explode(':', $media);
                        $mediaFile = get_attached_file($splitted[3]);
                        $filesList[] = $mediaFile;
                    }
                }

                if (!empty($dataToUpdate['meta_media'])) {
                    foreach ($dataToUpdate['meta_media'] as $key => $media) {
                        $mediaFile = get_attached_file($media['id']);
                        $filesList[] = $mediaFile;
                    }
                }

                $tempFolderName = 'post-' . $post_id . '-' . time();
                $uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/send/' . $tempFolderName;

                $wp_filesystem->mkdir($uploads_dir, FS_CHMOD_DIR);
                $filePut = $wp_filesystem->put_contents($uploads_dir . '/content.json', wp_json_encode($dataToUpdate, JSON_UNESCAPED_UNICODE), FS_CHMOD_FILE);
                if (!empty($filesList)) {
                    foreach ($filesList as $item) {
                        $fileData = pathinfo($item);
                        $imagePut = $wp_filesystem->copy($item, $uploads_dir . '/' . $fileData['basename']);
                    }
                }

                $zipPath = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/send/' . $tempFolderName . '.zip';

                if (class_exists('ZipArchive')) {
                    $zip = new \ZipArchive();
                    $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($uploads_dir),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = substr($filePath, strlen($uploads_dir) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                    $zip->close();
                    $wp_filesystem->rmdir($uploads_dir, true);
                } else {
                    require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
                    $archive = new \PclZip($zipPath);

                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($uploads_dir),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    $filesToZip = [];
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $filesToZip[] = $filePath;
                        }
                    }

                    $archive->create(implode(',', $filesToZip), PCLZIP_OPT_REMOVE_PATH, ABSPATH);
                    $wp_filesystem->rmdir($uploads_dir, true);

                }
                if ($wp_filesystem->exists($zipPath)) {
                    $size = filesize($zipPath);
                    $chuncks = ceil($size / FDG_CONTENT_SYNDICATOR_CHUNK_SIZE);
                    $setupChunks = [];
                    if ($chuncks && $chuncks > 0) {
                        for($i = 1; $i <= $chuncks; $i++) {
                            $setupChunks[] = FdgSyndicatorQueue::instance()->enqueue('sync_post_chunk',
                            [
                                'total_chuncks_amount' => $chuncks,
                                'post_archive_base' => $tempFolderName . '.zip',
                                'post_id' => $post_id,
                                'current_chunck' => $i,
                            ],
                            10, null, null, 5);
                        }

                        $setupChunks[] = FdgSyndicatorQueue::instance()->enqueue('sync_post_finalize',
                            [
                                'post_archive_base' => $tempFolderName . '.zip',
                                'post_id' => $post_id,
                            ],
                            20, null, null, 5);
                    }
                }
                //$fileMaster = new FdgFileProcess();
                //$chunkDemo = $fileMaster->get_chunk($tempFolderName . '.zip', 5);



                /*die;
                error_log('post type: ' . $post->post_type);
                error_log('POST ID: ' . $post_id);
                error_log('POST CONTENT UPDATE');
                $response = $this->requests->trigger_post_sync_request($dataToUpdate);
                if ($response["status"] === true) {
                    $syndicationOptions = get_post_meta($post_id, 'syndication_data', true);
                    if (!empty($syndicationOptions)) {
                        $syndicationOptions = json_decode($syndicationOptions, true);
                        $syndicationOptions['remote_post'] = $response['post'];
                    } else {
                        $syndicationOptions = [
                            'syndication'      => true,
                            'syndication_type' => 'search',
                            'syndication_flow' => 0,
                            'remote_post' => $response['post'],
                        ];
                    }
                    update_post_meta($post_id, 'syndication_data', wp_json_encode($syndicationOptions));
                }*/
                //var_dump($response);
            }
        }
    }

    public function detect_builder($sortedFields)
    {
        if (isset($sortedFields['meta_data']['_elementor_data'])) {
            return "elementor";
        } else if (strpos($sortedFields['post_content'], '<!-- wp:') !== false) {
            return "gutenberg";
        } else {
            return "classic";
        }
    }

    public function init_media_queue($listOfIds)
    {

    }

    public function parse_media_reference($value)
    {
        if (is_numeric($value) && wp_attachment_is_image((int) $value)) {
            return (int) $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            // Проверка: это ли URL из wp-uploads
            $upload_dir = wp_upload_dir();
            if (strpos($value, $upload_dir['baseurl']) === 0) {
                $id = attachment_url_to_postid($value);
                return $id ?: $value; // если не найден — оставить URL
            }
        }

        if (is_array($value)) {
            // например, ['id' => 123], ['url' => ''], и т.п.
            if (isset($value['id']) && wp_attachment_is_image($value['id'])) {
                return (int) $value['id'];
            } elseif (isset($value['url'])) {
                return resolve_media_reference($value['url']);
            }
        }

        return false;
    }

    public function resolve_media_from_text($html)
    {
        $urls = [];
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches);
        if (!empty($matches[1])) {
            $urls = array_merge($urls, $matches[1]);
        }

        preg_match_all('/<source[^>]+src=["\']([^"\']+)["\']/', $html, $matches2);
        if (!empty($matches2[1])) {
            $urls = array_merge($urls, $matches2[1]);
        }

        return array_unique($urls);
    }

    public function resolve_media_from_gutenberg_html($html)
    {
        $result = [];

        preg_match_all(
            '/<!--\s*wp:image\s+(\{[^}]*\})\s*-->/',
            $html,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[1] as $index => [$json, $offset]) {

            $decoded = json_decode($json, true);
            if (empty($decoded['id'])) {
                continue;
            }

            $start = $matches[0][$index][1] + strlen($matches[0][$index][0]);

            $end = isset($matches[0][$index + 1])
                ? $matches[0][$index + 1][1]
                : strlen($html);

            $blockHtml = substr($html, $start, $end - $start);

            preg_match(
                '/<img[^>]+src="([^"]+)"/',
                $blockHtml,
                $imgMatch
            );

            $result[] = [
                'id'   => $decoded['id'],
                'json' => $decoded,
                'src'  => $imgMatch[1] ?? null,
            ];
        }

        return $result;
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

        $termsData = $this->collect_post_terms_data($post_id);

        $dataToUpdate['terms'] = $termsData;

        $metaData = get_post_meta($post_id);

        $assembledMeta = [];

        foreach ($metaData as $metaKey => $metaValue) {
            if ($metaKey == 'syndication_data') {
                $tempData = json_decode($metaValue[0], true);
                if (isset($tempData['remote_post'])) {
                    $dataToUpdate['update_post'] = $tempData['remote_post']['id'];
                }
            }

            if ((strpos($metaValue[0], 'field_') !== false && strpos($metaKey, '_') !== false) || strpos($metaKey, '_edit') !== false) continue;
            if (strpos($metaKey, '_elementor_')) {
                do_action('prepare_elementor_queue_sync');
                $assembledMeta[$metaKey] = apply_filters('prepare_elementor_field_sync', $metaValue[0], $metaKey);
            } else {
                $assembledMeta[$metaKey] = $metaValue[0];
            }

        }

        $dataToUpdate['meta_data'] = $assembledMeta;

        /*
         * Parse for medias
         * */

        $dataToUpdate = $this->collect_media_from_page($dataToUpdate);


        //var_dump($dataToUpdate);

        return $dataToUpdate;
    }

    public function collect_media_from_page($data)
    {

        $builder = $this->detect_builder($data);
        if ($builder == "classic") {
            $contentMedia = $this->resolve_media_from_text($data["post_content"]);

            if (!empty($contentMedia)) {
                $resortedMedias = [];
                foreach ($contentMedia as $media) {
                    $mediaId = $this->parse_media_reference($media);
                    $resortedMedias[$media] = "fdgs:media-replacement:" . $mediaId;
                }
            }
        } else if ($builder == "gutenberg") {
            $contentMedia = $this->resolve_media_from_gutenberg_html($data["post_content"]);

            $resortedMedias = [];
            foreach ($contentMedia ?? [] as $item) {
                $resortedMedias[$item['src']] = "fdgs:media-replacement:" . $item['sizeSlug'] . ':' . $item['id'];
            }
        }
        if (!empty($resortedMedias)) {
            $data["post_content"] = str_replace(array_keys($resortedMedias), array_values($resortedMedias), $data["post_content"]);
        }

        $data['content_media'] = $resortedMedias;
        $metaFieldImages = [];
        if (!empty($data['meta_data'])) {
            foreach ($data['meta_data'] as $metaKey => $metaValue) {
                if (is_numeric($metaValue)) {
                    $media = $this->parse_media_reference($metaValue);
                    if ($media == $metaValue) {
                        $metaFieldImages[] = [
                            'id' => $media,
                            'meta_key' => $metaKey,
                        ];
                    }
                }
            }
        }

        $data['meta_media'] = $metaFieldImages;
        return $data;
    }

    public function collect_post_terms_data($post_id)
    {
        $termsCollection = [];

        $taxonomies = get_post_taxonomies($post_id);

        foreach ($taxonomies ?? [] as $taxonomy) {
            $terms = wp_get_post_terms($post_id, $taxonomy);
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    $termMeta = $this->filter_term_meta($term->term_id);

                    $termsCollection[$taxonomy][] = [
                        'term_id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'description' => $term->description,
                        'meta' => $termMeta
                    ];
                }
            }
        }

        return $termsCollection;
    }

    public function filter_term_meta($term_id)
    {
        $termMeta = get_term_meta($term_id);
        $assembledMeta = [];
        foreach ($termMeta ?? [] as $metaKey => $metaValue) {
            if ($metaKey == 'syndication_data') {
                $tempData = json_decode($metaValue[0], true);
                if (isset($tempData['remote_post'])) {
                    $dataToUpdate['update_post'] = $tempData['remote_post']['id'];
                }
            }

            if ((strpos($metaValue[0], 'field_') !== false && strpos($metaKey, '_') !== false) || strpos($metaKey, '_edit') !== false) continue;
            $assembledMeta[$metaKey] = $metaValue[0];

        }
        return $assembledMeta;
    }

    public function skip_request($post_id, $post)
    {
        if ($post->post_title == 'Auto Draft' ||
            $post->post_name == 'auto-draft' ||
            $post->post_status == 'auto-draft' ||
            in_array($post->post_type, ['revision', 'nav_menu_item', 'wp_template', 'wp_template_part'])) return true;
        if ($post->post_title == 'Auto Draft' || empty($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return true;
        $sourceHeader = isset($_SERVER['HTTP_X_REFERRER']) ? $_SERVER['HTTP_X_REFERRER'] : '';
        if ($sourceHeader == 'RemoteSyndicatorRequest') {
            return true;
        }

        return false;
    }
}