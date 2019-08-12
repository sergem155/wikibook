<?php

/* Copyright 2018 Sergey Menshikov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. */

class NamespaceLocalLinks {
	public static function onParserBeforeStrip( &$parser, &$text, &$strip_state ) {
		global $wgExtraNamespaces, $wgContLang;
		$title = $parser->getTitle();
		// only do this for pages, and only for those in custom namespaces
		if(empty($title) || !array_key_exists($title->getNamespace(),$wgExtraNamespaces)) {
			return true;
		}
		// check if page is translated, come up with lang suffix
		$langSuffix = false;
		$pageLang = $title->getPageLanguage()->getCode();
		if($pageLang != $wgLanguageCode){ // page lang != sitewide lang, need suffixes for all links
			$langSuffix = "/".$pageLang;
		}
		// for all links without a colon in URL part, do replacement
		if( preg_match_all(
						"/\[\[([A-Za-z0-9,.\/_ \(\)-]+)(\#[A-Za-z0-9 ._-]*)?([|](.*?))?\]\]/",
						$text,
						$matches,
						PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				// see if language variant exists
				$linkDoc = trim($title->getSubjectNsText().":".$match[1]);
				if($langSuffix){
					$linkLangTitle = Title::newFromText($linkDoc.$langSuffix);	
					if($linkLangTitle->exists())
						$linkDoc .= $langSuffix;
				}
				// replace link
				$text =	str_replace($match[0], "[[".$linkDoc.$match[2].$match[3]."]]", $text );
			}		
			return true;	
		}
	}

	public static function onInitializeArticleMaybeRedirect( $title, $request, &$ignoreRedirect, &$target, $article ) {
		$text = ContentHandler::getContentText( $article->getPage()->getContent() );
		if(preg_match("/^#REDIRECT\s+\[\[\s*(.*)\s*\]\]/",$text,$matches)){
			$link = $matches[1];
			if(strstr($link,":")) return; // main namespace or explicit namespace specification
			// check if page is translated, come up with lang suffix
			$langSuffix = false;
			$pageLang = $title->getPageLanguage()->getCode();
			if($pageLang != $wgLanguageCode){ // page lang != sitewide lang, need suffixes for all links
				$langSuffix = "/".$pageLang;
			}
			// get namespace-local title 
			$linkDoc = trim($title->getSubjectNsText().":".$link);
			if($langSuffix){
				$linkLangTitle = Title::newFromText($linkDoc.$langSuffix);
				if($linkLangTitle->exists()){
					$target = $linkLangTitle;
					return;
				}
			}
			$target = Title::newFromText($linkDoc);
		}
	}

	public static function onHtmlPageLinkRendererBegin($linkRenderer, $target, &$text, &$extraAttribs, &$query, &$ret ) {
		// TODO: "unbreak" namespace-local links
	}


}
?>
