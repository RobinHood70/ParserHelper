<?php

/**
 * See base class for documentation.
 */
class VersionHelper28 extends VersionHelper
{
	public function fileExists(Title $title): bool
	{
		$file = RepoGroup::singleton()->getLocalRepo()->newFile($title);
		return (bool)$file && $file->exists();
	}

	public function findVariantLink(Parser $parser, string &$titleText, Title &$title): void
	{
		$language = $parser->getFunctionLang();
		if ($language->hasVariants()) {
			$language->findVariantLink($titleText, $title, true);
		}
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

	public function replaceLinkHoldersText(Parser $parser, string $text): string
	{
		return $parser->replaceLinkHoldersText($text);
	}

	public function specialPageExists(Title $title): bool
	{
		return SpecialPageFactory::exists($title->getDBkey());
	}
}
