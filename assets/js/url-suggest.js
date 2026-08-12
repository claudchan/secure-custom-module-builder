/**
 * Link suggestion autocomplete for ACF URL fields (top-level and repeater sub-fields).
 */
(function($) {
    'use strict';

    if (typeof scmbUrlSuggest === 'undefined') {
        return;
    }

    var URL_FIELD_SELECTOR = '.acf-field[data-type="url"] input[type="url"], .acf-field[data-type="url"] input[type="text"]';
    var MIN_CHARS = parseInt(scmbUrlSuggest.minChars, 10) || 3;
    var debounceTimer = null;
    var currentRequest = null;
    var $activeInput = null;
    var $list = null;

    function getList() {
        if ($list) {
            return $list;
        }

        $list = $('<ul class="scmb-url-suggest-list" role="listbox"></ul>').appendTo(document.body);

        $list.on('mousedown', 'li[data-url]', function(e) {
            e.preventDefault();
            selectSuggestion($(this).data('url'), $(this).data('title'));
        });

        return $list;
    }

    function positionList($input) {
        var offset = $input.offset();

        getList().css({
            top: offset.top + $input.outerHeight() + 'px',
            left: offset.left + 'px',
            width: $input.outerWidth() + 'px'
        });
    }

    function openList($input) {
        $activeInput = $input;
        positionList($input);
        getList().show();
        $input.attr('aria-expanded', 'true');
    }

    function closeList() {
        if (currentRequest && currentRequest.abort) {
            currentRequest.abort();
        }

        currentRequest = null;

        if ($list) {
            $list.hide().empty();
        }

        if ($activeInput) {
            $activeInput.attr('aria-expanded', 'false');
        }

        $activeInput = null;
    }

    function renderMessage(message) {
        getList().empty().append($('<li class="scmb-url-suggest-message"></li>').text(message));
    }

    function renderResults($input, results) {
        var $ul = getList().empty();

        if (!results.length) {
            renderMessage(scmbUrlSuggest.i18n.noResults);
            return;
        }

        results.forEach(function(item) {
            var $li = $('<li role="option" tabindex="-1"></li>')
                .attr('data-url', item.url)
                .attr('data-title', item.title);

            $('<span class="scmb-url-suggest-title"></span>').text(item.title).appendTo($li);
            $('<span class="scmb-url-suggest-type"></span>').text(item.type).appendTo($li);
            $('<span class="scmb-url-suggest-url"></span>').text(item.url).appendTo($li);

            $ul.append($li);
        });

        openList($input);
    }

    function selectSuggestion(url, title) {
        if (!$activeInput || !url) {
            closeList();
            return;
        }

        var $input = $activeInput;

        $input.val(url).trigger('input').trigger('change');
        closeList();
        $input.trigger('focus');
    }

    function performSearch($input, term) {
        if (currentRequest && currentRequest.abort) {
            currentRequest.abort();
        }

        renderMessage(scmbUrlSuggest.i18n.searching);
        openList($input);

        currentRequest = $.ajax({
            url: scmbUrlSuggest.ajaxUrl,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'scmb_search_urls',
                nonce: scmbUrlSuggest.nonce,
                search: term
            }
        }).done(function(response) {
            if (!response || !response.success || $input.val() !== term) {
                return;
            }

            renderResults($input, (response.data && response.data.results) || []);
        }).fail(function(jqXHR, textStatus) {
            if (textStatus !== 'abort') {
                closeList();
            }
        });
    }

    function looksLikeResolvedUrl(term) {
        return /^(https?:|mailto:|tel:)/i.test(term) || term.charAt(0) === '#' || term.charAt(0) === '/';
    }

    $(document).on('input.scmbUrlSuggest', URL_FIELD_SELECTOR, function() {
        var $input = $(this);
        var term = $.trim($input.val());

        clearTimeout(debounceTimer);

        if (term.length < MIN_CHARS || looksLikeResolvedUrl(term)) {
            closeList();
            return;
        }

        debounceTimer = setTimeout(function() {
            performSearch($input, term);
        }, 250);
    });

    $(document).on('keydown.scmbUrlSuggest', URL_FIELD_SELECTOR, function(e) {
        if (!$list || !$list.is(':visible')) {
            return;
        }

        var $items = $list.find('li[data-url]');
        var $current = $items.filter('.is-active');
        var index = $items.index($current);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            index = (index + 1) % $items.length;
            $items.removeClass('is-active').eq(index).addClass('is-active');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            index = index <= 0 ? $items.length - 1 : index - 1;
            $items.removeClass('is-active').eq(index).addClass('is-active');
        } else if (e.key === 'Enter') {
            if ($current.length) {
                e.preventDefault();
                selectSuggestion($current.data('url'), $current.data('title'));
            }
        } else if (e.key === 'Escape') {
            closeList();
        }
    });

    $(document).on('blur.scmbUrlSuggest', URL_FIELD_SELECTOR, function() {
        setTimeout(closeList, 150);
    });

    $(document).on('mousedown.scmbUrlSuggest', function(e) {
        if ($list && !$(e.target).closest('.scmb-url-suggest-list').length && !$(e.target).is(URL_FIELD_SELECTOR)) {
            closeList();
        }
    });

    // Capture-phase scroll listener: catches scrolling inside nested containers
    // (e.g. the block editor sidebar), which a bubble-phase/window listener would miss.
    document.addEventListener('scroll', function() {
        if ($activeInput && $list && $list.is(':visible')) {
            positionList($activeInput);
        }
    }, true);

    $(window).on('resize.scmbUrlSuggest', function() {
        if ($activeInput && $list && $list.is(':visible')) {
            positionList($activeInput);
        }
    });
})(jQuery);
