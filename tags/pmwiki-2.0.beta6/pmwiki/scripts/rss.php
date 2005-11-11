<?php if (!defined('PmWiki')) exit();
/*  Copyright 2002-2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script defines "?action=rss".  It will read the current page
    for a WikiTrail, and then output an RSS 2.0 document with the current
    page as the channel and the pages in the WikiTrail as the items.

    To add RSS capabilities to your site, simply add to config.php:
        include_once("scripts/rss.php");
*/

SDV($HandleActions['rss'],'HandleRss');
SDV($HandleActions['rdf'],'HandleRss');

SDV($RssMaxItems,20);				# maximum items to display
SDV($RssSourceSize,400);			# max size to build desc from
SDV($RssDescSize,200);				# max desc size
SDV($RssItems,array());				# RSS item elements
SDV($RssItemsRDFList,array());			# RDF <items> elements

if ($action=='rdf') {
  ### RSS 1.0 (RDF) definitions
  SDV($RssTimeFmt,'%Y-%m-%dT%H:%MZ');	# time format
  SDV($RssItemsRDFListFmt,"<rdf:li rdf:resource=\"\$PageUrl\" />\n");
  SDV($RssChannelFmt,array('<?xml version="1.0"?'.'>
    <!DOCTYPE rdf:RDF [
    <!ENTITY % HTMLlat1 PUBLIC "-//W3C//ENTITIES Latin 1 for XHTML//EN"
      "http://www.w3.org/TR/xhtml1/DTD/xhtml-lat1.ent"> %HTMLlat1; 
    <!ENTITY % HTMLspecial PUBLIC "-//W3C//ENTITIES Special for XHTML//EN"
      "http://www.w3.org/TR/xhtml1/DTD/xhtml-special.ent"> %HTMLspecial;
    ]>
    <rdf:RDF  xmlns="http://purl.org/rss/1.0/"
        xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
        xmlns:dc="http://purl.org/dc/elements/1.1/">
      <channel rdf:about="$PageUrl">
        <title>$WikiTitle - $Group.$Name</title>
        <link>$PageUrl</link>
        <description>$RssChannelDesc</description>
        <dc:date>$RssChannelBuildDate</dc:date>
        <items>
          <rdf:Seq>',&$RssItemsRDFList,'
          </rdf:Seq>
        </items>
      </channel>'));
  SDV($RssItemFmt,'
      <item rdf:about="$PageUrl">
        <title>$WikiTitle - $Group.$Name</title>
        <link>$PageUrl</link>
        <description>$RssItemDesc</description>
        <dc:date>$RssItemPubDate</dc:date>
      </item>');
  SDV($HandleRssFmt,array(&$RssChannelFmt,&$RssItems,'</rdf:RDF>'));
}

### RSS 2.0 definitions
SDV($RssTimeFmt,'%Y-%m-%dT%H:%MZ');
SDV($RssChannelFmt,'<?xml version="1.0"?'.'>
  <!DOCTYPE rdf:RDF [
  <!ENTITY % HTMLlat1 PUBLIC "-//W3C//ENTITIES Latin 1 for XHTML//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml-lat1.ent"> %HTMLlat1; 
  <!ENTITY % HTMLspecial PUBLIC "-//W3C//ENTITIES Special for XHTML//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml-special.ent"> %HTMLspecial;
  ]>
  <rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
      <title>$WikiTitle - $Group.$Name</title>
      <link>$PageUrl</link>
      <description>$RssChannelDesc</description>
      <lastBuildDate>$RssChannelBuildDate</lastBuildDate>
      <generator>$Version</generator>');
SDV($RssItemFmt,'
        <item>
          <title>$FullName</title>
          <link>$PageUrl</link>
          <description>$RssItemDesc</description>
          <dc:contributor>$RssItemAuthor</dc:contributor>
          <dc:date>$RssItemPubDate</dc:date>
        </item>');
SDV($HandleRssFmt,array(&$RssChannelFmt,&$RssItems,'</channel></rss>'));

function rssencode($s) 
  { return preg_replace('/([\\x80-\\xff])/e',"'&#'.ord('\$1').';'",$s); }

function HandleRss($pagename) {
  global $RssMaxItems,$RssSourceSize,$RssDescSize,
    $RssChannelFmt,$RssChannelDesc,$RssTimeFmt,$RssChannelBuildDate,
    $RssItemsRDFList,$RssItemsRDFListFmt,$RssItems,$RssItemFmt,
    $HandleRssFmt,$FmtV;
  $t = ReadTrail($pagename,$pagename);
  $page = RetrieveAuthPage($pagename,'read',false);
  if (!$page) Abort("?cannot read $pagename");
  $cbgmt = $page['time'];
  $r = array();
  for($i=0;$i<count($t) && count($r)<$RssMaxItems;$i++) {
    if (!PageExists($t[$i]['pagename'])) continue;
    $page = RetrieveAuthPage($t[$i]['pagename'],'read',false);
    if (!$page) continue;
    $text = 
      MarkupToHTML($t[$i]['pagename'],substr($page['text'],0,$RssSourceSize));
    $text = rssencode(preg_replace("/<.*?>/s","",$text)); 
    preg_match("/^(.{0,$RssDescSize}\\s)/s",$text,$match);
    $r[] = array('name' => $t[$i]['pagename'],'time' => $page['time'],
       'desc' => $match[1]." ...", 'author' => $page['author']);
    if ($page['time']>$cbgmt) $cbgmt=$page['time'];
  }
  SDV($RssChannelBuildDate,rssencode(gmstrftime($RssTimeFmt,$cbgmt)));
  SDV($RssChannelDesc,rssencode(FmtPageName('$Group.$Title',$pagename)));
  foreach($r as $page) {
    $FmtV['$RssItemPubDate'] = gmstrftime($RssTimeFmt,$page['time']);
    $FmtV['$RssItemDesc'] = $page['desc']; 
    $FmtV['$RssItemAuthor'] = $page['author'];
    $RssItemsRDFList[] = 
      rssencode(FmtPageName($RssItemsRDFListFmt,$page['name']));
    $RssItems[] = 
      rssencode(FmtPageName($RssItemFmt,$page['name']));
  }
  header("Content-type: text/xml");
  PrintFmt($pagename,$HandleRssFmt);
  exit();
}
