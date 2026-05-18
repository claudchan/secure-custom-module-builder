(function(wp) {
    'use strict';

    if (!wp || !wp.data || !wp.domReady) {
        return;
    }

    var labels = window.scmbBlockLabels || {};

    function getAllBlocks(blocks) {
        return blocks.reduce(function(allBlocks, block) {
            allBlocks.push(block);

            if (block.innerBlocks && block.innerBlocks.length) {
                return allBlocks.concat(getAllBlocks(block.innerBlocks));
            }

            return allBlocks;
        }, []);
    }

    function decorateBlocks() {
        var editor = wp.data.select('core/block-editor');

        if (!editor || typeof editor.getBlocks !== 'function') {
            return;
        }

        getAllBlocks(editor.getBlocks()).forEach(function(block) {
            var label = labels[block.name];

            if (!label || !block.clientId) {
                return;
            }

            var blockElement = document.querySelector('[data-block="' + block.clientId + '"]');

            if (!blockElement) {
                return;
            }

            blockElement.classList.add('scmb-editor-block');

            var fields = blockElement.querySelector('.acf-block-fields, .acf-fields');

            if (!fields) {
                return;
            }

            if (!blockElement.querySelector('.scmb-editor-block-title')) {
                fields.parentNode.insertBefore(createBlockTitle(block, label, fields), fields);
            }
        });
    }

    function createBlockTitle(block, label, fields) {
        var title = document.createElement('div');
        title.className = 'scmb-editor-block-title';

        var identity = document.createElement('span');
        identity.className = 'scmb-editor-block-title__identity';

        var icon = document.createElement('span');
        icon.className = 'dashicons dashicons-' + (label.icon || 'admin-post');
        icon.setAttribute('aria-hidden', 'true');

        var text = document.createElement('span');
        text.className = 'scmb-editor-block-title__text';
        text.textContent = label.label || block.name.replace(/^acf\//, '');

        var toggle = document.createElement('button');
        toggle.className = 'scmb-editor-block-title__toggle';
        toggle.type = 'button';
        toggle.setAttribute('aria-expanded', 'true');
        toggle.textContent = 'Hide fields';

        toggle.addEventListener('click', function() {
            var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            toggle.textContent = isExpanded ? 'Show fields' : 'Hide fields';
            fields.hidden = isExpanded;
        });

        identity.appendChild(icon);
        identity.appendChild(text);
        title.appendChild(identity);
        title.appendChild(toggle);

        return title;
    }

    wp.domReady(function() {
        decorateBlocks();

        wp.data.subscribe(function() {
            window.requestAnimationFrame(decorateBlocks);
        });

        var observer = new MutationObserver(function() {
            window.requestAnimationFrame(decorateBlocks);
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
})(window.wp);
