(function () {
    var source = new URL('../../shared/dashboard-shell.js', document.currentScript.src).href;
    var script = document.createElement('script');
    script.src = source;
    document.head.appendChild(script);
}());
