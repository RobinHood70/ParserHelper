<?php

require_once('RHDebug.php');
require_once('VersionHelper.php');

/**
 * Provides a number of library routines, mostly related to the parser along with a few generic global methods.
 */
class ParserHelper
{
	public const AV_ALWAYS = 'parserhelper-always';

	public const NA_DEBUG = 'parserhelper-debug';
	public const NA_IF = 'parserhelper-if';
	public const NA_IFNOT = 'parserhelper-ifnot';
	public const NA_SEPARATOR  = 'parserhelper-separator';

	/**
	 * Cache for localized magic words.
	 *
	 * @var MagicWordArray
	 */
	private static $mwArray;

	/**
	 * Checks the debug argument to see if it's boolean or 'always'.Expects the keys to be magic word values rather
	 * than magic word IDs.
	 *
	 * @param Parser $parser The parser in use.
	 * @param PPFrame $frame The frame in use.
	 * @param array $magicArgs The magic-word arguments as created by getMagicArgs().
	 *
	 * @return bool
	 *
	 */
	public static function checkDebugMagic(Parser $parser, PPFrame $frame, array $magicArgs): bool
	{
		$debug = $frame->expand($magicArgs[self::NA_DEBUG] ?? false);
		$matched = VersionHelper::getInstance()->getMagicWord(self::AV_ALWAYS)->matchStartToEnd($debug);
		$preview = $parser->getOptions()->getIsPreview();
		$retval = $preview
			? (bool)$debug
			: $matched;
		#RHshow('Debug', (bool)$debug ? 'Yes' : 'No', "\nIn preview mode: ", $preview ? 'Yes' : 'No', "\nDebug word: ", $matched);
		return $retval;
	}

	/**
	 * Checks whether both the `if=` and `ifnot=` conditions have been satisfied.
	 *
	 * @param PPFrame $frame The frame in use.
	 * @param array $magicArgs The magic-word arguments as created by getMagicArgs().
	 *
	 * @return bool True if both conditions (if applicable) have been satisfied; otherwise, false.
	 *
	 */
	public static function checkIfs(PPFrame $frame, array $magicArgs): bool
	{
		return (isset($magicArgs[self::NA_IF])
			? $frame->expand($magicArgs[self::NA_IF])
			: true) &&
			!(isset($magicArgs[self::NA_IFNOT])
				? $frame->expand($magicArgs[self::NA_IFNOT])
				: false);
	}

	public static function error(string $key, ...$args)
	{
		$msg = wfMessage($key)->params($args)->inContentLanguage()->text();
		return '<strong class="error">' . htmlspecialchars($msg) . '</strong>';
	}

	/**
	 * Standardizes debug text formatting for parser functions.
	 *
	 * @param string $output The original text being output.
	 * @param bool $debug Whether to return debug or regular text.
	 *
	 * @return string The modified text.
	 *
	 */
	public static function formatPFForDebug(string $output, bool $debug, bool $noparse = false): array
	{
		return $debug && strlen($output)
			// Noparse needs to be false for debugging so that <pre> tag correctly breaks all processing.
			? ['<pre>' . htmlspecialchars($output) . '</pre>', 'noparse' => false]
			: [$output, 'noparse' => $noparse];
	}

	/**
	 * Standardizes debug text formatting for tags.
	 *
	 * @param string $output The original text being output.
	 * @param bool $debug Whether to return debug or regular text.
	 *
	 * @return string The modified text.
	 *
	 */
	public static function formatTagForDebug(string $output, bool $debug): array
	{
		if (!strlen($output)) {
			return [''];
		}

		return $debug
			// Noparse needs to be false for debugging so that <pre> tag correctly breaks all processing.
			? ['<pre>' . htmlspecialchars($output) . '</pre>', 'markerType' => 'nowiki', 'noparse' => false]
			: [$output, 'markerType' => 'none', 'noparse' => false];
	}

	/**
	 * Returns a string or part node split into a key/value pair, with the key expanded, if necessary, into a string.
	 * The return value is always an array. If the argument is of the wrong type, or isn't a key/value pair, the key
	 * will be returned as null and the value will be the original argument.
	 *
	 * @param PPFrame $frame The frame in use.
	 * @param string|PPNode_Hash_Tree $arg The argument to work on.
	 *
	 * @return array
	 */
	public static function getKeyValue(PPFrame $frame, $arg): array
	{
		if ($arg instanceof PPNode_Hash_Tree && $arg->getName() === 'part') {
			$split = $arg->splitArg();
			$key = empty($split['index']) ? $frame->expand($split['name']) : null;
			$value = $split['value'];
			#RHshow('Raw Value', $key, '=', $value);
			return [$key, $value];
		}

		if (is_string($arg)) {
			$split = explode('=', $arg, 2);
			if (count($split) == 2) {
				return [$split[0], $split[1]];
			}
		}

		// This handles both value-only nodes and unexpected values.
		return [null, $arg];
	}

