<?php

/* Copyright 2018, 2019 Sergey Menshikov

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE. */

class CanonicalNamespace {

	public static function onBeforeDisplayNoArticleText($article) {
		global $wgCanonicalNamespaceName, $wgLatestNamespaceName;
		$prefix = $wgCanonicalNamespaceName.':';
		$title = $article->getTitle();
		if(strcasecmp(substr($title->getText(), 0, strlen($prefix)),$prefix)==0){
			$r_title = Title::newFromText($wgLatestNamespaceName . ':' . substr($title->getText(), strlen($prefix)));
			header('HTTP/1.1 302 Moved Temporarily');
			header('Location: ' . $r_title->getLocalURL() );
		}
	}

	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		global $wgExtraNamespaces, $wgCanonicalNamespaceName;
		$title = $skin->getTitle();
		if(array_key_exists($title->getNamespace(), $wgExtraNamespaces)){
			// $r_title = Title::newFromText($wgCanonicalNamespaceName . ':' . $title->getText());
			// $out->setCanonicalUrl($r_title->getFullURL());
			$out->setCanonicalUrl("https://help.brightpattern.com/Latest:".$title->getText());
		}
	}
}
?>
