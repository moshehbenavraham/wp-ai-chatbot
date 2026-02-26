/**
 * AxiaChat AI – Training Context JS
 *
 * Manages the unified context page: source selection, document listing,
 * file uploads, advanced settings, and save/index operations.
 *
 * Reuses existing AJAX endpoints: aichat_modify_list_documents,
 * aichat_modify_add_documents, aichat_modify_remove_documents,
 * aichat_modify_load_items, aichat_process_context.
 *
 * @since 3.0.1
 */
(function($){
  'use strict';

  const __ = (wp && wp.i18n && wp.i18n.__) ? wp.i18n.__ : function(t){ return t; };

  function escapeHtml(s){
    if (!s) return '';
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
  }

  // ---- Data ----
  const $data = $('#aichat-ctx-data');
  if ( ! $data.length ) return;

  const ajaxUrl     = (window.aichat_training_ajax && window.aichat_training_ajax.ajax_url) || ajaxurl;
  const botId       = parseInt($data.data('bot-id'), 10);
  const botSlug     = $data.data('bot-slug');
  const nonce       = $data.data('nonce');
  const modifyNonce = $data.data('modify-nonce');
  const createNonce = $data.data('create-nonce');
  const pdfNonce    = $data.data('pdf-nonce');
  let   contextId   = parseInt($data.data('context-id'), 10) || 0;
  const contexts    = JSON.parse($data.attr('data-contexts') || '[]');
  const hasWoo      = parseInt($data.data('has-woo'), 10) || 0;

  // ---- Pre-populate indexing options from saved context ----
  (function initIndexingOptions(){
    if (!contextId) return;
    var ctx = contexts.find(function(c){ return parseInt(c.id,10) === contextId; });
    if (!ctx || !ctx.indexing_options) return;
    var opts;
    try { opts = typeof ctx.indexing_options === 'string' ? JSON.parse(ctx.indexing_options) : ctx.indexing_options; } catch(e){ return; }
    if (!opts) return;
    // When saved options exist, explicitly set/unset all checkboxes (overrides HTML defaults)
    $('#aichat-ctx-idx-excerpt').prop('checked', !!opts.include_excerpt);
    $('#aichat-ctx-idx-url').prop('checked', !!opts.include_url);
    $('#aichat-ctx-idx-featured-image').prop('checked', !!opts.include_featured_image);
    $('#aichat-ctx-idx-wc-short-desc').prop('checked', !!opts.include_wc_short_description);
    $('#aichat-ctx-idx-wc-attributes').prop('checked', !!opts.include_wc_attributes);
    if (opts.include_taxonomies && opts.include_taxonomies.length)
      $('#aichat-ctx-idx-taxonomies').val(opts.include_taxonomies.join(', '));
    if (opts.custom_meta_keys && opts.custom_meta_keys.length)
      $('#aichat-ctx-idx-custom-meta').val(opts.custom_meta_keys.join(', '));
  })();

  // ---- Remote (Pinecone) fields toggle ----
  function toggleRemoteFields(){
    var isRemote = $('#aichat-ctx-type').val() === 'remoto';
    $('#aichat-ctx-remote-fields').toggle(isRemote);
  }
  $('#aichat-ctx-type').on('change', toggleRemoteFields);

  // ---- AutoSync mode logic ----
  // Check if any source radio is set to "all"
  function hasAnyAllSource(){
    return $('input[name="aichat_ctx_posts"]:checked').val() === 'all'
        || $('input[name="aichat_ctx_pages"]:checked').val() === 'all'
        || $('input[name="aichat_ctx_products"]:checked').val() === 'all';
  }

  function updateAutosyncModeAvailability(){
    var anyAll = hasAnyAllSource();
    var $opt = $('#aichat-ctx-autosync-mode option[value="updates_and_new"]');
    if (anyAll) {
      $opt.prop('disabled', false);
      $('#aichat-ctx-autosync-help-limited').hide();
      $('#aichat-ctx-autosync-help-general').show();
    } else {
      $('#aichat-ctx-autosync-mode').val('updates');
      $opt.prop('disabled', true);
      if ($('#aichat-ctx-autosync').prop('checked')) {
        $('#aichat-ctx-autosync-help-limited').show();
        $('#aichat-ctx-autosync-help-general').hide();
      }
    }
  }

  // Toggle mode wrapper when autosync checkbox changes
  $('#aichat-ctx-autosync').on('change', function(){
    if ($(this).prop('checked')) {
      $('#aichat-ctx-autosync-mode-wrapper').slideDown(200);
      updateAutosyncModeAvailability();
    } else {
      $('#aichat-ctx-autosync-mode-wrapper').slideUp(200);
    }
  });

  // Re-evaluate when source radios change
  $('input[name="aichat_ctx_posts"], input[name="aichat_ctx_pages"], input[name="aichat_ctx_products"]').on('change', function(){
    if ($('#aichat-ctx-autosync').prop('checked')) {
      updateAutosyncModeAvailability();
    }
  });

  // Init autosync mode availability on page load
  (function(){
    if ($('#aichat-ctx-autosync').prop('checked')) {
      updateAutosyncModeAvailability();
    }
  })();

  // ---- Toast helper ----
  function toast(msg, isError){
    const $t = $('<div class="aichat-training-toast' + (isError ? ' error' : '') + '"></div>').text(msg);
    $('body').append($t);
    setTimeout(function(){ $t.fadeOut(400, function(){ $t.remove(); }); }, 3000);
  }

  // ---- Bot selector navigation ----
  $('#aichat-ctx-bot-select').on('change', function(){
    const slug = $(this).val();
    window.location.href = window.aichat_training_ajax.admin_url + '?page=aichat-training-context&bot=' + encodeURIComponent(slug);
  });

  // ---- Context selector ----
  $('#aichat-ctx-select').on('change', function(){
    contextId = parseInt($(this).val(), 10);
    // Update bot's context_id via AJAX
    $.post(ajaxUrl, {
      action: 'aichat_training_set_bot_context',
      nonce: nonce,
      bot_id: botId,
      context_id: contextId
    });
    // Update form fields from the contexts data array
    var ctx = contexts.find(function(c){ return parseInt(c.id,10) === contextId; });
    if (ctx) {
      $('#aichat-ctx-name').val(ctx.name || '');
      $('#aichat-ctx-type').val(ctx.context_type || 'local').trigger('change');
      // Autosync
      var as = parseInt(ctx.autosync || 0, 10);
      $('#aichat-ctx-autosync').prop('checked', !!as).trigger('change');
      if (ctx.autosync_mode) $('#aichat-ctx-autosync-mode').val(ctx.autosync_mode);
      // Indexing options
      var opts;
      try { opts = typeof ctx.indexing_options === 'string' ? JSON.parse(ctx.indexing_options) : ctx.indexing_options; } catch(e){ opts = null; }
      if (opts) {
        $('#aichat-ctx-idx-excerpt').prop('checked', !!opts.include_excerpt);
        $('#aichat-ctx-idx-url').prop('checked', !!opts.include_url);
        $('#aichat-ctx-idx-featured-image').prop('checked', !!opts.include_featured_image);
        $('#aichat-ctx-idx-wc-short-desc').prop('checked', !!opts.include_wc_short_description);
        $('#aichat-ctx-idx-wc-attributes').prop('checked', !!opts.include_wc_attributes);
        $('#aichat-ctx-idx-taxonomies').val(opts.include_taxonomies && opts.include_taxonomies.length ? opts.include_taxonomies.join(', ') : '');
        $('#aichat-ctx-idx-custom-meta').val(opts.custom_meta_keys && opts.custom_meta_keys.length ? opts.custom_meta_keys.join(', ') : '');
      } else {
        // Reset to defaults when no saved options
        $('#aichat-ctx-idx-excerpt').prop('checked', false);
        $('#aichat-ctx-idx-url').prop('checked', true);
        $('#aichat-ctx-idx-featured-image').prop('checked', false);
        $('#aichat-ctx-idx-wc-short-desc').prop('checked', false);
        $('#aichat-ctx-idx-wc-attributes').prop('checked', false);
        $('#aichat-ctx-idx-taxonomies').val('');
        $('#aichat-ctx-idx-custom-meta').val('');
      }
    }
    loadDocuments();
    updateStats();
  });

  // ---- Advanced toggle ----
  $('#aichat-ctx-advanced-toggle').on('click', function(){
    $(this).toggleClass('open');
    $('#aichat-ctx-advanced-body').toggleClass('show');
  });

  // Knowledge sources limit slider
  $('#aichat-ctx-limit').on('input', function(){
    $('#aichat-ctx-limit-val').text($(this).val());
  });

  // ---- Source radio toggles ----
  // Generic handler for posts / pages / products
  function handleSourceRadio(name, panelSel, searchBoxSel, itemsSel, cardSel, postType) {
    $('input[name="' + name + '"]').on('change', function(){
      const val = $(this).val();
      const $panel = $(panelSel);
      const $searchBox = $(searchBoxSel);

      if (val === 'none' || val === 'all') {
        $panel.removeClass('show');
      } else if (val === 'recent') {
        $panel.addClass('show');
        $searchBox.hide();
        loadItems(postType, itemsSel, 'recent', '');
      } else if (val === 'search') {
        $panel.addClass('show');
        $searchBox.show().find('input').val('').focus();
        $(itemsSel).html('<div class="text-muted small py-2">' + __('Type to search...', 'axiachat-ai') + '</div>');
      }

      toggleCardActive(cardSel);

      // Auto-open card when selecting a source
      const $card = $(cardSel);
      if (val !== 'none' && !$card.hasClass('open')) {
        $card.addClass('open');
      }
    });
  }

  handleSourceRadio('aichat_ctx_posts',    '#aichat-ctx-posts-panel',    '#aichat-ctx-posts-search-box',    '#aichat-ctx-posts-items',    '#aichat-ctx-card-wp',  'post');
  handleSourceRadio('aichat_ctx_pages',    '#aichat-ctx-pages-panel',    '#aichat-ctx-pages-search-box',    '#aichat-ctx-pages-items',    '#aichat-ctx-card-wp',  'page');
  handleSourceRadio('aichat_ctx_products', '#aichat-ctx-products-panel', '#aichat-ctx-products-search-box', '#aichat-ctx-products-items', '#aichat-ctx-card-woo', 'product');

  function toggleCardActive(cardSel){
    const $card = $(cardSel);
    const hasSelection = $card.find('input[type="radio"]:checked').filter(function(){ return $(this).val() !== 'none'; }).length > 0;
    $card.toggleClass('active', hasSelection);
  }

  // ---- Search inside custom panels ----
  $(document).on('input', '.aichat-ctx-search input', function(){
    const $input = $(this);
    const type = $input.data('type');
    const q = $input.val().trim();
    const $target = $input.closest('.aichat-ctx-custom-panel').find('.aichat-ctx-items');

    if (q.length < 2) {
      $target.html('<div class="text-muted small py-2">' + __('Type to search...', 'axiachat-ai') + '</div>');
      return;
    }

    // Debounce
    clearTimeout($input.data('timer'));
    $input.data('timer', setTimeout(function(){
      loadItems(type, '#' + $target.attr('id'), 'search', q);
    }, 300));
  });

  // ---- Load items (posts/pages/products) via existing aichat_modify_load_items ----
  function loadItems(postType, targetSel, tab, search){
    const $target = $(targetSel);
    $target.html('<div class="text-center text-muted small py-2"><i class="bi bi-hourglass-split"></i> ' + __('Loading...', 'axiachat-ai') + '</div>');

    $.post(ajaxUrl, {
      action:     'aichat_modify_load_items',
      nonce:      modifyNonce,
      post_type:  postType,
      tab:        tab || 'recent',
      search:     search || '',
      paged:      1,
      context_id: contextId
    }, function(res){
      if (!res.success || !res.data || !res.data.html) {
        $target.html('<div class="text-muted small py-2">' + __('No items found.', 'axiachat-ai') + '</div>');
        return;
      }
      $target.html(res.data.html);
    }).fail(function(){
      $target.html('<div class="text-muted small py-2 text-danger">' + __('Error loading items.', 'axiachat-ai') + '</div>');
    });
  }

  // ---- Card header toggle (click to expand/collapse) ----
  $(document).on('click', '.aichat-ctx-source-header', function(e){
    // Don't toggle when clicking inside inputs within the header
    if ($(e.target).is('input, label')) return;
    $(this).closest('.aichat-ctx-source-card').toggleClass('open');
  });

  // ---- File upload (PDF/TXT) — auto-upload immediately ----
  const $uploadZone = $('#aichat-ctx-upload-zone');
  const $fileInput  = $('#aichat-ctx-file-input');
  const uploadedIds = [];          // IDs of successfully uploaded aichat_upload posts
  let   uploadQueue = [];          // files waiting to be uploaded
  let   uploadActive = false;      // true while the sequential uploader is running
  let   uploadCounter = 0;         // unique row IDs

  $uploadZone.on('click', function(e){
    e.stopPropagation();
    $fileInput[0].click();
  });
  $uploadZone.on('dragover', function(e){ e.preventDefault(); $(this).addClass('dragover'); });
  $uploadZone.on('dragleave drop', function(e){ e.preventDefault(); $(this).removeClass('dragover'); });
  $uploadZone.on('drop', function(e){
    handleFiles(e.originalEvent.dataTransfer.files);
  });
  $fileInput.on('change', function(){ handleFiles(this.files); this.value = ''; });

  function handleFiles(files){
    var added = false;
    for (var i = 0; i < files.length; i++) {
      var f = files[i];
      var ext = f.name.split('.').pop().toLowerCase();
      if (ext !== 'pdf' && ext !== 'txt') {
        toast(__('Only PDF and TXT files are allowed.', 'axiachat-ai'), true);
        continue;
      }
      var rowId = 'upload-row-' + (++uploadCounter);
      uploadQueue.push({ file: f, rowId: rowId });
      appendUploadRow(f, rowId);
      added = true;
    }
    if (added) {
      $('#aichat-ctx-card-pdf').addClass('active');
      drainUploadQueue();
    }
  }

  /** Append a new progress row for a file */
  function appendUploadRow(file, rowId){
    $('#aichat-ctx-file-list').append(
      '<div class="aichat-ctx-file-item uploading" id="' + rowId + '">' +
        '<div class="file-info">' +
          '<i class="bi bi-file-earmark me-1"></i>' +
          '<span class="file-name">' + escapeHtml(file.name) + '</span>' +
          ' <small class="text-muted">(' + (file.size / 1024).toFixed(1) + ' KB)</small>' +
        '</div>' +
        '<div class="file-upload-bar"><div class="file-upload-fill" style="width:0%"></div></div>' +
        '<div class="file-upload-status"><i class="bi bi-hourglass-split"></i> ' + __('Queued', 'axiachat-ai') + '</div>' +
      '</div>'
    );
  }

  /** Process the upload queue one file at a time */
  function drainUploadQueue(){
    if (uploadActive) return;        // already running
    if (uploadQueue.length === 0) {
      isUploading = false;
      $('#aichat-ctx-card-pdf').removeClass('active');
      return;
    }
    uploadActive = true;
    isUploading  = true;

    var item = uploadQueue.shift();
    var file = item.file;
    var $row = $('#' + item.rowId);
    $row.find('.file-upload-status').html('<i class="bi bi-arrow-repeat aichat-spin"></i> ' + __('Uploading…', 'axiachat-ai'));

    var fd = new FormData();
    fd.append('action', 'aichat_upload_file');
    fd.append('nonce', pdfNonce);
    fd.append('file', file);

    $.ajax({
      url: ajaxUrl,
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      xhr: function(){
        var x = new window.XMLHttpRequest();
        x.upload.addEventListener('progress', function(e){
          if (e.lengthComputable) {
            var pct = Math.round((e.loaded / e.total) * 100);
            $row.find('.file-upload-fill').css('width', pct + '%');
            $row.find('.file-upload-status').html('<i class="bi bi-arrow-repeat aichat-spin"></i> ' + pct + '%');
          }
        });
        return x;
      },
      success: function(res){
        if (res.success) {
          var uid    = res.data ? (res.data.upload_id || 0) : 0;
          var chunks = res.data ? (res.data.chunks_created || 0) : 0;
          if (uid) uploadedIds.push(uid);
          $row.find('.file-upload-fill').css({width:'100%', background:'#22c55e'});
          $row.find('.file-upload-status').html('<i class="bi bi-check-circle text-success"></i> ' + chunks + ' chunks');
          $row.removeClass('uploading').addClass('done');
        } else {
          var msg = (res.data && res.data.message) ? res.data.message : __('Error', 'axiachat-ai');
          $row.find('.file-upload-fill').css({width:'100%', background:'#ef4444'});
          $row.find('.file-upload-status').html('<i class="bi bi-x-circle text-danger"></i> ' + escapeHtml(msg));
          $row.removeClass('uploading').addClass('error');
        }
        uploadActive = false;
        drainUploadQueue();
      },
      error: function(xhr){
        $row.find('.file-upload-fill').css({width:'100%', background:'#ef4444'});
        $row.find('.file-upload-status').html('<i class="bi bi-x-circle text-danger"></i> HTTP ' + xhr.status);
        $row.removeClass('uploading').addClass('error');
        uploadActive = false;
        drainUploadQueue();
      }
    });
  }

  // ---- Load documents in context ----
  let docsPage = 1;

  function loadDocuments(page){
    if (!contextId) return;
    page = page || 1;
    docsPage = page;

    const q = ($('#aichat-ctx-docs-search').val() || '').trim();
    const type = $('#aichat-ctx-docs-type').val();

    $.post(ajaxUrl, {
      action: 'aichat_modify_list_documents',
      nonce: modifyNonce,
      context_id: contextId,
      page: page,
      per_page: 10,
      q: q,
      type: type
    }, function(res){
      const $body = $('#aichat-ctx-docs-body');
      if (!res.success || !res.data || !res.data.rows || res.data.rows.length === 0) {
        $body.html('<tr><td colspan="5" class="text-center text-muted py-4">' + __('No documents found.', 'axiachat-ai') + '</td></tr>');
        $('#aichat-ctx-docs-pager').hide();
        $('#aichat-ctx-documents-card').hide();
        $('#aichat-ctx-save-top-wrapper').hide();
        return;
      }
      let rows = '';
      res.data.rows.forEach(function(doc){
        var typeLabel = doc.type || '';
        if (typeLabel === 'file' || typeLabel === 'aichat_upload_chunk') typeLabel = 'File PDF/TXT';
        if (typeLabel === 'web') typeLabel = 'Web Page';
        rows += '<tr>' +
          '<td><input type="checkbox" class="aichat-ctx-doc-cb" value="' + doc.post_id + '"></td>' +
          '<td>' + escapeHtml(doc.title || '#' + doc.post_id) + '</td>' +
          '<td><span class="badge bg-light text-dark">' + escapeHtml(typeLabel) + '</span></td>' +
          '<td>' + (doc.chunk_count || 0) + '</td>' +
          '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger aichat-ctx-remove-doc" data-id="' + doc.post_id + '"><i class="bi bi-trash"></i></button></td>' +
        '</tr>';
      });
      $body.html(rows);

      // Pager
      const total = res.data.total || 0;
      const pages = Math.ceil(total / 10);
      if (pages > 1) {
        $('#aichat-ctx-docs-pager').show();
        $('#aichat-ctx-docs-prev').prop('disabled', page <= 1);
        $('#aichat-ctx-docs-next').prop('disabled', page >= pages);
        $('#aichat-ctx-docs-pageinfo').text(page + ' / ' + pages);
      } else {
        $('#aichat-ctx-docs-pager').hide();
      }
      $('#aichat-ctx-docs-status').text(total + ' ' + __('documents', 'axiachat-ai'));
      $('#aichat-ctx-documents-card').show();
      $('#aichat-ctx-save-top-wrapper').show();
    });
  }

  // Docs pager
  $('#aichat-ctx-docs-prev').on('click', function(){ if(docsPage > 1) loadDocuments(docsPage - 1); });
  $('#aichat-ctx-docs-next').on('click', function(){ loadDocuments(docsPage + 1); });
  $('#aichat-ctx-docs-search, #aichat-ctx-docs-type').on('input change', function(){ loadDocuments(1); });
  $('#aichat-ctx-refresh-docs').on('click', function(){ loadDocuments(docsPage); });

  // Select all docs
  $('#aichat-ctx-select-all-docs').on('change', function(){
    const checked = $(this).prop('checked');
    $('.aichat-ctx-doc-cb').prop('checked', checked);
    $('#aichat-ctx-remove-selected').prop('disabled', !checked);
  });
  $(document).on('change', '.aichat-ctx-doc-cb', function(){
    const anyChecked = $('.aichat-ctx-doc-cb:checked').length > 0;
    $('#aichat-ctx-remove-selected').prop('disabled', !anyChecked);
  });

  // Remove selected docs
  $('#aichat-ctx-remove-selected').on('click', function(){
    const ids = [];
    $('.aichat-ctx-doc-cb:checked').each(function(){ ids.push(parseInt($(this).val(), 10)); });
    if (!ids.length) return;
    if (!confirm(__('Remove selected documents from context?', 'axiachat-ai'))) return;

    $.post(ajaxUrl, {
      action: 'aichat_modify_remove_documents',
      nonce: modifyNonce,
      context_id: contextId,
      post_ids: ids
    }, function(res){
      if (res.success) {
        toast(__('Documents removed.', 'axiachat-ai'));
        loadDocuments(docsPage);
      } else {
        toast(res.data && res.data.message ? res.data.message : __('Error removing documents.', 'axiachat-ai'), true);
      }
    });
  });

  // Remove single doc
  $(document).on('click', '.aichat-ctx-remove-doc', function(){
    const id = parseInt($(this).data('id'), 10);
    if (!confirm(__('Remove this document?', 'axiachat-ai'))) return;

    $.post(ajaxUrl, {
      action: 'aichat_modify_remove_documents',
      nonce: modifyNonce,
      context_id: contextId,
      post_ids: [id]
    }, function(res){
      if (res.success) {
        toast(__('Document removed.', 'axiachat-ai'));
        loadDocuments(docsPage);
      } else {
        toast(__('Error.', 'axiachat-ai'), true);
      }
    });
  });

  // ---- Update stats display ----
  function updateStats(){
    const ctx = contexts.find(function(c){ return parseInt(c.id,10) === contextId; });
    const $stats = $('#aichat-ctx-stats');
    if (!ctx) {
      $stats.html('<div class="aichat-ctx-stat-badge"><i class="bi bi-info-circle me-1"></i> ' + __('No context created yet. Configure sources below and save to create one.', 'axiachat-ai') + '</div>');
      return;
    }
    $stats.html(
      '<div class="aichat-ctx-stat-badge"><i class="bi bi-file-earmark-text me-1"></i> <strong>' + (ctx.post_count || 0) + '</strong> ' + __('documents', 'axiachat-ai') + '</div>' +
      '<div class="aichat-ctx-stat-badge"><i class="bi bi-puzzle me-1"></i> <strong>' + (ctx.chunk_count || 0) + '</strong> ' + __('chunks', 'axiachat-ai') + '</div>' +
      '<div class="aichat-ctx-stat-badge"><i class="bi bi-activity me-1"></i> ' + (ctx.processing_status || __('unknown', 'axiachat-ai')) + ' ' + (ctx.processing_progress || 0) + '%</div>'
    );
  }

  // ---- Process log helpers ----
  let isProcessing = false;
  let isUploading  = false;
  let totalProcessed = 0;
  let totalTokens = 0;

  function appendLog(line){
    const $log = $('#aichat-ctx-index-log');
    $log.append(line + '<br>');
    $log.scrollTop($log[0].scrollHeight);
  }

  function updateProgress(pct){
    const p = Math.max(0, Math.min(100, pct || 0));
    $('#aichat-ctx-progress-bar').css('width', p + '%').text(p.toFixed(1) + '%');
  }

  // ---- Save & Index ----
  $('.aichat-ctx-save-sources').on('click', function(){
    if (isUploading) {
      toast(__('Please wait — files are still uploading…', 'axiachat-ai'), true);
      return;
    }
    // Disable ALL Save & Index buttons during the process
    const $allBtns = $('.aichat-ctx-save-sources');
    $allBtns.prop('disabled', true).css({opacity: 0.5, cursor: 'not-allowed'}).html('<i class="bi bi-hourglass-split me-1"></i>' + __('Saving...', 'axiachat-ai'));
    const $btn = $allBtns;

    // Collect source selections
    const sources = {
      posts:    $('input[name="aichat_ctx_posts"]:checked').val() || 'none',
      pages:    $('input[name="aichat_ctx_pages"]:checked').val() || 'none',
      products: $('input[name="aichat_ctx_products"]:checked').val() || 'none',
      custom_post_ids: [],
      custom_page_ids: [],
      custom_product_ids: [],
      pending_files: 0
    };

    // Collect custom selections (from the loaded items checkboxes)
    if (sources.posts === 'recent' || sources.posts === 'search') {
      $('#aichat-ctx-posts-items input[type="checkbox"]:checked').each(function(){ sources.custom_post_ids.push(parseInt($(this).val(), 10)); });
    }
    if (sources.pages === 'recent' || sources.pages === 'search') {
      $('#aichat-ctx-pages-items input[type="checkbox"]:checked').each(function(){ sources.custom_page_ids.push(parseInt($(this).val(), 10)); });
    }
    if (sources.products === 'recent' || sources.products === 'search') {
      $('#aichat-ctx-products-items input[type="checkbox"]:checked').each(function(){ sources.custom_product_ids.push(parseInt($(this).val(), 10)); });
    }

    // First: save context sources via our training AJAX
    $.post(ajaxUrl, {
      action: 'aichat_training_save_context_sources',
      nonce: nonce,
      bot_id: botId,
      context_id: contextId,
      sources: JSON.stringify(sources),
      context_name: $('#aichat-ctx-name').val(),
      context_type: $('#aichat-ctx-type').val(),
      include_page: $('#aichat-ctx-include-page').prop('checked') ? 1 : 0,
      context_max_length: $('#aichat-ctx-max-length').val(),
      context_limit: $('#aichat-ctx-limit').val(),
      autosync: $('#aichat-ctx-autosync').prop('checked') ? 1 : 0,
      autosync_mode: $('#aichat-ctx-autosync-mode').val() || 'updates'
    }, function(res){
      if (!res.success) {
        $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>' + __('Save & Index', 'axiachat-ai'));
        toast(res.data && res.data.message ? res.data.message : __('Error saving.', 'axiachat-ai'), true);
        return;
      }

      if (res.data && res.data.context_id) {
        contextId = parseInt(res.data.context_id, 10);
      }

      toast(__('Saved!', 'axiachat-ai'));
      $('#aichat-ctx-save-msg').fadeIn().delay(2000).fadeOut();

      // Build the selected / allSelected arrays
      var selected = [];
      var allSelected = [];

      // Posts
      if (sources.posts === 'all') {
        allSelected.push('all_posts');
      } else if (sources.posts === 'recent' || sources.posts === 'search') {
        sources.custom_post_ids.forEach(function(id){ selected.push(id); });
      }

      // Pages
      if (sources.pages === 'all') {
        allSelected.push('all_pages');
      } else if (sources.pages === 'recent' || sources.pages === 'search') {
        sources.custom_page_ids.forEach(function(id){ selected.push(id); });
      }

      // Products
      if (sources.products === 'all') {
        allSelected.push('all_products');
      } else if (sources.products === 'recent' || sources.products === 'search') {
        sources.custom_product_ids.forEach(function(id){ selected.push(id); });
      }

      // Include uploaded file IDs (aichat_upload parents — server expands to chunks)
      if (uploadedIds.length > 0) {
        uploadedIds.forEach(function(uid){ selected.push(uid); });
      }

      // Include web scraper upload IDs (same pipeline as PDF/TXT)
      if (window.aichatWebScraperUploadIds && window.aichatWebScraperUploadIds.length > 0) {
        window.aichatWebScraperUploadIds.forEach(function(uid){ selected.push(uid); });
      }

      if (selected.length === 0 && allSelected.length === 0) {
        appendLog(__('No items to index. Select sources above.', 'axiachat-ai'));
        $btn.prop('disabled', false).css({opacity: '', cursor: ''}).html('<i class="bi bi-check-lg me-1"></i>' + __('Save & Index', 'axiachat-ai'));
        return;
      }

      // Show progress panel and start processing
      $('#aichat-ctx-process-panel').show();
      // Scroll to progress panel so it's visible (useful when triggered from the top button)
      document.getElementById('aichat-ctx-process-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
      $('#aichat-ctx-index-log').html(__('Starting indexing…', 'axiachat-ai') + '<br>');
      updateProgress(0);
      $('#aichat-ctx-progress-bar').addClass('progress-bar-striped progress-bar-animated').css('background', '');
      totalProcessed = 0;
      totalTokens = 0;
      isProcessing = true;
      $btn.html('<i class="bi bi-arrow-repeat me-1 aichat-spin"></i>' + __('Indexing...', 'axiachat-ai'));

      var ctxName = $('#aichat-ctx-name').val() || '';
      processContextBatch(0, ctxName, selected, allSelected);

    }).fail(function(){
      $btn.prop('disabled', false).css({opacity: '', cursor: ''}).html('<i class="bi bi-check-lg me-1"></i>' + __('Save & Index', 'axiachat-ai'));
      toast(__('Network error.', 'axiachat-ai'), true);
    });
  });

  // ---- Batch indexing (mirrors contexto-create.js) ----
  function processContextBatch(batch, contextName, selected, allSelected) {
    if (!isProcessing) return;

    var ctxType = $('#aichat-ctx-type').val() || 'local';
    var isRemote = ctxType === 'remoto';

    var payload = {
      action: 'aichat_process_context',
      nonce: createNonce,
      context_id: contextId,
      context_name: contextName,
      selected: selected,
      all_selected: allSelected,
      batch: batch,
      context_type: ctxType,
      remote_type: isRemote ? ($('#aichat-ctx-remote-type').val() || 'pinecone') : '',
      remote_api_key: isRemote ? ($('#aichat-ctx-remote-api-key').val() || '') : '',
      remote_endpoint: isRemote ? ($('#aichat-ctx-remote-endpoint').val() || '') : '',
      embedding_provider: '',
      autosync: $('#aichat-ctx-autosync').prop('checked') ? 1 : 0,
      autosync_mode: $('#aichat-ctx-autosync-mode').val() || 'updates',
      indexing_options: JSON.stringify(getIndexingOptions())
    };

    var t0 = performance.now();

    $.ajax({
      url: ajaxUrl,
      method: 'POST',
      data: payload
    })
    .done(function(response){
      var dt = ((performance.now() - t0) / 1000).toFixed(2) + 's';

      if (!response) {
        appendLog(__('Empty response. Retrying…', 'axiachat-ai'));
        if (isProcessing) setTimeout(function(){ processContextBatch(batch, contextName, selected, allSelected); }, 1000);
        return;
      }

      if (response.success) {
        var data = response.data || {};

        // Always sync contextId from the server (critical for first-time creation)
        if (data.context_id && parseInt(data.context_id, 10) > 0) {
          contextId = parseInt(data.context_id, 10);
        }

        if (data.message && /Otro proceso.*en curso/i.test(data.message)) {
          appendLog(__('Server busy (lock). Retrying in 1s…', 'axiachat-ai'));
          updateProgress(data.progress || 0);
          if (isProcessing) setTimeout(function(){ processContextBatch(batch, contextName, selected, allSelected); }, 1000);
          return;
        }

        var processedThis = Number(data.total_processed || 0);
        var tokensThis = Number(data.total_tokens || 0);
        totalProcessed += processedThis;
        totalTokens += tokensThis;

        appendLog(
          __('Batch', 'axiachat-ai') + ' ' + batch + ' → ' + __('items:', 'axiachat-ai') + ' ' + processedThis +
          ', ' + __('total:', 'axiachat-ai') + ' ' + totalProcessed +
          ', ' + __('tokens:', 'axiachat-ai') + ' ' + tokensThis +
          ' (' + dt + ')'
        );

        updateProgress(Number(data.progress || 0));

        if (data.continue) {
          var nextBatch = Number(data.batch || (batch + 1));
          if (isProcessing) {
            processContextBatch(nextBatch, contextName, selected, allSelected);
          }
        } else {
          // Done
          updateProgress(100);
          $('#aichat-ctx-progress-bar').removeClass('progress-bar-animated progress-bar-striped').css('background', '#22c55e');
          appendLog('<br><div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px 16px;margin:4px 0;">' +
            '<strong style="color:#16a34a;font-size:14px;">✅ ' + __('Indexing complete!', 'axiachat-ai') + '</strong><br>' +
            '<span style="color:#374151;">' + __('Total items:', 'axiachat-ai') + ' <strong>' + totalProcessed + '</strong> &nbsp;|&nbsp; ' +
            __('Tokens:', 'axiachat-ai') + ' <strong>' + totalTokens + '</strong></span>' +
            '</div>');
          isProcessing = false;
          var $btn = $('.aichat-ctx-save-sources');
          $btn.prop('disabled', false).css({opacity: '', cursor: ''}).html('<i class="bi bi-check-lg me-1"></i>' + __('Save & Index', 'axiachat-ai'));
          // Update bot's context_id link in DB
          if (contextId > 0) {
            $.post(ajaxUrl, {
              action: 'aichat_training_set_bot_context',
              nonce: nonce,
              bot_id: botId,
              context_id: contextId
            });
          }
          loadDocuments();
          updateStats();
          toast(__('Indexing complete!', 'axiachat-ai'));
        }
      } else {
        var msg = (response.data && response.data.message) ? response.data.message : __('Unknown error', 'axiachat-ai');
        appendLog('<span style="color:#ef4444;">' + __('Error:', 'axiachat-ai') + ' ' + msg + ' (' + dt + ')</span>');
        isProcessing = false;
        $('.aichat-ctx-save-sources').prop('disabled', false).css({opacity: '', cursor: ''}).html('<i class="bi bi-check-lg me-1"></i>' + __('Save & Index', 'axiachat-ai'));
      }
    })
    .fail(function(xhr){
      appendLog('<span style="color:#ef4444;">' + __('Network error', 'axiachat-ai') + ' (' + xhr.status + '). ' + __('Retrying…', 'axiachat-ai') + '</span>');
      if (isProcessing) setTimeout(function(){ processContextBatch(batch, contextName, selected, allSelected); }, 2000);
    });
  }

  // ---- Run AutoSync ----
  $('#aichat-ctx-run-autosync').on('click', function(){
    if (!contextId) { toast(__('No context selected.', 'axiachat-ai'), true); return; }
    if (isUploading) { toast(__('Please wait — files are still uploading…', 'axiachat-ai'), true); return; }
    const $btn = $(this);
    $btn.prop('disabled', true);

    // Re-use the batch processing logic for autosync
    var ctxName = $('#aichat-ctx-name').val() || '';
    $('#aichat-ctx-process-panel').show();
    $('#aichat-ctx-index-log').html(__('Starting AutoSync…', 'axiachat-ai') + '<br>');
    updateProgress(0);
    totalProcessed = 0;
    totalTokens = 0;
    isProcessing = true;

    // Step 1: Call the real autosync handler to queue modified/new items
    $.post(ajaxUrl, {
      action: 'aichat_autosync_run_now',
      nonce: createNonce,
      context_id: contextId,
      mode: 'modified_and_new'
    }, function(res){
      if (res.success) {
        var queued = res.data.queued_total || 0;
        var added  = res.data.added_to_queue || 0;
        appendLog(__('AutoSync scan complete:', 'axiachat-ai') + ' ' + added + ' ' + __('new items queued', 'axiachat-ai') + ' (' + queued + ' ' + __('total in queue', 'axiachat-ai') + ')');

        if (queued > 0) {
          // Step 2: Start batch processing (items already queued in DB by autosync)
          // Pass empty selected/all_selected so process_context reads from items_to_process
          $btn.html('<i class="bi bi-arrow-repeat me-1 aichat-spin"></i>' + __('Indexing...', 'axiachat-ai'));
          processContextBatch(0, ctxName, [], []);
        } else {
          appendLog(__('Nothing to sync — all content is up to date.', 'axiachat-ai'));
          isProcessing = false;
          $btn.prop('disabled', false);
          toast(__('Everything is up to date.', 'axiachat-ai'));
        }
      } else {
        isProcessing = false;
        $btn.prop('disabled', false);
        var msg = (res.data && res.data.message) ? res.data.message : __('Error.', 'axiachat-ai');
        appendLog('<span style="color:#ef4444;">' + __('AutoSync error:', 'axiachat-ai') + ' ' + msg + '</span>');
        toast(msg, true);
      }
    }).fail(function(xhr){
      isProcessing = false;
      $btn.prop('disabled', false);
      appendLog('<span style="color:#ef4444;">' + __('Network error', 'axiachat-ai') + ' (' + xhr.status + ')</span>');
      toast(__('Network error.', 'axiachat-ai'), true);
    });
  });

  // ---- Save advanced settings (auto-save on change) ----
  // ---- Collect indexing options ----
  function getIndexingOptions(){
    return {
      include_excerpt:              $('#aichat-ctx-idx-excerpt').prop('checked'),
      include_url:                  $('#aichat-ctx-idx-url').prop('checked'),
      include_featured_image:       $('#aichat-ctx-idx-featured-image').prop('checked'),
      include_taxonomies:           ($('#aichat-ctx-idx-taxonomies').val() || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean),
      custom_meta_keys:             ($('#aichat-ctx-idx-custom-meta').val() || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean),
      include_wc_short_description: $('#aichat-ctx-idx-wc-short-desc').prop('checked') || false,
      include_wc_attributes:        $('#aichat-ctx-idx-wc-attributes').prop('checked') || false
    };
  }

  function doSaveAdvanced(){
    var advCtxType = $('#aichat-ctx-type').val() || 'local';
    var advIsRemote = advCtxType === 'remoto';

    $.post(ajaxUrl, {
      action: 'aichat_training_save_advanced',
      nonce: nonce,
      bot_id: botId,
      context_id: contextId,
      ctx_name: $('#aichat-ctx-name').val(),
      ctx_type: advCtxType,
      remote_type: advIsRemote ? ($('#aichat-ctx-remote-type').val() || 'pinecone') : '',
      remote_api_key: advIsRemote ? ($('#aichat-ctx-remote-api-key').val() || '') : '',
      remote_endpoint: advIsRemote ? ($('#aichat-ctx-remote-endpoint').val() || '') : '',
      include_page_content: $('#aichat-ctx-include-page').prop('checked') ? 1 : 0,
      context_max_length: $('#aichat-ctx-max-length').val(),
      context_limit: $('#aichat-ctx-limit').val(),
      ctx_autosync: $('#aichat-ctx-autosync').prop('checked') ? 1 : 0,
      ctx_autosync_mode: $('#aichat-ctx-autosync-mode').val() || 'updates',
      indexing_options: JSON.stringify(getIndexingOptions())
    }, function(res){
      if (res.success) {
        var $saved = $('#aichat-ctx-advanced-saved');
        $saved.stop(true, true).fadeIn(200).delay(2000).fadeOut(400);
      } else {
        toast(res.data && res.data.message ? res.data.message : __('Error.', 'axiachat-ai'), true);
      }
    });
  }

  // Debounce helper
  var _advSaveTimer = null;
  function scheduleAdvSave(delay){
    if (_advSaveTimer) clearTimeout(_advSaveTimer);
    _advSaveTimer = setTimeout(doSaveAdvanced, delay || 600);
  }

  // Immediate save for checkboxes, selects, range
  $('#aichat-ctx-advanced-body').on('change',
    'select.aichat-ctx-adv-field, input[type="checkbox"].aichat-ctx-adv-field, input[type="range"].aichat-ctx-adv-field',
    function(){ scheduleAdvSave(100); }
  );

  // Debounced save for text / number inputs
  $('#aichat-ctx-advanced-body').on('input',
    'input[type="text"].aichat-ctx-adv-field, input[type="number"].aichat-ctx-adv-field',
    function(){ scheduleAdvSave(800); }
  );

  // ---- Init ----
  if (contextId) {
    loadDocuments();
  }

})(jQuery);
