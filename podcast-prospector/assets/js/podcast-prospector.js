/**
 * Podcast Prospector - Modern JavaScript Module
 *
 * ES6+ implementation with fetch API and accessibility enhancements.
 *
 * @package Podcast_Prospector
 * @since 2.1.0
 */

(function() {
    'use strict';

    /**
     * Podcast Prospector Application Class
     */
    class PodcastProspector {
        /**
         * Constructor
         */
        constructor() {
            this.config = window.interviewFinderData || {};
            this.ajaxUrl = this.config.ajaxurl || '/wp-admin/admin-ajax.php';
            this.restUrl = this.config.restUrl || '/wp-json/podcast-prospector/v1';
            this.searchNonce = this.config.searchNonce || '';
            this.importNonce = this.config.importNonce || '';
            this.restNonce = this.config.restNonce || '';

            this.i18n = {
                selectAtLeastOne: 'Please select at least one podcast/episode.',
                importing: 'Importing...',
                imported: 'Imported!',
                importSelected: 'Import Selected',
                importToTracker: 'Import to Tracker',
                invalidData: 'Error: Invalid data format.',
                communicationError: 'A communication error occurred. Please try again.',
                unexpectedResponse: 'Received an unexpected response.',
                searching: 'Searching...',
                noResults: 'No results found.',
                ...this.config.i18n
            };

            this.elements = {};
            this.abortController = null;

            this.init();
        }

        /**
         * Initialize the application
         */
        init() {
            this.cacheElements();
            this.bindEvents();
            this.setupAccessibility();
            this.toggleSearchBlocks();
        }

        /**
         * Cache DOM elements
         */
        cacheElements() {
            this.elements = {
                searchForm: document.querySelector('.search-form'),
                searchFormWrapper: document.querySelector('.search-form-wrapper'),
                resultsContainer: document.querySelector('.podsearch-results-container'),
                loadingSpinner: document.getElementById('loading-spinner'),
                errorMessage: document.getElementById('search-error-message'),
                filterSidebar: document.getElementById('filter-sidebar'),
                toggleFilters: document.getElementById('toggle-filters'),
                basicBlock: document.getElementById('basic-block'),
                advancedBlock: document.getElementById('advanced-block'),
                searchTypeInputs: document.querySelectorAll('input[name="search_type"]'),
                selectAllCheckbox: document.getElementById('select_all'),
                importButton: document.getElementById('add-to-formidable-button'),
            };
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Search type toggle
            this.elements.searchTypeInputs.forEach(input => {
                input.addEventListener('change', () => this.toggleSearchBlocks());
            });

            // Filter toggle
            if (this.elements.toggleFilters) {
                this.elements.toggleFilters.addEventListener('click', () => this.toggleFilters());
            }

            // Search form submission
            if (this.elements.searchForm) {
                this.elements.searchForm.addEventListener('submit', (e) => this.handleSearch(e));
            }

            // Delegated events for dynamic content
            document.addEventListener('click', (e) => this.handleDelegatedClick(e));
            document.addEventListener('change', (e) => this.handleDelegatedChange(e));

            // Keyboard navigation
            document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        }

        /**
         * Setup accessibility features
         */
        setupAccessibility() {
            // Add live region for announcements
            if (!document.getElementById('if-live-region')) {
                const liveRegion = document.createElement('div');
                liveRegion.id = 'if-live-region';
                liveRegion.className = 'screen-reader-text';
                liveRegion.setAttribute('aria-live', 'polite');
                liveRegion.setAttribute('aria-atomic', 'true');
                document.body.appendChild(liveRegion);
            }

            // Ensure proper ARIA labels
            if (this.elements.searchForm) {
                this.elements.searchForm.setAttribute('aria-label', 'Podcast search');
            }
        }

        /**
         * Announce message to screen readers
         * @param {string} message Message to announce
         */
        announce(message) {
            const liveRegion = document.getElementById('if-live-region');
            if (liveRegion) {
                liveRegion.textContent = '';
                setTimeout(() => {
                    liveRegion.textContent = message;
                }, 100);
            }
        }

        /**
         * Toggle search blocks based on search type
         */
        toggleSearchBlocks() {
            const selectedType = document.querySelector('input[name="search_type"]:checked');
            if (!selectedType) return;

            const searchType = selectedType.value;
            const isBasic = searchType === 'byperson' || searchType === 'bytitle';

            if (this.elements.filterSidebar) {
                this.elements.filterSidebar.hidden = true;
            }

            if (this.elements.basicBlock) {
                this.elements.basicBlock.hidden = !isBasic;
            }

            if (this.elements.advancedBlock) {
                this.elements.advancedBlock.hidden = isBasic;
            }

            if (this.elements.toggleFilters) {
                this.elements.toggleFilters.hidden = isBasic;
            }
        }

        /**
         * Toggle filters sidebar
         */
        toggleFilters() {
            if (this.elements.filterSidebar) {
                const isHidden = this.elements.filterSidebar.hidden;
                this.elements.filterSidebar.hidden = !isHidden;
                this.elements.toggleFilters?.setAttribute('aria-expanded', !isHidden);
            }
        }

        /**
         * Handle search form submission
         * @param {Event} e Submit event
         */
        async handleSearch(e) {
            e.preventDefault();

            // Cancel any pending request
            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();

            this.clearError();
            this.showLoading(true);
            this.announce(this.i18n.searching);

            try {
                const formData = this.getFormData(1);
                const response = await this.fetchSearch(formData);

                if (response.success) {
                    this.renderResults(response.data);
                    this.updateUserStats(response.data.user_data);
                    this.announce(`${response.data.count || 0} results found`);
                } else {
                    this.showError(response.data?.message || this.i18n.unexpectedResponse);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Search error:', error);
                    this.showError(this.i18n.communicationError);
                }
            } finally {
                this.showLoading(false);
            }
        }

        /**
         * Fetch search results
         * @param {Object} data Form data
         * @returns {Promise<Object>} Response
         */
        async fetchSearch(data) {
            const formData = new FormData();
            formData.append('action', 'perform_search');
            formData.append('_ajax_nonce', this.searchNonce);

            Object.entries(data).forEach(([key, value]) => {
                formData.append(key, value);
            });

            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData,
                signal: this.abortController.signal,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.json();
        }

        /**
         * Get form data
         * @param {number} page Page number
         * @returns {Object} Form data object
         */
        getFormData(page = 1) {
            const form = this.elements.searchForm;
            if (!form) return {};

            const searchType = form.querySelector('input[name="search_type"]:checked')?.value || 'byperson';
            const isBasic = searchType === 'byperson' || searchType === 'bytitle';

            const resultsPerPage = isBasic
                ? form.querySelector('#number_of_results')?.value
                : form.querySelector('#results_per_page')?.value;

            return {
                search_term: form.querySelector('input[name="search_term"]')?.value || '',
                search_type: searchType,
                language: form.querySelector('select[name="language"]')?.value || 'ALL',
                country: form.querySelector('select[name="country"]')?.value || 'ALL',
                genre: form.querySelector('select[name="genre"]')?.value || 'ALL',
                after_date: form.querySelector('input[name="after_date"]')?.value || '',
                before_date: form.querySelector('input[name="before_date"]')?.value || '',
                isSafeMode: form.querySelector('input[name="isSafeMode"]')?.checked || false,
                results_per_page: resultsPerPage || 10,
                page: page,
                sort_order: form.querySelector('#sort_order')?.value || 'BEST_MATCH',
            };
        }

        /**
         * Render search results
         * @param {Object} data Response data
         */
        renderResults(data) {
            // Remove existing results
            const existing = document.querySelector('.podsearch-results-container');
            if (existing) {
                existing.remove();
            }

            if (!data.html) {
                this.showError(this.i18n.noResults);
                return;
            }

            // Create new results container
            const container = document.createElement('div');
            container.className = 'podsearch-results-container';
            container.setAttribute('role', 'region');
            container.setAttribute('aria-label', 'Search results');
            container.innerHTML = data.html;

            // Insert after search form
            this.elements.searchFormWrapper?.after(container);

            // Update cached reference
            this.elements.resultsContainer = container;

            // Focus management for accessibility
            const firstResult = container.querySelector('.if-result-item, .podcast-result-row');
            if (firstResult) {
                firstResult.setAttribute('tabindex', '-1');
                firstResult.focus();
            }
        }

        /**
         * Update user stats display
         * @param {Object} userData User data
         */
        updateUserStats(userData) {
            if (!userData) return;

            const fields = ['search_count', 'searches_remaining', 'last_searched'];
            fields.forEach(field => {
                const element = document.getElementById(field);
                if (element && userData[field] !== undefined) {
                    element.textContent = userData[field];
                }
            });
        }

        /**
         * Handle delegated click events
         * @param {Event} e Click event
         */
        handleDelegatedClick(e) {
            const target = e.target;

            // Pagination
            if (target.classList.contains('pagination-btn') || target.closest('.pagination-btn')) {
                e.preventDefault();
                const btn = target.classList.contains('pagination-btn') ? target : target.closest('.pagination-btn');
                const page = parseInt(btn.dataset.page, 10);
                if (page) {
                    this.handlePagination(page);
                }
            }

            // Bulk import button
            if (target.id === 'add-to-formidable-button' || target.closest('#add-to-formidable-button')) {
                e.preventDefault();
                this.handleBulkImport(target);
            }

            // Individual import button
            if (target.classList.contains('individual-import-button') || target.closest('.individual-import-button')) {
                e.preventDefault();
                const btn = target.classList.contains('individual-import-button') ? target : target.closest('.individual-import-button');
                this.handleIndividualImport(btn);
            }
        }

        /**
         * Handle delegated change events
         * @param {Event} e Change event
         */
        handleDelegatedChange(e) {
            const target = e.target;

            // Select all checkbox
            if (target.id === 'select_all') {
                this.handleSelectAll(target.checked);
            }
        }

        /**
         * Handle keyboard navigation
         * @param {KeyboardEvent} e Keyboard event
         */
        handleKeyboard(e) {
            // Handle result item navigation
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                const focused = document.activeElement;
                if (focused?.closest('.if-results__list, .podcast-results-table')) {
                    e.preventDefault();
                    const items = Array.from(document.querySelectorAll('.if-result-item, .podcast-result-row'));
                    const currentIndex = items.indexOf(focused.closest('.if-result-item, .podcast-result-row'));

                    if (currentIndex !== -1) {
                        const nextIndex = e.key === 'ArrowDown'
                            ? Math.min(currentIndex + 1, items.length - 1)
                            : Math.max(currentIndex - 1, 0);

                        items[nextIndex]?.focus();
                    }
                }
            }

            // Space to toggle checkbox
            if (e.key === ' ') {
                const focused = document.activeElement;
                if (focused?.closest('.if-result-item, .podcast-result-row')) {
                    e.preventDefault();
                    const checkbox = focused.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
        }

        /**
         * Handle pagination
         * @param {number} page Page number
         */
        async handlePagination(page) {
            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();

            this.clearError();
            this.showLoading(true);

            try {
                const formData = this.getFormData(page);
                const response = await this.fetchSearch(formData);

                if (response.success) {
                    this.renderResults(response.data);
                    this.announce(`Page ${page} loaded`);
                    // Scroll to top of results
                    this.elements.resultsContainer?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    this.showError(response.data?.message || this.i18n.unexpectedResponse);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Pagination error:', error);
                    this.showError(this.i18n.communicationError);
                }
            } finally {
                this.showLoading(false);
            }
        }

        /**
         * Handle select all checkbox
         * @param {boolean} checked Checkbox state
         */
        handleSelectAll(checked) {
            const checkboxes = document.querySelectorAll(
                '.podsearch-results-container input[name="selected_podcasts[]"], .if-results input[type="checkbox"]'
            );
            checkboxes.forEach(checkbox => {
                checkbox.checked = checked;
            });

            const count = checked ? checkboxes.length : 0;
            this.announce(checked ? `Selected all ${count} items` : 'Deselected all items');
        }

        /**
         * Handle bulk import
         * @param {HTMLElement} button Import button
         */
        async handleBulkImport(button) {
            const selected = Array.from(document.querySelectorAll(
                '.podsearch-results-container input[name="selected_podcasts[]"]:checked, .if-results input[type="checkbox"]:checked'
            )).map(checkbox => checkbox.value).filter(Boolean);

            if (selected.length === 0) {
                this.showError(this.i18n.selectAtLeastOne);
                this.announce(this.i18n.selectAtLeastOne);
                return;
            }

            await this.performImport(selected, button, false);
        }

        /**
         * Handle individual import
         * @param {HTMLElement} button Import button
         */
        async handleIndividualImport(button) {
            const raw = button.getAttribute('data-podcast');
            if (!raw) {
                this.showError(this.i18n.invalidData);
                return;
            }

            // Decode HTML entities
            const textarea = document.createElement('textarea');
            textarea.innerHTML = raw;
            const decoded = textarea.value;

            try {
                JSON.parse(decoded); // Validate JSON
            } catch (err) {
                console.error('Invalid JSON in data-podcast:', err);
                this.showError(this.i18n.invalidData);
                return;
            }

            await this.performImport([decoded], button, true);
        }

        /**
         * Perform import operation
         * @param {Array} podcasts Array of podcast JSON strings
         * @param {HTMLElement} button Trigger button
         * @param {boolean} isIndividual Whether this is an individual import
         */
        async performImport(podcasts, button, isIndividual) {
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = this.i18n.importing;
            this.showLoading(true);
            this.announce(this.i18n.importing);

            const formData = new FormData();
            formData.append('action', 'add_podcasts_to_form');
            formData.append('_ajax_nonce', this.importNonce);
            formData.append('search_term', document.querySelector('input[name="search_term"]')?.value || '');
            formData.append('search_type', document.querySelector('input[name="search_type"]:checked')?.value || 'byperson');
            podcasts.forEach(podcast => formData.append('podcasts[]', podcast));

            try {
                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const data = await response.json();

                if (data.data?.html) {
                    this.showMessage(data.data.html);
                }

                if (data.success) {
                    button.textContent = this.i18n.imported;
                    this.announce(`${podcasts.length} item(s) imported successfully`);

                    if (!isIndividual) {
                        // Clear checkboxes
                        document.querySelectorAll(
                            '.podsearch-results-container input[name="selected_podcasts[]"]:checked'
                        ).forEach(cb => cb.checked = false);

                        const selectAll = document.getElementById('select_all');
                        if (selectAll) selectAll.checked = false;
                    }

                    setTimeout(() => {
                        button.textContent = isIndividual ? this.i18n.importToTracker : this.i18n.importSelected;
                        button.disabled = false;
                    }, 2000);
                } else {
                    button.textContent = originalText;
                    button.disabled = false;
                }
            } catch (error) {
                console.error('Import error:', error);
                this.showError(this.i18n.communicationError);
                button.textContent = originalText;
                button.disabled = false;
            } finally {
                this.showLoading(false);
            }
        }

        /**
         * Show/hide loading spinner
         * @param {boolean} show Whether to show spinner
         */
        showLoading(show) {
            if (this.elements.loadingSpinner) {
                this.elements.loadingSpinner.style.display = show ? 'block' : 'none';
                this.elements.loadingSpinner.setAttribute('aria-hidden', !show);
            }
        }

        /**
         * Show error message
         * @param {string} message Error message
         */
        showError(message) {
            if (this.elements.errorMessage) {
                this.elements.errorMessage.innerHTML = this.buildErrorHtml(message);
                this.elements.errorMessage.setAttribute('role', 'alert');
            }
        }

        /**
         * Show message (success or info)
         * @param {string} html HTML content
         */
        showMessage(html) {
            if (this.elements.errorMessage) {
                this.elements.errorMessage.innerHTML = html;
            }
        }

        /**
         * Clear error message
         */
        clearError() {
            if (this.elements.errorMessage) {
                this.elements.errorMessage.innerHTML = '';
                this.elements.errorMessage.removeAttribute('role');
            }
        }

        /**
         * Build error HTML
         * @param {string} message Error message
         * @returns {string} HTML string
         */
        buildErrorHtml(message) {
            return `
                <div class="import-message error" role="alert">
                    <div class="message-content">
                        <span class="message-icon" aria-hidden="true">⚠</span>
                        <span class="message-text">${this.escapeHtml(message)}</span>
                    </div>
                </div>
            `;
        }

        /**
         * Escape HTML entities
         * @param {string} text Text to escape
         * @returns {string} Escaped text
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.interviewFinder = new PodcastProspector();
        });
    } else {
        window.interviewFinder = new PodcastProspector();
    }
})();
