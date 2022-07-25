<?php

/**
 * Provides a number of library routines, mostly related to the parser along with a few generic global methods.
 */
class ParserHelper
{
	const AV_ANY = 'parserhelper-any';
	const AV_ALWAYS = 'parserhelper-always';

	const NA_ALLOWEMPTY = 'parserhelper-allowempty';
	const NA_CASE = 'parserhelper-case';
	const NA_DEBUG = 'parserhelper-debug';
	const NA_IF = 'parserhelper-if';
	const NA_IFNOT = 'parserhelper-ifnot';
	const NA_SEPARATOR = 'parserhelper-separator';

	const NA_NSBASE = 'parserhelper-ns_base';
	const NA_NSID = 'parserhelper-ns_id';

	/**
	 * Cache for localized magic words.
	 *
	 * @var MagicWordArray
	 */
	private static $mwArray;

	/**
	 * Gets a value from an array with proper existence checks beforehand.
	 * This can be replaced with `$array[$key] ?? $default` if upgraded to PHP 7.
	 *
	 * @param array $array The array to search.
	 * @param mixed $key The key of the value to retrieve.
	 * @param null $default A value to use if the key is not found in the array.
	 * If not provided, `null` will be returned.
	 *
	 * @return mixed The requested value, or `$default|null` if not found.
	 */
	public static function arrayGet(array $array, $key, $default = null)
	{
		return (isset($array[$key]) || array_key_exists($key, $array))
			? $array[$key]
			: $default;
	}

	/**
	 * Caches magic words in a static MagicWordArray. This should include any named arguments or argument values that
	 * need to be localized, along with any other magic words not already registered with the parser by other means,
	 * such as parser functions, tags, and so forth.
	 *
	 * @param array $magicWords The magic words to cache.
	 *
	 * @return void
	 */
	public static function cacheMagicWords(array $magicWords)
	{
		// TODO: Move most of the calls to this to something more appropriate. Magic Words should be unique and not
		// appropriated for values. This should be straight-up localization and nothing more. Check how image options
		// work, as these are likely to be similar.
		if (!isset(self::$mwArray)) {
			self::$mwArray = new MagicWordArray($magicWords);
		} else {
			self::$mwArray->addArray($magicWords);
		}
	}

	/**
	 * Checks the `case` parameter to see if matches `case=any` or any of the localized equivalents.
	 *
	 * @param array $magicArgs The list of arguments to search.
	 *
	 * @return boolean True if `case=any` or any localized equivalent was found in the argument list.
	 */
	public static function checkAnyCase(array $magicArgs)
	{
		return self::magicKeyEqualsValue($magicArgs, self::NA_CASE, self::AV_ANY);
	}

	/**
	 * Checks the debug argument to see if it's boolean or 'always'. This variant of checkDebug expects the keys to be
	 * magic word values rather than magic word IDs.
	 *
	 * @param Parser $parser The parser in use.
	 * @param array|null $magicArgs The magic word arguments as created by getMagicArgs().
	 *
	 * @return boolean
	 *
	 */
	public static function checkDebugMagic(Parser $parser, PPFrame $frame, array $magicArgs)
	{
		$debug = self::arrayGet($magicArgs, self::NA_DEBUG, false);
		// RHDebug::show('Debug parameter: ', $debug);
		return $parser->getOptions()->getIsPreview()
			? boolval($debug)
			: MagicWord::get(self::AV_ALWAYS)->matchStartToEnd($debug);
	}

	/**
	 * Checks whether both the `if=` and `ifnot=` conditions have been satisfied.
	 *
	 * @param array $magicArgs The magic word array containing the arguments.
	 *
	 * @return boolean True if both conditions (if applicable) have been satisfied; otherwise, false.
	 */
	public static function checkIfs(PPFrame $frame, array $magicArgs)
	{
		$if = $frame->expand(self::arrayGet($magicArgs, self::NA_IF, '1'));
		$ifnot = $frame->expand(self::arrayGet($magicArgs, self::NA_IFNOT, ''));

		return !empty($if) && empty($ifnot);
	}

	/**
	 * Expands an entire array of values using the MediaWiki pre-processor.
	 * This is useful when parsing arguments to parser functions.
	 *
	 * @param PPFrame $frame The expansion frame to use.
	 * @param array $values The values to expand.
	 * @param int $flags
	 *
	 * @return array
	 */
	public static function expandArray(PPFrame $frame, array $values, $trim = false)
	{
		$retval = [];

		// Micro-optimization: only check outside loop, not inside.
		if ($trim) {
			foreach ($values as $value) {
				$retval[] = trim($frame->expand($value));
			}
		} else {
			foreach ($values as $value) {
				$retval[] = $frame->expand($value);
			}
		}

		return $retval;
	}

	/**
	 * Finds the magic word ID that corresponds to the value provided.
	 *
	 * @param string $value The value to look up.
	 *
	 * @return string|false
	 *
	 */
	public static function findMagicID($value, $default = null)
	{
		$match = self::$mwArray->matchStartToEnd($value);
		return $match === false ? $default : $match;
	}

	/**
	 * Standardizes debug text formatting for parser functions.
	 *
	 * @param string $output The original text being output.
	 * @param Parser $parser The parser in use.
	 * @param $magicArgs The list of magic word arguments, typically from getMagicArgs().
	 *
	 * @return string The modified text.
	 *
	 */
	public static function formatPFForDebug($output, $debug = false, $noparse = false)
	{
		if (strlen($output) == 0) {
			return '';
		}

		if ($debug) {
			return ['<pre>' . htmlspecialchars($output) . '</pre>', 'noparse' => false];
		}

		return 		[$output, 'noparse' => $noparse];
	}

