(function($) {
    'use strict';
    
    $(document).ready(function() {
    
    // Event toggle instant save
    $(document).on('change', '.event-toggle', function() {
        var $toggle = $(this);
        var $label = $toggle.closest('.anticipater-mini-toggle');
        var index = $toggle.data('index');
        var enabled = $toggle.is(':checked') ? 1 : 0;
        
        $label.addClass('saving');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'anticipater_toggle_event',
                nonce: anticipaterAdmin.toggleNonce,
                index: index,
                enabled: enabled
            },
            success: function(response) {
                $label.removeClass('saving');
                if (!response.success) {
                    $toggle.prop('checked', !enabled);
                }
            },
            error: function() {
                $label.removeClass('saving');
                $toggle.prop('checked', !enabled);
            }
        });
    });
    
    var eventIndex = $('#events-list tr').length;
    
    function addEventRow(event) {
        event = event || {
            name: '',
            enabled: 1,
            type: 'click',
            trigger: 'click',
            selector: ''
        };
        
        var row = `
            <tr class="event-row" data-index="${eventIndex}">
                <td class="column-enabled">
                    <input type="checkbox" 
                           name="${anticipaterAdmin.optionName}[events][${eventIndex}][enabled]" 
                           value="1" 
                           ${event.enabled ? 'checked' : ''}>
                </td>
                <td class="column-name">
                    <input type="text" 
                           name="${anticipaterAdmin.optionName}[events][${eventIndex}][name]" 
                           value="${event.name}" 
                           class="regular-text">
                </td>
                <td class="column-type">
                    <select name="${anticipaterAdmin.optionName}[events][${eventIndex}][type]">
                        <option value="automatic" ${event.type === 'automatic' ? 'selected' : ''}>Automatic</option>
                        <option value="click" ${event.type === 'click' ? 'selected' : ''}>Click</option>
                        <option value="scroll" ${event.type === 'scroll' ? 'selected' : ''}>Scroll</option>
                        <option value="video" ${event.type === 'video' ? 'selected' : ''}>Video</option>
                        <option value="form" ${event.type === 'form' ? 'selected' : ''}>Form</option>
                    </select>
                </td>
                <td class="column-trigger">
                    <select name="${anticipaterAdmin.optionName}[events][${eventIndex}][trigger]">
                        <option value="pageload" ${event.trigger === 'pageload' ? 'selected' : ''}>Page Load</option>
                        <option value="click" ${event.trigger === 'click' ? 'selected' : ''}>Click</option>
                        <option value="scroll" ${event.trigger === 'scroll' ? 'selected' : ''}>Scroll</option>
                        <option value="time" ${event.trigger === 'time' ? 'selected' : ''}>Time on Page</option>
                        <option value="behavior" ${event.trigger === 'behavior' ? 'selected' : ''}>User Behavior</option>
                        <option value="play" ${event.trigger === 'play' ? 'selected' : ''}>Video Play</option>
                        <option value="progress" ${event.trigger === 'progress' ? 'selected' : ''}>Video Progress</option>
                        <option value="ended" ${event.trigger === 'ended' ? 'selected' : ''}>Video End</option>
                        <option value="wpcf7" ${event.trigger === 'wpcf7' ? 'selected' : ''}>CF7 Submit</option>
                    </select>
                </td>
                <td class="column-selector">
                    <input type="text" 
                           name="${anticipaterAdmin.optionName}[events][${eventIndex}][selector]" 
                           value="${event.selector || ''}" 
                           class="regular-text"
                           placeholder="CSS selector or filter">
                </td>
                <td class="column-actions">
                    <button type="button" class="button button-small delete-event" title="Delete">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            </tr>
        `;
        
        $('#events-list').append(row);
        eventIndex++;
    }
    
    $('#add-event').on('click', function() {
        addEventRow();
    });
    
    $(document).on('click', '.delete-event', function() {
        var row = $(this).closest('tr');
        row.addClass('removing');
        setTimeout(function() {
            row.remove();
        }, 200);
    });
    
    // Toggle conditions panel
    $(document).on('click', '.toggle-conditions', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var $panel = $btn.next('.conditions-panel');
        var isVisible = $panel.is(':visible');
        
        $('.conditions-panel, .params-panel').hide();
        
        if (!isVisible) {
            $panel.show();
        }
    });
    
    // Toggle params panel
    $(document).on('click', '.toggle-params', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var $panel = $btn.next('.params-panel');
        var isVisible = $panel.is(':visible');
        
        $('.conditions-panel, .params-panel').hide();
        
        if (!isVisible) {
            $panel.show();
        }
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.conditions-panel, .toggle-conditions, .params-panel, .toggle-params').length) {
            $('.conditions-panel, .params-panel').hide();
        }
    });
    
    // Add condition
    $(document).on('click', '.add-condition', function() {
        var row = $(this).closest('tr');
        var eventIndex = row.data('index');
        var conditionsList = $(this).siblings('.conditions-list');
        var conditionIndex = conditionsList.find('.condition-row').length;
        
        var html = `
            <div class="condition-row">
                <select name="${anticipaterAdmin.optionName}[events][${eventIndex}][conditions][${conditionIndex}][type]" class="condition-type">
                    <optgroup label="Engagement">
                        <option value="page_views">Page Views (session)</option>
                        <option value="time_on_site">Time on Site (sec)</option>
                        <option value="time_on_page">Time on Page (sec)</option>
                        <option value="scroll_depth">Scroll Depth (%)</option>
                        <option value="engaged_session">Engaged Session</option>
                    </optgroup>
                    <optgroup label="User">
                        <option value="session_count">Session Count (total)</option>
                        <option value="returning_visitor">Returning Visitor</option>
                        <option value="new_visitor">New Visitor</option>
                    </optgroup>
                    <optgroup label="Device">
                        <option value="device_mobile">Mobile Device</option>
                        <option value="device_desktop">Desktop Device</option>
                        <option value="device_tablet">Tablet Device</option>
                    </optgroup>
                    <optgroup label="Traffic Source">
                        <option value="referrer_contains">Referrer Contains</option>
                        <option value="utm_source">UTM Source</option>
                        <option value="utm_medium">UTM Medium</option>
                        <option value="utm_campaign">UTM Campaign</option>
                        <option value="traffic_organic">Organic Traffic</option>
                        <option value="traffic_direct">Direct Traffic</option>
                        <option value="traffic_social">Social Traffic</option>
                    </optgroup>
                    <optgroup label="Page">
                        <option value="page_url_contains">URL Contains</option>
                        <option value="page_url_equals">URL Equals</option>
                        <option value="page_path_contains">Path Contains</option>
                        <option value="landing_page">Is Landing Page</option>
                        <option value="exit_intent">Exit Intent</option>
                    </optgroup>
                    <optgroup label="Interaction">
                        <option value="click_count">Click Count</option>
                        <option value="form_interaction">Form Interaction</option>
                        <option value="video_watched">Video Watched (%)</option>
                        <option value="element_visible">Element Visible</option>
                        <option value="idle_time">Idle Time (sec)</option>
                    </optgroup>
                    <optgroup label="E-commerce">
                        <option value="cart_value">Cart Value</option>
                        <option value="cart_items">Cart Items</option>
                        <option value="product_viewed">Product Viewed</option>
                    </optgroup>
                    <optgroup label="Time">
                        <option value="day_of_week">Day of Week</option>
                        <option value="hour_of_day">Hour of Day</option>
                        <option value="date_range">Date Range</option>
                    </optgroup>
                    <optgroup label="Custom">
                        <option value="cookie_exists">Cookie Exists</option>
                        <option value="cookie_value">Cookie Value</option>
                        <option value="localstorage_exists">LocalStorage Exists</option>
                        <option value="js_variable">JS Variable</option>
                        <option value="css_selector_exists">CSS Selector Exists</option>
                    </optgroup>
                </select>
                <select name="${anticipaterAdmin.optionName}[events][${eventIndex}][conditions][${conditionIndex}][operator]" class="condition-operator">
                    <option value=">=">>=</option>
                    <option value=">">&gt;</option>
                    <option value="==">=</option>
                    <option value="!=">!=</option>
                    <option value="<">&lt;</option>
                    <option value="<="><=</option>
                    <option value="contains">contains</option>
                    <option value="not_contains">not contains</option>
                    <option value="starts_with">starts with</option>
                    <option value="ends_with">ends with</option>
                    <option value="matches">matches (regex)</option>
                    <option value="is_true">is true</option>
                    <option value="is_false">is false</option>
                </select>
                <input type="text" name="${anticipaterAdmin.optionName}[events][${eventIndex}][conditions][${conditionIndex}][value]" value="" class="condition-value" placeholder="Value">
                <button type="button" class="button button-small remove-condition"><span class="dashicons dashicons-no"></span></button>
            </div>
        `;
        conditionsList.append(html);
        updateConditionCount(row);
    });
    
    // Remove condition
    $(document).on('click', '.remove-condition', function() {
        var row = $(this).closest('tr');
        $(this).closest('.condition-row').remove();
        updateConditionCount(row);
    });
    
    function updateConditionCount(row) {
        var count = row.find('.condition-row').length;
        var btn = row.find('.toggle-conditions');
        btn.find('.condition-count').remove();
        if (count > 0) {
            btn.append('<span class="condition-count">' + count + '</span>');
        }
    }
    
    // Add param
    $(document).on('click', '.add-param', function() {
        var row = $(this).closest('tr');
        var eventIndex = row.data('index');
        var panel = $(this).closest('.params-panel');
        var key = panel.find('.new-param-key').val().trim();
        var value = panel.find('.new-param-value').val().trim();
        
        if (!key) return;
        
        var html = `
            <div class="param-row">
                <input type="text" name="${anticipaterAdmin.optionName}[events][${eventIndex}][params][${key}]" value="${value}" class="param-value">
                <label>${key}</label>
                <button type="button" class="button button-small remove-param"><span class="dashicons dashicons-no"></span></button>
            </div>
        `;
        panel.find('.params-list').append(html);
        panel.find('.new-param-key').val('');
        panel.find('.new-param-value').val('');
        updateParamsCount(row);
    });
    
    // Remove param
    $(document).on('click', '.remove-param', function() {
        var row = $(this).closest('tr');
        $(this).closest('.param-row').remove();
        updateParamsCount(row);
    });
    
    function updateParamsCount(row) {
        var count = row.find('.param-row').length;
        var btn = row.find('.toggle-params');
        btn.find('.params-count').remove();
        if (count > 0) {
            btn.append('<span class="params-count">' + count + '</span>');
        }
    }

    // Export events
    $('#export-events').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'anticipater_export_events' },
            success: function(response) {
                if (response.success) {
                    var json = JSON.stringify(response.data, null, 2);
                    var blob = new Blob([json], { type: 'application/json' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'anticipater-events-' + new Date().toISOString().slice(0,10) + '.json';
                    a.click();
                    URL.revokeObjectURL(url);
                }
            }
        });
    });
    
    // Show import area
    $('#import-events').on('click', function() {
        $('#import-area').slideDown();
    });
    
    $('#cancel-import').on('click', function() {
        $('#import-area').slideUp();
        $('#import-json').val('');
    });
    
    // Do import
    $('#do-import').on('click', function() {
        var json = $('#import-json').val().trim();
        if (!json) {
            alert('Please paste JSON data');
            return;
        }
        
        try {
            JSON.parse(json);
        } catch(e) {
            alert('Invalid JSON format');
            return;
        }
        
        if (!confirm('This will replace all existing events. Continue?')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'anticipater_import_events',
                nonce: $('#anticipater_import_nonce').val(),
                import_data: json
            },
            success: function(response) {
                if (response.success) {
                    alert('Imported ' + response.data.count + ' events. Page will reload.');
                    location.reload();
                } else {
                    alert('Import failed: ' + response.data);
                }
            }
        });
    });
    
    }); // end document ready
})(jQuery);
