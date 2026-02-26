jQuery(function($){
  // WordPress i18n functions
  var __ = wp.i18n.__;

  function microsToUSD(m){ if(!m) return '$0.0000'; return '$'+(m/1000000).toFixed(4); }
  function escHtml(s){
    return String(s==null?'':s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/\'/g,'&#039;');
  }
  function loadSummary(){
    $.post(AIChatUsageAjax.ajax_url,{action:'aichat_get_usage_summary',nonce:AIChatUsageAjax.nonce}, function(r){
      if(!r || !r.success) return;
      var s=r.data;
      $('[data-kpi="today-cost"]').text(microsToUSD(s.today.cost));
      $('[data-kpi="today-tokens"]').text(s.today.tokens||0);
      $('[data-kpi="last7-cost"]').text(microsToUSD(s.last7.cost));
      $('[data-kpi="last7-tokens"]').text(s.last7.tokens||0);
      $('[data-kpi="last30-cost"]').text(microsToUSD(s.last30.cost));
      $('[data-kpi="last30-tokens"]').text(s.last30.tokens||0);
      var tb = $('#aichat-usage-topmodels tbody').empty();
      if(!s.top_models.length){ tb.append('<tr><td colspan="3">' + __('(none)', 'axiachat-ai') + '</td></tr>'); }
      else { s.top_models.forEach(function(row){ tb.append('<tr><td>'+ (row.model||'') +'</td><td>'+ (row.provider||'') +'</td><td>'+ microsToUSD(row.cm) +'</td></tr>'); }); }
    });
  }
  var chartRef = null;
  function renderTimeseries(rows){
    var noDataEl = $('#aichat-usage-nodata');
    var ctx = document.getElementById('aichat-usage-chart'); if(!ctx) return;
    if(!rows.length){
      noDataEl.show();
      if(chartRef){ chartRef.destroy(); chartRef=null; }
      return;
    }
    noDataEl.hide();
  var labels = rows.map(x=>x.d);
  var total = rows.map(x=>parseInt(x.t|| ( (parseInt(x.p||0,10)+parseInt(x.c||0,10)) ),10));
  var cost = rows.map(x=> (x.m? (x.m/1000000).toFixed(4):0) );
    if(chartRef){ chartRef.destroy(); }
    chartRef = new Chart(ctx, {
      type:'bar',
      data:{ labels:labels, datasets:[
        {label:(AIChatUsageAjax.strings?AIChatUsageAjax.strings.totalTokens:__('Total Tokens', 'axiachat-ai')), data:total, backgroundColor:'#4e79a7'}
      ]},
      options:{
        responsive:true,
        plugins:{
          tooltip:{callbacks:{
            title:function(items){ return items[0].label; },
            afterBody:function(items){ var i=items[0].dataIndex; return (AIChatUsageAjax.strings?AIChatUsageAjax.strings.costLabel:__('Cost', 'axiachat-ai'))+': $'+cost[i]; }
          }}
        },
        scales:{ y:{ beginAtZero:true } }
      }
    });
  }
  function loadTimeseries(){
    $.post(AIChatUsageAjax.ajax_url,{action:'aichat_get_usage_timeseries',nonce:AIChatUsageAjax.nonce}, function(r){
      if(!r || !r.success) return;
      renderTimeseries(r.data.series||[]);
    });
  }

  // Last Conversations tab
  var lastConvOffset = 0;
  var lastConvLimit = 50;
  var lastConvLoading = false;

  function renderLastConvRows(items, append){
    var $tbody = $('#aichat-lastconv-table tbody');
    if(!$tbody.length) return;
    if(!append) $tbody.empty();
    if(!items || !items.length){
      if(!append) $tbody.append('<tr><td colspan="8">' + __('(none)', 'axiachat-ai') + '</td></tr>');
      return;
    }
    items.forEach(function(it){
      var p = (it.prompt_tokens==null?'–':it.prompt_tokens);
      var c = (it.completion_tokens==null?'–':it.completion_tokens);
      var t = (it.total_tokens==null?'–':it.total_tokens);
      var tokens = escHtml(p)+'/'+escHtml(c)+'/'+escHtml(t);
      var cost = microsToUSD(it.cost_micros);
      var q = escHtml(it.question_preview||'');
      var respPreview = escHtml(it.response_preview||'');
      var respFull = escHtml(it.response_full||'');
      var respCell = '<div style="white-space:pre-wrap;word-break:break-word;">'+respPreview+'</div>';
      if(respFull && respFull !== respPreview){
        respCell = '<details><summary style="cursor:pointer;">'+respPreview+'</summary><div style="white-space:pre-wrap;word-break:break-word;margin-top:6px;">'+respFull+'</div></details>';
      }

      $tbody.append(
        '<tr>'+
          '<td>'+escHtml(it.created_at||'')+'</td>'+
          '<td>'+escHtml(it.bot_slug||'')+'</td>'+
          '<td>'+escHtml(it.provider||'')+'</td>'+
          '<td style="word-break:break-word;">'+escHtml(it.model||'')+'</td>'+
          '<td>'+tokens+'</td>'+
          '<td>'+escHtml(cost)+'</td>'+
          '<td style="white-space:pre-wrap;word-break:break-word;">'+q+'</td>'+
          '<td>'+respCell+'</td>'+
        '</tr>'
      );
    });
  }

  function setLastConvStatus(txt){
    $('#aichat-lastconv-status').text(txt||'');
  }

  function loadLastConversations(opts){
    opts = opts || {};
    if(lastConvLoading) return;
    lastConvLoading = true;
    setLastConvStatus(__('Loading…', 'axiachat-ai'));

    var append = !!opts.append;
    var offset = append ? lastConvOffset : 0;

    $.post(AIChatUsageAjax.ajax_url, {
      action: 'aichat_get_last_conversations',
      nonce: AIChatUsageAjax.nonce,
      limit: lastConvLimit,
      offset: offset
    }, function(r){
      if(!r || !r.success){
        setLastConvStatus(__('Error', 'axiachat-ai'));
        return;
      }
      var items = (r.data && r.data.items) ? r.data.items : [];
      renderLastConvRows(items, append);
      if(append) lastConvOffset += items.length;
      else lastConvOffset = items.length;
      setLastConvStatus(items.length ? '' : __('No rows', 'axiachat-ai'));
    }).always(function(){
      lastConvLoading = false;
    });
  }

  $('#aichat-lastconv-refresh').on('click', function(e){
    e.preventDefault();
    loadLastConversations({append:false});
  });
  $('#aichat-lastconv-loadmore').on('click', function(e){
    e.preventDefault();
    loadLastConversations({append:true});
  });

  // Init only what's present on the current tab
  if($('#aichat-lastconv').length){
    loadLastConversations({append:false});
    return;
  }

  if($('#aichat-usage-kpis').length){
    loadSummary();
    // Carga inteligente: si Chart ya está, carga; si no, espera evento; fallback timeout.
    function tryInitTimeseries(){
      if(typeof window.Chart !== 'undefined'){
        loadTimeseries();
        return true;
      }
      return false;
    }
    if(!tryInitTimeseries()){
      document.addEventListener('aichat_chart_ready', function(){ tryInitTimeseries(); });
      // Fallback por si el evento se disparó antes de registrar el listener o no llega
      setTimeout(tryInitTimeseries, 500);
      setTimeout(tryInitTimeseries, 1500);
    }
  }
});
