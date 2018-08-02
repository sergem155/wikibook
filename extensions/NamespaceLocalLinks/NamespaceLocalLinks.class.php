<?php

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
		if($pageLang != $langSuffix){
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
}
?>
