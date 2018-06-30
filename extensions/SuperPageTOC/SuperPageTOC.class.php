<?php

class SuperPageTOC {

	private static $mLevel;

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

	public static function onParserAfterParse( &$parser, &$text, &$stripState ) {
		$tocText = $parser->mOutput->getTOCHTML();
		// substitute with a new TOC
		if(strlen($tocText) > 0){
			$title = $parser->getTitle();
			if(empty($title))
				return true;
			$doc = $title->getFullText();
			$separator_pos = strpos($doc,'/');
			if($separator_pos < 1) // calling not from a subpage
				return;
			$parent = Title::newFromText(substr($doc, 0, $separator_pos));
			$article = new Article($parent);
			$superTocText = ContentHandler::getContentText( $article->getPage()->getContent() );
			// process links and indentation here
			// memorize TOC
			$newToc = self::generateSuperPageToc($superTocText, $tocText, $doc);
			$text = str_replace($tocText,$newToc, $text);
		}
		return true;
	}

	private static function generateSuperPageToc($superPageText, $pageToc, $pageTitleText){
		$output = "";
		$level = 1;
		$superPageTocSeparator = "`-`-`-`-`-`-`SuperPageTOC-Separator`-`-`-`-`-`-`";
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $superPageText) as $line){
			// find this title in super page toc
			if(preg_match("/\[\[\s*".preg_quote($pageTitleText,'/')."\s*\|?\s*[^\]]*\s*\]\]/i",$line)==1){
				$output.=$superPageTocSeparator;
				continue;
			}
			// add <ul> or </ul>
			$newLevel = 1;
			$i = 0;
			while($line[$i++]=='*')
				$newLevel += 1;
			if($newLevel>1)
				$line = substr($line, $newLevel-1);
			if($newLevel > $level){
				for($i=$level; $i<$newLevel; $i++)
					$output .= "<ul>";
			}else{
				for($i=$level; $i>$newLevel; $i--)
					$output .= "</ul>";
			}
			$level = $newLevel;
			self::$mLevel = $level;
			// add links
			$line = preg_replace_callback("/\[\[\s*([^|]+)\s*\|?\s*([^\]]*)\]\]/","self::replaceLinks",$line);
			$output.='<li class="toclevel-'.$level.'">'.$line."</li>";
		} 
		$halves = explode($superPageTocSeparator,$output);
		return $halves[0].$pageToc.$halves[1];
		// process bullets, one on each line
		
		// create links
		$linkp = '/\[\[(.+?)\]\]/';
		$link1p = '/^[^|]+$/';
		$link2p = '/^([^|]+)(|.*)$/';
		// split
		// marry tocs

		return $output;
	}

	private static function replaceLinks($matches){
		$url = Title::newFromText($matches[1])->getFullURL();
		return '<a href="'.$url.'"><span class="toctext">'.$matches[2].'</span></a>';
	}
}
?>
