<?php

class ListArticleNamespaces {

	// register tag
	public static function onParserSetup( Parser $parser ) {
		$parser->setHook( 'list-article-namespaces', 'ListArticleNamespaces::renderTag' );
	}

	// render tag
	public static function renderTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgExtraNamespaces;
		$title = $parser->getTitle();
		$titleText = $title->getText(); // without namespace
		$nsText = $title->getNsText();
		$output = "<div class=\"list-article-namespaces\">";
		foreach ($wgExtraNamespaces as $index => $namespaceName){
			if($index&1) continue; // skip discussion namespaces  
			$possibleTitle = Title::newFromText($namespaceName.':'.$titleText);	
			if($possibleTitle->exists()){
				$url = $possibleTitle->getFullUrl();
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
