<?php

/* Copyright 2018 Sergey Menshikov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. */

class ListArticleNamespaces {

	// register tag
	public static function onParserSetup( Parser $parser ) {
		$parser->setHook( 'list-article-namespaces', 'ListArticleNamespaces::renderTag' );
	}

	// render tag
	public static function renderTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgExtraNamespaces, $wgUser, $wgWhitelistReadRegexp;
		$loggedIn = $wgUser->isSafeToLoad() && $wgUser->isLoggedIn();
		$title = $parser->getTitle();
		$titleText = $title->getText(); // without namespace
		$nsText = $title->getNsText();
		$output = "<div class=\"list-article-namespaces\">";
		foreach ($wgExtraNamespaces as $index => $namespaceName){
			if($index&1) continue; // skip discussion namespaces  
			$possibleTitle = Title::newFromText($namespaceName.':'.$titleText);	
			if($possibleTitle->exists()){
				$url = $possibleTitle->getFullUrl();
				// do not show non-whitelisted URLs
				if(!$loggedIn){ 
					$fullText = $possibleTitle->getFullText();
					$whitelisted = false;
					foreach ( $wgWhitelistReadRegexp as $listItem ) {
						if ( preg_match( $listItem, $fullText ) ) {
							$whitelisted = true;
							break;
						}
					}
					if(!$whitelisted) continue;
				}
				if($namespaceName == $nsText)
					$output .= "&bull;&nbsp;<span class=\"list-article-namespaces-item selected\">$namespaceName</span> ";
				else
					$output .= "&bull;&nbsp;<span class=\"list-article-namespaces-item\"><a href=\"$url\">$namespaceName</a></span> ";
			}
		}
		return $output."</div>";
	}
}





?>
