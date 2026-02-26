/* global jQuery, aichat_create_ajax */
const { __, sprintf } = wp.i18n;

jQuery(document).ready(function ($) {
  // =========================
  // Estado de la ejecución
  // =========================
  let isProcessing = false;
  let totalProcessed = 0;
  let totalTokens = 0;

  // =========================
  // Utilidades
  // =========================

  /**
   * Collect advanced indexing options from the UI checkboxes/inputs.
   * Returns an object matching the PHP aichat_default_indexing_options() structure.
   */
  function aichat_collect_indexing_options() {
    const opts = {
      include_excerpt: $('#aichat-idx-excerpt').is(':checked'),
      include_url: $('#aichat-idx-url').is(':checked'),
      include_featured_image: $('#aichat-idx-featured-image').is(':checked'),
      include_taxonomies: [],
      include_wc_short_description: $('#aichat-idx-wc-short-desc').is(':checked'),
      include_wc_attributes: $('#aichat-idx-wc-attributes').is(':checked'),
      include_meta_fields: [],
      custom_meta_keys: []
    };
    // Parse comma-separated taxonomy slugs
    const taxRaw = ($('#aichat-idx-taxonomies').val() || '').trim();
    if (taxRaw) {
      opts.include_taxonomies = taxRaw.split(',').map(s => s.trim()).filter(Boolean);
    }
    // Parse comma-separated custom meta keys
    const metaRaw = ($('#aichat-idx-custom-meta').val() || '').trim();
    if (metaRaw) {
      opts.custom_meta_keys = metaRaw.split(',').map(s => s.trim()).filter(Boolean);
    }
    return opts;
  }

  function debounce(fn, delay) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  function appendLog(line) {
    const $log = $('#aichat-index-log');
    $log.append(line + '<br>');
    $log.scrollTop($log[0].scrollHeight);
  }

  function updateProgress(pct) {
    const p = Math.max(0, Math.min(100, pct || 0));
    $('#aichat-progress-bar').css('width', p + '%').text(p.toFixed(1) + '%');
  }

  function toggleRemoteFields() {
    const isRemote = $('#context-type').val() === 'remoto';
    $('#remote-config-fields').toggle(isRemote);
  }

  function expandAccordionFor(pt) {
    // Abre el collapse del acordeón si está disponible Bootstrap 5
    const collapseSelector = `#collapse-${pt}`;
    const el = document.querySelector(collapseSelector);

    if (el && typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
      const inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
      inst.show();
    } else {
      // Fallback: simular click en el botón del acordeón
      $(`button[data-bs-target="${collapseSelector}"]`).trigger('click');
    }

    // Precargar pestañas + búsqueda
    loadItems(pt, 'recent');
    loadItems(pt, 'all', '', 1);
    $(`#search-input-${pt}`).off('keyup.aichat').on('keyup.aichat', debounce(function () {
      loadItems(pt, 'search', $(this).val(), 1);
    }, 300));
  }

  // =========================
  // Carga de items (pestañas)
  // =========================
  function loadItems(pt, tab, search = '', page = 1) {
    const $container  = $(`#${tab}-items-${pt}`);
    const $pagination = $(`#${tab}-pagination-${pt}`);
    if ($container.length === 0) return;

    $container.html('<div class="text-muted">' + __('Loading…', 'axiachat-ai') + '</div>');
    if ($pagination.length) $pagination.empty();

    $.ajax({
      url: aichat_create_ajax.ajax_url,
      method: 'POST',
      data: {
        action: 'aichat_load_items',
        nonce: aichat_create_ajax.nonce,
        post_type: pt,
        tab: tab,
        search: search,
        paged: page
      }
    })
    .done(function (res) {
      if (res && res.success) {
        $container.html(res.data.html || '<p>' + __('No items found.', 'axiachat-ai') + '</p>');

        // Escuchar cambios de selección en esta carga
        $container.find('input[type="checkbox"]').on('change', updateSelectionSummary);

        // Paginación para 'all' y 'search'
        if ((tab === 'all' || tab === 'search') && $pagination.length) {
          const maxPages = Number(res.data.max_pages || 1);
          const currentPage = Number(res.data.current_page || 1);

          if (maxPages > 1) {
            let html = '<div class="tablenav"><div class="tablenav-pages">';
            if (currentPage > 1) {
              html += `<a href="#" class="aichat-prev-page" data-pt="${pt}" data-tab="${tab}" data-page="${currentPage - 1}">«</a> `;
            }
            for (let i = 1; i <= maxPages; i++) {
              if (i === currentPage) html += `<span class="current-page">${i}</span> `;
              else html += `<a href="#" class="aichat-page-number" data-pt="${pt}" data-tab="${tab}" data-page="${i}">${i}</a> `;
            }
            if (currentPage < maxPages) {
              html += `<a href="#" class="aichat-next-page" data-pt="${pt}" data-tab="${tab}" data-page="${currentPage + 1}">»</a>`;
            }
            html += '</div></div>';
            $pagination.html(html);

            // Eventos de paginación
            $pagination.off('click.aichat').on('click.aichat', '.aichat-prev-page,.aichat-next-page,.aichat-page-number', function (e) {
              e.preventDefault();
              const newPage = Number($(this).data('page'));
              const _pt  = $(this).data('pt');
              const _tab = $(this).data('tab');
              const q    = (_tab === 'search') ? ($(`#search-input-${_pt}`).val() || '') : '';
              loadItems(_pt, _tab, q, newPage);
            });
          } else {
            $pagination.empty();
          }
        }
      } else {
        $container.html('<p class="text-danger">' + __('Error loading items.', 'axiachat-ai') + '</p>');
      }
    })
    .fail(function () {
      $container.html('<p class="text-danger">' + __('Network error loading items.', 'axiachat-ai') + '</p>');
    });
  }

  // =========================
  // Resumen de selección
  // =========================
  function updateSelectionSummary() {
    const lines = [];
  let anyAll = false; // track if any ALL_* selected

    // Posts
    const postsMode = $('input[name="aichat_select_posts_mode"]:checked').val();
    if (postsMode === 'all') {
      lines.push(__('Posts: ALL', 'axiachat-ai'));
      anyAll = true;
    } else if (postsMode === 'custom') {
      const n = $('#aichat-post-accordion .aichat-items input[type="checkbox"]:checked').length;
      /* translators: %d: Number of posts selected */
      if (n > 0) lines.push(sprintf(__('Posts: %d', 'axiachat-ai'), n));
    }

    // Pages
    const pagesMode = $('input[name="aichat_select_pages_mode"]:checked').val();
    if (pagesMode === 'all') {
      lines.push(__('Pages: ALL', 'axiachat-ai'));
      anyAll = true;
    } else if (pagesMode === 'custom') {
      const n = $('#aichat-page-accordion .aichat-items input[type="checkbox"]:checked').length;
      /* translators: %d: Number of pages selected */
      if (n > 0) lines.push(sprintf(__('Pages: %d', 'axiachat-ai'), n));
    }

    // Products
    if (aichat_create_ajax.has_woocommerce) {
      const productsMode = $('input[name="aichat_select_products_mode"]:checked').val();
      if (productsMode === 'all') {
        lines.push(__('Products: ALL', 'axiachat-ai'));
        anyAll = true;
      } else if (productsMode === 'custom') {
        const n = $('#aichat-product-accordion .aichat-items input[type="checkbox"]:checked').length;
        /* translators: %d: Number of products selected */
        if (n > 0) lines.push(sprintf(__('Products: %d', 'axiachat-ai'), n));
      }
    }

    // Uploaded Files (padres)
    const uploadedMode = $('input[name="aichat_select_uploaded_mode"]:checked').val();
    if (uploadedMode === 'all') {
      lines.push(__('Uploaded Files: ALL', 'axiachat-ai'));
      anyAll = true;
    } else if (uploadedMode === 'custom') {
      const n = $('#aichat-uploaded-accordion .aichat-items input[type="checkbox"]:checked').length;
      /* translators: %d: Number of uploaded files selected */
      if (n > 0) lines.push(sprintf(__('Uploaded Files: %d', 'axiachat-ai'), n));
    }

    $('#aichat-selection-summary').html(lines.length ? lines.join('<br>') : __('No selections yet.', 'axiachat-ai'));

    // Autosync card always visible now. Only adjust mode availability.
    if (anyAll) {
      $('#aichat-create-autosync-mode option[value="updates_and_new"]').prop('disabled', false);
      if($('#aichat-create-autosync').is(':checked')){
        $('#aichat-create-autosync-mode-wrapper').show();
      }
      $('#aichat-create-autosync-help-limited').hide();
    } else {
      // No ALL source: restrict to updates only when autosync active
      $('#aichat-create-autosync-mode').val('updates');
      $('#aichat-create-autosync-mode option[value="updates_and_new"]').prop('disabled', true);
      if($('#aichat-create-autosync').is(':checked')){
        $('#aichat-create-autosync-mode-wrapper').show();
        $('#aichat-create-autosync-help-limited').show();
      } else {
        $('#aichat-create-autosync-help-limited').hide();
        $('#aichat-create-autosync-mode-wrapper').hide();
      }
    }
  }

  // =========================
  // Encadenado de batches (AJAX)
  // =========================
  function processContextBatch(batch, contextName, selected, allSelected, contextType, remoteType, remoteApiKey, remoteEndpoint, embeddingProvider) {
    if (!isProcessing) return;

    const payload = {
      action: 'aichat_process_context',
      nonce: aichat_create_ajax.nonce,
      context_name: contextName,
      selected: selected,
      all_selected: allSelected,
      batch: batch,
      context_type: contextType,
      remote_type: remoteType,
      remote_api_key: remoteApiKey,
      remote_endpoint: remoteEndpoint,
      embedding_provider: embeddingProvider || '',
      autosync: $('#aichat-create-autosync').is(':checked') ? 1 : 0,
      autosync_mode: $('#aichat-create-autosync-mode').val(),
      indexing_options: JSON.stringify(aichat_collect_indexing_options())
    };

    const t0 = performance.now();

    $.ajax({
      url: aichat_create_ajax.ajax_url,
      method: 'POST',
      data: payload
    })
    .done(function (response) {
      const dt = ((performance.now() - t0) / 1000).toFixed(3) + 's';

      if (!response) {
        appendLog(__('Empty response. Retrying…', 'axiachat-ai'));
        if (isProcessing) setTimeout(() => processContextBatch(batch, contextName, selected, allSelected, contextType, remoteType, remoteApiKey, remoteEndpoint, embeddingProvider), 1000);
        return;
      }

      if (response.success) {
        const data = response.data || {};

        if (data.message && /Otro proceso.*en curso/i.test(data.message)) {
          appendLog(sprintf(
          /* translators: %d: Batch number being retried */
          __('Server busy (lock). Retrying batch %d in 1s…', 'axiachat-ai'), batch));
          updateProgress(data.progress || 0);
          if (isProcessing) setTimeout(() => processContextBatch(batch, contextName, selected, allSelected, contextType, remoteType, remoteApiKey, remoteEndpoint, embeddingProvider), 1000);
          return;
        }

        const processedThis = Number(data.total_processed || 0);
        const tokensThis = Number(data.total_tokens || 0);
        totalProcessed += processedThis;
        totalTokens += tokensThis;

        appendLog(
          sprintf(
            /* translators: 1: Batch number, 2: Items processed in this batch, 3: Total items, 4: Tokens in this batch, 5: Duration */
            __('Batch %1$d → this: %2$d, total: %3$d, tokens this: %4$d (%5$s)', 'axiachat-ai'),
            batch, processedThis, totalProcessed, tokensThis, dt)
        );

        updateProgress(Number(data.progress || 0));

        if (data.continue) {
          const nextBatch = Number(data.batch || (batch + 1));
          if (isProcessing) {
            processContextBatch(nextBatch, contextName, selected, allSelected, contextType, remoteType, remoteApiKey, remoteEndpoint, embeddingProvider);
          }
        } else {
          updateProgress(100);
          appendLog(sprintf(
            /* translators: 1: Total items processed, 2: Total tokens used */
            __('Processing completed. Total items: %1$d, Tokens: %2$d', 'axiachat-ai'), totalProcessed, totalTokens));
          isProcessing = false;
          $('#aichat-process-context').prop('disabled', false).css('background-color', '');
          // Reset básico
          $('#context-name').val('');
          $('#context-type').val('local');
          toggleRemoteFields();
          $('#remote-type').val('pinecone');
          $('#remote-api-key').val('');
          $('#remote-endpoint').val('https://controller.pinecone.io');
          $('input[name="aichat_select_posts_mode"]').prop('checked', false);
          $('input[name="aichat_select_pages_mode"]').prop('checked', false);
          $('input[name="aichat_select_products_mode"]').prop('checked', false);
          $('input[name="aichat_select_uploaded_mode"]').prop('checked', false);
          updateSelectionSummary();
          updateAccordions();
        }
      } else {
        const msg = (response.data && response.data.message) ? response.data.message : __('Unknown error', 'axiachat-ai');
        appendLog(__('Error:', 'axiachat-ai') + ' ' + msg + ' (dt ' + dt + ')');

        isProcessing = false;
        $('#aichat-process-context').prop('disabled', false).css('background-color', '');
      }
    })
    .fail(function (xhr) {
      appendLog(sprintf(
      /* translators: 1: HTTP status code, 2: Batch number being retried */
      __('Network error (%1$s). Retrying batch %2$d in 2s…', 'axiachat-ai'), xhr.status, batch));
      if (isProcessing) setTimeout(() => processContextBatch(batch, contextName, selected, allSelected, contextType, remoteType, remoteApiKey, remoteEndpoint, embeddingProvider), 2000);
    });
  }

  // =========================
  // Botón “Procesar”
  // =========================
  $('#aichat-process-context').on('click', function () {
    if (isProcessing) return;

    const contextName = ($('#context-name').val() || '').trim() || ('Default' + (new Date().getTime()));
    $('#context-name').val(contextName);

    const contextType = $('#context-type').val();
    const remoteType = contextType === 'remoto' ? $('#remote-type').val() : '';
    const remoteApiKey = $('#remote-api-key').val() || '';
    const remoteEndpoint = $('#remote-endpoint').val() || 'https://controller.pinecone.io';
    const embeddingProvider = $('#embedding-provider').val() || '';

    if (contextType === 'remoto' && (!remoteType || !remoteApiKey)) {
      alert(__('Remote type and API key are required for remote context.', 'axiachat-ai'));
      return;
    }

    // Recolectar selección
    const selected = [];
    const allSelected = [];

    const postsMode = $('input[name="aichat_select_posts_mode"]:checked').val();
    const pagesMode = $('input[name="aichat_select_pages_mode"]:checked').val();
    const productsMode = $('input[name="aichat_select_products_mode"]:checked').val();
    const uploadedMode = $('input[name="aichat_select_uploaded_mode"]:checked').val();

    if (postsMode === 'custom') {
      $('#aichat-post-accordion .aichat-items input[type="checkbox"]:checked').each(function () {
        const v = $(this).val();
        if (v && !isNaN(v)) selected.push(v);
      });
    } else if (postsMode === 'all') {
      allSelected.push('all_posts');
    }

    if (pagesMode === 'custom') {
      $('#aichat-page-accordion .aichat-items input[type="checkbox"]:checked').each(function () {
        const v = $(this).val();
        if (v && !isNaN(v)) selected.push(v);
      });
    } else if (pagesMode === 'all') {
      allSelected.push('all_pages');
    }

    if (aichat_create_ajax.has_woocommerce) {
      if (productsMode === 'custom') {
        $('#aichat-product-accordion .aichat-items input[type="checkbox"]:checked').each(function () {
          const v = $(this).val();
          if (v && !isNaN(v)) selected.push(v);
        });
      } else if (productsMode === 'all') {
        allSelected.push('all_products');
      }
    }

    // Uploaded Files (PADRES)
    if (uploadedMode === 'custom') {
      $('#aichat-uploaded-accordion .aichat-items input[type="checkbox"]:checked').each(function () {
        const v = $(this).val();
        if (v && !isNaN(v)) selected.push(v); // IDs de aichat_upload
      });
    } else if (uploadedMode === 'all') {
      allSelected.push('all_uploaded');
    }

    // Preparar UI
    $(this).prop('disabled', true).css('background-color', 'gray');
    $('#aichat-index-log').html(__('Starting indexing…', 'axiachat-ai') + '<br>');
    updateProgress(0);
    totalProcessed = 0;
    totalTokens = 0;
    isProcessing = true;

    if (selected.length === 0 && allSelected.length === 0) {
      appendLog(__('No items selected.', 'axiachat-ai'));
      isProcessing = false;
      $('#aichat-process-context').prop('disabled', false).css('background-color', '');
      return;
    }

    // Lanzar primer batch
    processContextBatch(
      0,
      contextName,
      selected,
      allSelected,
      contextType,
      remoteType,
      remoteApiKey,
      remoteEndpoint,
      embeddingProvider
    );
  });

  // =========================
  // Acordeones / Pestañas / Búsqueda
  // =========================
  $('.accordion-button').off('click.aichat').on('click.aichat', function () {
    const accordionId = $(this).attr('aria-controls'); // ej. "collapse-post"
    const pt = accordionId.replace('collapse-', '');   // post | page | product | aichat_upload
    loadItems(pt, 'recent');
    loadItems(pt, 'all', '', 1);

    $(`#search-input-${pt}`).off('keyup.aichat').on('keyup.aichat', debounce(function () {
      loadItems(pt, 'search', $(this).val(), 1);
    }, 300));
  });

  $('.nav-link').off('shown.bs.tab.aichat').on('shown.bs.tab.aichat', function (e) {
    const id = $(e.target).attr('id'); // ej. "recent-tab-post"
    if (!id) return;
    const parts = id.split('-'); // ["recent","tab","post"]
    const tab = parts[0];
    const pt  = parts[2];
    if (pt && tab) loadItems(pt, tab, '', 1);
  });

  // Seleccionar/desmarcar todos
  $(document).on('change', '.aichat-select-all', function () {
    const target = $(this).attr('data-target');
    $(target).find('input[type="checkbox"]').prop('checked', this.checked);
    updateSelectionSummary();
  });

  // Checkboxes exclusivas por modo
  // POSTS
  $('input[name="aichat_select_posts_mode"]').off('change.aichat').on('change.aichat', function () {
    const $group = $('input[name="aichat_select_posts_mode"]');
    const checked = $(this).is(':checked');
    if (checked) {
      $group.not(this).prop('checked', false);
      const mode = $(this).val(); // 'all' | 'custom'
      $('#aichat-post-accordion').toggle(mode === 'custom');
      if (mode === 'custom') expandAccordionFor('post');
    } else {
      // Si ninguna opción queda marcada → ocultar acordeón
      if ($group.filter(':checked').length === 0) {
        $('#aichat-post-accordion').hide();
      }
    }
    updateSelectionSummary(); // <-- siempre, también al desmarcar
  });

  // PAGES
  $('input[name="aichat_select_pages_mode"]').off('change.aichat').on('change.aichat', function () {
    const $group = $('input[name="aichat_select_pages_mode"]');
    const checked = $(this).is(':checked');
    if (checked) {
      $group.not(this).prop('checked', false);
      const mode = $(this).val();
      $('#aichat-page-accordion').toggle(mode === 'custom');
      if (mode === 'custom') expandAccordionFor('page');
    } else {
      if ($group.filter(':checked').length === 0) {
        $('#aichat-page-accordion').hide();
      }
    }
    updateSelectionSummary();
  });

  // PRODUCTS (si WooCommerce)
  $('input[name="aichat_select_products_mode"]').off('change.aichat').on('change.aichat', function () {
    const $group = $('input[name="aichat_select_products_mode"]');
    const checked = $(this).is(':checked');
    if (checked) {
      $group.not(this).prop('checked', false);
      const mode = $(this).val();
      $('#aichat-product-accordion').toggle(mode === 'custom');
      if (mode === 'custom') expandAccordionFor('product');
    } else {
      if ($group.filter(':checked').length === 0) {
        $('#aichat-product-accordion').hide();
      }
    }
    updateSelectionSummary();
  });

  // UPLOADED FILES (padres)
  $('input[name="aichat_select_uploaded_mode"]').off('change.aichat').on('change.aichat', function () {
    const $group = $('input[name="aichat_select_uploaded_mode"]');
    const checked = $(this).is(':checked');
    if (checked) {
      $group.not(this).prop('checked', false);
      const mode = $(this).val();
      $('#aichat-uploaded-accordion').toggle(mode === 'custom');
      if (mode === 'custom') expandAccordionFor('aichat_upload');
    } else {
      if ($group.filter(':checked').length === 0) {
        $('#aichat-uploaded-accordion').hide();
      }
    }
    updateSelectionSummary();
  });

  $(document).on('change', '.aichat-items input[type="checkbox"]', updateSelectionSummary);

  // Mostrar/ocultar campos remotos según tipo de contexto
  $('#context-type').on('change', toggleRemoteFields);
  toggleRemoteFields();

  // =========================
  // Acordeones: estado inicial
  // =========================
  function updateAccordions() {
    const postsMode = $('input[name="aichat_select_posts_mode"]:checked').val();
    $('#aichat-post-accordion').toggle(postsMode === 'custom');
    if (postsMode === 'custom') {
      loadItems('post', 'recent');
      loadItems('post', 'all', '', 1);
      $(`#search-input-post`).off('keyup.aichat').on('keyup.aichat', debounce(function () {
        loadItems('post', 'search', $(this).val(), 1);
      }, 300));
    }

    const pagesMode = $('input[name="aichat_select_pages_mode"]:checked').val();
    $('#aichat-page-accordion').toggle(pagesMode === 'custom');
    if (pagesMode === 'custom') {
      loadItems('page', 'recent');
      loadItems('page', 'all', '', 1);
      $(`#search-input-page`).off('keyup.aichat').on('keyup.aichat', debounce(function () {
        loadItems('page', 'search', $(this).val(), 1);
      }, 300));
    }

    if (aichat_create_ajax.has_woocommerce) {
      const productsMode = $('input[name="aichat_select_products_mode"]:checked').val();
      $('#aichat-product-accordion').toggle(productsMode === 'custom');
      if (productsMode === 'custom') {
        loadItems('product', 'recent');
        loadItems('product', 'all', '', 1);
        $(`#search-input-product`).off('keyup.aichat').on('keyup.aichat', debounce(function () {
          loadItems('product', 'search', $(this).val(), 1);
        }, 300));
      }
    }

    // Uploaded Files (PADRES)
    const uploadedMode = $('input[name="aichat_select_uploaded_mode"]:checked').val();
    $('#aichat-uploaded-accordion').toggle(uploadedMode === 'custom');
    if (uploadedMode === 'custom') {
      loadItems('aichat_upload', 'recent');
      loadItems('aichat_upload', 'all', '', 1);
      $(`#search-input-aichat_upload`).off('keyup.aichat').on('keyup.aichat', debounce(function () {
        loadItems('aichat_upload', 'search', $(this).val(), 1);
      }, 300));
    }
  }


  

  // Inicialización
  updateAccordions();
  updateSelectionSummary();

  // Autosync checkbox behaviour
  $(document).on('change', '#aichat-create-autosync', function(){
    const summaryHasAll = $('#aichat-selection-summary').html().match(/ALL/);
    if($(this).is(':checked')){
      $('#aichat-create-autosync-mode-wrapper').show();
      if(!summaryHasAll){
        $('#aichat-create-autosync-mode').val('updates');
        $('#aichat-create-autosync-mode option[value="updates_and_new"]').prop('disabled', true);
        $('#aichat-create-autosync-help-limited').show();
      } else {
        $('#aichat-create-autosync-mode option[value="updates_and_new"]').prop('disabled', false);
        $('#aichat-create-autosync-help-limited').hide();
      }
    } else {
      $('#aichat-create-autosync-mode-wrapper').hide();
    }
  });
});

