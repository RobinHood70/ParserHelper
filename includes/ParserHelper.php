<?php

/**
 * Provides a number of library routines, mostly related to the parser along with a few generic global methods.
 */
class ParserHelper
{
	const AV_ANY = 'parserhelper-any';
	const AV_ALWAYS = 'parserhelper-always';
	const NA_CASE = 'parserhelper-case';
	const NA_DEBUG = 'parserhelper-debug';
	const NA_IF = 'parserhelper-if';
	const NA_IFNOT = 'parserhelper-ifnot';
	const NA_NSBASE = 'namespaceinfo-ns_base';
	const NA_NSID = 'namespaceinfo-ns_id';

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
		if (is_array($key)) {
			show(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
		}

		if (isset($array[$key]) || array_key_exists($key, $array)) {
			return $array[$key];
		}

		return $default;
	}

	/**
	 * Gets the first value in an associative array where the key the list of keys provided.
	 *
	 * @param array $array The array to search.
	 * @param mixed $key The keys of the value to retrieve.
	 * @param null $default A value to use if none of the keys was found in the array.
	 * If not provided, `null` will be returned.
	 *
	 * @return mixed The requested value, or `$default|null` if not found.
	 */
	public static function arrayGetFirst(array $array, array $keys, $default = null)
	{
		foreach ($keys as $key) {
			if (isset($array[$key])) {
				return $array[$key];
			}
		}

		return $default;
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
		if (!isset(self::$mwArray)) {
			self::$mwArray = new MagicWordArray($magicWords);
		} else {
			self::$mwArray->addArray($magicWords);
		}

		/*
		foreach ($magicWords as $mw) {
			self::$magicWords[] = MagicWord::get($mw);
		}
		*/
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
		$caseValue = self::arrayGet($magicArgs, self::NA_CASE);
		return self::$mwArray->matchStartToEnd($caseValue) === self::AV_ANY;
	}

	/**
	 * Checks whether both the `if=` and `ifnot=` conditions have been satisfied.
	 *
	 * @param array $magicArgs The magic word array containing the arguments.
	 *
	 * @return boolean True if both conditions (if applicable) have been satisfied; otherwise, false.
	 */
	public static function checkIfs(array $magicArgs)
	{
		return
			self::arrayGet($magicArgs, self::NA_IF, true) &&
			!self::arrayGet($magicArgs, self::NA_IFNOT, false);
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
	public static function expandArray(PPFrame $frame, array $values, $flags = 0)
	{
		$retval = [];
		foreach ($values as $value) {
			$retval[] = $frame->expand($value, $flags);
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
	public static function findMagicID($value)
	{
		return self::$mwArray->matchStartToEnd($value);
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
		$allowedArray = new MagicWordArray($allowedArgs);
		if (count($args) && count($allowedArgs)) {
			for ($i = count($args) - 1; $i >= 0; $i--) {
				$arg = $args[$i];
				list($name, $value) = self::getKeyValue($frame, $arg);
				if (is_null($name)) {
					$values[] = $value;
				} else {
					$magKey = $allowedArray->matchStartToEnd($name);
					if ($magKey) {
						$magic[$magKey] = $frame->expand($value);
						break;
					}

					if (!$magKey) {
						$values[] = $arg;
					}
				}
			}
		}

		$values = array_reverse($values);
		return [$magic, $values];
	}

	/**
	 * Gets the value in an array where the key is one of the magic word's synonyms.
	 *
	 * @param string $word The magic word to search for.
	 * @param array $args The array to search.
	 * @param bool $default The default value, if nothing matches.
	 *
	 * @return mixed
	 *
	 */
	public static function getMagicValue($word, array $args, $default = false)
	{
		foreach ($args as $key => $value) {
			if (self::$mwArray->matchStartToEnd($key) === $word) {
				return $value;
			}
		}

		return $default;
	}

	public static function magicWordIn($word, $allowedWords)
	{
		$key = self::$mwArray->matchStartToEnd($word);
		return is_bool($key) ? null : in_array($key, $allowedWords);
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
		if (is_string($arg)) {
			$split = explode('=', $arg, 2);
			if (count($split) == 2) {
				return [$split[0], $split[1]];
			}
		} elseif ($arg instanceof PPNode_Hash_Tree && $arg->getName() === 'part') {
			$split = $arg->splitArg();
			$indexNode = $split['index'];
			$key = strlen($indexNode) ? null : $frame->expand($split['name']);
			return [$key, $split['value']];
		}

		// This handles both value-only nodes and unexpected values.
		return [null, $arg];
	}

	/**
	 * Initializes ParserHelper, caching all required magic words.
	 *
	 * @return void
	 */
	public static function init()
	{
		self::cacheMagicWords([
			self::AV_ANY,
			self::AV_ALWAYS,
			self::NA_CASE,
			self::NA_DEBUG,
			self::NA_IF,
			self::NA_IFNOT,
			self::NA_NSBASE, // These are shared here for now. There may be a better way to integrate
			self::NA_NSID,   // them later as Riven, MetaTemplate and UespCustomCode develop.
		]);
	}

	/**
	 * Primitive null coalescing for older versions of PHP.
	 *
	 * @param mixed ...$args The arguments to evaluate.
	 *
	 * @return mixed|null
	 */
	public static function nullCoalesce(...$args)
	{
		// Can be replaced with actual null coalescing operator in PHP 7+.
		foreach ($args as $arg) {
			if (!is_null($arg)) {
				return $arg;
			}
		}

		return null;
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
		foreach (MagicWord::get($id)->getSynonyms() as $synonym) {
			$parser->setHook($synonym, $callback);
		}
	}
}
