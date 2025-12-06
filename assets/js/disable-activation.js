document.addEventListener( 'DOMContentLoaded', function() {
    // Find the activate button for this plugin
    const activateLink = document.querySelector( '[data-plugin="secure-custom-module-builder/secure-custom-module-builder.php"] .activate a' );
    
    if ( activateLink ) {
        // Disable the link
        activateLink.style.pointerEvents = 'none';
        activateLink.style.opacity = '0.5';
        activateLink.style.cursor = 'not-allowed';
        activateLink.title = 'This plugin requires Advanced Custom Fields (ACF) to be installed and activated.';
    }
});