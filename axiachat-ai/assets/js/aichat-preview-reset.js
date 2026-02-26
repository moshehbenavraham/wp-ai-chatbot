/* AIChat Preview Home: defensive reset for admin-bar offsets */
(function(){
  try {
    if (document.documentElement && document.documentElement.style) {
      document.documentElement.style.setProperty('margin-top', '0px', 'important');
    }
    if (document.body && document.body.style) {
      document.body.style.marginTop = '0px';
      document.body.style.paddingTop = '0px';
    }
  } catch (e) {}
})();
