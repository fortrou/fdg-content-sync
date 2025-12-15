<?php
$options = FDG_App::parse_options();
$existingMeta = get_post_meta($post->ID, 'syndication_data', true);
$postType = get_post_type($post->ID);
if (!empty ($existingMeta)) {
    $existingMeta = json_decode($existingMeta, true);
}

$mergedSyndicationParams = [
    'enabled_syndication' => $options['syndication'],
    'syndication_type' => $options['syndication_type'],
    'syndication_flow' => $options['syndication_flow'],
];

if (!empty($existingMeta)) {
    $mergedSyndicationParams['enabled_syndication'] = $existingMeta['syndication'];
    $mergedSyndicationParams['syndication_type'] = $existingMeta['syndication_type'];
    $mergedSyndicationParams['syndication_flow'] = $existingMeta['syndication_flow'];
    $mergedSyndicationParams['remote_post'] = $existingMeta['remote_post'];
}

if (empty($mergedSyndicationParams['remote_post'])) {
    $mergedSyndicationParams['remote_post'] = [
        'id' => 0,
        'name' => 'Choose the post',
        'slug' => ''
    ];
}
wp_nonce_field('sync_meta_action', 'sync_meta_nonce');
?>
<div class="syndicator-setting-template">
    <div class="setting-line">
        <label for="">
            <input type="checkbox" name="enable_syndication" <?php if ($mergedSyndicationParams['enabled_syndication']): ?> checked="checked" <?php endif; ?>>
            Syndication enabled?
        </label>
    </div>

    <div class="syndication-configurations" <?php if (!$mergedSyndicationParams['enabled_syndication']): ?> style="display: none;" <?php endif; ?>>
        <input type="hidden" id="syndicator-post-type" value="<?php echo $postType; ?>">
        <div class="setting-line heading-line">Sync type</div>
        <div class="setting-line tabs-switcher-line">
            <input id="sync-by-slug" type="radio" name="syndication_type" value="slug" <?php if ($mergedSyndicationParams['syndication_type'] == 'slug'): ?> checked="checked" <?php endif; ?>>
            <label for="sync-by-slug">By slug?</label>
            <input id="sync-by-search" type="radio" name="syndication_type" value="search" <?php if ($mergedSyndicationParams['syndication_type'] == 'search'): ?> checked="checked" <?php endif; ?>>
            <label for="sync-by-search">By search?</label>
        </div>


        <div class="setting-line search-context-line heading-line" <?php if ($mergedSyndicationParams['syndication_type'] != 'search'): ?> style="display: none" <?php endif; ?>>Search by remote post name</div>
        <div class="setting-line search-context-line" <?php if ($mergedSyndicationParams['syndication_type'] != 'search'): ?> style="display: none" <?php endif; ?>>
            <div class="search-post-box">
                <div class="search-post-box-top">
                    <span class="post-name">
                        <?php echo $mergedSyndicationParams['remote_post']['name']; ?>
                    </span>
                    <input type="hidden" name="attached-post-id" class="attached-post-id" value="<?php echo $mergedSyndicationParams['remote_post']['id']; ?>">
                    <input type="hidden" name="attached-post-name" class="attached-post-name" value="<?php echo $mergedSyndicationParams['remote_post']['name']; ?>">
                    <input type="hidden" name="attached-post-slug" class="attached-post-slug" value="<?php echo $mergedSyndicationParams['remote_post']['slug']; ?>">
                </div>
                <div class="search-post-box-bottom">
                    <div class="search-line">
                        <input type="text" class="search-post-line" id="search-remote-post-classic">
                    </div>
                    <div class="search-variants">
                        <ul class="variants-listing"></ul>
                        <div class="pages"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .setting-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        $('input[name = enable_syndication]').on('change', function () {
            if ($('input[name = enable_syndication]:checked').length) {
                $('.syndication-configurations').show(0);
            } else {
                $('.syndication-configurations').hide(0);
            }
        })
    })
</script>