/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file provides Javascript functions to support WYSIWYG-style
    editing.  The concepts are borrowed from the editor used in Wikipedia,
    but the code has been rewritten from scratch to integrate better with
    PHP and PmWiki's codebase.  
*/

function insButton(mopen, mclose, mtext, mlabel) {
  document.write("<a href=\"javascript:insMarkup('"
    + mopen + "','"
    + mclose + "','"
    + mtext + "');\">"
    + mlabel + "</a>");
}

function insMarkup(mopen, mclose, mtext) {
  var tarea = document.getElementById('text');
  if (tarea.setSelectionRange > '') {
    var p0 = tarea.selectionStart;
    var p1 = tarea.selectionEnd;
    var top = tarea.scrollTop;
    var str = mtext;
    var cur = 0;
    while (p1 > p0 && tarea.value.substring(p1-1, p1) == ' ') { p1--; cur++; }
    if (p1 > p0) str = tarea.value.substring(p0, p1);
    tarea.value = tarea.value.substring(0,p0)
      + mopen + str + mclose
      + tarea.value.substring(p1);
    cur += p0 + mopen.length + str.length + mclose.length;
    tarea.focus();
    tarea.selectionStart = cur;
    tarea.selectionEnd = cur;
    tarea.scrollTop = top;
  } else if (document.selection) {
    var str = document.selection.createRange().text;
    if (str == '') str = mtext;
    tarea.focus();
    document.selection.createRange().text = mopen + str + mclose;
  } else { tarea.value += mopen + mtext + mclose; }
  return;
}
