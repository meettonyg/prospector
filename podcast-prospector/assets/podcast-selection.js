/**
 * Podcast Prospector - Frontend JavaScript
 *
 * Handles search form submission, pagination, and podcast import functionality.
 *
 * @package Podcast_Prospector
 * @since 2.0.0
 */

jQuery(document).ready(function ($) {
    'use strict';

    // Configuration object - populated by wp_localize_script
    var config = window.interviewFinderData || window.frontendajax || {};
    var ajaxUrl = config.ajaxurl || (window.frontendajax && window.frontendajax.ajaxurl);
    var searchNonce = config.searchNonce || '';
    var importNonce = config.importNonce || '';
    var i18n = config.i18n || {};

    // Default translations if not provided
    i18n = $.extend({
        selectAtLeastOne: 'Please select at least one podcast/episode using the checkboxes.',
        importing: 'Importing...',
        imported: 'Imported!',
        importSelected: 'Import Selected',
        importToTracker: 'Import to Tracker',
        invalidData: 'Error: Invalid data format received for this item.',
        communicationError: 'A communication error occurred. Please try again.',
        unexpectedResponse: 'Received an unexpected response from the server.'
    }, i18n);

    /**
     * Toggle between PodcastIndex (basic) and Taddy (advanced) UI blocks.
     */
    function toggleSearchBlocks() {
        $('#filter-sidebar').hide();
        var searchType = $('input[name="search_type"]:checked').val();

        if (searchType === 'byperson' || searchType === 'bytitle') {
            $('#basic-block').show();
            $('#advanced-block').hide();
            $('#toggle-filters').hide();
        } else {
            $('#basic-block').hide();
            $('#advanced-block').show();
            $('#toggle-filters').show();
        }
    }

    // Initial setup
    toggleSearchBlocks();

    // Update on tab change
    $('input[name="search_type"]').on('change', toggleSearchBlocks);

    /**
     * Toggle advanced filters sidebar.
     */
    $('#toggle-filters').on('click', function () {
        $('#filter-sidebar').toggle();
    });

    /**
     * Build error message HTML.
     *
     * @param {string} message Error message.
     * @return {string} HTML string.
     */
    function buildErrorHtml(message) {
        return '<div class="import-message error">' +
            '<div class="message-content">' +
            '<i class="fas fa-exclamation-triangle message-icon"></i>' +
            '<span class="message-text">' + message + '</span>' +
            '</div>' +
            '</div>';
    }

    /**
     * Get form data for search/pagination requests.
     *
     * @param {jQuery} form Form element.
     * @param {number} page Page number.
     * @return {object} Form data object.
     */
    function getFormData(form, page) {
        var searchType = form.find('input[name="search_type"]:checked').val();
        var resultsPerPage;

        if (searchType === 'byperson' || searchType === 'bytitle') {
            resultsPerPage = form.find('#number_of_results').val();
        } else {
            resultsPerPage = form.find('#results_per_page').val();
        }

        return {
            action: 'perform_search',
            _ajax_nonce: searchNonce,
            search_term: form.find('input[name="search_term"]').val(),
            search_type: searchType,
            language: form.find('select[name="language"]').val(),
            country: form.find('select[name="country"]').val(),
            genre: form.find('select[name="genre"]').val(),
            after_date: form.find('input[name="after_date"]').val(),
            before_date: form.find('input[name="before_date"]').val(),
            isSafeMode: form.find('input[name="isSafeMode"]').is(':checked'),
            results_per_page: resultsPerPage,
            page: page,
            sort_order: form.find('#sort_order').val()
        };
    }

    /**
     * Handle successful search response.
     *
     * @param {object} response AJAX response.
     */
    function handleSearchSuccess(response) {
        $('#loading-spinner').hide();
        $('.podsearch-results-container').remove();

        if (response.success) {
            var resultsContainer = $('<div class="podsearch-results-container"></div>');
            resultsContainer.html(response.data.html);
            $('.search-form-wrapper').after(resultsContainer);

            // Update user data display if available
            var userData = response.data.user_data;
            if (userData) {
                if ($('#search_count').length) {
                    $('#search_count').text(userData.search_count);
                }
                if ($('#searches_remaining').length) {
                    $('#searches_remaining').text(userData.searches_remaining);
                }
                if ($('#last_searched').length) {
                    $('#last_searched').text(userData.last_searched);
                }
            }

            bindCheckAll();
        } else {
            var errorMsg = response.data && response.data.message
                ? response.data.message
                : 'An unknown error occurred during the search.';
            $('#search-error-message')
                .html(errorMsg)
                .css('color', 'red')
                .css('font-weight', 'bold');
        }
    }

    /**
     * Handle search error.
     *
     * @param {object} jqXHR jQuery XHR object.
     * @param {string} textStatus Status text.
     * @param {string} errorThrown Error message.
     */
    function handleSearchError(jqXHR, textStatus, errorThrown) {
        console.error('AJAX Error during search:', textStatus, errorThrown, jqXHR.responseText);
        $('#loading-spinner').hide();
        $('.podsearch-results-container').remove();
        $('#search-error-message')
            .html(i18n.communicationError)
            .css('color', 'red')
            .css('font-weight', 'bold');
    }

    /**
     * Perform import AJAX request.
     *
     * @param {array} selectedData Array of podcast JSON strings.
     * @param {HTMLElement} triggerElement The button that triggered the import.
     */
    function performImportAjax(selectedData, triggerElement) {
        var messageContainer = $('#search-error-message');
        messageContainer.html('').removeAttr('style');

        if (!selectedData || selectedData.length === 0) {
            console.error('performImportAjax called with no data.');
            messageContainer.html(buildErrorHtml(i18n.selectAtLeastOne));
            return;
        }

        var isIndividual = triggerElement && $(triggerElement).hasClass('individual-import-button');
        var button = $(triggerElement);

        // Get context
        var searchTerm = $('input[name="search_term"]').val();
        var searchType = $('input[name="search_type"]:checked').val() || 'byperson';

        // Visual feedback
        button.prop('disabled', true).text(i18n.importing);
        $('#loading-spinner').show();

        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            dataType: 'json',
            data: {
                action: 'add_podcasts_to_form',
                _ajax_nonce: importNonce,
                podcasts: selectedData,
                search_term: searchTerm,
                search_type: searchType
            },
            success: function (response) {
                if (response.data && response.data.html) {
                    messageContainer.html(response.data.html);

                    if (response.success) {
                        button.text(i18n.imported);

                        if (!isIndividual) {
                            // Clear checkboxes after successful bulk import
                            $('.podsearch-results-container .podcast-results-table input[name="selected_podcasts[]"]:checked')
                                .prop('checked', false);
                            $('#select_all').prop('checked', false);
                            button.prop('disabled', false).text(i18n.importSelected);
                        } else {
                            button.prop('disabled', false).text(i18n.importToTracker);
                        }
                    } else {
                        button.prop('disabled', false).text(isIndividual ? i18n.importToTracker : i18n.importSelected);
                    }
                } else {
                    messageContainer.html(buildErrorHtml(i18n.unexpectedResponse));
                    console.warn('Import response missing expected HTML:', response);
                    button.prop('disabled', false).text(isIndividual ? i18n.importToTracker : i18n.importSelected);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error during import:', textStatus, errorThrown, jqXHR.responseText);

                var errorHtml = buildErrorHtml(i18n.communicationError);
                try {
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.html) {
                        errorHtml = jqXHR.responseJSON.data.html;
                    }
                } catch (e) {
                    console.error('Could not parse error response JSON.');
                }

                messageContainer.html(errorHtml);
                button.prop('disabled', false).text(isIndividual ? i18n.importToTracker : i18n.importSelected);
            },
            complete: function () {
                $('#loading-spinner').hide();
            }
        });
    }

    /**
     * Bulk Import Button Handler.
     */
    $(document).on('click', '#add-to-formidable-button', function () {
        var selectedPodcasts = [];

        $('.podsearch-results-container .podcast-results-table input[name="selected_podcasts[]"]:checked')
            .each(function () {
                if ($(this).val()) {
                    selectedPodcasts.push($(this).val());
                }
            });

        if (selectedPodcasts.length === 0) {
            $('#search-error-message').html(buildErrorHtml(i18n.selectAtLeastOne));
            return;
        }

        performImportAjax(selectedPodcasts, this);
    });

    /**
     * Individual Import Button Handler.
     */
    $(document).on('click', '.individual-import-button', function () {
        var button = $(this);

        // Get raw attribute
        var raw = button.attr('data-podcast');
        console.log('Raw data-podcast attribute:', raw);

        // Decode HTML entities
        var decoded = $('<textarea/>').html(raw).text();
        console.log('Decoded JSON string:', decoded);

        // Validate JSON
        var parsed;
        try {
            parsed = JSON.parse(decoded);
            console.log('Parsed JSON object:', parsed);
        } catch (err) {
            console.error('Invalid JSON in data-podcast:', err);
            $('#search-error-message').html(buildErrorHtml(i18n.invalidData));
            return;
        }

        performImportAjax([decoded], this);
    });

    /**
     * Main Search Form Submission Handler.
     */
    $('.search-form-wrapper').on('submit', '.search-form', function (e) {
        e.preventDefault();

        var messageContainer = $('#search-error-message');
        messageContainer.html('').removeAttr('style');

        var form = $(this);

        $('#loading-spinner').show();
        $('.podsearch-results-container').remove();

        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            dataType: 'json',
            data: getFormData(form, 1),
            success: handleSearchSuccess,
            error: handleSearchError
        });
    });

    /**
     * Pagination Handler.
     */
    $('.search-form-wrapper').parent().on('click', '.pagination-btn', function (event) {
        event.preventDefault();

        var messageContainer = $('#search-error-message');
        messageContainer.html('').removeAttr('style');

        var newPage = $(this).data('page');
        var form = $('.search-form-wrapper').find('.search-form');

        if (form.length === 0) {
            console.error('Could not find search form for pagination.');
            return;
        }

        $('#loading-spinner').show();
        $('.podsearch-results-container').remove();

        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            dataType: 'json',
            data: getFormData(form, newPage),
            success: handleSearchSuccess,
            error: handleSearchError
        });
    });

    /**
     * Bind "Select All" checkbox functionality.
     */
    function bindCheckAll() {
        $(document).off('change', '#select_all');
        $(document).on('change', '#select_all', function () {
            var isChecked = this.checked;
            $('.podsearch-results-container .podcast-results-table input[name="selected_podcasts[]"]')
                .prop('checked', isChecked);
        });
    }

    // Initialize select all binding
    bindCheckAll();

});
