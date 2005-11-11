<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script defines PmWiki's standard markup.  It is automatically
    included from stdconfig.php unless $EnableStdMarkup==0.

    Each call to Markup() below adds a new rule to PmWiki's translation
    engine.  The form of the call is Markup($id,$where,$pat,$rep); $id
    is a unique name for the rule, $where is the position of the rule
    relative to another rule, $pat is the pattern to look for, and
    $rep is the string to replace it with.
    
    
*/

## first we preserve text in [=...=] and [@...@]
Markup('[=','_begin','/\\[([=@])(.*?)\\1\\]/se',
    "Keep(\$K0['$1'].PSS('$2').\$K1['$1'])");
Markup('restore','<_end',"/$KeepToken(\\d.*?)$KeepToken/e",
    '$GLOBALS[\'KPV\'][\'$1\']');

## remove carriage returns before preserving text
Markup('\\r','<[=','/\\r/','');

# ${var} substitutions
Markup('${fmt}','>[=','/{\\$(Group|Name)}/e',
  "FmtPageName('$$1',\$pagename)");
Markup('${var}','>${fmt}',
  '/{\\$(Version|Author|LastModified|LastModifiedBy|LastModifiedHost|UrlPage|DefaultName|DefaultGroup)}/e',
  "\$GLOBALS['$1']");
Markup('if','fulltext',"\\[:(if[^\n]*?):\\](.*?)(?=\\[:if[^\n]*:\\]|$)/se",
  "CondText(\$pagename,PSS('$1'),PSS('$2'))");

## [:include:]
Markup('include','>if',"/\\[:(include\\s+.+?):\\]/e",
  "PRR().IncludeText(\$pagename,'$1')");

## GroupHeader/GroupFooter handling
Markup('nogroupheader','>include','/\\[:nogroupheader:\\]/e',
  "PZZ(\$GLOBALS['GroupHeaderFmt']='')");
Markup('nogroupfooter','>include','/\\[:nogroupfooter:\\]/e',
  "PZZ(\$GLOBALS['GroupFooterFmt']='')");
Markup('groupheader','>nogroupheader','/\\[:groupheader:\\]/e',
  "PRR().FmtPageName(\$GLOBALS['GroupHeaderFmt'],\$pagename)");
Markup('groupfooter','>nogroupfooter','/\\[:groupfooter:\\]/e',
  "PRR().FmtPageName(\$GLOBALS['GroupFooterFmt'],\$pagename)");

## [:nl:]
Markup('nl0','<split',"/(?!\n)\\[:nl:\\](?!\n)/","\n");
Markup('nl1','>nl0',"/\\[:nl:\\]/",'');

## \\$  (end of line joins)
Markup('\\$','>nl1',"/(\\\\*)\\\\\n/e",
  "Keep(' '.str_repeat('<br />',strlen('$1')))");

## [:noheader:],[:nofooter:],[:notitle:]...
Markup('noheader','directives','/\\[:noheader:\\]/e',
  "PZZ(\$GLOBALS['PageHeaderFmt']='')");
Markup('nofooter','directives','/\\[:noheader:\\]/e',
  "PZZ(\$GLOBALS['PageFooterFmt']='')");
Markup('notitle','directives','/\\[:notitle:\\]/e',
  "PZZ(\$GLOBALS['PageTitleFmt']='')");

## [:title:]
Markup('title','directives','/\\[:title\\s(.*?):\\]/e',
  "PZZ(\$GLOBALS['PageTitle']=PSS('$1'))");

## [:comment:]
Markup('comment','directives','/\\[:comment .*?:\\]/','');

## character entities
Markup('&','directives','/&amp;([A-Za-z0-9]+;|#\\d+;|#[xX][A-Fa-f0-9]+;)/',
  '&$1');

###### Links ######
## [[free links]]
Markup('[[','links',"/\\[\\[(.*?)\\]\\]($SuffixPattern)/e",
  "Keep(MakeLink(\$pagename,PSS('$1'),NULL,'$2'),'L')");

## [[target | text]]
Markup('[[|','<[[',"/\\[\\[([^|\\]]+)\\|(.*?)\\s*\\]\\]($SuffixPattern)/e",
  "Keep(MakeLink(\$pagename,PSS('$1'),PSS('$2'),'$3'),'L')");

