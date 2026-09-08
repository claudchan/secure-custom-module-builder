/**
 * SCMB Module Insights Admin JavaScript
 *
 * Provides client-side module switching, real-time table searching,
 * and column sorting without external dependencies.
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initModuleSelector();
        initLiveSearch();
        initSortableTables();
    });

    /**
     * Initialize left column module selector and client-side view toggling.
     */
    function initModuleSelector() {
        var sidebar = document.querySelector('.scmb-insights-sidebar');
        if (!sidebar) {
            return;
        }

        var moduleLinks = sidebar.querySelectorAll('.scmb-insights-module-link');
        var panels = document.querySelectorAll('.scmb-insights-panel');

        moduleLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                var targetId = link.getAttribute('data-module-id');
                if (!targetId) {
                    return;
                }

                // Update active state in module list
                sidebar.querySelectorAll('.scmb-insights-module-item').forEach(function(item) {
                    item.classList.remove('is-active');
                });
                var parentItem = link.closest('.scmb-insights-module-item');
                if (parentItem) {
                    parentItem.classList.add('is-active');
                }

                // Show selected panel, hide others
                panels.forEach(function(panel) {
                    if (panel.getAttribute('data-module-id') === targetId) {
                        panel.classList.add('is-active');
                        // Reset search input in newly active panel
                        var searchInput = panel.querySelector('.scmb-insights-search-input');
                        if (searchInput) {
                            searchInput.value = '';
                            resetTableRows(panel);
                        }
                    } else {
                        panel.classList.remove('is-active');
                    }
                });

                // Update browser URL without reloading
                if (window.history && window.history.pushState) {
                    window.history.pushState(null, '', link.href);
                }
            });
        });

        // Support browser back/forward buttons
        window.addEventListener('popstate', function() {
            var urlParams = new URLSearchParams(window.location.search);
            var moduleId = urlParams.get('module_id');
            if (moduleId) {
                var targetLink = sidebar.querySelector('.scmb-insights-module-link[data-module-id="' + moduleId + '"]');
                if (targetLink) {
                    targetLink.click();
                }
            }
        });
    }

    /**
     * Reset table search filters and display all rows.
     */
    function resetTableRows(panel) {
        var rows = panel.querySelectorAll('.scmb-insights-row');
        rows.forEach(function(row) {
            row.style.display = '';
        });

        var noResultsRow = panel.querySelector('.scmb-insights-no-results');
        if (noResultsRow) {
            noResultsRow.style.display = 'none';
        }
    }

    /**
     * Initialize real-time live search to filter rows by post/page title.
     */
    function initLiveSearch() {
        var searchInputs = document.querySelectorAll('.scmb-insights-search-input');

        searchInputs.forEach(function(input) {
            input.addEventListener('input', function() {
                var query = input.value.trim().toLowerCase();
                var panel = input.closest('.scmb-insights-panel');
                if (!panel) {
                    return;
                }

                var rows = panel.querySelectorAll('.scmb-insights-row');
                var visibleCount = 0;

                rows.forEach(function(row) {
                    var title = (row.getAttribute('data-title') || '').toLowerCase();
                    if (!query || title.indexOf(query) !== -1) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                var noResultsRow = panel.querySelector('.scmb-insights-no-results');
                if (noResultsRow) {
                    noResultsRow.style.display = (visibleCount === 0 && rows.length > 0) ? '' : 'none';
                }
            });
        });
    }

    /**
     * Initialize sortable table headers.
     */
    function initSortableTables() {
        var tables = document.querySelectorAll('.scmb-insights-table');

        tables.forEach(function(table) {
            var headers = table.querySelectorAll('th.sortable');
            var tbody = table.querySelector('tbody');

            headers.forEach(function(th) {
                th.addEventListener('click', function() {
                    var sortKey = th.getAttribute('data-sort');
                    var isAsc = th.classList.contains('is-sorted-asc');
                    var newDirection = isAsc ? 'desc' : 'asc';

                    // Clear sort states from all headers in this table
                    headers.forEach(function(h) {
                        h.classList.remove('is-sorted-asc', 'is-sorted-desc');
                        h.setAttribute('aria-sort', 'none');
                        var indicator = h.querySelector('.sort-indicator');
                        if (indicator) {
                            indicator.className = 'sort-indicator dashicons dashicons-sort';
                        }
                    });

                    // Set new sort state
                    th.classList.add(newDirection === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
                    th.setAttribute('aria-sort', newDirection === 'asc' ? 'ascending' : 'descending');

                    var indicator = th.querySelector('.sort-indicator');
                    if (indicator) {
                        indicator.className = 'sort-indicator dashicons dashicons-arrow-' + (newDirection === 'asc' ? 'up' : 'down') + '-alt2';
                    }

                    // Sort rows
                    var rows = Array.from(tbody.querySelectorAll('.scmb-insights-row'));

                    rows.sort(function(rowA, rowB) {
                        var valA = rowA.getAttribute('data-' + sortKey) || '';
                        var valB = rowB.getAttribute('data-' + sortKey) || '';

                        var comparison = 0;
                        if (sortKey === 'instances') {
                            var numA = parseInt(valA, 10) || 0;
                            var numB = parseInt(valB, 10) || 0;
                            comparison = numA - numB;
                        } else {
                            comparison = valA.localeCompare(valB, undefined, { sensitivity: 'base' });
                        }

                        return newDirection === 'asc' ? comparison : -comparison;
                    });

                    // Re-append rows in new order
                    rows.forEach(function(row) {
                        tbody.appendChild(row);
                    });

                    // Ensure no-results row stays at the bottom
                    var noResultsRow = tbody.querySelector('.scmb-insights-no-results');
                    if (noResultsRow) {
                        tbody.appendChild(noResultsRow);
                    }
                });
            });
        });
    }
})();

