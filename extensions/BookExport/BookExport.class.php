<?php

class BookExport {

	public static function onUnknownAction( $action, Article $article ) {
		// We don't do any processing unless it's pdfbook
		if ($action != 'pdf-export' && $action != 'html-localimages-export'
				 					&& $action != 'html-remoteimages-export'
				 					&& $action != 'wikimarkup-export' ) {
			return true;
		}
		if ($action == 'wikimarkup-export'){
			echo self::exportWikimarkup($article);
			die();
		}
	}

	private function sanitizeFragment($fragment){
		return preg_replace("/[^a-zA-Z0-9-._~:@!$&'\(\)*+,;=?\/]/","_",$fragment);
	}

	private function sanitizeLinks($match){
		return("[[#topic_".self::sanitizeFragment(trim($match[1])).$match[2]."]]");
	}

	private function exportWikimarkup(Article $article){
		$title = $article->getTitle();
		$doc = $title->getFullText();
		$separator_pos = strpos($doc,'/');
		if($separator_pos < 1) { // calling not from a subpage, return the article's wikitext
			$content = ContentHandler::getContentText( $article->getPage()->getContent() );
			$content = '<span id="topic_'.self::sanitizeFragment(trim($title->getText())).'"></span>'."\r\n".$content;
			$content = "<!--ARTICLE:".$doc."-->\r\n".$content;
			return $content;
		}
		# find all doc names in super page toc (all links)
		$parent = Title::newFromText(substr($doc, 0, $separator_pos));
		$article = new Article($parent);
		$superPageText = ContentHandler::getContentText( $article->getPage()->getContent() );
		# make all TOC links namespace-local
		if( preg_match_all( // all links without a colon in URL part
						"/\[\[([A-Za-z0-9,.\/_ \(\)-]+)(\#[A-Za-z0-9 ._-]*)?([|]([A-Za-z0-9,:.'_?!@\/\"()#$ -{}]*))?\]\]/",
						$superPageText,
						$matches,
						PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$superPageText =	str_replace(
									$match[0],
									"[[".$title->getSubjectNsText().":".$match[1].$match[2].$match[3]."]]",
									$superPageText );
			}		
		}
		# collect all docs pointed by links
		$docs = array();
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $superPageText) as $line){
			if(preg_match("/\[\[\s*([^|]+)\s*\|?\s*([^\]]*)\]\]/",$line, $matches)==1){
				$docs[] = $matches[1];
			}
		}
		# ??? H1 title of the whole thing?
		$result = "";
		foreach($docs as $doc){
			# ensure same namespace
			$doc_title = Title::newFromText($doc);
			$doc_article = new Article($doc_title);
			$doc_content = ContentHandler::getContentText( $doc_article->getPage()->getContent() );
			$doc_content = preg_replace_callback("/\[\[([A-Za-z0-9,.\/_ \(\)-]+)([|]([A-Za-z0-9,:.'_?!@\/\"\(\)#$ -{}]*))?\]\]/",
'self::sanitizeLinks',$doc_content);
			$result .= '<span id="topic_'.self::sanitizeFragment(trim($title->getText())).'"></span>'."\r\n"."<!--ARTICLE:".$doc."-->\r\n".$doc_content."\r\n\r\n";
		}
		return $result;
	}

}
?>
