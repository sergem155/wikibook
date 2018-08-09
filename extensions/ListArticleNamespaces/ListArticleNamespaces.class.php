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
		$output = "<div class=\"list-article-namespaces\">";
		foreach ($wgExtraNamespaces as $index => $namespaceName){
			if($index&1) continue; // skip discussion namespaces  
			$possibleTitle = Title::newFromText($namespaceName.':'.$titleText);	
			if($possibleTitle->exists()){
				$url = $possibleTitle->getFullUrl();
				$output .= "&bull;&nbsp;<a href=\"$url\">$namespaceName</a> ";
			}
		}
		return $output."</div>";
	}
}





?>
