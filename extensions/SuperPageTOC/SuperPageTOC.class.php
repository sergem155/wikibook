<?php

/* Copyright 2018 Sergey Menshikov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. */

class SuperPageTOC {

	private static $mLevel;
	private static $mHeading; // last heading found - toplevel document's first h1
	private static $mBoolFlag;
	private static $mNamespace;
	private static $mCurrentLink;
	private static $mBoolTopicFound;
	private static $mContLangCode;
	private static $mPageLangCode;

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
			$text = "<languages/><list-article-namespaces/>\n".$text; # show language bar for pages subject to translation
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
		global $wgContLang;
		$tocText = $parser->mOutput->getTOCHTML();
		// if there is a TOC and we are not printing - substitute with a new TOC
		if(strlen($tocText) > 0 and !$parser->getOptions()->getIsPrintable()){
			$title = $parser->getTitle();
			if(empty($title))
				return true;
			// memorize lang codes
			self::$mNamespace = $title->getSubjectNsText();
			self::$mPageLangCode = $title->getPageLanguage()->getCode();
			self::$mContLangCode = $wgContLang->getCode();
			// build the TOC list
			self::$mHeading = self::findHeadingTextFromTitle($title);
			$tocList = self::generateSuperPageTocList($title, self::$mHeading, [['level'=>1,'title'=>1,'link'=>1]]);
			//
			$level=1;
			$section=1;
			$newTocText = "".'<li class="toc-heading">'.self::$mHeading."</li>";
			$prev = false;
			$next = false;
			$last = false;
			$childFound = false;
			$openli = false;
			$index1 = stripos($tocText,'<ul>')+4;
			$index2 = strripos($tocText,'</ul>');
			foreach($tocList as $item){
				// not a link - text
				if($item['link']==null){
					// TODO close li before static if changing levels - save levels with static
					$newTocText .= '<span class="toclevel-'.$level.' toctext tocstatic">'.$item['title'].'</span>';
					continue;
				}
				// if matches link = 1, insert this page's toc HTML
				if($item['link']==1){
					$tocSnippet = substr($tocText,$index1,$index2-$index1);
					if(preg_match('/<li\s.+?(<ul.+)<\/li>/s',$tocSnippet,$matches)==1){ // remove top heading
						$tocSnippet=$matches[1];
					}else{
						$tocSnippet="";
					}
					$tocSnippet = preg_replace('/toclevel-1 tocsection-1/', 'toclevel-'.$level.' tocsection-'.$section.' toc-open',$tocSnippet,1);
					$newTocText .= $tocSnippet;
					$prev = $last;
					$childFound = true;
					$section += 1;
					continue;
				}
				// if prev link is set, then this one is next
				if($childFound and !$next)
					$next = $item;
				// adjust levels
				if($level < $item['level']){
					$newTocText .= str_repeat('<ul>', $item['level']-$level);
					$level = $item['level'];
				}elseif($level > $item['level']){
					$newTocText .= str_repeat('</ul></li>', $level-$item['level']);
					$level = $item['level'];
					$openli = false;
				}elseif($openli){
					$newTocText .= '</li>';
				}
				// render lines from toc list
				$url = self::renderLink($item['link']);
				$is_open="";
				if(array_key_exists('is_open',$item)) $is_open = "toc-open";
				$newTocText .= '<li class="toclevel-'.$level.' tocsection-'.$section.' '.$is_open.'">'
					.'<a href="'.$url.'"><span class="toctext">'.$item['title'].'</span></a>';
				$openli = true;
				$section += 1;
				// memorize last item so we could set prev link
				$last=$item;
			}
			if($openli){
				$newTocText .= '</li>';
			}
			// add beginning and end of the original HTML toc and replace TOC in the page 
			$newToc = substr($tocText,0,$index1).$newTocText.substr($tocText,$index2);
			$text = str_replace($tocText,$newToc, $text);

			// generate Previous | Next links at the bottom of the page
			$prevnext = "";
			if ($prev){
				$prevnext .= '<a href="'.self::renderLink($prev['link']).'">&lt; '.(wfMessage( 'wikibook-prev' )->inLanguage(self::$mPageLangCode)->text()).'</a>';
			}
			if($prev and $next)
				$prevnext .= ' | ';
			if ($next){
				$prevnext .= '<a href="'.self::renderLink($next['link']).'">'.(wfMessage( 'wikibook-next' )->inLanguage(self::$mPageLangCode)->text()).' &gt;</a>';
			}
			$prevnext = "<center>".$prevnext."</center>";
			$text = str_replace("/prevnext/",$prevnext, $text);
		}
		return true;
	}

	private static function renderLink($link)
	{
		// prepare link 
		$linkDoc = trim(self::$mNamespace.':'.$link);
		// see if there is a language-specific version of the link doc
		if(self::$mPageLangCode != self::$mContLangCode){
			$linkLangTitle = Title::newFromText($linkDoc."/".self::$mPageLangCode);	
			if($linkLangTitle->exists())
				$linkDoc .= "/".self::$mPageLangCode;
		}
		// render URL
		$doc = Title::newFromText($linkDoc);
		if($doc){
			$url = $doc->getFullURL();
			return $url;			
		}
		return "bad+link";
	}

	// looks up for a parent title and returns TOC array; recurses if there are ancestors on top of the parent
	private static function generateSuperPageTocList($childTitle, $heading, $superTocArray){
		$topicFound = false;

		// find a superpage, if exists
		$parent = self::findSuperpage($childTitle);
		if(!$parent){
			return $superTocArray; // no parents, return whatever list was given as a parameter
		}

		// get parent body
		$article = new Article($parent);
		$superPageText = ContentHandler::getContentText( $article->getPage()->getContent() );
		self::$mHeading = self::findHeadingTextFromContent($superPageText);

		// remove lang suffix from pagetitle, so it would match toc entry
		if(self::$mPageLangCode != self::$mContLangCode){
			$pageTitleText = substr($pageTitleText, 0, strlen($pageTitleText)-1-strlen(self::$mPageLangCode));
		}

		// for each line in toc:, look for bullets with links; bullets can be multi-level
		$results = [];
		foreach(explode(PHP_EOL, $superPageText) as $line){ 
			// link, with a bullet 
			if(preg_match("/^(\*+)\s*\[\[\s*([^|]+)\s*(?:\|\s*([^\]]*))?\]\]/",$line,$matches)==1){
				$asterisks = $matches[1];
				$level = strlen($asterisks);
				$url = trim($matches[2]);
				if(self::$mPageLangCode != self::$mContLangCode){
					$url .= "/".self::$mPageLangCode;
				}
				if(count($matches)>3)
					$title = $matches[3];
				else
					$title = $url;
				// toc item matching with child
				$parentTitleText = $childTitle->getDBKey();
				$len = strlen($parentTitleText); 
				//echo $parentTitleText.'--'.$url."\n";
				//echo substr($url,$len,1)."\n";
				if(strtolower($url) == strtolower($parentTitleText) ||
				( (strtolower(substr($url,0,$len)) == strtolower($parentTitleText)) && 
					(substr($url,$len,1)=='/' || substr($url,$len,1)=='#') ) ){
					// incorporate param array here, add current level to each item, set bool flag
					array_push($results,['level'=>$level,'title'=>$title,'link'=>$url,'is_open'=>true]);
					foreach($superTocArray as $item){
						$item['level']+=$level;
						array_push($results, $item);
					}
					$topicFound = true;
				}
				else
					array_push($results,['level'=>$level,'title'=>$title,'link'=>$url]);
			// heading and markup - ignore
			}elseif(preg_match("/(=.+?=|<.+>)/", $line)){
			// text
			}elseif(strlen(trim($line))>0){
				array_push($results,['level'=>0,'title'=>$line,'link'=>null]);
			}
		}
		if(!$topicFound){ // paste child array to the and of the list
			foreach($superTocArray as $item){
				array_push($results, $item);
			}
		}
		// recurse parent ancestor tocs, if any 
		return self::generateSuperPageTocList($parent,$heading,$results);
	}

	public static function findSuperpage($title){ // shared with wikibook-breadcrumbs extension
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

	public static function findHeadingTextFromTitle($title){
		$article = new Article($title);
		$heading = "";
		$url = "";
		if($article){
			$text = ContentHandler::getContentText( $article->getPage()->getContent() );
			if($text){
				return self::findHeadingTextFromContent($text);				
			}
		}
		return null;
	}

	public static function findHeadingTextFromContent($text){
		if(preg_match("/=+([^=]+)=+/", $text, $matches)){
			return $matches[1];
		}
		return null;
	}

}
?>
