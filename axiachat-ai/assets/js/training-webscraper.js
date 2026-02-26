/**
 * AxiaChat AI – Web Scraper Frontend JS
 *
 * Handles the "Web Pages" source card in the Training → Context page.
 * Two modes: Import URLs (paste) and Discover from Website (crawler).
 * Scraped pages become aichat_upload + aichat_upload_chunk posts (same as PDF/TXT).
 *
 * @since 3.2.0
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

  // ── Data from page ──
  const $data = $('#aichat-ctx-data');
  if ( ! $data.length ) return;

  const ajaxUrl = (window.aichat_training_ajax && window.aichat_training_ajax.ajax_url) || ajaxurl;
  const nonce   = $data.data('nonce');

  // ── Track uploaded IDs from web scraping (integrate with main Save & Index) ──
  // We'll push to the global uploadedIds array that training-context.js owes
  window.aichatWebScraperUploadIds = [];

  // ── Method tabs toggle ──
  $('input[name="aichat_ctx_web_method"]').on('change', function(){
    var method = $(this).val();
    if (method === 'urls') {
      $('#aichat-ctx-web-urls-panel').show();
      $('#aichat-ctx-web-crawl-panel').hide();
    } else {
      $('#aichat-ctx-web-urls-panel').hide();
      $('#aichat-ctx-web-crawl-panel').show();
    }
  });

  // ── Toast helper (reuse from main JS if available) ──
  function toast(msg, isError){
    const $t = $('<div class="aichat-training-toast' + (isError ? ' error' : '') + '"></div>').text(msg);
    $('body').append($t);
    setTimeout(function(){ $t.fadeOut(400, function(){ $t.remove(); }); }, 3000);
  }

  // ── Log helper ──
  function appendWebLog(line){
    var $log = $('#aichat-ctx-web-log');
    $log.append(line + '<br>');
    $log.scrollTop($log[0].scrollHeight);
  }

  function updateWebProgress(pct){
    pct = Math.max(0, Math.min(100, pct));
    $('#aichat-ctx-web-progress-bar').css('width', pct + '%').text(Math.round(pct) + '%');
  }

  // ══════════════════════════════
  // METHOD A: Import URLs (paste)
  // ══════════════════════════════
  $('#aichat-ctx-web-fetch-urls').on('click', function(){
    var raw = $('#aichat-ctx-web-urls-input').val().trim();
    if (!raw) {
      toast(__('Please paste at least one URL.', 'axiachat-ai'), true);
      return;
    }

    // Parse URLs (one per line, filter empty/invalid)
    var lines = raw.split('\n').map(function(l){ return l.trim(); }).filter(function(l){ return l.length > 5 && /^https?:\/\//i.test(l); });
    if (lines.length === 0) {
      toast(__('No valid URLs found. Make sure each line starts with http:// or https://', 'axiachat-ai'), true);
      return;
    }

    // Show count
    $('#aichat-ctx-web-urls-count').text(lines.length + ' ' + __('URLs detected', 'axiachat-ai'));

    // Pass to the import pipeline
    startImport(lines);
  });


  // ══════════════════════════════
  // METHOD B: Discover from Website
  // ══════════════════════════════
  $('#aichat-ctx-web-crawl-start').on('click', function(){
    var rootUrl  = $('#aichat-ctx-web-crawl-root').val().trim();
    var maxDepth = parseInt($('#aichat-ctx-web-crawl-depth').val(), 10) || 2;
    var maxPages = parseInt($('#aichat-ctx-web-crawl-limit').val(), 10) || 50;

    if (!rootUrl || !/^https?:\/\//i.test(rootUrl)) {
      toast(__('Please enter a valid URL starting with http:// or https://', 'axiachat-ai'), true);
      return;
    }

    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>' + __('Discovering...', 'axiachat-ai'));

    // Show progress
    $('#aichat-ctx-web-progress').show();
    $('#aichat-ctx-web-log').html('');
    appendWebLog(__('Starting discovery from:', 'axiachat-ai') + ' ' + escapeHtml(rootUrl));
    appendWebLog(__('Depth:', 'axiachat-ai') + ' ' + maxDepth + ', ' + __('Max pages:', 'axiachat-ai') + ' ' + maxPages);
    updateWebProgress(10);

    $.post(ajaxUrl, {
      action:    'aichat_webscraper_crawl_site',
      nonce:     nonce,
      root_url:  rootUrl,
      max_depth: maxDepth,
      max_pages: maxPages
    }, function(res){
      $btn.prop('disabled', false).html('<i class="bi bi-search me-1"></i>' + __('Discover Pages', 'axiachat-ai'));
      updateWebProgress(100);

      if (!res.success) {
        var msg = (res.data && res.data.message) ? res.data.message : __('Error discovering pages.', 'axiachat-ai');
        appendWebLog('<span style="color:#ef4444;">' + escapeHtml(msg) + '</span>');
        toast(msg, true);
        return;
      }

      var urls   = res.data.urls || [];
      var total  = res.data.total || 0;
      var domain = res.data.domain || '';
      var errors = res.data.errors || [];

      appendWebLog('<span style="color:#16a34a;">' + __('Found', 'axiachat-ai') + ' <strong>' + total + '</strong> ' + __('pages on', 'axiachat-ai') + ' ' + escapeHtml(domain) + '</span>');

      if (errors.length > 0) {
        appendWebLog('<span style="color:#f59e0b;">' + errors.length + ' ' + __('errors during crawl', 'axiachat-ai') + '</span>');
      }

      if (urls.length === 0) {
        toast(__('No pages found.', 'axiachat-ai'), true);
        return;
      }

      // Show discovered URLs as a selectable list
      showUrlList(urls);

    }).fail(function(xhr){
      $btn.prop('disabled', false).html('<i class="bi bi-search me-1"></i>' + __('Discover Pages', 'axiachat-ai'));
      appendWebLog('<span style="color:#ef4444;">' + __('Network error', 'axiachat-ai') + ' (' + xhr.status + ')</span>');
      toast(__('Network error.', 'axiachat-ai'), true);
    });
  });


  // ══════════════════════════════
  // URL list display (after crawl)
  // ══════════════════════════════
  function showUrlList(urls){
    var $list = $('#aichat-ctx-web-results-list');
    $list.html('');

    urls.forEach(function(url, i){
      $list.append(
        '<label class="aichat-ctx-web-url-item">' +
          '<input type="checkbox" value="' + escapeHtml(url) + '" checked> ' +
          '<span class="url-text">' + escapeHtml(url) + '</span>' +
        '</label>'
      );
    });

    $('#aichat-ctx-web-results-title').text(__('Discovered Pages', 'axiachat-ai') + ' (' + urls.length + ')');
    $('#aichat-ctx-web-results').show();

    // Activate the card
    $('#aichat-ctx-card-web').addClass('active');
  }

  // Select All / Deselect All
  $('#aichat-ctx-web-select-all').on('click', function(){
    $('#aichat-ctx-web-results-list input[type="checkbox"]').prop('checked', true);
  });
  $('#aichat-ctx-web-deselect-all').on('click', function(){
    $('#aichat-ctx-web-results-list input[type="checkbox"]').prop('checked', false);
  });

  // Import Selected from crawl results
  $('#aichat-ctx-web-import-selected').on('click', function(){
    var selected = [];
    $('#aichat-ctx-web-results-list input[type="checkbox"]:checked').each(function(){
      selected.push($(this).val());
    });

    if (selected.length === 0) {
      toast(__('Select at least one page to import.', 'axiachat-ai'), true);
      return;
    }

    startImport(selected);
  });


  // ══════════════════════════════
  // Import pipeline (batch scraping)
  // ══════════════════════════════
  function startImport(urls){
    var includeUrl = $('#aichat-ctx-web-include-url').prop('checked') ? 1 : 0;
    var totalUrls  = urls.length;
    var batchSize  = 5; // Process 5 URLs at a time
    var batches    = [];

    // Split into batches
    for (var i = 0; i < urls.length; i += batchSize) {
      batches.push(urls.slice(i, i + batchSize));
    }

    // Show progress
    $('#aichat-ctx-web-progress').show();
    $('#aichat-ctx-web-log').html('');
    updateWebProgress(0);
    appendWebLog(__('Starting import of', 'axiachat-ai') + ' ' + totalUrls + ' ' + __('pages...', 'axiachat-ai'));

    // Disable buttons
    $('#aichat-ctx-web-fetch-urls, #aichat-ctx-web-import-selected, #aichat-ctx-web-crawl-start').prop('disabled', true);

    var batchIndex  = 0;
    var totalOk     = 0;
    var totalErr    = 0;
    var totalChunks = 0;

    function processBatch(){
      if (batchIndex >= batches.length) {
        // Done!
        updateWebProgress(100);
        $('#aichat-ctx-web-progress-bar').css('background', '#22c55e');
        appendWebLog('<br><div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;">' +
          '<strong style="color:#16a34a;">✅ ' + __('Import complete!', 'axiachat-ai') + '</strong><br>' +
          '<span style="color:#374151;">' + __('Pages imported:', 'axiachat-ai') + ' <strong>' + totalOk + '</strong> &nbsp;|&nbsp; ' +
          __('Chunks created:', 'axiachat-ai') + ' <strong>' + totalChunks + '</strong>' +
          (totalErr > 0 ? (' &nbsp;|&nbsp; <span style="color:#ef4444;">' + __('Errors:', 'axiachat-ai') + ' ' + totalErr + '</span>') : '') +
          '</span></div>');

        // Re-enable buttons
        $('#aichat-ctx-web-fetch-urls, #aichat-ctx-web-import-selected, #aichat-ctx-web-crawl-start').prop('disabled', false);

        // Activate card
        if (totalOk > 0) {
          $('#aichat-ctx-card-web').addClass('active');
          toast(__('Web pages imported successfully!', 'axiachat-ai'));
        }

        return;
      }

      var batch = batches[batchIndex];
      var pct   = ((batchIndex * batchSize) / totalUrls) * 100;
      updateWebProgress(pct);

      appendWebLog(__('Batch', 'axiachat-ai') + ' ' + (batchIndex + 1) + '/' + batches.length + ' (' + batch.length + ' ' + __('URLs', 'axiachat-ai') + ')...');

      $.post(ajaxUrl, {
        action:      'aichat_webscraper_fetch_urls',
        nonce:       nonce,
        urls:        JSON.stringify(batch),
        include_url: includeUrl
      }, function(res){
        if (res.success) {
          var data = res.data || {};
          var ok   = data.total_ok || 0;
          var err  = data.total_err || 0;
          var ids  = data.upload_ids || [];

          totalOk  += ok;
          totalErr += err;

          // Track upload IDs for Save & Index integration
          ids.forEach(function(id){
            window.aichatWebScraperUploadIds.push(id);
          });

          // Count chunks
          if (data.results) {
            data.results.forEach(function(r){
              totalChunks += (r.chunks_created || 0);
              var icon = r.content_warning ? '⚠️' : '✅';
              appendWebLog('&nbsp;&nbsp;' + icon + ' ' + escapeHtml(r.title || r.url) + ' — ' + (r.chunks_created || 0) + ' chunks');
            });
          }

          // Log errors
          if (data.errors) {
            data.errors.forEach(function(e){
              appendWebLog('&nbsp;&nbsp;<span style="color:#ef4444;">❌ ' + escapeHtml(e.url) + ': ' + escapeHtml(e.message) + '</span>');
            });
          }

        } else {
          var msg = (res.data && res.data.message) ? res.data.message : __('Unknown error', 'axiachat-ai');
          appendWebLog('<span style="color:#ef4444;">' + __('Batch error:', 'axiachat-ai') + ' ' + escapeHtml(msg) + '</span>');
          totalErr += batch.length;
        }

        batchIndex++;
        processBatch();

      }).fail(function(xhr){
        appendWebLog('<span style="color:#ef4444;">' + __('Network error', 'axiachat-ai') + ' (' + xhr.status + ')</span>');
        totalErr += batch.length;
        batchIndex++;
        processBatch();
      });
    }

    processBatch();
  }


  // ══════════════════════════════
  // Auto-open card on interaction
  // ══════════════════════════════
  $('#aichat-ctx-web-urls-input').on('focus', function(){
    var $card = $('#aichat-ctx-card-web');
    if (!$card.hasClass('open')) $card.addClass('open');
  });

  $('#aichat-ctx-web-crawl-root').on('focus', function(){
    var $card = $('#aichat-ctx-card-web');
    if (!$card.hasClass('open')) $card.addClass('open');
  });

})(jQuery);
