<?php

/* Copyright 2018 Sergey Menshikov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. */

class WikibookBreadcrumbs {

	// register tag
	public static function onParserSetup( Parser $parser ) {
		$parser->setHook( 'wikibook-breadcrumbs', 'WikibookBreadcrumbs::renderTag' );
	}

	// render tag
	public static function renderTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		$title = $parser->getTitle();
		$linkRenderer = $parser->getLinkRenderer();
		$tuple = self::getHeadingTextAndFirstLink($title);
		$parent = self::findSuperpage($title);
		$output = "</ol>";
		$output = "<li>".$tuple[0]."</li>".$output; 
		while($parent){
			$tuple = self::getHeadingTextAndFirstLink($parent);
			if($tuple){
				$link_title =  Title::newFromText($parent->getNsText().':'.$tuple[1]);
				if($link_title){
					$link = '<a href="'.$link_title->getLinkURL().'">'.$tuple[0].'</a>';
					$output = "<li>$link</li>".$output;
				}else
					$output = "<li>Broken TOC:".$parent->getText()."</li>".$output;
			}else
				$output = "<li>a book</li>".$output;
			$parent = self::findSuperpage($parent);
		}
		return '<ol class="breadcrumb wikibook-breadcrumbs">'.$output;
	}

	public static function findSuperpage($title){
		if(!$title->isSubpage())
			return null;
		// find a superpage, if exists
		$doc = $title->getFullText();
		//echo "SUPERPAGE FOR: ".$doc;
		$page_lang_code = $title->getPageLanguage()->getCode();
		// strip lang superpage suffix
		$langsuffix="";
		$langsuffix_len = strlen($page_lang_code)+1;
		if(substr($doc, -$langsuffix_len) === ('/'.$page_lang_code)){
			$doc = substr($doc,0,-$langsuffix_len);
			$langsuffix = '/'.$page_lang_code;
		}
		// find a parent
		$separator_pos = strrpos($doc,'/');
		if($separator_pos < 1) // calling not from a subpage
			return null;
		$superpage_doc = substr($doc, 0, $separator_pos);
		// see if there is a language-specific version of the superpage (only if page had the suffix)
		if($langsuffix){
			$link_lang_title = Title::newFromText($superpage_doc.$langsuffix);	
			if($link_lang_title->exists())
				$superpage_doc .= $langsuffix;
		}
		// get the superpage
		$superpage = Title::newFromText($superpage_doc);
		return $superpage;
	}

	public static function getHeadingTextAndFirstLink($title){
		$article = new Article($title);
		$heading = "";
		$url = "";
		if($article){
			$text = ContentHandler::getContentText( $article->getPage()->getContent() );
			if($text){
				if(preg_match("/=+([^=]+)=+/", $text, $matches)){
					$heading = $matches[1];
				}
				if(preg_match("/\[\[([^\|]+)\|?.*\]\]/",$text,$matches)==1){
					$url = $matches[1];
				}
				return array($heading,$url);
			}
		}
		return null;
	}
}





?>
