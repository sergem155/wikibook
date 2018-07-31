<?php

class SuperPageTOC {

	private static $mLevel;
	private static $mBoolFlag;
	private static $mNamespace;
	private static $mCurrentLink;
	private static $mBoolTopicFound;

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
			$text = "<languages/>\n".$text; # show language bar for pages subject to translation
			// add /prevnext/ ? only from subpages
			$doc = $title->getFullText();
			$separator_pos = strpos($doc,'/');
			if($separator_pos > 1){ // calling from a subpage
				$text .= "/prevnext/";
			}
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
			self::$mNamespace = $parent->getSubjectNsText();
			$article = new Article($parent);
			$superTocText = ContentHandler::getContentText( $article->getPage()->getContent() );
			// process links and indentation here
			// memorize TOC
			$prev = false;
			$next = false;
			$newToc = self::generateSuperPageToc($superTocText, $tocText, $title->getText(), $prev, $next);
			$text = str_replace($tocText,$newToc, $text);
			// generate Previous | Next links at the bottom of the page
			$prevnext = "";
			if ($prev){
				$prevnext .= '<a href="'.$prev.'">&lt; Previous</a>';
			}
			if($prev and $next)
				$prevnext .= ' | ';
			if ($next){
				$prevnext .= '<a href="'.$next.'">Next &gt;</a>';
			}
			$prevnext = "<center>".$prevnext."</center>";
			$text = str_replace("/prevnext/",$prevnext, $text);
		}
		return true;
	}

	private static function generateSuperPageToc($superPageText, $pageToc, $pageTitleText, &$prev, &$next){
		$output = "";
		$level = 1;
		$sectionLevel = 1;
		self::$mBoolTopicFound = false;
		self::$mCurrentLink = false;
		$superPageTocSeparator = "`-`-`-`-`-`-`SuperPageTOC-Separator`-`-`-`-`-`-`";
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $superPageText) as $line){
			// heading
			if(preg_match("/=(.+?)=/", $line, $matches)){
				$output.='<div class="book-title">'.$matches[1].'</div>';
				continue;
			}
			// find this title in super page toc
			if(preg_match("/\[\[\s*".preg_quote($pageTitleText,'/')."\s*\|?\s*[^\]]*\s*\]\]/i",$line)==1){
				$output.=$superPageTocSeparator;
				if(self::$mCurrentLink){
					$prev = self::$mCurrentLink;
				}
				self::$mBoolTopicFound = true;
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
			self::$mBoolFlag = false;
			$line = preg_replace_callback("/\[\[\s*([^|]+)\s*\|?\s*([^\]]*)\]\]/","self::replaceLinks",$line);
			if(!self::$mBoolFlag) // not a link line
				$line = '<span class="toctext">'.$line.'</span>';
			else
				if(self::$mBoolTopicFound and !$next){
					$next = self::$mCurrentLink;
				}
			$output.='<li class="toclevel-'.$level.' tocsection-'.$sectionLevel.'">'.$line."</li>";
			$sectionLevel++;
		} 
		// assemble 2 tocs
		$halves = explode($superPageTocSeparator,$output);
		
		$index1 = stripos($pageToc,'<ul>')+4;
		$index2 = strripos($pageToc,'</ul>');

		return substr($pageToc,0,$index1).$halves[0].substr($pageToc,$index1,$index2-$index1).$halves[1].substr($pageToc,$index2);
	}

	private static function replaceLinks($matches){
		self::$mBoolFlag = true;
		$url = Title::newFromText(self::$mNamespace.':'.$matches[1])->getFullURL();
		self::$mCurrentLink = $url;
		return '<a href="'.$url.'"><span class="toctext">'.$matches[2].'</span></a>';
	}
}
?>