	/**
	 * Standardizes debug text formatting for tags.
	 *
	 * @param string $output The original text being output.
	 * @param boolean $debug Whether to format as debug text or as normal.
	 *
	 * @return string The modified text.
	 *
	 */
	public static function formatTagForDebug($output, $debug = false)
	{
		// It ended up that for both the cases of this so far, we needed to process the debug value before getting
		// here, so I made the debug check a simple boolean.
		return $debug
			? ['<pre>' . htmlspecialchars($output) . '</pre>', 'markerType' => 'nowiki']
			: [$output, 'markerType' => 'none'];
	}

	/**
	 * Returns a string or part node split into a key/value pair, with the key expanded, if necessary, into a string.
	 * The return value is always an array. If the argument is of the wrong type, or isn't a key/value pair, the key
	 * will be returned as null and the value will be the original argument.
	 *
	 * @param PPFrame $frame
	 * @param string|PPNode_Hash_Tree $arg
	 *
	 * @return array
	 */
	public static function getKeyValue(PPFrame $frame, $arg)
	{
		if ($arg instanceof PPNode_Hash_Tree && $arg->getName() === 'part') {
			$split = $arg->splitArg();
			$key = empty($split['index']) ? $frame->expand($split['name']) : null;
			return [$key, $split['value']];
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
	 * Returns an associative array of the named arguments that are allowed for a magic word or parser function along
	 * with their values. The function checks all localized variants for a named argument and returns their associated
	 * values under a single unified key.
	 *
	 * @param PPFrame $frame The expansion frame to use.
	 * @param array $args The arguments to search.
	 * @param mixed ...$allowedArgs A list of arguments that should be expanded and returned in the `$magic` portion of
	 * the returned array. All other arguments will be returned in the `$values` portion of the returned array.
	 *
	 * @return [array, array] The return value consists of two sets of array. The first array contains the list of any
	 * named arguments from `$allowedArgs` that were found. The values will have been expanded before being returned.
	 * The second array will contain all other arguments. These are left unexpanded to avoid processing conditional code.
	 */
	public static function getMagicArgs(PPFrame $frame, array $args = [], ...$allowedArgs)
	{
		$magic = [];
		$values = [];
		$dupes = [];
		$allowedArray = new MagicWordArray($allowedArgs);
		if (count($args) && count($allowedArgs)) {
			$args = array_reverse($args); // Make sure last value gets processed and any others go to $dupes.
			foreach ($args as $arg) {
				list($name, $value) = self::getKeyValue($frame, $arg);
				if (is_null($name)) {
					// RHDebug::show('Add anon: ', $frame->expand($value));
					$values[] = $value;
				} else {
					$name = trim($name);
					$magKey = $allowedArray->matchStartToEnd($name);
					if ($magKey) {
						if (isset($magic[$magKey])) {
							// If a key already exists and is one of the allowed keys, add it to the dupes list. This
							// allows for the possibility of merging the duplicate back into values later on for cases
							// like #splitargs where it may be desirable to have keys for the called template
							// (e.g., separator) that overlap with those of the template.
							if (!isset($dupes[$name])) {
								$dupes[$name] = $value;
							}
						} else {
							$magic[$magKey] = trim($frame->expand($value));
						}
					} else {
						// RHDebug::show('Add fake k=v: ', $frame->expand($arg));
						$values[] = $arg;
					}
				}
			}

			$values = array_reverse($values);
		}

		return [$magic, $values, $dupes];
	}

	public static function getSeparator(PPFrame $frame, array $magicArgs)
	{
		$separator = ParserHelper::arrayGet($magicArgs, self::NA_SEPARATOR, '');
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
	 * Initializes ParserHelper, caching all required magic words.
	 *
	 * @return void
	 */
	public static function init()
	{
		self::cacheMagicWords([
			self::NA_ALLOWEMPTY,
			self::NA_CASE,
			self::NA_DEBUG,
			self::NA_IF,
			self::NA_IFNOT,
			self::NA_SEPARATOR,

			self::NA_NSBASE, // These are shared here for now. There may be a better way to integrate
			self::NA_NSID,   // them later as Riven, MetaTemplate and UespCustomCode develop.
		]);
	}

	/**
	 * Determines if the word at a specific key matches a certain value after everything's converted to their
	 * respective IDs.
	 *
	 * @param mixed $magicArguments The arguments they key can be found in.
	 * @param mixed $key The key to search for.
	 * @param mixed $value The value to match with.
	 *
	 * @return boolean True if the value at the specifc key was the same as the value specified.
	 *
	 */
	public static function magicKeyEqualsValue($magicArguments, $key, $value)
	{
		$arrayValue = self::arrayGet($magicArguments, $key);
		return
			!is_null($arrayValue) &&
			self::$mwArray->matchStartToEnd($arrayValue) === $value;
	}

	/**
	 * Calls setHook() for all synonyms of a tag.
	 *
	 * @param Parser $parser The parser to register the tag names with.
	 * @param mixed $id The magic word ID whose synonyms should be registered.
	 * @param callable $callback The function to call when the tag is used.
	 *
	 * @return void
	 *
	 */
	public static function setHookSynonyms(Parser $parser, $id, callable $callback)
	{
		global $wgContLang;

		$magicWord = MagicWord::get($id);
		$case = $magicWord->isCaseSensitive();
		foreach ($magicWord->getSynonyms() as $synonym) {
			if (!$case) {
				$synonym = $wgContLang->lc($synonym);
			}

			$parser->setHook($synonym, $callback);
		}
	}

	public static function transformArgs(array $args)
	{
		$retval = [];
		foreach ($args as $key => $value) {
			$match = self::$mwArray->matchStartToEnd($key);
			if ($match) {
				$retval[$match] = $value;
			}
		}

		return $retval;
	}
}
