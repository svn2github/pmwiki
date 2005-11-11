<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file attempts to ease conversions of PmWiki 1.x installations
    to PmWiki 2.  This is definitely a preliminary implementation and
    still probably needs some work.

    The major component is the PageStore1x, which allows pages
    to be read from an existing PmWiki 1.x wiki.d/ directory, 
    converting PmWiki 1 markup into PmWiki 2 markup as the page is read.  
    Pages are then saved in the PmWiki 2 installation's wiki.d/
    directory, which should be separate from the original wiki.d/.

    The intent is that a wiki administrator can install, configure, and
    test a PmWiki 2 installation on an existing set of PmWiki 1.x pages 
    without losing or modifying the 1.x page files.

    Details on this are being maintained at the UpgradingFromPmWiki1 page 
    http://www.pmwiki.org/pmwiki2/pmwiki.php/PmWiki/UpgradingFromPmWiki1 .
*/

SDVA($Compat1x,array(
  # noheader, nofooter, etc.
  "/\\[\\[(noheader|nofooter|nogroupheader|nogroupfooter|notitle|spacewikiwords)\\]\\]/" => '(:$1:)',

  # include, redirect
  "/\\[\\[(include|redirect):(.*?)\\]\\]/" => '(:$1 $2:)',

  # table, cell, cellnr, endtable
  "/\\[\\[(table|cell|cellnr|tableend)(\\s.*?)?\\]\\]\n?/" => "(:$1$2:)\n",

  # [[$Title]]
  "/\\[\\[\\\$Title\\]\\]/" => '{$Name}',

  # [[$pagecount]], from SimplePageCount cookbook script
  "/\\[\\[\\\$pagecount\\]\\]/" => '{$PageCount}',

  # [[$Group]], [[$Version]], etc.
  "/\\[\\[\\$(Group|Version|Author|LastModified|LastModifiedBy|LastModifiedHost)\\]\\]/" => '{$$1}',

  # [[$Edit text]], [[$Diff text]]
  "/\\[\\[\\\$Edit\\s(.*?)\\]\\]/" => '[[{$Name}?action=edit |$1]]',
  "/\\[\\[\\\$Diff\\s(.*?)\\]\\]/" => '[[{$Name}?action=diff |$1]]',

  # [[$Search]], [[$SearchResults]], [[$Attachlist]]
  "/\\[\\[\\\$Search\\]\\]/" => '(:searchbox:)',
  "/\\[\\[\\\$Searchresults\\]\\]/" => '(:searchresults:)',
  "/\\[\\[\\\$Attachlist(\\s.*?)?\\]\\]/" => '(:attachlist$1:)',

  # [[target linktext]]
  "/\\[\\[((\\w|\\#)[^$UrlExcludeChars\\s]*)\\s(.*?)\\]\\]/" => '[[$1 |$3]]',

  # [[target]]
  "/\\[\\[(\\w[^$UrlExcludeChars\\s]*)\\]\\]/" => '[[$1 |<#>]]',

  # [[Group.{{free link}} link text]]
  "/\\[\\[($GroupPattern([\\/.]))?\\{\\{(~?\\w[-\\w\\s.\\/]*)\\}\\}([-#\\w]*)\\s(.*?)\\]\\]/" => '[[$1$3$4 |$5]]',

  # [[Group.{{free link|s}} link text]]
  "/\\[\\[($GroupPattern([\\/.]))?\\{\\{(~?\\w[-\\w\\s.\\/]*)\\|([-\\w\\s]*)\\}\\}([-#\\w]*)\\s(.*?)\\]\\]/" => '[[$1$3$4$5 |$6]]',

  # Group.{{free link}}ext
  "/($GroupPattern([\\/.]))?\\{\\{(~?\\w[-\\w\\s.\\/]*)\\}\\}([-\\w]*)/" 
    => '[[$1$3]]$4',

  # Group.{{free link|s}}ext
  "/($GroupPattern([\\/.]))?\\{\\{(~?\\w[-\\w\\s.\\/]*)\\|([-\\w\\s]*)\\}\\}([-\\w]*)/" => '[[$1$3($4)]]$5',

  # :: lists
  "/^(:+)(:[^:\n]*)$/m" => '$1 $2',
));

class PageStore1x extends PageStore {
  function read($pagename) {
    global $Compat1x,$KeepToken;
    $page = parent::read($pagename);
    if ($page) {
      $page['text'] = preg_replace('/(\\[([=@]).*?\\2\\])/se',"Keep(PSS('$1'))",
        @$page['text']);
      $page['text'] = preg_replace(array_keys($Compat1x),
        array_values($Compat1x), $page['text']);
      $page['text'] = preg_replace("/$KeepToken(\\d.*?)$KeepToken/e",
        '$GLOBALS[\'KPV\'][\'$1\']',$page['text']);
    }
    return $page;
  }
}
   
?>
