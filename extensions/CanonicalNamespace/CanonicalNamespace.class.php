<?php

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
			$r_title = Title::newFromText($wgCanonicalNamespaceName . ':' . $title->getText());
			$out->setCanonicalUrl($r_title->getFullURL());
		}
	}
}
?>
