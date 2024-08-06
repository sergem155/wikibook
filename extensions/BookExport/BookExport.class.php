<?php

/* Copyright 2018-2021 Sergey Menshikov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. */

class BookExport extends Action {

	/**
	 * @param string $haystack
	 * @param string $needle
	 *
	 * @return bool
	 */
	private static function endsWith( string $haystack, string $needle ): bool {
		$length = strlen( $needle );
		if ( $length == 0 ) {
			return true;
		}

		return ( substr( $haystack, -$length ) === $needle );
	}

	/**
	 * for Mediawiki 1.29+
	 *
	 * @return string
	 */
	public function getName(): string {
		return 'pdf-export';
	}

	/**
	 * @return void
	 */
	public function show(): void {
		// $title = $this->page->getTitle();
		// $article = new Article( Title::newFromText( $title ) ) ;
		self::exportPdf( $this->getArticle() );
		die();
	}

	/**
	 * for older implementations, do onUnknownAction
	 *
	 * @param string $action
	 * @param Article $article
	 *
	 * @return true|void
	 */
	public static function onUnknownAction( string $action, Article $article ) {
		// We don't do any processing unless it's pdfbook
		if (
			$action != 'pdf-export' &&
			$action != 'html-localimages-export' &&
			$action != 'html-remoteimages-export' &&
			$action != 'wikimarkup-export'
		) {
			return true;
		}
		if ( $action == 'wikimarkup-export' ) {
			echo self::exportWikimarkup( $article, $docTitleText, $lastTimestamp );
			die();
		}
		if ( $action == 'html-localimages-export' ) {
			echo self::exportHtml( $article );
			die();
		}
		if ( $action == 'pdf-export' ) {
			self::exportPdf( $article );
			die();
		}
	}

	/**
	 * @param $fragment
	 *
	 * @return string
	 */
	private function sanitizeFragment( $fragment ): string {
		$str = preg_replace( "/[^a-zA-Z0-9-._~:@!$&'\(\)*+,;=?\/]/", "_", $fragment );

		return strtolower( $str );
	}

	/**
	 * @param $match
	 *
	 * @return string
	 */
	private function sanitizeLinks( $match ): string {
		return ( "[[#topic_" . self::sanitizeFragment( trim( $match[1] ) ) . $match[2] . "]]" );
	}