	/**
	 * Splits the standard parser function arguments into recognized parameters and all others.
	 *
	 * @param PPFrame $frame The frame in use.
	 * @param array $args The arguments to search.
	 * @param MagicWordArray|mixed ...$allowedArgs A list of arguments that should be expanded and returned in the
	 *     `$magic` portion of the returned array. All other arguments will be returned in the `$values` portion of
	 *     the returned array. Currently accepts either an array of magic words constants (e.g. ParserHelper::NA_IF)
	 *     or a MagicWordArray of the same constants. The former is now deprecated; MagicWordArrays should be used in
	 *     all future code.
	 *
	 * @return array The return value consists of three arrays:
	 *     Magic Arguments: contains the list of any named arguments where the key appears in $allowedArray. Keys and
	 *         values are pre-expanded under the assumption that they will be needed that way.
	 *     Values: The second array will contain any arguments that are anonymous or were not specified in
	 *         $allowedArgs. Although these are returned unaltered, anything before the first equals sign (if any) will
	 *         have been expanded as part of processing. If there's no equals sign in the value, none of it will have
	 *         been expanded.
	 *
	 */
	public static function getMagicArgs(PPTemplateFrame_Hash $frame, array $args, MagicWordArray $allowedArray): array
	{
		$magic = [];
		$values = [];

		foreach ($args as $arg) {
			[$name, $value] = self::getKeyValue($frame, $arg);
			if (is_null($name)) {
				$values[] = $value;
			} else {
				$magKey = $allowedArray->matchStartToEnd(trim($name));
				if ($magKey && !isset($magic[$magKey])) {
					$magic[$magKey] = trim($frame->expand($value));
				} else {
					$values[] = $arg;
				}
			}
		}

		return [$magic, $values];
	}

	/**
	 * Parse separator string for C-like character entities and surrounding quotes.
	 *
	 * @param array $magicArgs The magic-word arguments as created by getMagicArgs().
	 *
	 * @return string The parsed string.
	 *
	 */
	public static function getSeparator(array $magicArgs): string
	{
		$separator = $magicArgs[self::NA_SEPARATOR] ?? '';
		if (strlen($separator) > 1) {
			$separator = stripcslashes($separator);
			$first = $separator[0];
			if (in_array($first, ['\'', '`', '"']) && $first === substr($separator, -1, 1)) {
				return substr($separator, 1, -1);
			}
		}

		return $separator;
	}

	/**
	 * Determines if the word at a specific key matches a certain value after everything's converted to their
	 * respective IDs.
	 *
	 * @param array $magicArguments The arguments the key can be found in.
	 * @param string $key The key to search for.
	 * @param string $value The value to match with.
	 *
	 * @return bool True if the value at the specifc key was the same as the value specified.
	 *
	 */
	public static function magicKeyEqualsValue(array $magicArguments, string $key, string $value): bool
	{
		$arrayValue = $magicArguments[$key] ?? null;
		return
			!is_null($arrayValue) &&
			VersionHelper::getInstance()->getMagicWord($value)->matchStartToEnd($arrayValue);
	}

	/**
	 * Calls setHook() for all synonyms of a tag.
	 *
	 * @param Parser $parser The parser to register the tag names with.
	 * @param string $id The magic word ID whose synonyms should be registered.
	 * @param callable $callback The function to call when the tag is used.
	 *
	 * @return void
	 *
	 */
	public static function setHookSynonyms(Parser $parser, string $id, callable $callback): void
	{
		$synonyms = VersionHelper::getInstance()->getMagicWord($id)->getSynonyms();
		foreach ($synonyms as $synonym) {
			$parser->setHook($synonym, $callback);
		}
	}

	/**
	 * Splits named arguments from unnamed.
	 *
	 * @param PPFrame $frame The template frame in use.
	 * @param ?array $args The arguments to split.
	 *
	 * @return array An array of arrays, the first element being the named values and the second element being the anonymous values.
	 */
	public static function splitNamedArgs(PPFrame $frame, ?array $args = null): array
	{
		$named = [];
		$unnamed = [];
		if (!empty($args)) {
			// $unnamed[] = $args[0];
			foreach (array_values($args) as $arg) {
				[$name, $value] = self::getKeyValue($frame, $arg);
				if (is_null($name)) {
					$unnamed[] = $value;
				} else {
					$named[$name] = $value;
				}
			}
		}

		return [$named, $unnamed];
	}

	/**
	 * Transforms tag attributes so that only wanted elements are present and are represented by their qqq key rather
	 * than the language-specific word.
	 *
	 * @param array $attributes The attributes to transform.
	 * @param ?MagicWordArray The MagicWordArray to compare against. Defaults to previously registered magic words.
	 *
	 * @return array The filtered array.
	 *k
	 */
	public static function transformAttributes(array $attributes, MagicWordArray $magicWords): array
	{
		$retval = [];
		foreach ($attributes as $key => $value) {
			$match = $magicWords->matchStartToEnd($key);
			if ($match) {
				$retval[$match] = $value;
			}
		}

		return $retval;
	}
}
