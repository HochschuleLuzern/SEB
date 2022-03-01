il = il || {};
il.seb = il.seb || {};

il.seb = (function () {
	let checkSebKeyGuiURL;
	saveAndCheckSEBKey = function (url) {
		checkSebKeyGuiURL = url;
		document.cookie = 'uri=' + window.location.href;
		if (typeof SafeExamBrowser !== 'undefined' &&
				SafeExamBrowser.security !== undefined ) {
			SafeExamBrowser.security.updateKeys(afterUpdateKey);
		}
	}
	
	afterUpdateKey = function () {
		document.cookie = 'examKey=' + SafeExamBrowser.security.browserExamKey;
		document.cookie = 'configKey=' + SafeExamBrowser.security.configKey;
		document.cookie = 'sebClientVersion=' + SafeExamBrowser.version;
		sendRequest();
	}
	
	sendRequest = function () {
		let request = new XMLHttpRequest();
		request.open("GET", checkSebKeyGuiURL, true); 
		request.onreadystatechange = function() {
   			if (this.readyState === 4 && this.status === 403) {
				document.open('text/html');
				document.write(this.responseText);
				document.close();
   			}
		};
		request.send();
	}
	
	return {
		saveAndCheckSEBKey: saveAndCheckSEBKey
	}
})()