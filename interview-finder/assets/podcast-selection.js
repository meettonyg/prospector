jQuery(document).ready(function ($) {
    // ******************************************
    // Toggle between PodcastIndex and Taddy UI blocks new 3
    // ******************************************
    function toggleSearchBlocks() {
        $('#filter-sidebar').hide();
        let val = $('input[name="search_type"]:checked').val();
        if (val === 'byperson' || val === 'bytitle') {
            $('#basic-block').show();
            $('#advanced-block').hide();
            $('#toggle-filters').hide();
        } else {
            $('#basic-block').hide();
            $('#advanced-block').show();
            $('#toggle-filters').show();
        }
    }
    toggleSearchBlocks(); // Initial setup
    $('input[name="search_type"]').on('change', toggleSearchBlocks); // Update on change

    // ******************************************
    // Toggle advanced filters sidebar
    // ******************************************
    $('#toggle-filters').on('click', function () {
        $('#filter-sidebar').toggle();
    });

     // ******************************************
    // Helper function to handle AJAX call for importing (BOTH Bulk & Individual)
    // ******************************************
     function performImportAjax(selectedData, triggerElement) {
        const messageContainer = $('#search-error-message'); // Area for feedback
        messageContainer.html('').removeAttr('style'); // Clear previous messages/styles

        if (!selectedData || selectedData.length === 0) {
             console.error("performImportAjax called with no data.");
             messageContainer.html('<div class="import-message error"><div class="message-content"><i class="fas fa-exclamation-triangle message-icon"></i><span class="message-text">Error: No data selected for import.</span></div></div>');
             return;
         }

        // Determine button type for feedback
        const isIndividual = triggerElement && $(triggerElement).hasClass('individual-import-button');
        const button = $(triggerElement); // The specific button clicked (bulk or individual)

        // Get context
        const searchTerm = $('input[name="search_term"]').val(); // Use value from main search input
        const searchType = $('input[name="search_type"]:checked').val() || 'byperson'; // Use current search type

         // Provide visual feedback
        button.prop('disabled', true).text('Importing...');
         // Show global spinner (can be shown for both or just bulk)
         $('#loading-spinner').show();

        $.ajax({
            type: 'POST',
            url: frontendajax.ajaxurl, // Ensure localized
            dataType: 'json',
            data: {
                action: 'add_podcasts_to_form',
                podcasts: selectedData, // Array of JSON strings (one or many)
                search_term: searchTerm,
                search_type: searchType
                // Add nonce if needed: _ajax_nonce: frontendajax.nonce
            },
            success: function (response) {
                 if (response.data && response.data.html) {
                    messageContainer.html(response.data.html); // Display success/error HTML from PHP
                    if (response.success) {
                        button.text('Imported!'); // Indicate success on the button
                        // Clear checkboxes after successful bulk import
                        if (!isIndividual) {
                             // Use a container that definitely exists when results are present
                             $('.podsearch-results-container .podcast-results-table input[name="selected_podcasts[]"]:checked').prop('checked', false);
                             $('#select_all').prop('checked', false); // Deselect header checkbox too
                             // Re-enable bulk button after success
                             button.prop('disabled', false).text('Import Selected');
                         } else {
                             // Optionally re-enable individual button after success after a delay or immediately
                              button.prop('disabled', false).text('Import to Tracker');
                         }
                    } else {
                         // Re-enable button on partial/total failure reported by PHP
                         button.prop('disabled', false).text(isIndividual ? 'Import to Tracker' : 'Import Selected');
                    }
                } else {
                    // Fallback if HTML is missing in the response data
                    const fallbackHtml = '<div class="import-message error"><div class="message-content"><i class="fas fa-exclamation-triangle message-icon"></i><span class="message-text">Received an unexpected response from the server after import.</span></div></div>';
                    messageContainer.html(fallbackHtml);
                    console.warn("Import response missing expected HTML:", response);
                    button.prop('disabled', false).text(isIndividual ? 'Import to Tracker' : 'Import Selected');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                 // Handle network errors, server errors (5xx), etc.
                 console.error("AJAX Error during import:", textStatus, errorThrown, jqXHR.responseText);
                 let errorHtml = '<div class="import-message error"><div class="message-content"><i class="fas fa-exclamation-triangle message-icon"></i><span class="message-text">A communication error occurred. Please try again.</span></div></div>';
                  try { if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.html) { errorHtml = jqXHR.responseJSON.data.html; }
                  } catch (e) { console.error("Could not parse error response JSON."); }
                 messageContainer.html(errorHtml);
                 button.prop('disabled', false).text(isIndividual ? 'Import to Tracker' : 'Import Selected'); // Re-enable button
            },
            complete: function() {
                 // Hide global spinner on complete
                 $('#loading-spinner').hide();
             }
        });
     }

    // ******************************************
    // Bulk Import Button Handler (uses checkboxes - MODIFIED TO USE HELPER)
    // ******************************************
     // Use delegation in case button is loaded dynamically (e.g., within results)
    $(document).on('click', '#add-to-formidable-button', function () {
        const selectedPodcasts = [];
        // Find checked checkboxes within the currently displayed results container
        $('.podsearch-results-container .podcast-results-table input[name="selected_podcasts[]"]:checked').each(function () {
            if ($(this).val()) { // Ensure checkbox has a value
                 selectedPodcasts.push($(this).val());
            }
        });

        if (selectedPodcasts.length === 0) {
            $('#search-error-message').html('<div class="import-message error"><div class="message-content"><i class="fas fa-exclamation-triangle message-icon"></i><span class="message-text">Please select at least one podcast/episode using the checkboxes.</span></div></div>');
            return;
        }
        performImportAjax(selectedPodcasts, this); // Call helper with selected data and this button
    });

    // ******************************************
    // Individual Import Button Handler (NEW)
    // ******************************************
    // Use event delegation on a static parent
// ******************************************
// Individual Import Button Handler (UPDATED)
// ******************************************
$(document).on('click', '.individual-import-button', function () {
    const button = $(this);

    // 1) grab the raw attribute
    const raw = button.attr('data-podcast');
    console.log("🔍 raw attr data-podcast:", raw);

    // 2) decode any HTML entities back into plain JSON text
    const decoded = $('<textarea/>').html(raw).text();
    console.log("📥 decoded JSON string:", decoded);

    // 3) sanity-check that it’s valid JSON
    let parsed;
    try {
        parsed = JSON.parse(decoded);
        console.log("✅ parsed JSON object:", parsed);
    } catch (err) {
        console.error("❌ invalid JSON in data-podcast:", err);
        $('#search-error-message').html(
            '<div class="import-message error">' +
              '<div class="message-content">' +
                '<i class="fas fa-exclamation-triangle message-icon"></i>' +
                '<span class="message-text">Error: Invalid data format received for this item.</span>' +
              '</div>' +
            '</div>'
        );
        return;
    }

    // 4) hand it off to your bulk-import helper
    performImportAjax([ decoded ], this);
});



    // ******************************************
    // Main Search Form Submission Handler (Ensure results container class matches PHP)
    // ******************************************
     $('.search-form-wrapper').on('submit', '.search-form', function (e) {
         e.preventDefault();
         const messageContainer = $('#search-error-message');
         messageContainer.html('').removeAttr('style'); // Clear import/error messages on new search
         const form = $(this); let searchTerm = form.find('input[name="search_term"]').val(); let searchType = form.find('input[name="search_type"]:checked').val(); let language = form.find('select[name="language"]').val(); let country = form.find('select[name="country"]').val(); let genre = form.find('select[name="genre"]').val(); let after_date = form.find('input[name="after_date"]').val(); let before_date = form.find('input[name="before_date"]').val(); let isSafeMode = form.find('input[name="isSafeMode"]').is(':checked'); let resultsPerPage; if (searchType === 'byperson' || searchType === 'bytitle') { resultsPerPage = form.find('#number_of_results').val(); } else { resultsPerPage = form.find('#results_per_page').val(); } let sortOrder = form.find('#sort_order').val();
         $('#loading-spinner').show();
         $('.podsearch-results-container').remove(); // Use the container class from PHP display functions

         $.ajax({
             type: 'POST', url: frontendajax.ajaxurl, dataType: 'json',
             data: { action: 'perform_search', search_term: searchTerm, search_type: searchType, language: language, country: country, genre: genre, after_date: after_date, before_date: before_date, isSafeMode: isSafeMode, results_per_page: resultsPerPage, page: 1, sort_order: sortOrder },
             success: function (response) {
                 $('#loading-spinner').hide(); $('.podsearch-results-container').remove(); // Remove again just in case
                 if (response.success) {
                     const resultsContainer = $('<div class="podsearch-results-container"></div>'); // Add the container div
                     resultsContainer.html(response.data.html); // Contains the table etc.
                     $('.search-form-wrapper').after(resultsContainer); // Insert after form wrapper
                     const userData = response.data.user_data; if (userData) { if ($('#search_count').length) $('#search_count').text(userData.search_count); if ($('#searches_remaining').length) $('#searches_remaining').text(userData.searches_remaining); if ($('#last_searched').length) $('#last_searched').text(userData.last_searched); }
                     bindCheckAll(); // Re-bind select-all
                 } else {
                     let errMsg = 'An unknown error occurred during the search.'; if (response.data && response.data.message) { errMsg = response.data.message; }
                     messageContainer.html(errMsg).css('color', 'red').css('font-weight', 'bold');
                 }
             },
             error: function (jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error during search:", textStatus, errorThrown, jqXHR.responseText); $('#loading-spinner').hide(); $('.podsearch-results-container').remove();
                 messageContainer.html('An error occurred communicating with the server during the search.').css('color', 'red').css('font-weight', 'bold');
             }
         });
    });

    // ******************************************
    // Pagination Handler (Ensure results container class matches PHP)
    // ******************************************
      // Use delegation from a static parent
      $('.search-form-wrapper').parent().on('click', '.pagination-btn', function (event) {
           event.preventDefault();
           const messageContainer = $('#search-error-message');
           messageContainer.html('').removeAttr('style'); // Clear import messages
           let newPage = $(this).data('page'); const form = $('.search-form-wrapper').find('.search-form'); if(form.length === 0) { console.error("Could not find search form for pagination."); return; } let searchTerm = form.find('input[name="search_term"]').val(); let searchType = form.find('input[name="search_type"]:checked').val(); let language = form.find('select[name="language"]').val(); let country = form.find('select[name="country"]').val(); let genre = form.find('select[name="genre"]').val(); let after_date = form.find('input[name="after_date"]').val(); let before_date = form.find('input[name="before_date"]').val(); let isSafeMode = form.find('input[name="isSafeMode"]').is(':checked'); let resultsPerPage; if (searchType === 'byperson' || searchType === 'bytitle') { resultsPerPage = form.find('#number_of_results').val(); } else { resultsPerPage = form.find('#results_per_page').val(); } let sortOrder = form.find('#sort_order').val();
           $('#loading-spinner').show(); $('.podsearch-results-container').remove(); // Remove results container
           $.ajax({
               type: 'POST', url: frontendajax.ajaxurl, dataType: 'json',
               data: { action: 'perform_search', search_term: searchTerm, search_type: searchType, language: language, country: country, genre: genre, after_date: after_date, before_date: before_date, isSafeMode: isSafeMode, results_per_page: resultsPerPage, page: newPage, sort_order: sortOrder },
               success: function (response) {
                   $('#loading-spinner').hide(); $('.podsearch-results-container').remove();
                   if (response.success) {
                       const resultsContainer = $('<div class="podsearch-results-container"></div>'); // Add container
                       resultsContainer.html(response.data.html);
                       $('.search-form-wrapper').after(resultsContainer); // Insert after form wrapper
                       const userData = response.data.user_data; if (userData) { if ($('#search_count').length) $('#search_count').text(userData.search_count); if ($('#searches_remaining').length) $('#searches_remaining').text(userData.searches_remaining); if ($('#last_searched').length) $('#last_searched').text(userData.last_searched); }
                       bindCheckAll(); // Re-bind select-all
                   } else {
                       let errMsg = 'An unknown error occurred during pagination.'; if (response.data && response.data.message) { errMsg = response.data.message; }
                       messageContainer.html(errMsg).css('color', 'red').css('font-weight', 'bold');
                   }
               },
               error: function (jqXHR, textStatus, errorThrown) {
                   console.error("AJAX Error during pagination:", textStatus, errorThrown, jqXHR.responseText); $('#loading-spinner').hide(); $('.podsearch-results-container').remove();
                    messageContainer.html('An error occurred communicating with the server during pagination.').css('color', 'red').css('font-weight', 'bold');
               }
           });
      });

    // ******************************************
    // Bind "Select All" functionality (RESTORED)
    // ******************************************
    function bindCheckAll() {
         // Use event delegation on document as a reliable static parent
         $(document).off('change', '#select_all'); // Unbind previous delegated events from document
         $(document).on('change', '#select_all', function () { // Re-bind to document
            let isChecked = this.checked;
            // Target checkboxes within the current results container specifically
            $('.podsearch-results-container .podcast-results-table input[name="selected_podcasts[]"]').prop('checked', isChecked);
        });
    }
    // Call bindCheckAll once on page load to ensure the handler is ready
    bindCheckAll();

}); // End jQuery(document).ready