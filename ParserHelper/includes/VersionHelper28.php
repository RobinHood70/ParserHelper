<?php

use MediaWiki\MediaWikiServices;

/**
 * See base class for documentation.
 */
class VersionHelper28 extends VersionHelper
{
	/** @param ?Revision $revision */
	public function doSecondaryDataUpdates(WikiPage $page, ParserOutput $parserOutput, ParserOptions $options): void
	{
		$title = $page->getTitle();
		$content = $page->getContent();

		// Even though we will in many cases have just parsed the output, there's no reliable way to get it from there
		// to here, so we ask for it again. The parser cache should make this relatively fast.
		$updates = $content->getSecondaryDataUpdates($title, null, true, $parserOutput);
		foreach ($updates as $update) {
			DeferredUpdates::addUpdate($update, DeferredUpdates::PRESEND);
		}

		try {
			MediaWikiServices::getInstance()->getParserCache()->save($parserOutput, $page, $options);
		} catch (Exception $e) {
		}
	}

	public function fileExists(Title $title): bool
	{
		$file = RepoGroup::singleton()->getLocalRepo()->newFile($title);
		return (bool)$file && $file->exists();
	}

	public function findVariantLink(Parser $parser, string &$titleText, ?Title &$title): void
	{
		$language = $parser->getFunctionLang();
		if ($language->hasVariants()) {
			$language->findVariantLink($titleText, $title, true);
		}
	}

	public function getContentLanguage(): Language
	{
		global $wgContLang;
		return $wgContLang;
	}

	public function getLatestRevision(WikiPage $page)
	{
		return $page->getRevision();
	}

	public function getMagicWord(string $id): MagicWord
	{
		return MagicWord::get($id);
	}

	public function getStripState(Parser $parser): StripState
	{
		return $parser->mStripState;
	}

	public function handleInternalLinks(Parser $parser, string $text): string
	{
		return $parser->replaceInternalLinks($text);
	}

	/** @param ?Revision $revision */
	public function onArticleEdit(Title $title, $revId): void
	{
		if ($revId instanceof Parser) {
			$revision = $revId->getRevisionObject();
		} else {
			$revision = Revision::newFromId($revId);
		}

		WikiPage::onArticleEdit($title, $revision);
	}

	/** @param ?Revision $revision */
	public function purge($page, bool $recursive): void
	{
		$content = $page->getContent(Revision::RAW);
		if (!$content) {
			return;
		}

		// Even though we will in many cases have just parsed the output, there's no reliable way to get it from there
		// to here, so we ask for it again. The parser cache should make this relatively fast.
		$title = $page->getTitle();
		$popts = $page->makeParserOptions('canonical');
		$enableParserCache = MediaWikiServices::getInstance()->getMainConfig()->get('EnableParserCache');
		$parserOutput = $content->getParserOutput($title, $page->getLatest(), $popts, $enableParserCache);
		$updates = $content->getSecondaryDataUpdates($title, null, $recursive, $parserOutput);
		foreach ($updates as $update) {
			DeferredUpdates::addUpdate($update, DeferredUpdates::PRESEND);
		}

		if ($enableParserCache) {
			MediaWikiServices::getInstance()->getParserCache()->save($parserOutput, $page, $popts);
		}
	}

	public function replaceLinkHoldersText(Parser $parser, string $text): string
	{
		return $parser->replaceLinkHoldersText($text);
	}

	public function setPreprocessor(Parser $parser, $preprocessor): void
	{
		$propName = 'mPreprocessorClass'; // Call by name to avoid error from property not being defined in Parser.
		$parser->$propName = get_class($preprocessor);
		$parser->mPreprocessor = $preprocessor;
	}

	public function specialPageExists(Title $title): bool
	{
		return SpecialPageFactory::exists($title->getDBkey());
	}

	public function updateBackLinks(Title $title, string $tableName): void
	{
		LinksUpdate::queueRecursiveJobsForTable($title, $tableName);
	}
}
