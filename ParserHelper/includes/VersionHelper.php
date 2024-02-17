<?php

/**
 * Provides version-specific methods for those calls that differ substantially across versions.
 */
abstract class VersionHelper
{
	// Copy of Parser::PTD_FOR_INCLUSION / Preprocessor::DOM_FOR_INCLUSION
	const FOR_INCLUSION = 1;

	// Copy of (Revision|RevisionRecord)::RAW
	const RAW_CONTENT = 3;

	#region Private Static Variables
	/**
	 * Instance variable for singleton.
	 *
	 * @var VersionHelper
	 */
	private static $instance;
	#endregion

	#region Public Static Functions
	/**
	 * Gets the singleton instance for this class.
	 *
	 * @return VersionHelper The singleton instance.
	 *
	 */
	public static function getInstance(): VersionHelper
	{
		if (!self::$instance) {
			$version = VersionHelper::getMWVersion();
			if (version_compare($version, '1.38', '>=')) {
				require_once(__DIR__ . '/VersionHelper38.php');
				self::$instance = new VersionHelper38();
			} elseif (version_compare($version, '1.35', '>=')) {
				require_once(__DIR__ . '/VersionHelper35.php');
				self::$instance = new VersionHelper35();
			} elseif (version_compare($version, '1.28', '>=')) {
				require_once(__DIR__ . '/VersionHelper28.php');
				self::$instance = new VersionHelper28();
			} else {
				throw new Exception('MediaWiki version could not be found or is too low.');
			}
		}

		return self::$instance;
	}

	/**
	 * Gets the MediaWiki version number as text. This is static rather than absttract so it can be used to determine
	 * the version without relying on the version.
	 *
	 * @return string
	 *
	 */
	public static function getMWVersion(): string
	{
		global $wgVersion;
		return defined('MW_VERSION') ? constant('MW_VERSION') : $wgVersion;
	}
	#endregion

	#region Public Abstract Functions
	/**
	 * Recursively updates a page.
	 *
	 * @param WikiPage $page The page to purge. This is always a link-update purge, optionally recursive.
	 * @param ParserOutput $parserOutput The parser output from a prior call to $page->getParserOutput().
	 * @param ParserOptions $options The same parser options used with the above $parserOutput.
	 *
	 * @return void
	 *
	 */
	public abstract function doSecondaryDataUpdates(WikiPage $page, ParserOutput $parserOutput, ParserOptions $options): void;

	/**
	 * Determines if a File page exists on the local wiki.
	 *
	 * @param Title $title The file to search for.
	 *
	 * @return bool True if the file was found; otherwise, false.
	 */
	public abstract function fileExists(Title $title): bool;

	/**
	 * Finds a language-variant link, if appropriate. See https://www.mediawiki.org/wiki/LangConv.
	 *
	 * @param Parser $parser The parser in use.
	 * @param string $titleText The title to search for. (May be modified on exit.)
	 * @param Title $title The resultant title. (May be modified on exit.)
	 */
	public abstract function findVariantLink(Parser $parser, string &$titleText, Title &$title): void;

	/**
	 * Gets the wiki's content language.
	 *
	 * @return Language
	 */
	public abstract function getContentLanguage(): Language;

	/**
	 * Gets the latest revision of a page as the appropriate revision type for the version.
	 *
	 * @param WikiPage $page The page to get the revision of.
	 *
	 * @return Revision|RevisionRecord|null
	 */
	public abstract function getLatestRevision(WikiPage $page);

	/**
	 * Gets the magic word for the specified id.
	 *
	 * @param string $id The id of the magic word to get.
	 *
	 * @return MagicWord The magic word or null if not found.
	 */
	public abstract function getMagicWord(string $id): MagicWord;

	/**
	 * Gets the namespace ID from the parser.
	 *
	 * @param Parser $parser The object in use.
	 *
	 * @return int The namespace ID.
	 */
	public abstract function getParserNamespace(Parser $parser): int;

	/**
	 * Retrieves the parser's strip state object.
	 *
	 * @param Parser $parser The parser in use.
	 *
	 * @return StripState
	 */
	public abstract function getStripState(Parser $parser): StripState;

	/**
	 * Converts internal links to <!--LINK #--> objects.
	 *
	 * @param Parser $parser The parser in use.
	 * @param string $text The text to convert.
	 *
	 * @return string
	 */
	public abstract function handleInternalLinks(Parser $parser, string $text): string;

	/**
	 * Launches any recursive updates needed for the title passed to it.
	 *
	 * @param Title $title The title that was edited.
	 * @param mixed $revision The current revision. Can also be a Parser object for backwards compatibility.
	 */
	public abstract function onArticleEdit(Title $title, $revId): void;

	/**
	 * Recursively updates a page. This is always a link-update purge, optionally recursive.
	 *
	 * @param WikiPage $page The page to purge.
	 * @param bool $recursive Whether the purge should be recursive.
	 */
	public abstract function purge($page, bool $recursive);

	/**
	 * Calls $parser->replaceLinkHoldersText(), bypassing the private access modifier if needed.
	 *
	 * @param Parser $parser The parser in use.
	 * @param mixed $output The output text to replace in.
	 *
	 * @return string
	 */
	public abstract function replaceLinkHoldersText(Parser $parser, string $text): string;

	/**
	 * Sets the Parser's mPreprocessor variable.
	 *
	 * @param Parser $parser The parser in use.
	 */
	public abstract function setPreprocessor(Parser $parser, $preprocessor): void;

	/**
	 * Determines if a Special page exists on the local wiki.
	 *
	 * @param Title $title The Special page to search for.
	 *
	 * @return bool True if the page was found; otherwise, false.
	 */
	public abstract function specialPageExists(Title $title): bool;

	/**
	 * Updates the backlinks for a specific page and specific type of backlink (based on table name).
	 *
	 * @param Title $title The title whose backlinks should be updated.
	 * @param string $tableName The table name of the links to update.
	 *
	 * @return void
	 *
	 */
	public abstract function updateBackLinks(Title $title, string $tableName): void;
	#endregion
}