	/**
	 * @param Article $article
	 * @param $docTitleText
	 * @param $lastTimestamp
	 *
	 * @return string
	 * @throws MWException
	 */
	public static function exportWikimarkup( Article $article, &$docTitleText, &$lastTimestamp ) {
		$title = $article->getTitle();
		$titleNS = $title->getSubjectNsText() . ":";
		$parent = null;
		$doc = $title->getFullText();
		$lang = $title->getPageLanguage()->getCode();
		$separator_pos = strpos( $doc, '/' );
		$lastTimestamp = 0;
		$result = "";

		// calling not from a subpage
		if ( $separator_pos < 1 || self::endsWith( $doc, '/' . $lang ) ) {
			$content = ContentHandler::getContentText( $article->getPage()->getContent() );
			if ( preg_match( '/=\s*Articles From Namespace (.+?)\s*=/i', $content, $matches ) ) {
				$titleNS = $matches[1];
				if ( $titleNS == 'Main' ) {
					$titleNS = "";
				}
				$titleNS .= ":";
				// force TOC to this doc
				$parent = $title;
			} else {
				// return the article's wikitext
				$content = '<span id="topic_' .
					self::sanitizeFragment( trim( $title->getText() ) ) .
					'"></span>' .
					"\r\n" .
					$content;
				$content = "<!--ARTICLE:" . $doc . "-->\r\n" . $content;
				$lastTimestamp = wfTimestamp( TS_UNIX, $title->getTouched() );

				return $content;
			}
		}
		// find all doc names in super page toc (all links)
		// TOC is not forced
		if ( $parent == null ) {
			$parent = self::findSuperpage( $title );
		}
		// is there a localized version of parent?
		if ( $lang != $parent->getPageLanguage()->getCode() ) {
			$parentLangTitle = Title::newFromText( $parent->getFullText() . "/" . $lang );
			if ( $parentLangTitle->exists() ) {
				$parent = $parentLangTitle;
			}
		}
		// save last edit time
		$tmpTimestamp = wfTimestamp( TS_UNIX, $parent->getTouched() );
		if ( $tmpTimestamp && $tmpTimestamp > $lastTimestamp ) {
			$lastTimestamp = $tmpTimestamp;
		}

		$article = new Article( $parent );
		$superPageText = ContentHandler::getContentText( $article->getPage()->getContent() );
		// find title
		if ( preg_match( "/.*?=(.+?)=.*/s", $superPageText, $matches ) ) {
			$docTitleText = $matches[1];
		}
		// make all TOC links namespace-local
		if (
			// all links without a colon in URL part
			preg_match_all(
				"/\[\[([A-Z:a-z0-9,.\/_ \(\)-]+)(\#[A-Za-z0-9 ._-]*)?([|](.*?))?\]\]/",
				$superPageText,
				$matches,
				PREG_SET_ORDER
			)
		) {
			foreach ( $matches as $match ) {
				if ( strpos( $match[1], ':' ) ) {
					// colon is included if needed
					$rep_title = Title::newFromText( $match[1] );
				} else {
					// colon is included if needed
					$rep_title = Title::newFromText( $titleNS . $match[1] );
				}
				if ( $lang != $rep_title->getPageLanguage()->getCode() ) {
					$repLangTitle = Title::newFromText( $rep_title->getFullText() . "/" . $lang );
					if ( $repLangTitle->exists() ) {
						$rep_title = $repLangTitle;
					}
				}
				$superPageText = str_replace(
					$match[0],
					"[[" . $rep_title->getFullText() . $match[2] . $match[3] . "]]",
					$superPageText
				);
			}
		}
		// collect all docs pointed by links
		$docs = [];
		foreach ( preg_split( "/((\r?\n)|(\r\n?))/", $superPageText ) as $line ) {
			if ( preg_match( "/\[\[\s*([^|]+)\s*\|?\s*([^\]]*)\]\]/", $line, $matches ) == 1 ) {
				$docs[] = $matches[1];
			}
		}
		// ??? H1 title of the whole thing?
		foreach ( $docs as $doc ) {
			// ensure same namespace
			$doc_title = Title::newFromText( $doc );
			// save last edit time
			$tmpTimestamp = wfTimestamp( TS_UNIX, $doc_title->getTouched() );
			if ( $tmpTimestamp && $tmpTimestamp > $lastTimestamp ) {
				$lastTimestamp = $tmpTimestamp;
			}
			//
			$doc_article = new Article( $doc_title );
			$doc_content = ContentHandler::getContentText( $doc_article->getPage()->getContent() );
			// TODO warining - this replaces all links, including those outside the book
			$doc_content = preg_replace_callback(
				"/\[\[([A-Za-z0-9,.\/_ \(\)-]+)([|](.*?))?\]\]/",
				'self::sanitizeLinks',
				$doc_content
			);
			// TODO also need to replace all local hash links
			// TODO replace remote in-book hash links, to avoid double hash - just strip the file part
			$result .= '<span id="topic_' .
				self::sanitizeFragment( trim( $doc_title->getText() ) ) .
				'"></span>' .
				"\r\n" .
				"<!--ARTICLE:" .
				$doc .
				"-->\r\n" .
				$doc_content .
				"\r\n\r\n";
		}

		return $result;
	}

	/**
	 * shared with SuperPageTOC extension
	 *
	 * @param Title $title
	 *
	 * @return Title|null
	 */
	public static function findSuperpage( Title $title ) {
		if ( !$title->isSubpage() ) {
			return null;
		}
		// find a superpage, if exists
		$doc = $title->getFullText();
		// echo "SUPERPAGE FOR: ".$doc;
		$page_lang_code = $title->getPageLanguage()->getCode();
		// strip lang superpage suffix
		$langsuffix = "";
		$langsuffix_len = strlen( $page_lang_code ) + 1;
		if ( substr( $doc, -$langsuffix_len ) === ( '/' . $page_lang_code ) ) {
			$doc = substr( $doc, 0, -$langsuffix_len );
			$langsuffix = '/' . $page_lang_code;
		}
		// find a parent
		$separator_pos = strrpos( $doc, '/' );
		// calling not from a subpage
		if ( $separator_pos < 1 ) {
			return null;
		}
		$superpage_doc = substr( $doc, 0, $separator_pos );
		// see if there is a language-specific version of the superpage (only if page had the suffix)
		if ( $langsuffix ) {
			$link_lang_title = Title::newFromText( $superpage_doc . $langsuffix );
			if ( $link_lang_title->exists() ) {
				$superpage_doc .= $langsuffix;
			}
		}
		// get the superpage
		$superpage = Title::newFromText( $superpage_doc );

		return $superpage;
	}

	/**
	 * @param Article $article
	 *
	 * @return string
	 * @throws MWException
	 */
	public static function exportHtml( Article $article ) {
		global $wgUser, $wgParser;
		$title = $article->getTitle();

		$opt = ParserOptions::newFromUser( $wgUser );
		$opt->setIsPrintable( true );
		$wikimarkup = self::exportWikimarkup( $article, $docTitleText, $lastTimestamp );
		$out = $wgParser->parse( $wikimarkup, $title, $opt, true, true );
		$text = $out->getText( [ 'enableSectionEditLinks' => false ] );
		$base = '<base href="' . ( $title->getFullUrl() ) . '?action=html-localimages-export">';

		return self::htmlPage( self::coverPageHtml( $docTitleText ) . $text, $base );
	}

