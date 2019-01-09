<?php

/* Copyright 2018 Sergey Menshikov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. */

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
		$str = preg_replace("/[^a-zA-Z0-9-._~:@!$&'\(\)*+,;=?\/]/","_",$fragment);
		return strtolower($str);
	}

	private function sanitizeLinks($match){
		return("[[#topic_".self::sanitizeFragment(trim($match[1])).$match[2]."]]");
	}

	private function exportWikimarkup(Article $article, &$docTitleText, &$lastTimestamp){
		$title = $article->getTitle();
		$doc = $title->getFullText();
		$lang = $title->getPageLanguage()->getCode();
		$separator_pos = strpos($doc,'/');
		$lastTimestamp = 0;
		if($separator_pos < 1) { // calling not from a subpage, return the article's wikitext
			$content = ContentHandler::getContentText( $article->getPage()->getContent() );
			$content = '<span id="topic_'.self::sanitizeFragment(trim($title->getText())).'"></span>'."\r\n".$content;
			$content = "<!--ARTICLE:".$doc."-->\r\n".$content;
			$lastTimestamp = wfTimestamp( TS_UNIX, $title->getTouched());
			return $content;
		}
		# find all doc names in super page toc (all links)
		$parent = Title::newFromText(substr($doc, 0, $separator_pos));
		// is there a localized version of parent?
		if($lang != $parent->getPageLanguage()->getCode()){
			$parentLangTitle = Title::newFromText($parent_doc."/".$lang);	
			if($parentLangTitle->exists())
				$parent = $parentLangTitle;
		}
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
				$rep_title = Title::newFromText($title->getSubjectNsText().":".$match[1]);
				if($lang != $rep_title->getPageLanguage()->getCode()){
					$repLangTitle = Title::newFromText($rep_title->getFullText()."/".$title->getPageLanguage()->getCode());	
					if($repLangTitle->exists())
						$rep_title = $repLangTitle;
				}
				$superPageText =	str_replace(
									$match[0],
									"[[".$rep_title->getFullText().$match[2].$match[3]."]]", 
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
			// TODO warining - this replaces all links, including those outside the book
			$doc_content = preg_replace_callback("/\[\[([A-Za-z0-9,.\/_ \(\)-]+)([|](.*?))?\]\]/",
'self::sanitizeLinks',$doc_content);
			// TODO also need to replace all local hash links
			// TODO replace remote in-book hash links, to avoid double hash - just strip the file part
			$result .= '<span id="topic_'.self::sanitizeFragment(trim($doc_title->getText())).'"></span>'."\r\n"."<!--ARTICLE:".$doc."-->\r\n".$doc_content."\r\n\r\n";
		}
		return $result;
	}

	private function exportHtml(Article $article){
		global $wgOut, $wgUser, $wgTitle, $wgParser, $wgRequest;
		global $wgServer, $wgArticlePath, $wgScriptPath, $wgUploadPath, $wgUploadDirectory, $wgScript, $wgStylePath;
		$title = $article->getTitle();

		$opt = ParserOptions::newFromUser($wgUser);
		$opt->setIsPrintable(true);
		$opt->setEditSection(false);
		$wikimarkup = self::exportWikimarkup($article, $docTitleText, $lastTimestamp);
		$out = $wgParser->parse($wikimarkup, $title, $opt, true, true);
		$text = $out->getText();
		$base = '<base href="'.($title->getFullUrl()).'?action=html-localimages-export">';
		return self::htmlPage(self::coverPageHtml($docTitleText).$text, $base);
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

	private function htmlPage($text, $additionalHeaderHtml=""){

		$html = <<<EOT
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:og="http://ogp.me/ns#" xmlns:fb="http://ogp.me/ns/fb#" charset="utf-8">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title></title>
<style>
@import url('https://fonts.googleapis.com/css?family=Noto+Sans');
@import url('https://fonts.googleapis.com/earlyaccess/notosansjp.css');
body {
	font-family: "Noto Sans", "Noto Sans CJK JP", sans-serif;
	font-size: 12pt;
}
img, blockquote  {page-break-inside:avoid;}
tr {page-break-inside:avoid;}
p {page-break-inside: avoid;}
</style>
$additionalHeaderHtml
</head>
<body>
EOT;
		$html .= $text;
		$html .= "</body></html>";
		return $html;
	}

	private function writeFile($data, $suffix = '.html'){
		$tempName = tempnam(sys_get_temp_dir(), 'wikibook');
		unlink($tempName);
		$file = fopen($tempName.$suffix, 'w+');
        $fileName = stream_get_meta_data($file)['uri'];
		fwrite($file, $data);
		fclose($file);
		return $fileName;
	}

	static public function serveFileAs($fileName, $filePath) {
		if (file_exists($filePath)) {
			header("Content-Type: application/pdf");
			header("Content-Disposition: attachment; filename=\"$fileName\"");
			readfile($filePath);
			die();
		} else {
			return false;
		}
	}

	private function exportPdf($article){
		global $wgParser, $wgUser, $wgUploadDirectory;
		$title = $article->getTitle();
		$docVersionText = $title->getSubjectNsText();
		$base = '<base href="'.($title->getFullUrl()).'?action=html-localimages-export">';

		$wikimarkup = self::exportWikimarkup($article, $docTitleText, $lastTimestamp);

		$opt = ParserOptions::newFromUser($wgUser);
		$opt->setIsPrintable(true);
		$opt->setEditSection(false);
		$out = $wgParser->parse($wikimarkup."\n__NOTOC__\n", $title, $opt, true, true);

		$body = self::htmlPage($out->getText(),$base);
		$cover = self::htmlPage(self::coverPageHTML($docVersionText.' '.$docTitleText),$base);

		$coverFileName = self::writeFile($cover);
		$bodyFileName = self::writeFile($body);
		// make converter happy
		rename($coverFileName, $coverFileName.".html");
		$coverFileName .= ".html";
		rename($bodyFileName, $bodyFileName.".html");
		$bodyFileName .= ".html";

		$pdfFileName = "wikibook-" . $docVersionText . "-" . ($title->getRootText()) .".pdf";
		$pdfFilePath = "$wgUploadDirectory/".$pdfFileName;

		//$wgOut->disable();
		$cmd = "/opt/wkhtmltox/bin/wkhtmltopdf -s Letter --outline --margin-bottom 0.5in --margin-top 0.5in --margin-left 0.5in --margin-right 0.5in --load-error-handling ignore --load-media-error-handling ignore "
			." cover $coverFileName toc $bodyFileName $pdfFilePath";
		$output = array();
		$returnVar = 1;
		exec($cmd, $output, $returnVar);
		unlink($coverFileName);
		unlink($bodyFileName);

		if($returnVar != 0) { // 0 is success
			error_log("INFO [PonyDocsPdfBook::onUnknownAction] " . php_uname('n') . ": Failed to run wkhtmltopdf (" . $returnVar . ") Output is as follows: " . implode("-", $output));
			print("Failed to create PDF.  Our team is looking into it.");
		}

		self::serveFileAs($pdfFileName , $pdfFilePath);
	}
}
?>
