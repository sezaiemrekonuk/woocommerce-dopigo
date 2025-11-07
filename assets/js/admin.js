jQuery(document).ready(function($) {
    'use strict';

    /**
     * Test Connection Button
     */
    $('#dopigo-test-connection').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#dopigo-test-result');
        var originalText = $button.text();

        $button.prop('disabled', true).text(dopigoAdmin.strings.testing);
        $result.html('');

        $.ajax({
            url: dopigoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'dopigo_test_connection',
                nonce: dopigoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $result.html('<span style="color: #dc3232;">✗ ' + dopigoAdmin.strings.error + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    /**
     * Generate Token Button
     */
    $('#dopigo-generate-token').on('click', function(e) {
        e.preventDefault();

        var username = $('#dopigo_username').val();
        var password = $('#dopigo_password').val();
        var $button = $(this);
        var originalText = $button.text();

        if (!username || !password) {
            alert('Please enter both username and password');
            return;
        }

        $button.prop('disabled', true).text('Generating...');

        $.ajax({
            url: dopigoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'dopigo_generate_token',
                nonce: dopigoAdmin.nonce,
                username: username,
                password: password
            },
            success: function(response) {
                if (response.success) {
                    $('#dopigo_api_key').val(response.data.token);
                    alert('Token generated successfully! Please save your settings.');
                } else {
                    alert('Failed to generate token: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error generating token');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    /**
     * Fetch Products Button
     */
    $('#dopigo-fetch-products').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#dopigo-products-result');
        var $preview = $('#dopigo-products-preview');
        var originalText = $button.html();

        $button.prop('disabled', true).html('<span class="spinner is-active" style="float: none;"></span> ' + dopigoAdmin.strings.fetching);
        $result.html('');
        $preview.hide();

        $.ajax({
            url: dopigoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'dopigo_get_all_products',
                nonce: dopigoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var products = response.data.products;
                    var count = response.data.count;
                    var total_count = response.data.total_count || count;

                    var message = '✓ Successfully fetched ' + count + ' products from Dopigo';
                    if (total_count !== count) {
                        message += ' (expected: ' + total_count + ')';
                    }
                    $result.html('<div class="notice notice-success"><p>' + message + '</p></div>');

                    // Store products data - handle paginated response structure
                    if (products && products.results && Array.isArray(products.results)) {
                        window.dopigoProducts = products.results;
                    } else if (Array.isArray(products)) {
                        window.dopigoProducts = products;
                    } else {
                        window.dopigoProducts = [];
                    }

                    if (window.dopigoProducts.length === 0) {
                        $result.html('<div class="notice notice-warning"><p>⚠ No products found in response</p></div>');
                        return;
                    }

                    // Show preview
                    displayProductsPreview(window.dopigoProducts);
                    $preview.show();

                } else {
                    $result.html('<div class="notice notice-error"><p>✗ ' + response.data.message + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<div class="notice notice-error"><p>✗ Error: ' + error + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    /**
     * Import Products Button
     */
    $('#dopigo-import-products').on('click', function(e) {
        e.preventDefault();

        if (!window.dopigoProducts || window.dopigoProducts.length === 0) {
            alert('No products to import');
            return;
        }

        if (!confirm(dopigoAdmin.strings.confirm)) {
            return;
        }

        var $button = $(this);
        var originalText = $button.html();

        $button.prop('disabled', true).html('<span class="spinner is-active" style="float: none;"></span> ' + dopigoAdmin.strings.importing);

        // Get selected products (or all if none selected)
        var selectedProducts = [];
        $('.dopigo-product-select:checked').each(function() {
            var index = $(this).data('index');
            selectedProducts.push(window.dopigoProducts[index]);
        });

        if (selectedProducts.length === 0) {
            selectedProducts = window.dopigoProducts;
        }

        $.ajax({
            url: dopigoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'dopigo_import_products',
                nonce: dopigoAdmin.nonce,
                products: JSON.stringify(selectedProducts)
            },
            success: function(response) {
                if (response.success) {
                    var message = response.data.message;
                    if (response.data.pending_categories && response.data.pending_categories.length) {
                        message += '\n\n' + dopigoAdmin.strings.pendingCategories.replace('%pending%', response.data.pending_categories.join(', '));
                    }
                    alert(message);
                    location.reload();
                } else {
                    alert('Import failed: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error importing products');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    /**
     * Display products preview
     */
    function displayProductsPreview(products) {
        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th style="width: 40px;"><input type="checkbox" id="select-all-products"></th>';
        html += '<th>Product Name</th>';
        html += '<th>SKU</th>';
        html += '<th>Price</th>';
        html += '<th>Stock</th>';
        html += '<th>Status</th>';
        html += '</tr></thead>';
        html += '<tbody>';

        $.each(products, function(index, product) {
            var primaryVariant = product.products && product.products.length > 0 ? product.products[0] : {};
            var activeText = product.active ? '<span style="color: #46b450;">Active</span>' : '<span style="color: #dc3232;">Inactive</span>';
            
            html += '<tr>';
            html += '<td><input type="checkbox" class="dopigo-product-select" data-index="' + index + '" checked></td>';
            html += '<td><strong>' + escapeHtml(product.name) + '</strong>';
            if (product.products && product.products.length > 1) {
                html += ' <span class="badge" style="background: #2271b1; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">Variable (' + product.products.length + ' variants)</span>';
            }
            html += '</td>';
            html += '<td>' + (primaryVariant.sku || '-') + '</td>';
            html += '<td>' + (primaryVariant.price || '-') + ' ' + (primaryVariant.price_currency || '') + '</td>';
            html += '<td>' + (primaryVariant.available_stock || 0) + '</td>';
            html += '<td>' + activeText + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        $('#dopigo-products-list').html(html);

        // Select all checkbox
        $('#select-all-products').on('change', function() {
            $('.dopigo-product-select').prop('checked', $(this).prop('checked'));
        });
    }

    /**
     * Import Categories Button
     */
    $('#dopigo-import-categories').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#dopigo-categories-result');
        var fileInput = document.getElementById('dopigo-category-xml');
        var file = fileInput ? fileInput.files[0] : null;

        $result.html('');
        setButtonLoading($button, dopigoAdmin.strings.importingCategories);

        if (file) {
            var reader = new FileReader();
            reader.onload = function(event) {
                submitCategoryImport({ xml: event.target.result }, $button, $result);
            };
            reader.onerror = function() {
                resetButton($button);
                $result.html('<div class="notice notice-error"><p>' + dopigoAdmin.strings.readFileError + '</p></div>');
            };
            reader.readAsText(file);
        } else {
            submitCategoryImport({ use_feed: true }, $button, $result);
        }
    });

    /**
     * Fetch Categories from Feed Button
     */
    $('#dopigo-fetch-category-xml').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#dopigo-categories-result');

        $result.html('');
        setButtonLoading($button, dopigoAdmin.strings.fetchingCategories);

        submitCategoryImport({ use_feed: true, force_fetch: true }, $button, $result, function() {
            resetButton($('#dopigo-import-categories'));
        });
    });

    function submitCategoryImport(payload, $button, $result, callback) {
        $.ajax({
            url: dopigoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'dopigo_import_categories',
                nonce: dopigoAdmin.nonce,
                xml: payload.xml || '',
                use_feed: payload.use_feed ? 1 : 0,
                force_fetch: payload.force_fetch ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data || {};
                    var created = data.created || 0;
                    var updated = data.updated || 0;
                    var pending = data.pending || [];
                    var message = dopigoAdmin.strings.importCategoriesSuccess
                        .replace('%created%', created)
                        .replace('%updated%', updated);

                    var noticeClass = 'notice-success';
                    if (pending.length) {
                        noticeClass = 'notice-warning';
                        message += '<br>' + dopigoAdmin.strings.pendingCategories.replace('%pending%', pending.join(', '));
                    }

                    $result.html('<div class="notice ' + noticeClass + '"><p>' + message + '</p></div>');
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : dopigoAdmin.strings.error;
                    $result.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<div class="notice notice-error"><p>' + (error || dopigoAdmin.strings.error) + '</p></div>');
            },
            complete: function() {
                resetButton($button);
                if (typeof callback === 'function') {
                    callback();
                }
            }
        });
    }

    function setButtonLoading($button, text) {
        if (!$button || !$button.length) {
            return;
        }
        $button.data('original-html', $button.html());
        $button.prop('disabled', true).html('<span class="spinner is-active" style="float: none;"></span> ' + text);
    }

    function resetButton($button) {
        if (!$button || !$button.length) {
            return;
        }
        var original = $button.data('original-html');
        $button.prop('disabled', false);
        if (original) {
            $button.html(original);
            $button.removeData('original-html');
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Trigger Sync Button
     */
    $('#dopigo-trigger-sync').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#dopigo-trigger-result');
        var $progressContainer = $('#dopigo-sync-progress-container');
        var $progressDetails = $('#dopigo-sync-progress-details');
        var originalText = $button.text();

        if (!confirm('Are you sure you want to trigger the sync now? This may take a few minutes.')) {
            return;
        }

        $button.prop('disabled', true).text('Starting...');
        $result.html('');
        $progressContainer.show();
        $progressDetails.html('<div class="spinner is-active" style="float: none; margin: 10px 0;"></div> <span>Initializing sync...</span>');

        // Start sync
        $.ajax({
            url: dopigoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'dopigo_trigger_sync',
                nonce: dopigoAdmin.nonce
            },
            timeout: 30000,
            success: function(response) {
                if (response.success && response.data.progress_key) {
                    $result.html('<span style="color: #46b450;">✓ Sync started!</span>');
                    
                    // Start polling for progress
                    var progressKey = response.data.progress_key;
                    pollSyncProgress(progressKey, $progressDetails, $button, originalText);
                } else {
                    $result.html('<span style="color: #dc3232;">✗ ' + (response.data.message || 'Failed to start sync') + '</span>');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span style="color: #dc3232;">✗ Error: ' + error + '</span>');
                $button.prop('disabled', false).text(originalText);
                $progressContainer.hide();
            }
        });
    });

    /**
     * Poll sync progress
     */
    function pollSyncProgress(progressKey, $progressDetails, $button, originalText) {
        var pollInterval = setInterval(function() {
            $.ajax({
                url: dopigoAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dopigo_get_sync_progress',
                    nonce: dopigoAdmin.nonce,
                    progress_key: progressKey
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var progress = response.data;
                        updateProgressDisplay(progress, $progressDetails);
                        
                        // Check if completed or error
                        if (progress.status === 'completed' || progress.status === 'error') {
                            clearInterval(pollInterval);
                            $button.prop('disabled', false).text(originalText);
                            
                            if (progress.status === 'completed') {
                                setTimeout(function() {
                                    location.reload();
                                }, 3000);
                            }
                        }
                    } else {
                        // Progress not found or error
                        $progressDetails.html('<div style="color: #dc3232;">Unable to get progress. Sync may still be running.</div>');
                    }
                },
                error: function() {
                    // Continue polling even on error
                }
            });
        }, 1000); // Poll every second
        
        // Stop polling after 10 minutes (fallback)
        setTimeout(function() {
            clearInterval(pollInterval);
        }, 600000);
    }

    /**
     * Update progress display
     */
    function updateProgressDisplay(progress, $container) {
        var html = '';
        var total = progress.total || 0;
        var processed = progress.processed || 0;
        var success = progress.success || 0;
        var errors = progress.errors || 0;
        var percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
        var currentProduct = progress.current_product || 'Processing...';
        var currentMetaId = progress.current_meta_id || '';
        
        // Status badge
        var statusClass = 'running';
        var statusText = 'Running';
        if (progress.status === 'completed') {
            statusClass = 'completed';
            statusText = 'Completed';
        } else if (progress.status === 'error') {
            statusClass = 'error';
            statusText = 'Error';
        }
        
        html += '<div style="margin-bottom: 15px;">';
        html += '<span class="dopigo-status-badge ' + statusClass + '">' + statusText + '</span>';
        if (progress.message) {
            html += '<span style="margin-left: 10px;">' + escapeHtml(progress.message) + '</span>';
        }
        html += '</div>';
        
        // Progress bar
        html += '<div style="margin-bottom: 15px;">';
        html += '<div style="background: #f0f0f0; border-radius: 4px; height: 25px; position: relative; overflow: hidden;">';
        html += '<div style="background: #2271b1; height: 100%; width: ' + percentage + '%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 12px;">';
        html += percentage + '%';
        html += '</div>';
        html += '</div>';
        html += '<div style="margin-top: 5px; font-size: 12px; color: #666;">';
        html += 'Progress: ' + processed + ' / ' + total + ' products';
        if (progress.duration) {
            html += ' | Duration: ' + Math.round(progress.duration) + ' seconds';
        }
        html += '</div>';
        html += '</div>';
        
        // Current product
        html += '<div style="padding: 10px; background: #fff; border-left: 4px solid #2271b1; margin-bottom: 10px;">';
        html += '<strong>Currently Processing:</strong><br>';
        html += '<span style="color: #2271b1; font-weight: 600;">' + escapeHtml(currentProduct) + '</span>';
        if (currentMetaId) {
            html += '<br><small style="color: #666;">Meta ID: ' + currentMetaId + '</small>';
        }
        html += '</div>';
        
        // Statistics
        html += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">';
        html += '<div style="padding: 10px; background: #fff; text-align: center; border: 1px solid #ddd;">';
        html += '<div style="font-size: 24px; font-weight: 700; color: #2271b1;">' + processed + '</div>';
        html += '<div style="font-size: 12px; color: #666;">Processed</div>';
        html += '</div>';
        html += '<div style="padding: 10px; background: #fff; text-align: center; border: 1px solid #ddd;">';
        html += '<div style="font-size: 24px; font-weight: 700; color: #46b450;">' + success + '</div>';
        html += '<div style="font-size: 12px; color: #666;">Success</div>';
        html += '</div>';
        html += '<div style="padding: 10px; background: #fff; text-align: center; border: 1px solid #ddd;">';
        html += '<div style="font-size: 24px; font-weight: 700; color: #dc3232;">' + errors + '</div>';
        html += '<div style="font-size: 12px; color: #666;">Errors</div>';
        html += '</div>';
        html += '</div>';
        
        // Product list (recent)
        if (progress.recent_products && progress.recent_products.length > 0) {
            html += '<div style="margin-top: 15px;">';
            html += '<strong>Recent Products:</strong>';
            html += '<ul style="list-style: none; padding: 0; margin: 5px 0;">';
            progress.recent_products.slice(-5).reverse().forEach(function(product) {
                html += '<li style="padding: 5px; background: #fff; margin: 3px 0; border-left: 3px solid #46b450;">';
                html += escapeHtml(product.name || 'Unknown') + ' <small style="color: #666;">(' + product.status + ')</small>';
                html += '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        $container.html(html);
    }

    /**
     * Reschedule Sync Button
     */
    $('#dopigo-reschedule-sync').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $result = $('#dopigo-reschedule-result');
        var originalText = $button.text();

        $button.prop('disabled', true).text('Rescheduling...');
        $result.html('');

        $.ajax({
            url: dopigoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'dopigo_reschedule_sync',
                nonce: dopigoAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                    $result.append('<br><small>Next run: ' + response.data.next_run + ' (' + response.data.time_until + ' from now)</small>');
                    // Reload page after 2 seconds to show updated status
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span style="color: #dc3232;">✗ Error: ' + error + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    /**
     * AJAX: Generate Token
     */
    $(document).on('click', '#dopigo-generate-token', function(e) {
        e.preventDefault();
        
        var username = $('#dopigo_username').val();
        var password = $('#dopigo_password').val();
        
        if (!username || !password) {
            alert('Please enter username and password');
            return;
        }

        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Generating...');

        // Call the PHP function directly via AJAX
        $.ajax({
            url: dopigoAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'dopigo_generate_token_ajax',
                nonce: dopigoAdmin.nonce,
                username: username,
                password: password
            },
            success: function(response) {
                if (response.success && response.data.token) {
                    $('#dopigo_api_key').val(response.data.token);
                    alert('Token generated! Please save your settings to persist it.');
                } else {
                    alert('Failed to generate token: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error generating token');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
});

