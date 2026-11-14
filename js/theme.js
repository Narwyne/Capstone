// js/theme.js — applies dark mode. Put in <head> of every page (before </head>).
(function () {
  var m = document.cookie.match(/(?:^|; )theme=(\w+)/);
  var t = m ? m[1] : (localStorage.getItem('theme') || 'light');
  var root = document.documentElement;

  function loadCss() {
    if (document.getElementById('darkCss')) return;
    var l = document.createElement('link');
    l.id = 'darkCss'; l.rel = 'stylesheet'; l.href = 'css/dark.css';
    document.head.appendChild(l);
  }

  // Used by Profile → Appearance for instant preview
  window.setTheme = function (v) {
    v = v === 'dark' ? 'dark' : 'light';
    document.cookie = 'theme=' + v + ';path=/;max-age=31536000';
    try { localStorage.setItem('theme', v); } catch (e) {}
    root.classList.toggle('dark', v === 'dark');
    if (v === 'dark') loadCss();
  };

  if (t === 'dark') {
    root.classList.add('dark');
    document.write('<link id="darkCss" rel="stylesheet" href="css/dark.css">'); // blocking = no white flash
  }
})();
