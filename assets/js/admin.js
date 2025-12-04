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
    } else {
        // Fallback if ACF is not available
        console.log('ACF not detected, using jQuery ready');
        $(document).ready(function() {
            initCodeMirror();
        });
    }
    
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
        
        // Check if CodeMirror is available
        if (typeof CodeMirror === 'undefined') {
            console.error('SCMB: CodeMirror not loaded');
            return;
        }
        
        console.log('CodeMirror found:', typeof CodeMirror);
        
        // HTML Editor
        var htmlField = $('[data-name="module_html"] textarea');
        console.log('HTML field found:', htmlField.length);
        
        if (htmlField.length && !htmlField.data('codemirror-initialized')) {
            console.log('Initializing HTML editor');
            try {
                var htmlEditor = CodeMirror.fromTextArea(htmlField[0], {
                    mode: 'htmlmixed',
                    lineNumbers: true,
                    theme: 'default',
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
                console.log('HTML editor initialized successfully');
            } catch (e) {
                console.error('Error initializing HTML editor:', e);
            }
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
                    theme: 'default',
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
                    theme: 'default',
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
     * Add helpful tooltips and enhancements
     */
    $(document).ready(function() {
        if (!$('body').hasClass('post-type-scmb_module')) {
            return;
        }
        
        // Add template variable helper
        addTemplateHelper();
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