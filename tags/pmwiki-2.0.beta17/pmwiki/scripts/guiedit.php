<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script adds a graphical button bar to the edit page form.
    The buttons are placed in the $GUIButtons array; each button
    is specified by an array of five values:
      - the position of the button relative to others (a number)
      - the opening markup sequence
      - the closing markup sequence
      - the default text if none was highlighted
      - the text of the button, either (a) HTML markup or (b) the 
        url of a gif/jpg/png image to be used for the button 
        (along with optional "title" text in quotes).
*/

$HTMLHeaderFmt[] = "<script language='javascript' 
  src='\$FarmPubDirUrl/guiedit/guiedit.js'></script>\n";

array_push($EditFunctions, 'GUIEdit');

SDVA($GUIButtons, array(
  'em'       => array(100, "''", "''", '$[Emphasized]',
                  '$FarmPubDirUrl/guiedit/em.gif"$[Emphasized (italic)]"'),
  'strong'   => array(110, "'''", "'''", '$[Strong]',
                  '$FarmPubDirUrl/guiedit/strong.gif"$[Strong (bold)]"'),
  'pagelink' => array(200, '[[', ']]', '$[Page link]', 
               '$FarmPubDirUrl/guiedit/pagelink.gif"$[Link to internal page]"'),
  'extlink'  => array(210, '[[', ']]', 'http:// | $[link text]',
               '$FarmPubDirUrl/guiedit/extlink.gif"$[Link to external page]"'),
  'attach'   => array(220, 'Attach:', '', '$[file.ext]',
                  '$FarmPubDirUrl/guiedit/attach.gif"$[Attach file]"'),
  'big'      => array(300, "'+", "+'", '$[Big text]',
                  '$FarmPubDirUrl/guiedit/big.gif"$[Big text]"'),
  'small'    => array(310, "'-", "-'", '$[Small text]',
                  '$FarmPubDirUrl/guiedit/small.gif"$[Small text]"'),
  'sup'      => array(320, "'^", "^'", '$[Superscript]',
                  '$FarmPubDirUrl/guiedit/sup.gif"$[Superscript]"'),
  'sub'      => array(330, "'_", "_'", '$[Subscript]',
                  '$FarmPubDirUrl/guiedit/sub.gif"$[Subscript]"'),
  'h3'       => array(400, '\\n!!! ', '\\n', '$[Heading 3]',
                  '$FarmPubDirUrl/guiedit/h3.gif"$[Heading 3]"'),
  'center'   => array(410, '%25center%25', '', '',
                  '$FarmPubDirUrl/guiedit/center.gif"$[Center]"')));

function GUIEdit($pagename, &$page, &$new) {
  global $GUIButtons, $EditMessageFmt;
  sort($GUIButtons);
  $out = array("<script language='javascript' type='text/javascript'>\n");
  foreach ($GUIButtons as $k => $g) {
    if (!$g) continue;
    list($when, $mopen, $mclose, $mtext, $tag) = $g;
    if (preg_match('/^(.*\\.(gif|jpg|png))("([^"]+)")?$/', $tag, $m)) {
      $title = (@$m[4] > '') ? "title='{$m[4]}'" : '';
      $tag = "<img src='{$m[1]}' $title style='border:0px;' />";
    }
    $mopen = str_replace(array('\\', "'"), array('\\\\', "\\\\'"), $mopen);
    $mclose = str_replace(array('\\', "'"), array('\\\\', "\\\\'"), $mclose);
    $mtext = str_replace(array('\\', "'"), array('\\\\', "\\\\'"), $mtext);
    $out[] = "insButton(\"$mopen\", \"$mclose\", '$mtext', \"$tag\");\n";
  }
  $out[] = '</script><br />';
  $EditMessageFmt .= implode('', $out);
}

?>
