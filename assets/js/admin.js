/**
 * SCMB Admin JavaScript
 */

(function($) {
    'use strict';

    // CodeMirror instances
    let htmlEditor, cssEditor, jsEditor;

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initCodeEditors();
        initPreview();
        enhanceFieldBuilder();
    });

    /**
     * Initialize CodeMirror editors for HTML, CSS, JS
     */
    function initCodeEditors() {
        // HTML Editor
        const htmlTextarea = $('#smart-cf-module_html');
        if (htmlTextarea.length) {
            htmlEditor = wp.codeEditor.initialize(htmlTextarea[0], {
                codemirror: {
                    mode: 'htmlmixed',
                    lineNumbers: true,
                    lineWrapping: true,
                    theme: 'default',
                    autoCloseTags: true,
                    matchBrackets: true,
                    indentUnit: 2,
                    tabSize: 2,
                }
            });

            // Add label
            htmlTextarea.before('<label class="scmb-code-label"><strong>HTML Template</strong> - Use {{field_name}} for variables</label>');
        }

        // CSS Editor
        const cssTextarea = $('#smart-cf-module_css');
        if (cssTextarea.length) {
            cssEditor = wp.codeEditor.initialize(cssTextarea[0], {
                codemirror: {
                    mode: 'css',
                    lineNumbers: true,
                    lineWrapping: true,
                    theme: 'default',
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    indentUnit: 2,
                    tabSize: 2,
                }
            });

            cssTextarea.before('<label class="scmb-code-label"><strong>CSS Styles</strong></label>');
        }

        // JavaScript Editor
        const jsTextarea = $('#smart-cf-module_js');
        if (jsTextarea.length) {
            jsEditor = wp.codeEditor.initialize(jsTextarea[0], {
                codemirror: {
                    mode: 'javascript',
                    lineNumbers: true,
                    lineWrapping: true,
                    theme: 'default',
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    indentUnit: 2,
                    tabSize: 2,
                }
            });

            jsTextarea.before('<label class="scmb-code-label"><strong>JavaScript</strong></label>');
        }
    }

    /**
     * Initialize preview functionality
     */
    function initPreview() {
        $('#scmb-preview-btn').on('click', function(e) {
            e.preventDefault();
            
            const $output = $('#scmb-preview-output');
            const $btn = $(this);
            
            // Get template content
            let htmlTemplate = '';
            if (htmlEditor && htmlEditor.codemirror) {
                htmlTemplate = htmlEditor.codemirror.getValue();
            }
            
            let cssContent = '';
            if (cssEditor && cssEditor.codemirror) {
                cssContent = cssEditor.codemirror.getValue();
            }
            
            // Get fields
            const fields = getModuleFields();
            
            // Replace template variables with sample data
            let renderedHtml = htmlTemplate;
            fields.forEach(field => {
                const placeholder = getSampleDataForField(field);
                const regex = new RegExp('{{\\s*' + field.name + '\\s*}}', 'g');
                renderedHtml = renderedHtml.replace(regex, placeholder);
            });
            
            // Build preview
            let preview = '<div class="scmb-preview-wrapper">';
            
            if (cssContent) {
                preview += '<style>' + cssContent + '</style>';
            }
            
            preview += renderedHtml;
            preview += '</div>';
            
            // Show preview
            $output.html(preview).slideDown();
            
            // Scroll to preview
            $('html, body').animate({
                scrollTop: $output.offset().top - 100
            }, 500);
        });
    }

    /**
     * Get module fields from form
     */
    function getModuleFields() {
        const fields = [];
        
        $('.smart-cf-meta-box-repeat-tables .smart-cf-meta-box-table').each(function() {
            const $table = $(this);
            const fieldName = $table.find('input[name*="[field_name]"]').val();
            const fieldLabel = $table.find('input[name*="[field_label]"]').val();
            const fieldType = $table.find('select[name*="[field_type]"]').val();
            
            if (fieldName) {
                fields.push({
                    name: fieldName,
                    label: fieldLabel || fieldName,
                    type: fieldType || 'text'
                });
            }
        });
        
        return fields;
    }

    /**
     * Get sample data for field preview
     */
    function getSampleDataForField(field) {
        const samples = {
            'text': 'Sample Text',
            'textarea': 'Sample paragraph text with multiple lines.\nThis is line two.\nThis is line three.',
            'wysiwyg': '<p>Sample <strong>rich text</strong> content with <em>formatting</em>.</p>',
            'image': '<img src="https://via.placeholder.com/400x300" alt="Sample Image" />',
            'select': 'Option 1',
            'checkbox': 'checked'
        };
        
        return samples[field.type] || field.label;
    }

    /**
     * Enhance field builder UI
     */
    function enhanceFieldBuilder() {
        // Add helpful tooltips
        $('input[name*="[field_name]"]').attr('placeholder', 'e.g., title, content, image_url');
        $('input[name*="[field_label]"]').attr('placeholder', 'e.g., Title, Content, Image');
        
        // Show template variable when field name is entered
        $(document).on('input', 'input[name*="[field_name]"]', function() {
            const $input = $(this);
            const fieldName = $input.val();
            const $wrapper = $input.closest('tr');
            
            // Remove existing helper
            $wrapper.find('.scmb-field-helper').remove();
            
            if (fieldName) {
                const helper = $('<div class="scmb-field-helper" style="margin-top: 5px; padding: 8px; background: #f0f6fc; border-left: 3px solid #2271b1; font-size: 12px;">' +
                    '<strong>Template variable:</strong> <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">{{' + fieldName + '}}</code>' +
                    '</div>');
                $input.closest('td').append(helper);
            }
        });
        
        // Trigger on page load for existing fields
        $('input[name*="[field_name]"]').trigger('input');
    }

    /**
     * Auto-save warning
     */
    let contentChanged = false;
    
    // Track changes in editors
    if (htmlEditor && htmlEditor.codemirror) {
        htmlEditor.codemirror.on('change', function() {
            contentChanged = true;
        });
    }
    
    if (cssEditor && cssEditor.codemirror) {
        cssEditor.codemirror.on('change', function() {
            contentChanged = true;
        });
    }
    
    if (jsEditor && jsEditor.codemirror) {
        jsEditor.codemirror.on('change', function() {
            contentChanged = true;
        });
    }
    
    // Warn before leaving
    $(window).on('beforeunload', function() {
        if (contentChanged) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    // Reset flag on save
    $('#post').on('submit', function() {
        contentChanged = false;
    });

})(jQuery);