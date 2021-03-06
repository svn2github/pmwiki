<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script defines the ?action=print action to give a printable
    view of a page.  Essentially it performs the following modifications:
      - Redefines the standard layout to a format suitable for printing
      - Redefines internal links to keep ?action=print
      - Changes the display of URL and mailto: links
      - Uses GroupPrintHeader and GroupPrintFooter pages instead
        of GroupHeader and GroupFooter
*/

if ($action!='print') return;

# $PrintTemplateFmt is deprecated, use $PrintSkinFmt
SDV($PrintSkinFmt,@$PrintTemplateFmt);
$PageSkinFmt = (@$PrintSkinFmt) ? $PrintSkinFmt : 'print';
$SkinTmplFmt = 'print.tmpl';

$LinkPageExistsFmt = "<a class='wikilink' href='\$PageUrl?action=print\$Fragment'>\$LinkText</a>";
$UrlLinkTextFmt = "<cite class='urllink'>\$LinkText</cite> [<a class='urllink' href='\$Url'>\$Url</a>]";
SDV($GroupPrintHeaderFmt,'(:include $Group.GroupPrintHeader:)(:nl:)');
SDV($GroupPrintFooterFmt,'(:nl:)(:include $Group.GroupPrintFooter:)');
$GroupHeaderFmt = $GroupPrintHeaderFmt;
$GroupFooterFmt = $GroupPrintFooterFmt;
#$DoubleBrackets["/\\[\\[mailto:($UrlPathPattern)(.*?)\\]\\]/"] = 
#  "''\$2'' [mailto:\$1]";

?>
