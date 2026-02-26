/* global aichat_pdf_ajax */
(function ($) {
  'use strict';

  // WordPress i18n functions
  const { __, sprintf } = wp.i18n;

  // Evita doble inicialización si el script se evalúa de nuevo (partial reload, etc.)
  if (window.AIChatPdfInitialized) {
    console.warn('[AIChat PDF] Duplicate init prevented');
    return;
  }
  window.AIChatPdfInitialized = true;

  const cfg = aichat_pdf_ajax || {};
  const AJAX = cfg.ajax_url;
  const NONCE = cfg.nonce;
  const MAX_MB = Number(cfg.max_mb || 20);
  const MAX_BYTES = MAX_MB * 1024 * 1024;
  const ALLOWED_MIMES = (cfg.allowed_mimes || []).map(String);
  const ALLOWED_EXTS = (cfg.allowed_exts || []).map((s) => s.toLowerCase());
  const I18N = cfg.i18n || {};
  const CAPS = cfg.caps || {};

  const escapeHtml = (function () {
    if (window._ && _.escape) return _.escape;
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    const re = /[&<>"']/g;
    return (s) => String(s).replace(re, (c) => map[c]);
  })();

  function bytesToHuman(b) {
    if (b < 1024) return b + ' B';
    if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
    if (b < 1024 * 1024 * 1024) return (b / (1024 * 1024)).toFixed(1) + ' MB';
    return (b / (1024 * 1024 * 1024)).toFixed(1) + ' GB';
  }
  function nowISO() {
    return new Date().toISOString().replace('T', ' ').slice(0, 19);
  }
  function debounce(fn, delay) {
    let t;
    return function () {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, arguments), delay);
    };
  }
  function api(action, data, opts) {
    return $.ajax(Object.assign({
      url: AJAX,
      method: 'POST',
      data: Object.assign({ action, nonce: NONCE }, data || {}),
      dataType: 'json'
    }, opts || {}));
  }
  function toast(msg, isError) {
    const $n = $('<div/>')
      .addClass('notice ' + (isError ? 'notice-error' : 'notice-success'))
      .css({ marginTop: '10px' })
      .append($('<p/>').text(msg));
    $('.wrap h1').first().after($n);
    setTimeout(() => $n.fadeOut(300, () => $n.remove()), 3000);
  }
  function setRowLoading($btn, loading) {
    if (!$btn || !$btn.length) return;
    const original = $btn.data('txt') || $btn.text();
    if (!$btn.data('txt')) $btn.data('txt', original);
    $btn.prop('disabled', loading);
    $btn.toggleClass('button-primary', loading);
    $btn.text(loading ? ($btn.data('loading') || '…') : $btn.data('txt'));
  }

  const state = { page: 1, per_page: 10, search: '', total: 0, items: [] };

  const $drop = $('#aichat-pdf-dropzone');
  const $fileInput = $('#aichat-file-input');
  const $fileSelect = $('#aichat-file-select');
  const $list = $('#aichat-upload-list');
  const $pagination = $('#aichat-upload-pagination');
  const $search = $('#aichat-upload-search');
  const $refresh = $('#aichat-refresh-uploads');

  // Limpia handlers previos (por si HTML reinyectado) usando namespace .aichat
  $drop.add($fileInput).add($fileSelect).off('.aichat');

  function isAllowedFile(f) {
    const name = (f.name || '').toLowerCase();
    const ext = name.split('.').pop();
    const mime = (f.type || '').toLowerCase();
    if (f.size > MAX_BYTES) { toast(sprintf(
      /* translators: 1: File name, 2: Maximum size in MB */
      __('"%1$s" exceeds %2$d MB.', 'axiachat-ai'), f.name, MAX_MB), true); return false; }
    if (ALLOWED_EXTS.length && !ALLOWED_EXTS.includes(ext)) { toast(sprintf(
      /* translators: %s: File name */
      __('"%s" extension not allowed.', 'axiachat-ai'), f.name), true); return false; }
    if (ALLOWED_MIMES.length && mime && !ALLOWED_MIMES.includes(mime)) {
      if (mime.length) { toast(sprintf(
        /* translators: 1: File name, 2: MIME type */
        __('"%1$s" type not allowed (%2$s).', 'axiachat-ai'), f.name, mime), true); return false; }
    }
    return true;
  }

  function uploadFiles(files) {
    if (!files || !files.length) return;
    const queue = Array.from(files);
    (function next() {
      const f = queue.shift();
      if (!f) return;
      if (!isAllowedFile(f)) return next();

      const fd = new FormData();
      fd.append('action', 'aichat_upload_file');
      fd.append('nonce', NONCE);
      fd.append('file', f);

      const tempId = 'temp-' + Math.random().toString(36).slice(2, 8);
      const $row = $(`
        <tr id="row-${tempId}">
          <td class="filename">${escapeHtml(f.name)}</td>
          <td>${(f.type || '').toUpperCase() || 'N/A'}</td>
          <td>${bytesToHuman(f.size)}</td>
          <td class="status"><span class="aichat-badge">` + sprintf(
            /* translators: %d: Upload percentage */
            __('Uploading… %d%%', 'axiachat-ai'), 0) + `</span></td>
          <td class="chunks">-</td>
          <td class="updated">${nowISO()}</td>
          <td class="aichat-actions"><em>—</em></td>
        </tr>`);
      if ($list.find('tr').length === 1 && $list.find('tr td').attr('colspan')) $list.html('');
      $list.prepend($row);

      $.ajax({
        url: AJAX,
        method: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json',
        xhr: function () {
          const xhr = $.ajaxSettings.xhr();
          if (xhr.upload) {
            xhr.upload.addEventListener('progress', function (e) {
              if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                $row.find('.status .aichat-badge').text(sprintf(
                  /* translators: %d: Upload percentage */
                  __('Uploading… %d%%', 'axiachat-ai'), pct));
              }
            });
          }
          return xhr;
        }
      }).done(function (res) {
        if (res && res.success) {
          const data = res.data || {};
          const chunksCreated = Number(data.chunks_created || 0);
          if (data.parse_error) {
            toast(sprintf(
              /* translators: 1: File name, 2: Parse error message */
              __('Uploaded: %1$s (parse error: %2$s)', 'axiachat-ai'), f.name, data.parse_error), true);
          } else if (chunksCreated > 0) {
            toast(sprintf(
              /* translators: 1: File name, 2: Number of chunks created */
              __('Uploaded & chunked: %1$s → %2$d chunks', 'axiachat-ai'), f.name, chunksCreated));
          } else {
            toast(sprintf(
              /* translators: %s: File name */
              __('Uploaded: %s', 'axiachat-ai'), f.name));
          }
          loadUploads(state.page, state.search);
        } else {
          $row.find('.status .aichat-badge').text(__('Error', 'axiachat-ai'));
          toast(sprintf(
            /* translators: 1: File name, 2: Error message */
            __('%1$s: %2$s', 'axiachat-ai'), f.name, (res && res.data && res.data.message) || __('Upload error', 'axiachat-ai')), true);
        }
      }).fail(function (xhr) {
        $row.find('.status .aichat-badge').text(__('Error', 'axiachat-ai'));
        toast(sprintf(
          /* translators: 1: File name, 2: HTTP status code */
          __('%1$s: network error (%2$s)', 'axiachat-ai'), f.name, xhr.status), true);
      }).always(next);
    })();
  }

  function loadUploads(page, search) {
    state.page = page || 1;
    state.search = typeof search === 'string' ? search : state.search;
    api('aichat_list_uploads', { page: state.page, per_page: state.per_page, search: state.search })
      .done(function (res) {
        if (res && res.success) {
          const data = res.data || {};
          state.total = Number(data.total || 0);
          state.items = data.items || [];
          renderList(state.items);
          renderPagination();
        } else {
          renderList([]);
          renderPagination();
          toast((res && res.data && res.data.message) || __('List error', 'axiachat-ai'), true);
        }
      })
      .fail(function () {
        renderList([]);
        renderPagination();
        toast(__('Network error listing uploads', 'axiachat-ai'), true);
      });
  }

  function renderList(items) {
    if (!items || !items.length) {
      $list.html('<tr><td colspan="7">' + __('No files yet. Upload some to get started.', 'axiachat-ai') + '</td></tr>');
      return;
    }
    const rows = items.map(function (it) {
      const id = it.id || it.upload_id || '';
      const filename = it.filename || '—';
      const size = bytesToHuman(Number(it.size || 0));
      const mime = (it.mime || '').toUpperCase() || 'N/A';
      const status = (it.status || 'uploaded').toLowerCase();
      const chunks = Number(it.chunks || it.chunk_count || 0);
      const updated = it.updated_at || it.modified_at || it.date || nowISO();
      const statusBadge =
        status === 'chunked'
          ? '<span class="aichat-badge" style="background:#d1fae5;border:1px solid #10b981;color:#065f46">' + __('Chunked', 'axiachat-ai') + '</span>'
          : status === 'parsed'
            ? '<span class="aichat-badge" style="background:#fef3c7;border:1px solid #f59e0b;color:#92400e">' + __('Parsed', 'axiachat-ai') + '</span>'
            : status === 'uploaded'
              ? '<span class="aichat-badge" style="background:#e5e7eb;border:1px solid #9ca3af;color:#374151">' + __('Uploaded', 'axiachat-ai') + '</span>'
              : `<span class="aichat-badge" style="background:#fee2e2;border:1px solid #ef4444;color:#991b1b">${status}</span>`;
      const btnReparse = `<button class="button aichat-reparse" data-id="${id}" data-loading="` + __('Re-parsing…', 'axiachat-ai') + `">` + __('Re-parse', 'axiachat-ai') + `</button>`;
      const btnDelete = `<button class="button aichat-delete" data-id="${id}" data-loading="` + __('Deleting…', 'axiachat-ai') + `">` + __('Delete', 'axiachat-ai') + `</button>`;
      // Only Re-parse (if chunked) and Delete buttons - Parse is automatic on upload
      let actions = status === 'chunked' ? btnReparse + ' ' + btnDelete : btnDelete;
      return `
        <tr data-id="${id}">
          <td class="filename">${escapeHtml(filename)}</td>
          <td>${mime}</td>
          <td>${size}</td>
          <td class="status">${statusBadge}</td>
          <td class="chunks">${chunks}</td>
          <td class="updated">${updated}</td>
          <td class="aichat-actions">${actions}</td>
        </tr>`;
    });
    $list.html(rows.join(''));
  }

  function renderPagination() {
    const totalPages = Math.max(1, Math.ceil(state.total / state.per_page));
    const cur = state.page;
    if (totalPages <= 1) { $pagination.html(''); return; }
    function pageBtn(p, label, disabled) {
      return `<button type="button" class="button aichat-page-btn" data-page="${p}" ${disabled ? 'disabled' : ''}>${label}</button>`;
    }
    $pagination.html(
      pageBtn(1, __('« First', 'axiachat-ai'), cur === 1) +
      pageBtn(Math.max(1, cur - 1), __('‹ Prev', 'axiachat-ai'), cur === 1) +
      `<span style="margin:0 8px;">` + sprintf(
        /* translators: 1: Current page, 2: Total pages */
        __('Page %1$d / %2$d', 'axiachat-ai'), cur, totalPages) + `</span>` +
      pageBtn(Math.min(totalPages, cur + 1), __('Next ›', 'axiachat-ai'), cur === totalPages) +
      pageBtn(totalPages, __('Last »', 'axiachat-ai'), cur === totalPages)
    );
  }

  // Parse button handler removed - parsing is now automatic on upload

  $list.on('click', '.aichat-reparse', function () {
    if (!confirm(I18N.reparse_q || __('Re-parse this file?', 'axiachat-ai'))) return;
    const $btn = $(this);
    const id = $btn.data('id');
    setRowLoading($btn, true);
    api('aichat_parse_upload', { upload_id: id, force: 1 })
      .done(function (res) {
        if (res && res.success) {
          const data = res.data || {};
          toast(sprintf(
            /* translators: %d: Number of chunks created */
            __('Re-parsed: %d chunks.', 'axiachat-ai'), Number(data.chunks_created || (data.chunk_ids ? data.chunk_ids.length : 0) || 0)));
          loadUploads(state.page, state.search);
        } else {
          toast((res && res.data && res.data.message) || __('Re-parse error', 'axiachat-ai'), true);
        }
      })
      .fail(function (xhr) { toast(sprintf(
        /* translators: %s: HTTP status code */
        __('Network error re-parsing upload (%s)', 'axiachat-ai'), xhr.status), true); })
      .always(function () { setRowLoading($btn, false); });
  });

  $list.on('click', '.aichat-delete', function () {
    if (!confirm(I18N.delete_q || __('Delete this file and its chunks?', 'axiachat-ai'))) return;
    const $btn = $(this);
    const id = $btn.data('id');
    setRowLoading($btn, true);
    api('aichat_delete_upload', { upload_id: id })
      .done(function (res) {
        if (res && res.success) { toast(__('Deleted.', 'axiachat-ai')); loadUploads(state.page, state.search); }
        else { toast((res && res.data && res.data.message) || __('Delete error', 'axiachat-ai'), true); }
      })
      .fail(function (xhr) { toast(sprintf(
        /* translators: %s: HTTP status code */
        __('Network error deleting upload (%s)', 'axiachat-ai'), xhr.status), true); })
      .always(function () { setRowLoading($btn, false); });
  });

  $fileSelect.on('click.aichat', function (e) {
    e.preventDefault();
    e.stopPropagation();
    console.log('[AIChat PDF] Select button -> input.click()');
    openFileDialog();
  });

  $fileInput.on('change.aichat', function () {
    const files = this.files;
    if (files && files.length) {
      console.log('[AIChat PDF] Files selected:', files.length);
      uploadFiles(files);
      $fileInput.val('');
    }
  });
  // Función centralizada para abrir el diálogo evitando recursión por bubbling
  function openFileDialog() {
    const inputEl = $fileInput[0];
    if (!inputEl) {
      console.error('[AIChat PDF] file input missing');
      return;
    }
    // Guard contra múltiples aperturas simultáneas
    if (openFileDialog._busy) return;
    openFileDialog._busy = true;
    setTimeout(() => { openFileDialog._busy = false; }, 700);

    // Evita que el click programático vuelva a disparar el handler del drop (recursión)
    const stopBubbleOnce = function (ev) {
      ev.stopPropagation();
      $(inputEl).off('click.aichat-stop', stopBubbleOnce);
    };
    $(inputEl).on('click.aichat-stop', stopBubbleOnce);

    try {
      inputEl.click();
    } catch (err) {
      console.warn('[AIChat PDF] input.click() failed', err);
    }
  }

  $drop.on('click.aichat', function (e) {
    // Ignora clicks que vienen de la propagación del click programático
    if (e.isTrigger) return; // evento sintético
    if ($(e.target).is('#aichat-file-select') || $(e.target).closest('#aichat-file-select').length) return;
    e.preventDefault();
    e.stopPropagation();
    console.log('[AIChat PDF] Dropzone click -> input (once)');
    openFileDialog();
  });

  $drop.on('keydown.aichat', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openFileDialog();
    }
  });

  $drop.on('dragover.aichat', function (e) {
    e.preventDefault(); e.stopPropagation();
    $drop.addClass('dragover');
  }).on('dragleave.aichat dragend.aichat', function (e) {
    e.preventDefault(); e.stopPropagation();
    $drop.removeClass('dragover');
  }).on('drop.aichat', function (e) {
    e.preventDefault(); e.stopPropagation();
    $drop.removeClass('dragover');
    const dt = e.originalEvent.dataTransfer;
    if (!dt || !dt.files || !dt.files.length) return;
    console.log('[AIChat PDF] Dropped files:', dt.files.length);
    uploadFiles(dt.files);
  });

  $pagination.on('click', '.aichat-page-btn', function () {
    loadUploads(Number($(this).data('page') || 1), state.search);
  });
  $search.on('keyup', debounce(function () {
    loadUploads(1, ($(this).val() || '').trim());
  }, 300));
  $refresh.on('click', function () {
    loadUploads(state.page, state.search);
  });

  $(function () {
    console.log('[AIChat PDF] Initializing...');
    console.log('[AIChat PDF] Config:', cfg);
    console.log('[AIChat PDF] Elements found:', {
      drop: $drop.length,
      fileInput: $fileInput.length,
      fileSelect: $fileSelect.length,
      list: $list.length
    });
    loadUploads(1, '');
    console.log('[AIChat PDF] caps:', CAPS);
  });
})(jQuery);
