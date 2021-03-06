<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script handles author tracking.
*/

SDV($AuthorCookieExpires,$Now+60*60*24*30);
SDV($AuthorCookieDir,'/');
SDV($AuthorGroup,'Profiles');
SDV($AuthorRequiredFmt,
  "<h3 class='wikimessage'>$[An author name is required.]</h3>");
Markup('[[~','<[[','/\\[\\[~(.*?)\\]\\]/',"[[$AuthorGroup/$1]]");
  "[[$AuthorGroup/$1]]";

if (!isset($Author)) {
  if (isset($_POST['author'])) {
    $Author = htmlspecialchars(stripmagic($_POST['author']),ENT_QUOTES);
    setcookie('author',$Author,$AuthorCookieExpires,$AuthorCookieDir);
  } else {
    $Author = htmlspecialchars(stripmagic(@$_COOKIE['author']),ENT_QUOTES);
  }
  $Author = preg_replace('/(^[^[:alpha:]]+)|[^\\w- ]/','',$Author);
}
$k = MakePageName($pagename,$Author);
SDV($AuthorPage,"$AuthorGroup/$k");
SDV($AuthorLink,"[[~$Author]]");

if (IsEnabled($EnableAuthorSignature,1)) {
  $ROSPatterns['/~~~~/'] = '[[~$Author]] $CurrentTime';
  $ROSPatterns['/~~~/'] = '[[~$Author]]';
  Markup('~~~~','<links','/~~~~/',"[[~$Author]] $CurrentTime");
  Markup('~~~','>~~~~','/~~~/',"[[~$Author]]");
}
if (IsEnabled($EnableAuthorRequired,0))
  array_unshift($EditFunctions,'RequireAuthor');

## RequireAuthor forces an author to enter a name before posting.
function RequireAuthor($pagename,&$page,&$new) {
  global $Author,$EditMessageFmt,$AuthorRequiredFmt;
  if (!$Author) {
    $EditMessageFmt .= $AuthorRequiredFmt;
    $_REQUEST['post'] = '';
  }
}
?>