## [[text -> target ]]
Markup('[[->',
  '>[[|',"/\\[\\[([^\\]]+?)\\s*-+&gt;\\s*(.*?)\\]\\]($SuffixPattern)/e",
  "Keep(MakeLink(\$pagename,PSS('$2'),PSS('$1'),'$3'),'L')");

## [[#anchor]]
Markup('[[#','<[[','/\\[\\[#([A-Za-z][-.:\\w]*)\\]\\]/e',
  "Keep(\"<a name='$1' id='$1'></a>\",'L')");

## bare urllinks 
Markup('urllink','>[[',
  "/\\b(\\L)[^\\s$UrlExcludeChars]*[^\\s.,?!$UrlExcludeChars]/e",
  "Keep(MakeLink(\$pagename,'$0','$0'),'L')");

## mailto: links 
Markup('mailto','<urllink','/\\bmailto:(\\S+)/e',
  "Keep(MakeLink(\$pagename,'$0','$1'),'L')");

## inline images
Markup('img','<urllink',
  "/\\b(\\L)([^\\s$UrlExcludeChars]+$ImgExtPattern)(\"([^\"]*)\")?/e",
  "Keep(\$GLOBALS['LinkFunctions']['$1'](\$pagename,'$1','$2','$4','$1$2',
    \$GLOBALS['ImgTagFmt']),'L')");

## bare wikilinks
Markup('wikilink','>urllink',"/\\b($GroupPattern([\\/.]))?($WikiWordPattern)/e",
  "Keep(MakeLink(\$pagename,'$0'),'L')");

#### Block markups ####
## process any <:...> markup
Markup('^<:','<inline','/^(<:([^>]+)>)?/e',"Block('$2')");

## bullet lists
Markup('^*','block','/^(\\*+)/','<:ul,$1>');

## numbered lists
Markup('^#','block','/^(#+)/','<:ol,$1>');

## indented text
Markup('^->','block','/^(-+)&gt;/','<:indent,$1>');

## definition lists
Markup('^::','block','/^(:+)([^:]+):/','<:dl,$1><dt>$2</dt><dd>');

## preformatted text
Markup('^ ','block','/^(\\s)/','<:pre,1>');

## blank lines
Markup('blank','<^ ','/^\\s*$/','<:vspace>');

## tables
Markup('^||||','block','/^\\|\\|.*\\|\\|.*$/e',"FormatTableRow(PSS('$0'))");
Markup('^||','>^||||','/^\\|\\|(.*)$/e',
  "PZZ(\$GLOBALS['BlockMarkups']['table'][0] = PSS('<table $1>'))");

## headers
Markup('^!','block','/^(!{1,6})(.*)$/e',
  "'<:block><h'.strlen('$1').PSS('>$2</h').strlen('$1').'>'");

## horiz rule
Markup('^----','>^->','/^----+/','<:block><hr />');

#### inline markups ####
## ''emphasis''
Markup("''",'inline',"/''(.*?)''/",'<em>$1</em>');

## '''strong'''
Markup("'''","<''","/'''(.*?)'''/",'<strong>$1</strong>');

## '''''strong emphasis'''''
Markup("'''''","<'''","/'''''(.*?)'''''/",'<strong><em>$1</em></strong>');

## @@code@@
Markup('@@','inline','/@@(.*?)@@/','<code>$1</code>');

## [+big+], [-small-]
Markup('[+','inline','/\\[(([-+])+)(.*?)\\1\\]/e',
  "'<span style=\'font-size:'.(round(pow(1.2,$2strlen('$1'))*100,0)).'%\'>'.
    PSS('$3</span>')");

## [[<<]] (break)
Markup('[[<<]]','inline','/\\[\\[&lt;&lt;\\]\\]/',"<br clear='all' />");

#### special stuff ####
## [:markup:] for displaying markup examples
Markup('markup','<[=',"/\n\\[:markup:\\]\\s*\\[=(.*?)=\\]/se",
  "'\n'.Keep('<div class=\"markup\" <pre>'.wordwrap(PSS('$1'),60).
    '</pre>').PSS('\n$1\n<:block,0></div>\n')");
$HTMLStylesFmt[] = "
  div.markup { border:2px dotted #ccf; 
    margin-left:30px; margin-right:30px; 
    padding-left:10px; padding-right:10px; }
  div.markup pre { border-bottom:1px solid #ccf; 
    padding-top:10px; padding-bottom:10px; }
  ";

?>
