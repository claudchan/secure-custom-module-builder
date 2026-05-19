/**
 * SCMB Admin JavaScript
 */

(function($) {
    'use strict';
    
    console.log('SCMB Admin JS Loaded');
    
    // Wait for ACF to be ready
    if (typeof acf !== 'undefined') {
        console.log('ACF detected, waiting for ready event');
        acf.addAction('ready', function() {
            console.log('ACF ready event triggered');
            initCodeMirror();
        });
        
        // Also init when new fields are appended (for repeaters)
        acf.addAction('append', function() {
            console.log('ACF append event triggered');
            initCodeMirror();
        });
    }

    // Fallback for screens where the ACF ready event has already fired.
    $(document).ready(function() {
        initCodeMirror();
    });
    
    /**
     * Initialize CodeMirror for template fields
     */
    function initCodeMirror() {
        console.log('initCodeMirror function called');
        
        // Only on module edit screen
        if (!$('body').hasClass('post-type-scmb_module')) {
            console.log('Not on module edit screen, skipping');
            return;
        }
        
        console.log('On module edit screen');

        initSlugNormalizers();
        
        // HTML Editor
        var htmlField = $('[data-name="module_html"] textarea');
        console.log('HTML field found:', htmlField.length);

        if (htmlField.length) {
            initTemplateSnippets(htmlField);
        }

        // Check if CodeMirror is available
        if (typeof CodeMirror === 'undefined') {
            console.error('SCMB: CodeMirror not loaded');
            return;
        }
        
        console.log('CodeMirror found:', typeof CodeMirror);
        
        if (htmlField.length && !htmlField.data('codemirror-initialized')) {
            console.log('Initializing HTML editor');
            try {
                var htmlEditor = CodeMirror.fromTextArea(htmlField[0], {
                    mode: 'htmlmixed',
                    lineNumbers: true,
                    theme: 'nord',
                    lineWrapping: true,
                    indentUnit: 2,
                    tabSize: 2,
                    indentWithTabs: false,
                    matchBrackets: true,
                    autoCloseTags: true,
                    extraKeys: {
                        "Ctrl-Space": "autocomplete",
                        "Ctrl-J": "toMatchingTag"
                    }
                });
                
                // Sync CodeMirror with textarea
                htmlEditor.on('change', function(cm) {
                    htmlField.val(cm.getValue());
                });
                
                htmlField.data('codemirror-initialized', true);
                htmlField.data('codemirror-instance', htmlEditor);
                initTemplateSnippets(htmlField);
                console.log('HTML editor initialized successfully');
            } catch (e) {
                console.error('Error initializing HTML editor:', e);
            }
        } else if (htmlField.length && htmlField.data('codemirror-instance')) {
            initTemplateSnippets(htmlField);
        }
        
        // CSS Editor
        var cssField = $('[data-name="module_css"] textarea');
        console.log('CSS field found:', cssField.length);
        
        if (cssField.length && !cssField.data('codemirror-initialized')) {
            console.log('Initializing CSS editor');
            try {
                var cssEditor = CodeMirror.fromTextArea(cssField[0], {
                    mode: 'css',
                    lineNumbers: true,
                    theme: 'nord',
                    lineWrapping: true,
                    indentUnit: 2,
                    tabSize: 2,
                    indentWithTabs: false,
                    matchBrackets: true,
                    autoCloseBrackets: true,
                    extraKeys: {
                        "Ctrl-Space": "autocomplete"
                    }
                });
                
                cssEditor.on('change', function(cm) {
                    cssField.val(cm.getValue());
                });
                
                cssField.data('codemirror-initialized', true);
                cssField.data('codemirror-instance', cssEditor);
                console.log('CSS editor initialized successfully');
            } catch (e) {
                console.error('Error initializing CSS editor:', e);
            }
        }
        
        // JavaScript Editor
        var jsField = $('[data-name="module_js"] textarea');
        console.log('JS field found:', jsField.length);
        
        if (jsField.length && !jsField.data('codemirror-initialized')) {
            console.log('Initializing JS editor');
            try {
                var jsEditor = CodeMirror.fromTextArea(jsField[0], {
                    mode: 'javascript',
                    lineNumbers: true,
                    theme: 'nord',
                    lineWrapping: true,
                    indentUnit: 2,
                    tabSize: 2,
                    indentWithTabs: false,
                    matchBrackets: true,
                    autoCloseBrackets: true,
                    extraKeys: {
                        "Ctrl-Space": "autocomplete"
                    }
                });
                
                jsEditor.on('change', function(cm) {
                    jsField.val(cm.getValue());
                });
                
                jsField.data('codemirror-initialized', true);
                jsField.data('codemirror-instance', jsEditor);
                console.log('JS editor initialized successfully');
            } catch (e) {
                console.error('Error initializing JS editor:', e);
            }
        }
        
        // Refresh editors when ACF tabs are switched
        $('.acf-tab-button').on('click', function() {
            setTimeout(function() {
                if (htmlField.data('codemirror-instance')) {
                    htmlField.data('codemirror-instance').refresh();
                }
                if (cssField.data('codemirror-instance')) {
                    cssField.data('codemirror-instance').refresh();
                }
                if (jsField.data('codemirror-instance')) {
                    jsField.data('codemirror-instance').refresh();
                }
            }, 100);
        });
    }

    /**
     * Add a live snippet helper based on the configured module fields.
     */
    function initTemplateSnippets(htmlField) {
        var wrapper = htmlField.closest('[data-name="module_html"]');

        if (!wrapper.length) {
            return;
        }

        if (wrapper.data('scmb-snippets-initialized')) {
            var existingUpdater = wrapper.data('scmb-update-snippets');
            if (typeof existingUpdater === 'function') {
                existingUpdater();
            }
            return;
        }

        var snippetPanel = wrapper.find('.scmb-snippet-panel').first();

        if (!snippetPanel.length) {
            snippetPanel = $(
                '<div class="scmb-snippet-panel" aria-live="polite">' +
                    '<div class="scmb-snippet-panel__header">' +
                        '<strong>Field Snippets</strong>' +
                        '<span>Copy or insert snippets from your Module Fields.</span>' +
                    '</div>' +
                    '<div class="scmb-snippet-list"></div>' +
                '</div>'
            );
            wrapper.find('.acf-input').prepend(snippetPanel);
        }

        wrapper.data('scmb-snippets-initialized', true);

        $(document)
            .off('input.scmbSnippets change.scmbSnippets', '[data-name="module_fields"] input, [data-name="module_fields"] textarea, [data-name="module_fields"] select')
            .on('input.scmbSnippets change.scmbSnippets', '[data-name="module_fields"] input, [data-name="module_fields"] textarea, [data-name="module_fields"] select', updateTemplateSnippets);

        $(document)
            .off('click.scmbSnippetCopy')
            .on('click.scmbSnippetCopy', '.scmb-snippet-copy', function(e) {
                e.preventDefault();
                copySnippet($(this));
            });

        $(document)
            .off('click.scmbSnippetInsert')
            .on('click.scmbSnippetInsert', '.scmb-snippet-insert', function(e) {
                e.preventDefault();
                insertSnippet($(this).data('snippet'));
            });

        wrapper.data('scmb-update-snippets', updateTemplateSnippets);
        updateTemplateSnippets();

        function updateTemplateSnippets() {
            var fields = getModuleFields();
            var list = snippetPanel.find('.scmb-snippet-list');

            list.empty();

            if (!fields.length) {
                list.append('<p class="scmb-snippet-empty">Add fields in Module Fields to generate snippets.</p>');
                return;
            }

            fields.forEach(function(field) {
                list.append(renderSnippetItem(field));
            });
        }

        function getModuleFields() {
            var fields = [];

            $('[data-name="module_fields"] .acf-row:not(.acf-clone)').each(function() {
                var row = $(this);
                var name = normalizeFieldName(row.find('[data-name="field_name"] input').val() || '');
                var type = row.find('[data-name="field_type"] select').val() || 'text';
                var label = $.trim(row.find('[data-name="field_label"] input').val() || name);
                var subFields = row.find('[data-name="field_sub_fields"] textarea').val() || '';

                if (!name) {
                    return;
                }

                fields.push({
                    name: name,
                    type: type,
                    label: label || name,
                    snippet: buildSnippet(name, type, subFields)
                });
            });

            return fields;
        }

        function buildSnippet(name, type, subFieldsRaw) {
            if (type === 'repeater') {
                var subFields = parseRepeaterSubFields(subFieldsRaw);
                var inner = subFields.length
                    ? subFields.map(function(subField) {
                        return '  <div class="' + escapeAttribute(subField.name) + '">{{' + subField.name + '}}</div>';
                    }).join('\n')
                    : '  {{sub_field_name}}';

                return '{{#' + name + '}}\n' +
                    '  <div class="{{#if __first}}active{{/if}}">\n' +
                    inner + '\n' +
                    '  </div>\n' +
                    '{{/' + name + '}}';
            }

            if (type === 'image') {
                return '<img src="{{' + name + '}}" alt="">';
            }

            if (type === 'url') {
                return '<a href="{{' + name + '}}">{{' + name + '}}</a>';
            }

            if (type === 'checkbox' || type === 'true_false') {
                return '{{#if ' + name + '}}\n  \n{{/if}}';
            }

            return '{{' + name + '}}';
        }

        function parseRepeaterSubFields(raw) {
            return raw.split(/\r?\n/)
                .map(function(line) {
                    var parts = line.split('|');
                    return {
                        name: normalizeFieldName(parts[0] || ''),
                        label: $.trim(parts[1] || parts[0] || '')
                    };
                })
                .filter(function(field) {
                    return !!field.name;
                });
        }

        function renderSnippetItem(field) {
            var snippet = field.snippet;
            var item = $('<div class="scmb-snippet-item"></div>');
            var meta = $('<div class="scmb-snippet-item__meta"></div>');
            var actions = $('<div class="scmb-snippet-actions"></div>');

            meta.append($('<span class="scmb-snippet-name"></span>').text(field.label));
            meta.append($('<span class="scmb-snippet-type"></span>').text(field.type));
            item.append(meta);
            item.append($('<code class="scmb-snippet-code"></code>').text(snippet));

            actions.append(
                $('<button type="button" class="button button-small scmb-snippet-insert">Insert</button>')
                    .data('snippet', snippet)
            );
            actions.append(
                $('<button type="button" class="button button-small scmb-snippet-copy">Copy</button>')
                    .data('snippet', snippet)
            );

            item.append(actions);

            return item;
        }

        function insertSnippet(snippet) {
            var editor = htmlField.data('codemirror-instance');

            if (editor) {
                editor.replaceSelection(snippet);
                editor.focus();
                htmlField.val(editor.getValue());
                return;
            }

            htmlField.val(htmlField.val() + snippet);
        }

        function copySnippet(button) {
            var snippet = button.data('snippet');
            var originalText = button.text();

            copyText(snippet).then(function() {
                button.text('Copied');
                setTimeout(function() {
                    button.text(originalText);
                }, 1200);
            });
        }

        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }

            var temp = $('<textarea readonly></textarea>').val(text).css({
                position: 'fixed',
                top: '-9999px',
                left: '-9999px'
            });

            $('body').append(temp);
            temp[0].select();
            document.execCommand('copy');
            temp.remove();

            return $.Deferred().resolve().promise();
        }

        function escapeAttribute(value) {
            return String(value).replace(/[^a-zA-Z0-9_-]/g, '-');
        }
    }

    /**
     * Keep module keys and field names in their allowed formats.
     */
    function initSlugNormalizers() {
        if ($(document).data('scmb-slug-normalizers-initialized')) {
            return;
        }

        $(document).data('scmb-slug-normalizers-initialized', true);

        $(document)
            .off('input.scmbModuleKey keyup.scmbFieldNames change.scmbFieldNames paste.scmbFieldNames blur.scmbFieldNames change.scmbModuleKey paste.scmbModuleKey blur.scmbModuleKey blur.scmbModuleLabel blur.scmbFieldLabels')
            .on('blur.scmbModuleLabel', getModuleLabelSelector(), function() {
                fillModuleKeyFromLabel($(this));
            })
            .on('blur.scmbFieldLabels', '[data-name="field_label"] input', function() {
                fillFieldNameFromLabel($(this));
            })
            .on('input.scmbModuleKey paste.scmbModuleKey', getModuleKeySelector(), function() {
                normalizeInputValue($(this), normalizeModuleKey, {
                    preserveSpaces: true,
                    preserveTrailingHyphen: true
                });
            })
            .on('change.scmbModuleKey blur.scmbModuleKey', getModuleKeySelector(), function() {
                normalizeInputValue($(this), normalizeModuleKey, {
                    preserveSpaces: false,
                    preserveTrailingHyphen: false
                });
            })
            .on('keyup.scmbFieldNames paste.scmbFieldNames', '[data-name="field_name"] input', function() {
                normalizeInputValue($(this), normalizeFieldName, {
                    preserveTrailingUnderscore: true
                });
            })
            .on('change.scmbFieldNames blur.scmbFieldNames', '[data-name="field_name"] input', function() {
                normalizeInputValue($(this), normalizeFieldName, {
                    preserveTrailingUnderscore: false
                });
            })
            .on('change.scmbFieldNames paste.scmbFieldNames blur.scmbFieldNames', '[data-name="field_sub_fields"] textarea', function() {
                normalizeInputValue($(this), normalizeSubFieldLines, {
                    preserveTrailingUnderscore: false
                });
            });
    }

    function getModuleKeySelector() {
        return '[data-name="module_key"] input[type="text"], [data-key="field_module_key"] input[type="text"], input[name="acf[field_module_key]"]';
    }

    function getModuleLabelSelector() {
        return '[data-name="module_label"] input[type="text"], [data-key="field_module_label"] input[type="text"], input[name="acf[field_module_label]"]';
    }

    function fillModuleKeyFromLabel(labelInput) {
        setTimeout(function() {
            var labelValue = $.trim(labelInput.val() || '');
            var moduleKeyInput = $(getModuleKeySelector()).first();

            if (!labelValue || !moduleKeyInput.length || $.trim(moduleKeyInput.val() || '')) {
                return;
            }

            moduleKeyInput
                .val(normalizeModuleKey(labelValue, {
                    preserveSpaces: false,
                    preserveTrailingHyphen: false
                }))
                .trigger('input')
                .trigger('change');
        }, 0);
    }

    function fillFieldNameFromLabel(labelInput) {
        setTimeout(function() {
            var labelValue = $.trim(labelInput.val() || '');
            var row = labelInput.closest('.acf-row');
            var fieldNameInput = row.find('[data-name="field_name"] input').first();

            if (!labelValue || !fieldNameInput.length || $.trim(fieldNameInput.val() || '')) {
                return;
            }

            fieldNameInput
                .val(normalizeFieldName(labelValue, {
                    preserveTrailingUnderscore: false
                }))
                .trigger('input')
                .trigger('change');
        }, 0);
    }

    function normalizeInputValue(input, normalizer, options) {
        setTimeout(function() {
            var element = input[0];
            var original = input.val() || '';
            var normalized = normalizer(original, options || {});
            var cursorPosition = null;

            if (original !== normalized) {
                if (element && typeof element.selectionStart === 'number') {
                    cursorPosition = normalizer(original.slice(0, element.selectionStart), options || {}).length;
                }

                input.val(normalized).trigger('input');

                if (null !== cursorPosition && element && typeof element.setSelectionRange === 'function') {
                    element.setSelectionRange(cursorPosition, cursorPosition);
                }
            }
        }, 0);
    }

    function normalizeSubFieldLines(value, options) {
        return String(value).split(/\r?\n/).map(function(line) {
            var pipeIndex = line.indexOf('|');
            var fieldName = pipeIndex === -1 ? line : line.slice(0, pipeIndex);
            var rest = pipeIndex === -1 ? '' : line.slice(pipeIndex);

            if (!$.trim(fieldName || '')) {
                return line;
            }

            return normalizeFieldName(fieldName, options || {}) + rest;
        }).join('\n');
    }

    function normalizeFieldName(value, options) {
        options = options || {};

        var normalized = String(value)
            .toLowerCase()
            .replace(/[^a-z0-9_]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_+/g, '');

        if (!options.preserveTrailingUnderscore) {
            normalized = normalized.replace(/_+$/g, '');
        }

        if (normalized && !/^[a-z_]/.test(normalized)) {
            normalized = 'field_' + normalized;
        }

        return normalized;
    }

    function normalizeModuleKey(value, options) {
        options = options || {};

        var normalized = String(value)
            .toLowerCase()
            .replace(options.preserveSpaces ? /[^a-z0-9\-\s]+/g : /\s+/g, options.preserveSpaces ? '' : '-');

        if (options.preserveSpaces) {
            normalized = normalized.replace(/\s+/g, ' ');
        } else {
            normalized = normalized.replace(/[^a-z0-9-]+/g, '');
        }

        normalized = normalized
            .replace(/-+/g, '-')
            .replace(/^-+/g, '');

        if (!options.preserveTrailingHyphen) {
            normalized = normalized.replace(/-+$/g, '');
        }

        return normalized;
    }
    
    /**
     * Add helpful tooltips and enhancements
     */
    $(document).ready(function() {
        if (!$('body').hasClass('post-type-scmb_module')) {
            return;
        }
        
        // Add template variable helper
        // addTemplateHelper();
    });
    
    /**
     * Add template variable helper
     */
    function addTemplateHelper() {
        var htmlField = $('[data-name="module_html"]');
        if (htmlField.length) {
            var helpText = $('<div class="scmb-help-text"></div>')
                .html('Use <code>{{field_name}}</code> for simple fields or <code>{{#field_name}} ... {{/field_name}}</code> for repeaters');
            htmlField.find('.acf-label').append(helpText);
        }
    }
    
})(jQuery);
