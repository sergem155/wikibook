<?php

$wgSuperPageTOC_pageTOC = '';
$wgSuperPageTOC_newPageTOC = '';

class SuperPageTOC {
	public static function onParserBeforeStrip( &$parser, &$text, &$strip_state ) {
		global $wgRequest;
		$title = $parser->getTitle();
		// only do this for pages
		if(empty($title)) {
			return true;
		}
		$action = $wgRequest->getVal('action','view');
		if (($action == ('view' || 'print' || 'purge' || null)) && ($parser->getOptions()->getEnableLimitReport())) {
			static $hasRun = false;
			if ($hasRun) return true;
			$hasRun = true;
			// ensure TOC is always shown 
			$text = "__FORCETOC__\r\n".$text;
			return true;
		}
		return true;
	}

	public static function onParserSectionCreate( $parser, $section, &$sectionContent, $showEditLinks ) {
		global $wgSuperPageTOC_pageTOC, $wgSuperPageTOC_newPageTOC;
		if($section == 0) {
			$wgSuperPageTOC_pageTOC = $parser->mOutput->getTOCHTML();
			if(strlen($wgSuperPageTOC_pageTOC) < 1)
				return true;
			$title = $parser->getTitle();
			if(empty($title))
				return true;
			$doc = $title->getFullText();
			$separator_pos = strpos($doc,'/');
			if($separator_pos < 1)
				return;
			$parent = Title::newFromText(substr($doc, 0, $separator_pos));
			$article = new Article($parent);
			$toctext = ContentHandler::getContentText( $article->getPage()->getContent() );
			// process links and indentation here
			// memorize TOC
			$wgSuperPageTOC_newPageTOC = $wgSuperPageTOC_pageTOC.$toctext;
		}
	}

	public static function onParserAfterParse( &$parser, &$text, &$stripState ) {
		global $wgSuperPageTOC_pageTOC, $wgSuperPageTOC_newPageTOC;
		// substitute with a new TOC
		if(strlen($wgSuperPageTOC_pageTOC) > 0)
			$text = str_replace($wgSuperPageTOC_pageTOC,$wgSuperPageTOC_newPageTOC, $text);
		return true;
	}
}
?>