	/**
	 * @param string $docTitleText
	 *
	 * @return string
	 */
	private static function coverPageHTML( string $docTitleText ): string {
		global $wgLogo, $wgRightsText, $wgSitename;
		$titleText = "";
		$titleText .= '<table height="100%" width="100%"><tr><td valign="top" height="50%">';
		if ( $wgLogo ) {
			$titleText .= '<center><img src="' . $wgLogo . '"></center>';
		}
		$titleText .= '<h1>' . $docTitleText . '</h1>';
		if ( $wgSitename ) {
			$titleText .= '<h2>' . $wgSitename . '</h2>';
		}

		$titleText .= 'Generated: ' .
			date( 'n/d/Y g:i a', time() ) .
			'</td></tr><tr><td height="50%" width="100%" align="left" valign="bottom"><font size="2">' .
			str_replace( '$1', $wgRightsText, wfMessage( 'copyright' )->text() ) .
			'</td></tr></table></body></html>';

		return $titleText;
	}

	/**
	 * @param string $text
	 * @param string $additionalHeaderHtml
	 *
	 * @return string
	 */
	private static function htmlPage(
		string $text,
		string $additionalHeaderHtml = ""
	): string {
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

	/**
	 * @param string $data
	 * @param string $suffix
	 *
	 * @return mixed|string
	 */
	private static function writeFile( string $data, string $suffix = '.html' ) {
		$tempName = tempnam( sys_get_temp_dir(), 'wikibook' );
		unlink( $tempName );
		$file = fopen( $tempName . $suffix, 'w+' );
		$fileName = stream_get_meta_data( $file )['uri'];
		fwrite( $file, $data );
		fclose( $file );

		return $fileName;
	}

	/**
	 * @param string $fileName
	 * @param string $filePath
	 *
	 * @return false|void
	 */
	public static function serveFileAs( string $fileName, string $filePath ) {
		if ( file_exists( $filePath ) ) {
			header( "Content-Type: application/pdf" );
			header( "Content-Disposition: attachment; filename=\"$fileName\"" );
			readfile( $filePath );
			die();
		} else {
			return false;
		}
	}

	/**
	 * @param $article
	 *
	 * @return void
	 * @throws MWException
	 */
	private static function exportPdf( $article ): void {
		global $wgParser, $wgUser, $wgUploadDirectory;
		$title = $article->getTitle();
		$docVersionText = $title->getSubjectNsText();
		$base = '<base href="' . ( $title->getFullUrl() ) . '?action=html-localimages-export">';

		$wikimarkup = self::exportWikimarkup( $article, $docTitleText, $lastTimestamp );

		$opt = ParserOptions::newFromUser( $wgUser );
		$opt->setIsPrintable( true );
		$out = $wgParser->parse( $wikimarkup . "\n__NOTOC__\n", $title, $opt, true, true );

		$body = self::htmlPage( $out->getText( [ 'enableSectionEditLinks' => false ] ), $base );
		$cover = self::htmlPage( self::coverPageHTML( $docVersionText . ' ' . $docTitleText ), $base );

		$coverFileName = self::writeFile( $cover );
		$bodyFileName = self::writeFile( $body );
		// make converter happy
		rename( $coverFileName, $coverFileName . ".html" );
		$coverFileName .= ".html";
		rename( $bodyFileName, $bodyFileName . ".html" );
		$bodyFileName .= ".html";

		$pdfFileName = "wikibook-" . $docVersionText . "-" . ( $title->getRootText() ) . ".pdf";
		$pdfFilePath = "$wgUploadDirectory/" . $pdfFileName;

		//$wgOut->disable();
		$cmd = "/opt/wkhtmltox/bin/wkhtmltopdf -s Letter --outline --margin-bottom 0.5in --margin-top 0.5in --margin-left 0.5in --margin-right 0.5in --load-error-handling ignore --load-media-error-handling ignore " .
			" cover $coverFileName toc $bodyFileName $pdfFilePath";
		$output = [];
		$returnVar = 1;
		exec( $cmd, $output, $returnVar );
		unlink( $coverFileName );
		unlink( $bodyFileName );

		// 0 is success
		if ( $returnVar != 0 ) {
			error_log(
				"INFO [PonyDocsPdfBook::onUnknownAction] " .
				php_uname( 'n' ) .
				": Failed to run wkhtmltopdf (" .
				$returnVar .
				") Output is as follows: " .
				implode( "-", $output )
			);
			print( "Failed to create PDF.  Our team is looking into it." );
		}

		self::serveFileAs( $pdfFileName, $pdfFilePath );
	}
}
