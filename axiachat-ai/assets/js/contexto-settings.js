const { __, sprintf } = wp.i18n;

jQuery(document).ready(function($) {
    // Toggle RAG
    $('#aichat_rag_enabled').on('change', function() {
        var enabled = $(this).is(':checked') ? 1 : 0;
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_toggle_rag',
                nonce: aichat_settings_ajax.nonce,
                enabled: enabled
            },
            success: function(response) {
                if (response.success) {
                    $('#aichat-message').text(__('RAG status updated.', 'axiachat-ai')).css('color', 'green').show().fadeOut(3000);
                } else {
                    $('#aichat-message').text(__('Error updating RAG status.', 'axiachat-ai')).show();
                }
            },
            error: function(xhr, status, error) {
                $('#aichat-message').text(__('Error:', 'axiachat-ai') + ' ' + error).show();
                console.log('AJAX Error:', xhr.responseText);
            }
        });
    });

    // Actualizar Active Context via AJAX
    $('#aichat_active_context').on('change', function() {
        var contextId = $(this).val();
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_update_active_context',
                nonce: aichat_settings_ajax.nonce,
                context_id: contextId
            },
            success: function(response) {
                if (response.success) {
                    $('#aichat-message').text(__('Active context updated.', 'axiachat-ai')).css('color', 'green').show().fadeOut(3000);
                } else {
                    $('#aichat-message').text(__('Error updating active context.', 'axiachat-ai')).show();
                }
            },
            error: function(xhr, status, error) {
                $('#aichat-message').text(__('Error:', 'axiachat-ai') + ' ' + error).show();
                console.log('AJAX Error:', xhr.responseText);
            }
        });
    });

    // Cargar contextos al iniciar
    function loadContexts() {
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action: 'aichat_load_contexts', nonce: aichat_settings_ajax.nonce },
            success: function(response) {
                if (!response.success) {
                    $('#aichat-message').text(response.data && response.data.message ? response.data.message : __('Error loading contexts', 'axiachat-ai')).show();
                    return;
                }
                var tbody = $('#aichat-contexts-body');
                tbody.empty();
                var list = response.data.contexts || [];
                if(list.length===0){
                    tbody.append('<tr><td colspan="8" class="text-center py-4 text-muted">'+(aichat_settings_ajax.no_contexts || __('No contexts', 'axiachat-ai'))+'</td></tr>');
                } else {
                    $.each(list, function(i,context){
                        var progress = parseInt(context.processing_progress || 0,10);
                        var createdStatus = (context.created_at ? context.created_at : '') + ' / ' + (context.processing_status || '');
                        var isPending = (context.processing_status === 'pending' || context.processing_status === 'processing' || context.processing_status === 'in_progress' || context.processing_status === 'wizard');
                        var runLabel = (aichat_settings_ajax.run_autosync || 'Run AutoSync');
                        var disabledAttr = isPending ? ' disabled="disabled"' : '';
                        var pendingHint = isPending ? ' data-bs-toggle="tooltip" title="Processing in progress"' : '';
                        // For Browse button we need context_type; fallback local if not provided
                        var isLocal = (context.context_type ? context.context_type === 'local' : true);
                        // AI Provider column
                        var providerLabel = '';
                        var ep = (context.embedding_provider || '').toLowerCase();
                        if (ep === 'openai') {
                            providerLabel = '🟢 OpenAI';
                        } else if (ep === 'claude' || ep === 'anthropic') {
                            providerLabel = '🟠 Claude';
                        } else if (ep === 'gemini') {
                            providerLabel = '🔵 Gemini';
                        } else if (ep) {
                            providerLabel = '⚪ ' + ep.charAt(0).toUpperCase() + ep.slice(1);
                        }
                        tbody.append(
                            '<tr>'+
                              '<td class="text-muted small">'+context.id+'</td>'+
                              '<td><span class="context-name fw-semibold" data-id="'+context.id+'"><i class="bi bi-folder2"></i> '+escapeHtml(context.name||'')+'</span></td>'+
                              '<td class="text-muted small">'+providerLabel+'</td>'+
                              '<td class="text-muted small">'+(context.chunk_count||0)+'</td>'+
                              '<td class="text-muted small">'+(context.post_count||0)+'</td>'+
                              '<td class="text-muted small">'+escapeHtml(createdStatus)+'</td>'+
                              '<td><div class="progress" style="height:14px;"><div class="progress-bar" role="progressbar" style="width:'+progress+'%;" aria-valuenow="'+progress+'" aria-valuemin="0" aria-valuemax="100">'+progress+'%</div></div></td>'+
                              '<td class="text-end"><div class="btn-group" role="group">'+
                                  '<button class="button btn btn-sm btn-outline-secondary edit-context-settings" data-id="'+context.id+'"><i class="bi bi-gear"></i> '+(aichat_settings_ajax.settings_label || 'Settings')+'</button> '+
                                  '<button class="button btn btn-sm btn-outline-secondary edit-context-simtest" data-id="'+context.id+'"><i class="bi bi-search"></i> '+(aichat_settings_ajax.similarity_label || 'Similarity')+'</button> '+
                                  (isLocal ? '<button type="button" class="button btn btn-sm btn-outline-dark browse-context" data-id="'+context.id+'"><i class="bi bi-list-ul"></i> '+(aichat_settings_ajax.browse_label||'Browse')+'</button> ' : '')+
                                  '<button type="button" class="button btn btn-sm btn-outline-info run-autosync-now"'+disabledAttr+pendingHint+' data-id="'+context.id+'"><i class="bi bi-arrow-repeat"></i> '+runLabel+'</button> '+
                                  '<button class="button btn btn-sm btn-outline-danger delete-context" data-id="'+context.id+'"><i class="bi bi-trash"></i> '+aichat_settings_ajax.delete_text+'</button>'+
                              '</div></td>'+
                            '</tr>'
                        );
                    });
                }
                $('#aichat-message').text('').hide();
                startProgressUpdates();
            },
            error: function(xhr, status, error){
                $('#aichat-message').text(__('Error loading contexts:', 'axiachat-ai') + ' ' + error).show();
                console.log('AJAX Error (loadContexts):', xhr.responseText);
            }
        });
    }

    // Función para actualizar el progreso
    function updateProgress() {
        $('table#aichat-contexts-table .progress-bar').each(function() {
            var $progressBar = $(this);
            var contextId = $progressBar.closest('tr').find('.context-name').data('id');
            $.ajax({
                url: aichat_settings_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'aichat_update_progress',
                    nonce: aichat_settings_ajax.nonce,
                    context_id: contextId
                },
                success: function(response) {
                    if (response.success) {
                        var progress = response.data.progress || 0;
                        $progressBar.css('width', progress + '%').attr('aria-valuenow', progress);
                    } else {
                        console.log('Error in AJAX response for context ' + contextId + ': ', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error for context ' + contextId + ': ' + error);
                    // Mantener el valor anterior si falla
                    $progressBar.css('width', $progressBar.attr('aria-valuenow') + '%');
                }
            });
        });
    }

    // Iniciar actualizaciones de progreso
    function startProgressUpdates() {
        updateProgress(); // Primera actualización inmediata
        setInterval(updateProgress, 10000); // Actualizar cada 10 segundos
    }

    // Cargar contextos al iniciar
    loadContexts();

    function openInnerTab(tabBtnId){
        var btn = document.getElementById(tabBtnId);
        if(btn && window.bootstrap && bootstrap.Tab){
            bootstrap.Tab.getOrCreateInstance(btn).show();
        } else {
            $('#aichat-inner-tabs button').removeClass('active');
            $('#'+tabBtnId).addClass('active');
            $('.tab-pane','#aichat-inner-tabcontent').removeClass('show active');
            var target = $('#'+tabBtnId).data('bs-target');
            if(target){ $(target).addClass('show active'); }
        }
    }

    function openContextPanel(id){
        $('#aichat-context-test-wrapper').data('context-id', id);
        $('#aichat-context-panel-name').text('#'+id);
        $('#aichat-test-results').hide();
        $('#aichat-test-status').hide();
        $('#aichat-context-meta').hide();
        if($('#aichat-context-test-wrapper').is(':hidden')){
            $('#aichat-context-test-wrapper').slideDown(150);
        }
        fetchContextMeta(id);
    }

    $(document).on('click','.edit-context-settings', function(){
        var id = $(this).data('id');
        openContextPanel(id);
        openInnerTab('aichat-tab-settings');
    });
    $(document).on('click','.edit-context-simtest', function(){
        var id = $(this).data('id');
        openContextPanel(id);
        openInnerTab('aichat-tab-simtest');
    });

    // Guardar nombre (botón dentro del panel)
    $(document).on('click', '#aichat-save-context-name', function(){
        var id = $('#aichat-context-test-wrapper').data('context-id');
        var newName = $('#aichat-edit-context-name').val().trim();
        var autosyncEnabled = $('#aichat-autosync-toggle').is(':checked') ? 1 : 0;
        var autosyncMode = $('#aichat-autosync-mode').val();
        if(!id) return false;
        if(newName===''){ alert(__('Name required', 'axiachat-ai')); return false; }
        var btn = $(this).prop('disabled', true);
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action: 'aichat_update_context_name', nonce: aichat_settings_ajax.nonce, id: id, name: newName, autosync: autosyncEnabled, autosync_mode: autosyncMode },
            success: function(r){
                if(r.success){
                    // Also save indexing options
                    $.post(aichat_settings_ajax.ajax_url, {
                        action: 'aichat_save_indexing_options',
                        nonce: aichat_settings_ajax.nonce,
                        context_id: id,
                        indexing_options: JSON.stringify(collectSettingsIndexingOptions())
                    });
                    // Actualizar en la tabla visible
                    $('.context-name[data-id="'+id+'"]').text(newName);
                    $('#aichat-message').text(aichat_settings_ajax.updated_text || 'Updated').css('color','green').show().fadeOut(2500);
                    loadContexts();
                } else {
                    alert(r.data && r.data.message ? r.data.message : __('Error', 'axiachat-ai'));
                }
            },
            error: function(){ alert(__('Request failed', 'axiachat-ai')); },
            complete: function(){ btn.prop('disabled', false); }
        });
        return false;
    });

    function fetchContextMeta(id){
        $('#aichat-context-meta').hide();
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action: 'aichat_get_context_meta', nonce: aichat_settings_ajax.nonce, id: id },
            success: function(resp){
                if(resp.success){
                    var c = resp.data.context;
                    $('#aichat-edit-context-name').val(c.name||'');
                    // Actualizar título principal: "Context <Name> (#ID)"
                    var displayName = c.name ? c.name : ('#'+c.id);
                    $('#aichat-context-panel-name').text(displayName + ' (#'+c.id+')');
                    // Autosync handling
                    var enabled = parseInt(c.autosync,10) === 1;
                    $('#aichat-autosync-toggle').prop('checked', enabled);
                    var postTypesRaw = c.autosync_post_types || '';
                    var isAllContext = /ALL_POSTS|ALL_PAGES|ALL_PRODUCTS/.test(postTypesRaw);
                    if(enabled){
                        $('#aichat-autosync-mode-wrapper').show();
                    } else {
                        $('#aichat-autosync-mode-wrapper').hide();
                    }
                    if (c.autosync_mode){
                        $('#aichat-autosync-mode').val(c.autosync_mode);
                    }
                    if(!isAllContext){
                        // Force updates only
                        $('#aichat-autosync-mode').val('updates');
                        $('#aichat-autosync-mode option[value="updates_and_new"]').prop('disabled', true);
                        $('#aichat-autosync-mode-restricted').show();
                    } else {
                        $('#aichat-autosync-mode option[value="updates_and_new"]').prop('disabled', false);
                        $('#aichat-autosync-mode-restricted').hide();
                    }
                    $('#aichat-context-meta').fadeIn(120);

                    // Also load indexing options
                    loadSettingsIndexingOptions(id);
                } else {
                    $('#aichat-meta-created').text(__('Error', 'axiachat-ai'));
                }
            },
            error: function(){ $('#aichat-meta-created').text(__('Error', 'axiachat-ai')); }
        });
    }

    // Habilitar/deshabilitar select de modo cuando se cambia el switch
    $(document).on('change','#aichat-autosync-toggle', function(){
        var en = $(this).is(':checked');
        if(en){
            $('#aichat-autosync-mode-wrapper').slideDown(120);
        } else {
            $('#aichat-autosync-mode-wrapper').slideUp(120);
        }
    });

    // === Indexing Options helpers ===
    function loadSettingsIndexingOptions(ctxId){
        $.post(aichat_settings_ajax.ajax_url, {
            action: 'aichat_get_indexing_options',
            nonce: aichat_settings_ajax.nonce,
            context_id: ctxId
        }, function(resp){
            if(!resp || !resp.success) return;
            var o = resp.data.options || {};
            $('#aichat-settings-idx-excerpt').prop('checked', !!o.include_excerpt);
            $('#aichat-settings-idx-url').prop('checked', !!o.include_url);
            $('#aichat-settings-idx-featured-image').prop('checked', !!o.include_featured_image);
            $('#aichat-settings-idx-taxonomies').val((o.include_taxonomies||[]).join(', '));
            $('#aichat-settings-idx-custom-meta').val((o.custom_meta_keys||[]).join(', '));
            $('#aichat-settings-idx-wc-short-desc').prop('checked', !!o.include_wc_short_description);
            $('#aichat-settings-idx-wc-attributes').prop('checked', !!o.include_wc_attributes);
        });
    }
    function collectSettingsIndexingOptions(){
        var opts = {
            include_excerpt: $('#aichat-settings-idx-excerpt').is(':checked'),
            include_url: $('#aichat-settings-idx-url').is(':checked'),
            include_featured_image: $('#aichat-settings-idx-featured-image').is(':checked'),
            include_taxonomies: [],
            include_wc_short_description: $('#aichat-settings-idx-wc-short-desc').is(':checked'),
            include_wc_attributes: $('#aichat-settings-idx-wc-attributes').is(':checked'),
            include_meta_fields: [],
            custom_meta_keys: []
        };
        var taxRaw = ($('#aichat-settings-idx-taxonomies').val()||'').trim();
        if(taxRaw) opts.include_taxonomies = taxRaw.split(',').map(function(s){return s.trim();}).filter(Boolean);
        var metaRaw = ($('#aichat-settings-idx-custom-meta').val()||'').trim();
        if(metaRaw) opts.custom_meta_keys = metaRaw.split(',').map(function(s){return s.trim();}).filter(Boolean);
        return opts;
    }

    // Borrar contexto
    $(document).on('click', '.delete-context', function() {
        if (confirm(aichat_settings_ajax.delete_confirm)) {
            var id = $(this).data('id');
            var row = $(this).closest('tr');
            console.log('Delete confirmed for ID:', id);

            $.ajax({
                url: aichat_settings_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'aichat_delete_context',
                    nonce: aichat_settings_ajax.nonce,
                    id: id
                },
                success: function(response) {
                    console.log('AJAX Success (delete):', response);
                    if (response.success) {
                        row.remove();
                        loadContexts();
                        $('#aichat-message').text(aichat_settings_ajax.deleted_text).css('color', 'green').show().fadeOut(3000);
                    } else {
                        $('#aichat-message').text(response.data.message).show();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error (delete):', xhr.status, xhr.statusText, xhr.responseText);
                    $('#aichat-message').text(__('Error deleting context:', 'axiachat-ai') + ' ' + error).show();
                }
            });
        }
    });

    // Cerrar tarjeta de test
    $(document).on('click', '#aichat-close-test', function(){
        $('#aichat-context-test-wrapper').slideUp(120);
        $('#aichat-test-results').hide();
        $('#aichat-test-status').hide();
    });

    // Ejecutar búsqueda semántica de prueba
    $(document).on('click', '#aichat-run-test', function(){
        var ctxId = $('#aichat-context-test-wrapper').data('context-id') || 0;
        var q = $('#aichat-test-query').val().trim();
        var limit = $('#aichat-test-limit').val();
        if(!ctxId){
            alert(__('Select a context first (edit).', 'axiachat-ai'));
            return false;
        }
        if(!q){
            $('#aichat-test-query').focus();
            return false;
        }
        var $status = $('#aichat-test-status');
        $status.text(aichat_settings_ajax.searching || 'Searching...').show();
        $('#aichat-test-results').hide();
        $('#aichat-test-results tbody').empty();
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'aichat_search_context_chunks',
                nonce: aichat_settings_ajax.nonce,
                context_id: ctxId,
                q: q,
                limit: limit
            },
            success: function(resp){
                if(resp.success){
                    var rows = resp.data.results || [];
                    if(rows.length === 0){
                        $status.text(aichat_settings_ajax.no_results || __('No results', 'axiachat-ai'));
                        return;
                    }
                    $status.text(sprintf(
                        /* translators: %d: Number of search results */
                        __('%d result(s).', 'axiachat-ai'), rows.length));
                    var tbody = $('#aichat-test-results tbody');
                    $.each(rows, function(i,r){
                        var score = (r.score !== undefined) ? r.score.toFixed(4) : '';
                        var cls = (i===0) ? 'table-success' : '';
                        tbody.append('<tr class="'+cls+'">'
                          +'<td><code>'+score+'</code></td>'
                          +'<td>'+escapeHtml(r.title || '')+'</td>'
                          +'<td><small>'+escapeHtml(r.excerpt || '')+'</small></td>'
                          +'</tr>');
                    });
                    $('#aichat-test-results').fadeIn(120);
                } else {
                    $status.text(resp.data && resp.data.message ? resp.data.message : (aichat_settings_ajax.error_generic || 'Error'));
                }
            },
            error: function(xhr){
                $status.text('Error: '+xhr.status);
            }
        });
        return false;
    });

    // Helper para escapar HTML (simple)
    function escapeHtml(str){
        return (str||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]||c;});
    }

    // =====================
    // Run AutoSync Now
    // =====================
    $(document).on('click','.run-autosync-now', function(){
        var ctxId = $(this).data('id');
        $('#aichat-autosync-modal-context-id').val(ctxId);
        // Reset radios
        $('#aichat-autosync-radio-modified').prop('checked', true);
        $('#aichat-autosync-radio-full').prop('checked', false);
        $('#aichat-autosync-radio-modified-new').prop('checked', false).prop('disabled', false);
        $('#aichat-autosync-limited-note').addClass('d-none');
        // Fetch meta to know if LIMITED or mode
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action:'aichat_get_context_meta', nonce: aichat_settings_ajax.nonce, id: ctxId },
            success: function(resp){
                if(resp.success){
                    var c = resp.data.context;
                    var limited = (c.autosync_post_types && c.autosync_post_types === 'LIMITED');
                    var canAdd = (!limited && c.autosync_mode === 'updates_and_new');
                    if(!canAdd){
                        $('#aichat-autosync-radio-modified-new').prop('disabled', true);
                        $('#aichat-autosync-limited-note').removeClass('d-none');
                    }
                }
                var modalEl = document.getElementById('aichat-autosync-modal');
                if(window.bootstrap && bootstrap.Modal){
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    // Fallback simple display
                    $('#aichat-autosync-modal').show();
                }
            },
            error: function(){
                alert(__('Failed to load context meta', 'axiachat-ai'));
            }
        });
    });

    $(document).on('click','#aichat-autosync-run-confirm', function(){
        var btn = $(this).prop('disabled', true);
        var ctxId = $('#aichat-autosync-modal-context-id').val();
        var mode = $('input[name="aichat-autosync-mode-radio"]:checked').val();
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action:'aichat_autosync_run_now', nonce: aichat_settings_ajax.nonce, context_id: ctxId, mode: mode },
            success: function(r){
                if(r.success){
                    $('#aichat-message').text(sprintf(
                    /* translators: %d: Number of items added to queue */
                    __('Autosync queued: %d new item(s).', 'axiachat-ai'), r.data.added_to_queue)).css('color','green').show().fadeOut(6000);
                    // Disable button immediately in current table (if present)
                    $('.run-autosync-now[data-id="'+ctxId+'"]').prop('disabled', true);
                    // Force a refresh soon to reflect pending state & progress reset
                    setTimeout(loadContexts, 500);
                } else {
                    alert(r.data && r.data.message ? r.data.message : __('Error', 'axiachat-ai'));
                }
            },
            error: function(){ alert(__('Request failed', 'axiachat-ai')); },
            complete: function(){ btn.prop('disabled', false); }
        });
        // Close modal
        var modalEl = document.getElementById('aichat-autosync-modal');
        if(window.bootstrap && bootstrap.Modal){
            var inst = bootstrap.Modal.getInstance(modalEl); if(inst) inst.hide();
        } else { $('#aichat-autosync-modal').hide(); }
    });

    // Periodic contexts refresh (status + ability to re-enable button after completion)
    setInterval(function(){
        // If edit panel open we may still want status updates in main table
        loadContexts();
    }, 30000); // every 30s

    // =====================
    // Browse Chunks Feature
    // =====================
    var browseState = { page:1, total_pages:0, ctx:0, timer:null, lastQuery:'' };

    function openBrowseTab(){
        var tabBtn = document.getElementById('aichat-tab-browse');
        if(tabBtn && window.bootstrap && bootstrap.Tab){
            bootstrap.Tab.getOrCreateInstance(tabBtn).show();
        } else {
            // fallback: add classes manually
            $('#aichat-inner-tabs button').removeClass('active');
            $('#aichat-tab-browse').addClass('active');
            $('.tab-pane','#aichat-inner-tabcontent').removeClass('show active');
            $('#aichat-pane-browse').addClass('show active');
        }
    }

    function fetchBrowse(){
        var ctxId = browseState.ctx;
        if(!ctxId) return;
        var q = $('#aichat-browse-q').val().trim();
        var type = $('#aichat-browse-type').val();
        var per = $('#aichat-browse-perpage').val();
        $('#aichat-browse-status').text(aichat_settings_ajax.loading || 'Loading...').show();
        $('#aichat-browse-results').hide();
        $.ajax({
            url: aichat_settings_ajax.ajax_url,
            method: 'POST',
            data: { action:'aichat_browse_context_chunks', nonce:aichat_settings_ajax.nonce, context_id: ctxId, page: browseState.page, per_page: per, q: q, type: type },
            success: function(r){
                if(!r.success){
                    $('#aichat-browse-status').text(r.data && r.data.message ? r.data.message : 'Error');
                    return;
                }
                var rows = r.data.rows || [];
                var tbody = $('#aichat-browse-results tbody').empty();
                if(rows.length===0){
                    tbody.append('<tr><td colspan="7" class="text-center text-muted small py-3">'+(aichat_settings_ajax.no_chunks || __('No chunks found', 'axiachat-ai'))+'</td></tr>');
                } else {
                    $.each(rows,function(i,row){
                        tbody.append('<tr>'+
                            '<td><code>'+(row.chunk_index)+'</code></td>'+
                            '<td>'+(row.post_id)+'</td>'+
                            '<td>'+escapeHtml(row.type||'')+'</td>'+
                            '<td>'+escapeHtml(row.title||'')+'</td>'+
                            '<td><small>'+(row.updated_at||'')+'</small></td>'+
                            '<td>'+(row.size||'')+'</td>'+
                            '<td><small>'+escapeHtml(row.excerpt||'')+'</small></td>'+
                        '</tr>');
                    });
                }
                $('#aichat-browse-results').show();
                browseState.total_pages = r.data.total_pages || 0;
                var page = r.data.page || 1;
                var info = sprintf(
                    /* translators: 1: Current page, 2: Total pages, 3: Total items */
                    __('Page %1$d / %2$d — %3$d item(s)', 'axiachat-ai'), page, browseState.total_pages||0, r.data.total);
                $('#aichat-browse-pageinfo').text(info);
                $('#aichat-browse-pager').show();
                $('#aichat-browse-prev').prop('disabled', page<=1);
                $('#aichat-browse-next').prop('disabled', page>=browseState.total_pages);
                $('#aichat-browse-status').hide();
            },
            error: function(){ $('#aichat-browse-status').text(__('Error', 'axiachat-ai')); }
        });
    }

    function scheduleBrowse(){
        if(browseState.timer) clearTimeout(browseState.timer);
        browseState.timer = setTimeout(function(){ browseState.page=1; fetchBrowse(); }, 400);
    }

    // Ensure that when the user clicks the inner Browse tab (without using the row Browse button)
    // the browse context is updated to the currently opened context (set by Settings/Similarity buttons)
    function ensureBrowseContextSynced(){
        var currentCtx = $('#aichat-context-test-wrapper').data('context-id');
        if(!currentCtx) return;
        if(browseState.ctx !== currentCtx){
            browseState.ctx = currentCtx;
            browseState.page = 1;
            fetchBrowse();
        } else {
            // If no rows loaded yet (e.g., first manual tab click) trigger initial fetch
            if($('#aichat-browse-results tbody').children().length === 0){
                browseState.page = 1;
                fetchBrowse();
            }
        }
    }

    // Click fallback (non-Bootstrap or before shown.bs.tab fires)
    $(document).on('click', '#aichat-tab-browse', function(){
        ensureBrowseContextSynced();
    });

    // Bootstrap tab event (more reliable when using keyboard navigation)
    $(document).on('shown.bs.tab', '#aichat-tab-browse', function(){
        ensureBrowseContextSynced();
    });

    $(document).on('click','.browse-context', function(){
        var id = $(this).data('id');
        $('#aichat-context-test-wrapper').data('context-id', id);
        // Ensure panel open
        if($('#aichat-context-test-wrapper').is(':hidden')){
            $('#aichat-context-test-wrapper').slideDown(150);
        }
        // Load meta (will set name etc.)
        fetchContextMeta(id);
        browseState.ctx = id; browseState.page=1;
        openBrowseTab();
        fetchBrowse();
    });

    $(document).on('click','#aichat-browse-run', function(){ browseState.page=1; fetchBrowse(); });
    $(document).on('input','#aichat-browse-q', scheduleBrowse);
    $(document).on('change','#aichat-browse-type,#aichat-browse-perpage', function(){ browseState.page=1; fetchBrowse(); });
    $(document).on('click','#aichat-browse-prev', function(){ if(browseState.page>1){ browseState.page--; fetchBrowse(); } });
    $(document).on('click','#aichat-browse-next', function(){ if(browseState.page < browseState.total_pages){ browseState.page++; fetchBrowse(); } });
});