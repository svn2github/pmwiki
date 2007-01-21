<?php if (!defined('PmWiki')) exit();
/*  Copyright 2001-2007 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script adds WikiWord (CamelCase) processing to PmWiki.
    Originally WikiWords were part of the default configuration,
    but their usage has died out over time and so it's now optional.

    To enable WikiWord links, simply add the following to
    a local customization file:

        $EnableWikiWords = 1;

    To have PmWiki recognize and process WikiWords but not link
    them (i.e., the default behavior in PmWiki 2.1), also add

        $LinkWikiWords = 0;

*/

SDV($LinkWikiWords, 1);

## bare wikilinks
Markup('wikilink', '>urllink',
  "/\\b(?<![#&])($GroupPattern([\\/.]))?($WikiWordPattern)/e",
  "Keep('<span class=\\'wikiword\\'>'.WikiLink(\$pagename,'$0').'</span>',
        'L')");

function WikiLink($pagename, $word) {
  global $LinkWikiWords, $WikiWordCount, $SpaceWikiWords, $AsSpacedFunction,
    $MarkupFrame, $WikiWordCountMax;
  if (!$LinkWikiWords || ($WikiWordCount[$word] < 0)) return $word;
  $text = ($SpaceWikiWords) ? $AsSpacedFunction($word) : $word;
  $text = preg_replace('!.*/!', '', $text);
  if (!isset($MarkupFrame[0]['wwcount'][$word]))
    $MarkupFrame[0]['wwcount'][$word] = $WikiWordCountMax;
  if ($MarkupFrame[0]['wwcount'][$word]-- < 1) return $text;
  return MakeLink($pagename, $word, $text);
}


