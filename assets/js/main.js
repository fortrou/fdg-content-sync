jQuery(document).ready(function($) {
    let searchAjaxCall = null;

    $('input[name = syndication_type]').on('change', function () {
        if ($('input[name = syndication_type]:checked').val() == 'search') {
            $('.setting-line.search-context-line').show(0);
        } else {
            $('.setting-line.search-context-line').hide(0);
        }
    });

    $(document).on('input', '#search-remote-post-classic', function () {
        if ($(this).val().length > 3) {
            let searchTerm = $(this).val();
            let delayTimeout = null;
            if (searchAjaxCall !== null) {
                console.log(searchAjaxCall);
                searchAjaxCall.abort();
            }

            clearTimeout(delayTimeout);

            delayTimeout = setTimeout(function() {
                searchAjaxCall = $.ajax({
                    url: fdgsyncajax.ajax_url,
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        action: 'fdg_sync_search_posts',
                        search: searchTerm,
                        postType: $('#syndicator-post-type').val()
                    },
                    success: function(response) {
                        $('.search-post-box-bottom .search-variants .variants-listing').empty().append(response.data.list)
                        $('.search-post-box-bottom .search-variants .pages').empty().append(response.data.pages)
                    },
                    error: function(xhr) {
                        if (xhr.statusText !== 'abort') {
                            console.error('Ошибка:', xhr.statusText);
                        }
                    }
                });
            }, 500);
        }
    })

    $(document).on('click', '.search-post-box .search-variants .variants-listing li', function () {
        let searchBox = $(this).closest('.search-post-box');
        $('input[name = attached-post-id]').val($(this).data('id'));
        $('input[name = attached-post-name]').val($(this).data('name'));
        $('input[name = attached-post-slug]').val($(this).data('slug'));

        searchBox.find('.post-name').text($(this).data('name'))
        searchBox.toggleClass('active');

        searchBox.find('.variants-listing').empty();
        searchBox.find('.pages').empty();
        searchBox.find('#search-remote-post-classic').val('');
    });

    $(document).on('click', '.search-post-box .search-post-box-top', function () {
        $(this).closest('.search-post-box').toggleClass('active');

        if ($(this).closest('.search-post-box').hasClass('active')) {
            $('#search-remote-post-classic').focus();
        }
    })

    $(document).on('click', '#trigger-post-sync', function () {
        $.ajax({
            url: fdgsyncajax.ajax_url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'fdg_direct_sync_post',
                post_type: $('#syndicator-post-type').val(),
                origin_post: $('#syndicator-origin-post').val(),
                sync_type: $('[name=syndicator-origin-post]:checked').val(),
                sync_post_id: $('#attached-post-id').val(),
                sync_post_name: $('#attached-post-name').val(),
                sync_post_slug: $('#attached-post-slug').val()
            },
            success: function(response) {

            },
            error: function(xhr) {
                if (xhr.statusText !== 'abort') {
                    console.error('Ошибка:', xhr.statusText);
                }
            }
        });
    })
})