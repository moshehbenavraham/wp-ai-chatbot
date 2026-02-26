/**
 * AxiaChat AI – Training Hub JS
 *
 * Handles bot selector → updates card links when bot changes.
 *
 * @since 3.0.1
 */
(function($){
  'use strict';

  const __ = (wp && wp.i18n && wp.i18n.__) ? wp.i18n.__ : function(t){ return t; };

  $(function(){
    const $select = $('#aichat-training-bot-select');
    if ( ! $select.length ) return;

    $select.on('change', function(){
      const slug = $(this).val();
      // Update card links
      const base = window.aichat_training_ajax ? window.aichat_training_ajax.admin_url : '';
      if ( ! base ) {
        // Fallback: reload with query param
        const url = new URL(window.location.href);
        url.searchParams.set('bot', slug);
        window.location.href = url.toString();
        return;
      }
      $('#aichat-training-card-instructions').attr('href', base + '?page=aichat-training-instructions&bot=' + encodeURIComponent(slug));
      $('#aichat-training-card-context').attr('href', base + '?page=aichat-training-context&bot=' + encodeURIComponent(slug));
    });
  });

})(jQuery);
