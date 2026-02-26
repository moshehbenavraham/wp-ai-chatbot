/**
 * Modify Context – Admin JS
 * Handles listing/removing/adding documents to an existing context.
 *
 * @package AxiaChat
 */
/* global jQuery, aichat_modify_ajax, wp */
(function($){
'use strict';

// --- i18n helpers (mirror contexto-create.js) ---
var _wp_i18n = (typeof wp !== 'undefined' && wp.i18n) ? wp.i18n : null;
function __(text){ return _wp_i18n ? _wp_i18n.__(text, 'axiachat-ai') : text; }
function sprintf(fmt){
    var args = Array.prototype.slice.call(arguments, 1), i = 0;
    return fmt.replace(/%[sd%]/g, function(m){ if(m==='%%') return '%'; return args[i++]; });
}
function escapeHtml(s){
    if(!s) return '';
    var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML;
}

var cfg = aichat_modify_ajax || {};
var selectedContextId = 0;
var selectedContextType = '';
var selectedProvider = '';
var selectedStatus = '';

// =====================
// State for documents table
// =====================
var docsState = {page:1, total_pages:0, timer:null};

// =====================
// State for add processing
// =====================
var addProcessing = false;

$(document).ready(function(){

    // =====================
    // Context Selector
    // =====================
    $('#aichat-modify-context-select').on('change', function(){
        var val = $(this).val();
        if(!val){
            selectedContextId = 0;
            $('#aichat-modify-main').slideUp(150);
            $('#aichat-modify-context-info').hide();
            return;
        }
        var opt = $(this).find('option:selected');
        selectedContextId   = parseInt(val, 10);
        selectedContextType = opt.data('type') || 'local';
        selectedProvider    = opt.data('provider') || '';
        selectedStatus      = opt.data('status') || '';

        // Show info
        var info = __('Type') + ': ' + selectedContextType;
        if(selectedProvider) info += ' | ' + __('Provider') + ': ' + selectedProvider;
        if(selectedStatus !== 'completed') info += ' | ⚠ ' + __('Status') + ': ' + selectedStatus;
        $('#aichat-modify-info-text').text(info);
        $('#aichat-modify-context-info').show();

        // Show/hide processing warning
        if(selectedStatus !== 'completed'){
            $('#aichat-modify-processing-warn').show();
            $('#aichat-modify-add-process').prop('disabled', true);
        } else {
            $('#aichat-modify-processing-warn').hide();
        }

        // Show main area
        $('#aichat-modify-main').slideDown(150);

        // Load documents
        docsState.page = 1;
        fetchDocuments();

        // Load indexing options for this context
        loadIndexingOptions(selectedContextId);

        // Reset add tab
        resetAddTab();
    });

    // =====================
    // INDEXING OPTIONS
    // =====================

    function loadIndexingOptions(ctxId){
        $.post(cfg.ajax_url, {
            action: 'aichat_get_indexing_options',
            nonce: cfg.nonce,
            context_id: ctxId
        }, function(resp){
            if(!resp || !resp.success) return;
            var o = resp.data.options || {};
            $('#aichat-modify-idx-excerpt').prop('checked', !!o.include_excerpt);
            $('#aichat-modify-idx-url').prop('checked', !!o.include_url);
            $('#aichat-modify-idx-featured-image').prop('checked', !!o.include_featured_image);
            $('#aichat-modify-idx-taxonomies').val((o.include_taxonomies||[]).join(', '));
            $('#aichat-modify-idx-custom-meta').val((o.custom_meta_keys||[]).join(', '));
            $('#aichat-modify-idx-wc-short-desc').prop('checked', !!o.include_wc_short_description);
            $('#aichat-modify-idx-wc-attributes').prop('checked', !!o.include_wc_attributes);
            $('#aichat-modify-idx-saved-msg').hide();
        });
    }

    function collectModifyIndexingOptions(){
        var opts = {
            include_excerpt: $('#aichat-modify-idx-excerpt').is(':checked'),
            include_url: $('#aichat-modify-idx-url').is(':checked'),
            include_featured_image: $('#aichat-modify-idx-featured-image').is(':checked'),
            include_taxonomies: [],
            include_wc_short_description: $('#aichat-modify-idx-wc-short-desc').is(':checked'),
            include_wc_attributes: $('#aichat-modify-idx-wc-attributes').is(':checked'),
            include_meta_fields: [],
            custom_meta_keys: []
        };
        var taxRaw = ($('#aichat-modify-idx-taxonomies').val()||'').trim();
        if(taxRaw) opts.include_taxonomies = taxRaw.split(',').map(function(s){return s.trim();}).filter(Boolean);
        var metaRaw = ($('#aichat-modify-idx-custom-meta').val()||'').trim();
        if(metaRaw) opts.custom_meta_keys = metaRaw.split(',').map(function(s){return s.trim();}).filter(Boolean);
        return opts;
    }

    $('#aichat-modify-save-indexing-options').on('click', function(){
        if(!selectedContextId) return;
        var btn = $(this);
        btn.prop('disabled', true);
        $.post(cfg.ajax_url, {
            action: 'aichat_save_indexing_options',
            nonce: cfg.nonce,
            context_id: selectedContextId,
            indexing_options: JSON.stringify(collectModifyIndexingOptions())
        }, function(resp){
            btn.prop('disabled', false);
            if(resp && resp.success){
                $('#aichat-modify-idx-saved-msg').show().delay(3000).fadeOut(300);
            } else {
                alert(__('Error saving indexing options'));
            }
        }).fail(function(){
            btn.prop('disabled', false);
            alert(__('Error saving indexing options'));
        });
    });

    // =====================
    // DOCUMENTS TAB
    // =====================
    function fetchDocuments(){
        if(!selectedContextId) return;
        var q    = $('#aichat-modify-docs-search').val().trim();
        var type = $('#aichat-modify-docs-type').val();
        var per  = $('#aichat-modify-docs-perpage').val();
        $('#aichat-modify-docs-status').text(__('Loading...'));
        $.ajax({
            url: cfg.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_modify_list_documents',
                nonce: cfg.nonce,
                context_id: selectedContextId,
                page: docsState.page,
                per_page: per,
                q: q,
                type: type
            },
            success: function(r){
                if(!r.success){
                    $('#aichat-modify-docs-status').text(r.data && r.data.message ? r.data.message : __('Error'));
                    return;
                }
                var rows = r.data.rows || [];
                var tbody = $('#aichat-modify-docs-body').empty();
                if(rows.length === 0){
                    tbody.append('<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> '+__('No documents found.')+'</td></tr>');
                } else {
                    $.each(rows, function(i, row){
                        var editUrl = cfg.admin_url + 'post.php?post=' + row.post_id + '&action=edit';
                        tbody.append(
                            '<tr data-post-id="'+row.post_id+'">'+
                            '<td><input type="checkbox" class="aichat-modify-doc-check" value="'+row.post_id+'"></td>'+
                            '<td>'+row.post_id+'</td>'+
                            '<td><a href="'+escapeHtml(editUrl)+'" target="_blank" class="text-decoration-none">'+escapeHtml(row.title || '(no title)')+'</a></td>'+
                            '<td><span class="badge bg-secondary bg-opacity-25 text-dark">'+escapeHtml(row.type)+'</span></td>'+
                            '<td>'+row.chunk_count+'</td>'+
                            '<td><small>'+escapeHtml(row.last_update || '')+'</small></td>'+
                            '<td class="text-end">'+
                                '<button type="button" class="btn btn-sm btn-outline-danger aichat-modify-remove-one" data-id="'+row.post_id+'" title="'+__('Remove')+'"><i class="bi bi-trash"></i></button>'+
                            '</td>'+
                            '</tr>'
                        );
                    });
                }
                docsState.total_pages = r.data.total_pages || 0;
                var page = r.data.page || 1;
                $('#aichat-modify-docs-pageinfo').text(sprintf(__('Page %s / %s — %s document(s)'), page, docsState.total_pages, r.data.total));
                $('#aichat-modify-docs-pager').show();
                $('#aichat-modify-docs-prev').prop('disabled', page <= 1);
                $('#aichat-modify-docs-next').prop('disabled', page >= docsState.total_pages);
                $('#aichat-modify-docs-status').text('');
                updateRemoveButton();
            },
            error: function(){ $('#aichat-modify-docs-status').text(__('Error')); }
        });
    }

    function scheduleDocsSearch(){
        if(docsState.timer) clearTimeout(docsState.timer);
        docsState.timer = setTimeout(function(){ docsState.page = 1; fetchDocuments(); }, 400);
    }

    function updateRemoveButton(){
        var checked = $('.aichat-modify-doc-check:checked').length;
        $('#aichat-modify-remove-selected').prop('disabled', checked === 0);
    }

    // Select all
    $(document).on('change', '#aichat-modify-select-all-docs', function(){
        var checked = $(this).is(':checked');
        $('.aichat-modify-doc-check').prop('checked', checked);
        updateRemoveButton();
    });
    $(document).on('change', '.aichat-modify-doc-check', updateRemoveButton);

    // Search/filter
    $(document).on('input', '#aichat-modify-docs-search', scheduleDocsSearch);
    $(document).on('change', '#aichat-modify-docs-type, #aichat-modify-docs-perpage', function(){ docsState.page = 1; fetchDocuments(); });
    $(document).on('click', '#aichat-modify-docs-prev', function(){ if(docsState.page > 1){ docsState.page--; fetchDocuments(); } });
    $(document).on('click', '#aichat-modify-docs-next', function(){ if(docsState.page < docsState.total_pages){ docsState.page++; fetchDocuments(); } });
    $(document).on('click', '#aichat-modify-refresh-docs', function(){ fetchDocuments(); });

    // Remove single document
    $(document).on('click', '.aichat-modify-remove-one', function(){
        var postId = $(this).data('id');
        if(!confirm(sprintf(__('Remove document %s from this context?'), postId))) return;
        removeDocuments([postId]);
    });

    // Remove selected
    $(document).on('click', '#aichat-modify-remove-selected', function(){
        var ids = [];
        $('.aichat-modify-doc-check:checked').each(function(){ ids.push(parseInt($(this).val(), 10)); });
        if(ids.length === 0) return;
        if(!confirm(sprintf(__('Remove %s selected document(s) from this context?'), ids.length))) return;
        removeDocuments(ids);
    });

    function removeDocuments(postIds){
        $('#aichat-modify-docs-status').text(__('Removing...'));
        $.ajax({
            url: cfg.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_modify_remove_documents',
                nonce: cfg.nonce,
                context_id: selectedContextId,
                post_ids: postIds
            },
            success: function(r){
                if(!r.success){
                    alert(r.data && r.data.message ? r.data.message : __('Error'));
                    $('#aichat-modify-docs-status').text('');
                    return;
                }
                var d = r.data;
                $('#aichat-modify-docs-status').text(
                    sprintf(__('Removed %s document(s), %s chunk(s) deleted.'), d.removed_posts, d.deleted_chunks)
                );
                // Refresh context info in selector
                var opt = $('#aichat-modify-context-select option[value="'+selectedContextId+'"]');
                if(opt.length){
                    // Update text with new counts
                    var name = opt.text().split(' (#')[0];
                    opt.text(name + ' (#'+selectedContextId+') — '+d.new_doc_count+' docs, '+d.new_chunk_count+' chunks');
                }
                fetchDocuments();
            },
            error: function(){ $('#aichat-modify-docs-status').text(__('Error')); }
        });
    }

    // =====================
    // ADD DOCUMENTS TAB
    // =====================

    // Source mode toggles (All / Custom)
    $(document).on('change', 'input[name^="aichat_modify_"]', function(){
        var name = $(this).attr('name');
        var val  = $(this).val();
        var checked = $(this).is(':checked');

        // Extract post type from name: aichat_modify_posts_mode → post, pages → page, etc.
        var ptMap = {
            'aichat_modify_posts_mode': 'post',
            'aichat_modify_pages_mode': 'page',
            'aichat_modify_products_mode': 'product',
            'aichat_modify_uploaded_mode': 'aichat_upload'
        };
        var pt = ptMap[name];
        if(!pt) return;

        // Uncheck sibling (all vs custom are exclusive)
        $('input[name="'+name+'"]').not(this).prop('checked', false);

        var panel = $('.aichat-modify-source-panel[data-post-type="'+pt+'"]');
        if(val === 'custom' && checked){
            panel.slideDown(200);
            loadModifyItems(pt, 'recent');
        } else {
            panel.slideUp(200);
        }

        updateAddSummary();
    });

    // Select all in items
    $(document).on('change', '.aichat-modify-select-all', function(){
        var target = $($(this).data('target'));
        target.find('input[type="checkbox"]').prop('checked', $(this).is(':checked'));
        updateAddSummary();
    });
    $(document).on('change', '.aichat-modify-source-panel input[type="checkbox"]', function(){
        if(!$(this).hasClass('aichat-modify-select-all')){ updateAddSummary(); }
    });

    // Load items via AJAX
    function loadModifyItems(pt, tab, paged, search){
        paged = paged || 1;
        search = search || '';
        var containerId = '#aichat-modify-' + tab + '-items-' + pt;
        var pagId = '#aichat-modify-' + tab + '-pagination-' + pt;
        $(containerId).html('<span class="text-muted small">'+__('Loading...')+'</span>');
        $.ajax({
            url: cfg.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_modify_load_items',
                nonce: cfg.nonce,
                post_type: pt,
                tab: tab,
                paged: paged,
                search: search,
                context_id: selectedContextId
            },
            success: function(r){
                if(!r.success){ $(containerId).html('<span class="text-muted small">'+__('Error')+'</span>'); return; }
                $(containerId).html(r.data.html);
                // Pagination
                if(tab === 'all' || tab === 'search'){
                    var mp = r.data.max_pages || 1;
                    var cp = r.data.current_page || 1;
                    var pagHtml = '';
                    if(mp > 1){
                        if(cp > 1) pagHtml += '<button class="btn btn-sm btn-outline-secondary aichat-modify-pag-btn" data-pt="'+pt+'" data-tab="'+tab+'" data-page="'+(cp-1)+'">&laquo;</button> ';
                        pagHtml += '<span class="small text-muted mx-1">'+cp+' / '+mp+'</span> ';
                        if(cp < mp) pagHtml += '<button class="btn btn-sm btn-outline-secondary aichat-modify-pag-btn" data-pt="'+pt+'" data-tab="'+tab+'" data-page="'+(cp+1)+'">&raquo;</button>';
                    }
                    $(pagId).html(pagHtml);
                }
            }
        });
    }

    // Pagination clicks
    $(document).on('click', '.aichat-modify-pag-btn', function(){
        var pt   = $(this).data('pt');
        var tab  = $(this).data('tab');
        var page = $(this).data('page');
        var search = '';
        if(tab === 'search'){
            search = $('.aichat-modify-search-input[data-post-type="'+pt+'"]').val() || '';
        }
        loadModifyItems(pt, tab, page, search);
    });

    // Search input
    var searchTimers = {};
    $(document).on('input', '.aichat-modify-search-input', function(){
        var pt = $(this).data('post-type');
        var val = $(this).val();
        if(searchTimers[pt]) clearTimeout(searchTimers[pt]);
        searchTimers[pt] = setTimeout(function(){ loadModifyItems(pt, 'search', 1, val); }, 400);
    });

    // Tab shown → load items
    $(document).on('shown.bs.tab', '.aichat-modify-source-panel .nav-link', function(){
        var target = $(this).data('bs-target') || $(this).attr('href');
        if(!target) return;
        // Parse: #aichat-modify-{tab}-{pt}
        var m = target.match(/#aichat-modify-(recent|all|search)-(.+)/);
        if(m){
            var tab = m[1], pt = m[2];
            var container = $(target).find('.aichat-items');
            if(container.length && container.children().length === 0){
                loadModifyItems(pt, tab);
            }
        }
    });

    function updateAddSummary(){
        var parts = [];
        // Check "All" modes
        if($('input[name="aichat_modify_posts_mode"][value="all"]').is(':checked')) parts.push(__('All Posts'));
        if($('input[name="aichat_modify_pages_mode"][value="all"]').is(':checked')) parts.push(__('All Pages'));
        if($('input[name="aichat_modify_products_mode"][value="all"]').is(':checked')) parts.push(__('All Products'));
        if($('input[name="aichat_modify_uploaded_mode"][value="all"]').is(':checked')) parts.push(__('All Uploaded'));

        // Count custom selections
        var customCount = 0;
        $('.aichat-modify-source-panel').each(function(){
            $(this).find('.aichat-items input[type="checkbox"]:checked').each(function(){
                customCount++;
            });
        });
        if(customCount > 0) parts.push(sprintf(__('%s custom item(s)'), customCount));

        if(parts.length === 0){
            $('#aichat-modify-add-summary').text(__('No selections yet.'));
            $('#aichat-modify-add-process').prop('disabled', true);
        } else {
            $('#aichat-modify-add-summary').text(parts.join(', '));
            $('#aichat-modify-add-process').prop('disabled', selectedStatus !== 'completed');
        }
    }

    function resetAddTab(){
        $('input[name^="aichat_modify_"]').prop('checked', false);
        $('.aichat-modify-source-panel').hide();
        $('.aichat-modify-source-panel .aichat-items').empty();
        $('#aichat-modify-add-summary').text(__('No selections yet.'));
        $('#aichat-modify-add-process').prop('disabled', true);
        $('#aichat-modify-add-progress-wrap').hide();
        $('#aichat-modify-add-log').hide().empty();
        addProcessing = false;
    }

    // =====================
    // Add & Process
    // =====================
    $(document).on('click', '#aichat-modify-add-process', function(){
        if(addProcessing) return;
        if(!selectedContextId) return;

        // Gather selections
        var selected = [];
        var allSelected = [];

        if($('input[name="aichat_modify_posts_mode"][value="all"]').is(':checked')) allSelected.push('all_posts');
        if($('input[name="aichat_modify_pages_mode"][value="all"]').is(':checked')) allSelected.push('all_pages');
        if($('input[name="aichat_modify_products_mode"][value="all"]').is(':checked')) allSelected.push('all_products');
        if($('input[name="aichat_modify_uploaded_mode"][value="all"]').is(':checked')) allSelected.push('all_uploaded');

        $('.aichat-modify-source-panel .aichat-items input[type="checkbox"]:checked').each(function(){
            selected.push(parseInt($(this).val(), 10));
        });

        if(selected.length === 0 && allSelected.length === 0){
            alert(__('Please select at least one document to add.'));
            return;
        }

        addProcessing = true;
        $('#aichat-modify-add-process').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>'+__('Processing...'));
        $('#aichat-modify-add-progress-wrap').show();
        $('#aichat-modify-add-log').show().empty();

        addLog(__('Starting add process...'));
        processAddBatch(0, selected, allSelected);
    });

    function processAddBatch(batch, selected, allSelected){
        $.ajax({
            url: cfg.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_modify_add_documents',
                nonce: cfg.nonce,
                context_id: selectedContextId,
                selected: batch === 0 ? selected : [],
                all_selected: batch === 0 ? allSelected : [],
                batch: batch
            },
            success: function(r){
                if(!r.success){
                    addLog('❌ ' + (r.data && r.data.message ? r.data.message : __('Error')));
                    finishAdd();
                    return;
                }
                var d = r.data;
                var pct = d.progress || 0;
                $('#aichat-modify-add-progress').css('width', pct+'%').attr('aria-valuenow', pct).text(pct+'%');
                addLog(sprintf(__('Batch %s: %s items processed (%s%%)'), d.batch, d.total_processed, pct));

                if(d['continue']){
                    setTimeout(function(){ processAddBatch(d.batch, selected, allSelected); }, 500);
                } else {
                    addLog('✅ ' + __('Completed.'));
                    finishAdd();
                    // Refresh docs list
                    docsState.page = 1;
                    fetchDocuments();
                    // Update selector counts
                    refreshContextSelector();
                }
            },
            error: function(xhr, status, err){
                addLog('❌ ' + __('Network error') + ': ' + err);
                finishAdd();
            }
        });
    }

    function finishAdd(){
        addProcessing = false;
        $('#aichat-modify-add-process').prop('disabled', false).html('<i class="bi bi-plus-circle me-1"></i>'+__('Add & Process Selected'));
    }

    function addLog(msg){
        var el = $('#aichat-modify-add-log');
        el.append('<div>' + escapeHtml(msg) + '</div>');
        el.scrollTop(el[0].scrollHeight);
    }

    function refreshContextSelector(){
        // Reload the page context data via a quick AJAX to update counts in selector
        $.ajax({
            url: cfg.ajax_url,
            method: 'POST',
            data: { action: 'aichat_modify_list_documents', nonce: cfg.nonce, context_id: selectedContextId, per_page: 1 },
            success: function(r){
                if(r.success){
                    var opt = $('#aichat-modify-context-select option[value="'+selectedContextId+'"]');
                    if(opt.length){
                        var name = opt.text().split(' (#')[0];
                        opt.text(name + ' (#'+selectedContextId+') — '+r.data.total+' docs');
                    }
                }
            }
        });
    }

});
})(jQuery);
