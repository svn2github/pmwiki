/***********************************************************************
**  skin.js
**  Copyright 2016-2017 Petko Yotov www.pmwiki.org/petko
**  
**  This file is part of PmWiki; you can redistribute it and/or modify
**  it under the terms of the GNU General Public License as published
**  by the Free Software Foundation; either version 2 of the License, or
**  (at your option) any later version.  See pmwiki.php for full details.
**  
**  This script fixes the styles of some elements when some directives
**  like (:noleft:) are used in a page.
***********************************************************************/
(function(){
  function $(x) { // returns element from id
    return document.getElementById(x);
  }
  function hide(id) { // hides element
    var el = $(id);
    if(el) el.style.display = 'none'; 
  }
  function cname(id, c) { // set element className
    var el = $(id);
    if(el) el.className = c;
  }
  var wsb = $('wikisidebar');
  if(! wsb) { // (:noleft:)
    hide('wikileft-toggle-label')
    cname('wikifoot', 'nosidebar');
  }
  else { 
    var sbcontent = wsb.textContent || wsb.innerText;
    if(! sbcontent.replace(/\s+/, '').length) // empty sidebar, eg. protected
      hide('wikileft-toggle-label');
  }
  if(! $('wikihead-searchform')) // no search form, eg. custom header
    hide('wikihead-search-toggle-label');
  var overlay = $('wikioverlay');
  if(overlay) {
    overlay.addEventListener('click', function(){
      $('wikicmds-toggle').checked = false;
      $('wikihead-search-toggle').checked = false;
      $('wikileft-toggle').checked = false;
    });
  }
})();
