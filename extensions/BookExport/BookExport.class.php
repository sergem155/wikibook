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
			echo self::exportWikimarkup($article, $docTitleText, $lastTimestamp);
			die();
		}
		if ($action == 'html-localimages-export'){
			echo self::exportHtml($article);
			die();
		}
		if ($action == 'pdf-export'){
			self::exportPdf($article);
			die();
		}
	}

	private function sanitizeFragment($fragment){
		return preg_replace("/[^a-zA-Z0-9-._~:@!$&'\(\)*+,;=?\/]/","_",$fragment);
	}

	private function sanitizeLinks($match){
		return("[[#topic_".self::sanitizeFragment(trim($match[1])).$match[2]."]]");
	}

	private function exportWikimarkup(Article $article, &$docTitleText, &$lastTimestamp){
		$title = $article->getTitle();
		$doc = $title->getFullText();
		$separator_pos = strpos($doc,'/');
		$lastTimestamp = 0;
		if($separator_pos < 1) { // calling not from a subpage, return the article's wikitext
			$content = ContentHandler::getContentText( $article->getPage()->getContent() );
			$content = '<span id="topic_'.self::sanitizeFragment(trim($title->getText())).'"></span>'."\r\n".$content;
			$content = "<!--ARTICLE:".$doc."-->\r\n".$content;
			return $content;
		}
		# find all doc names in super page toc (all links)
		$parent = Title::newFromText(substr($doc, 0, $separator_pos));
		// save last edit time
		$tmpTimestamp = wfTimestamp( TS_UNIX, $parent->getTouched());
		if($tmpTimestamp and $tmpTimestamp > $lastTimestamp)
			$lastTimestamp = $tmpTimestamp;
		//
		$article = new Article($parent);
		$superPageText = ContentHandler::getContentText( $article->getPage()->getContent() );
		// find title
		if(preg_match("/.*?=(.+?)=.*/s", $superPageText, $matches)){
			$docTitleText = $matches[1];
		}
		# make all TOC links namespace-local
		if( preg_match_all( // all links without a colon in URL part
						"/\[\[([A-Za-z0-9,.\/_ \(\)-]+)(\#[A-Za-z0-9 ._-]*)?([|](.*?))?\]\]/",
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
			// save last edit time
			$tmpTimestamp = wfTimestamp( TS_UNIX, $doc_title->getTouched());
			if($tmpTimestamp and $tmpTimestamp > $lastTimestamp)
				$lastTimestamp = $tmpTimestamp;
			//
			$doc_article = new Article($doc_title);
			$doc_content = ContentHandler::getContentText( $doc_article->getPage()->getContent() );
			$doc_content = preg_replace_callback("/\[\[([A-Za-z0-9,.\/_ \(\)-]+)([|](.*?))?\]\]/",
'self::sanitizeLinks',$doc_content);
			$result .= '<span id="topic_'.self::sanitizeFragment(trim($doc_title->getText())).'"></span>'."\r\n"."<!--ARTICLE:".$doc."-->\r\n".$doc_content."\r\n\r\n";
		}
		return $result;
	}

	private function exportHtml(Article $article){
		global $wgOut, $wgUser, $wgTitle, $wgParser, $wgRequest;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript, $wgStylePath;
		$title = $article->getTitle();

		// find parent, set base url
		$title_text = $title->getFullText();
		$separator_pos = strpos($title_text,'/');
		if($separator_pos > 1) // calling from a subpage
			$book_title = Title::newFromText(substr($title_text, 0, $separator_pos));
		else
			$book_title = $title;
		$base = $book_title->getFullUrl();
		$base .= "?action=html-localimages-export";
		// <base href="$base" target="_blank">

		$opt = ParserOptions::newFromUser($wgUser);
		$opt->setIsPrintable(true);
		$opt->setEditSection(false);
		$wikimarkup = self::exportWikimarkup($article, $docTitleText, $lastTimestamp);
		$out = $wgParser->parse($wikimarkup, $title, $opt, true, true);
		$text = $out->getText();
		return self::htmlPage(self::coverPageHtml($docTitleText).$text);
	}


	private function coverPageHTML($docTitleText)
	{
		global $wgLogo, $wgRightsText, $wgSitename;
		$titleText  .= '<table height="100%" width="100%"><tr><td valign="top" height="50%">';
		if($wgLogo)
			$titleText .= '<center><img src="' . $wgLogo .  '"></center>';
		$titleText .=
			 '<h1>' . $docTitleText . '</h1>';
		if($wgSitename)
			$titleText .= '<h2>' .$wgSitename. '</h2>';
		
		$titleText .= 'Generated: ' . date('n/d/Y g:i a', time())
			. '</td></tr><tr><td height="50%" width="100%" align="left" valign="bottom"><font size="2">'
			. str_replace('$1',$wgRightsText, wfMessage( 'copyright' )->text())
			. '</td></tr></table></body></html>';
		return $titleText;
	}

	private function htmlPage($text){

		$html = <<<EOT
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:og="http://ogp.me/ns#" xmlns:fb="http://ogp.me/ns/fb#" charset="utf-8">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title></title>
<style>
html,body {font-family: Arial, Helvetica, sans-serif; font-size: 12pt;}
img, blockquote  {page-break-inside:avoid;}
tr {page-break-inside:avoid;}
p {page-break-inside: avoid;}
</style>
</head>
<body>
EOT;
		$html .= $text;
		$html .= "</body></html>";
		return $html;
	}

	private function writeFile($data){
		$file = fopen(tempnam(sys_get_temp_dir(), 'wikibook'), 'w+');
        $fileName = stream_get_meta_data($file)['uri'];
		fwrite($file, $data);
		fclose($file);
	}

	static public function servePdf($fileName, $filePath) {
		if (file_exists($filePath)) {
			header("Content-Type: application/pdf");
			header("Content-Disposition: attachment; filename=\"$fileName.pdf\"");
			readfile($filePath);
			die();
		} else {
			return false;
		}
	}

	private function exportPdf($article){
		global $wgParser, $wgUser, $wgUploadDirectory;
		$title = $article->getTitle();

		$wikimarkup = self::exportWikimarkup($article, $docTitleText, $lastTimestamp);

		$opt = ParserOptions::newFromUser($wgUser);
		$opt->setIsPrintable(true);
		$opt->setEditSection(false);
		$out = $wgParser->parse($wikimarkup."\n__NOTOC__\n", $title, $opt, true, true);

		$body = self::htmlPage($out->getText());
		$cover = self::htmlPage(self::coverPageHTML($docVersionText.' '.$docTitleText));

		$coverFileName = self::writeFile($cover);
		$bodyFileName = self::writeFile($body);

		$docVersionText = $title->getNsText();

		$pdfFileName = "wikibook-" . $docVersionText . "-" . ($title->getText()) .".pdf";
		$pdfFilePath = "$wgUploadDirectory/".$pdfFileName;

		//$wgOut->disable();
		$cmd = "wkhtmltopdf -s Letter --outline --margin-bottom 0.5in --margin-top 0.5in --margin-left 0.5in --margin-right 0.5in "
			." cover $coverFileName toc $bodyFileName $pdfFilePath";
		$output = array();
		$returnVar = 1;
		exec($cmd, $output, $returnVar);
		if($returnVar != 0) { // 0 is success
			error_log("INFO [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": Failed to run wkhtmltopdf (" . $returnVar . ") Output is as follows: " . implode("-", $output));
			print("Failed to create PDF.  Our team is looking into it.");
			die();
		}
		//unlink($coverFileName);
		//unlink($bodyFileName);

		self::servePdf($pdfFileName , $pdfFileName);

		echo "<h1>aaa:".$lastTimestamp.'--'.$docVersionText.'--'.$docTitleText."</h1>";
	}
}
?>
