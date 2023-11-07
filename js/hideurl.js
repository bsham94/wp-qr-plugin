jQuery(document).ready(function ($) {
  console.log("hideurl.js loaded");
  if (window.location.search) {
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});
