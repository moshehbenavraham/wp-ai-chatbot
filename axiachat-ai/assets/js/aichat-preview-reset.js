/* AIChat Preview Home: defensive reset for admin-bar offsets */
(() => {
	try {
		if (document.documentElement?.style) {
			document.documentElement.style.setProperty('margin-top', '0px', 'important');
		}
		if (document.body?.style) {
			document.body.style.marginTop = '0px';
			document.body.style.paddingTop = '0px';
		}
	} catch (_e) {}
})();
