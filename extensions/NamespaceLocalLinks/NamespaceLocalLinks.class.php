<?php

class NamespaceLocalLinks {
	public static function onParserBeforeStrip( &$parser, &$text, &$strip_state ) {
		global $wgExtraNamespaces;
		$title = $parser->getTitle();
		// only do this for pages, and only for those in custom namespaces
		if(empty($title) || !array_key_exists($title->getNamespace(),$wgExtraNamespaces)) {
			return true;
		}
		if( preg_match_all( // all links without a colon in URL part
						"/\[\[([A-Za-z0-9,.\/_ -]+)(\#[A-Za-z0-9 ._-]*)?([|]([A-Za-z0-9,:.'_?!@\/\"()#$ -{}]*))?\]\]/",
						$text,
						$matches,
						PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$text =	str_replace(
									$match[0],
									"[[".$title->getSubjectNsText().":".$match[1].$match[2].$match[3]."]]",
									$text );
			}		
			return true;	
		}
	}
}
?>
