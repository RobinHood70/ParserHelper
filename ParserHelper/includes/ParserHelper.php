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
	const NA_SEPARATOR = 'parserhelper-separator';

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

	public static function magicKeyEqualsValue($magicArguments, $key, $value)
	{
		$arrayValue = self::arrayGet($magicArguments, $key);
		return
			!is_null($arrayValue) &&
			MagicWord::get($value)->matchStartToEnd($arrayValue);
	}

	public static function magicValueEquals($value, $magicWord)
	{
		// No point in accessing cache for this, as it will only add overhead. MW caches individual magic words on
		// first access anyway.
		return MagicWord::get($magicWord)->matchStartToEnd($value);
	}

	/**
	 * Checks the debug argument to see if it's boolean or 'always'.
	 *
	 * @param Parser $parser The parser in use.
	 * @param array|null $magicArgs The magic word arguments as created by getMagicArgs().
	 *
	 * @return boolean
	 *
	 */
	public static function checkDebug(Parser $parser, array $magicArgs)
	{
		$debug = self::arrayGet($magicArgs, self::NA_DEBUG);
		// show('Debug parameter: ', $debug);
		return $parser->getOptions()->getIsPreview()
			? boolval($debug)
			: MagicWord::get(self::AV_ALWAYS)->matchStartToEnd($debug);
		// show('Debug final: ', $debug);
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
	public static function checkDebugMagic(Parser $parser, array $args)
	{
		$debug = self::getArgumentValue(self::NA_DEBUG, $args);
		// show('Debug parameter: ', $debug);
		return $parser->getOptions()->getIsPreview()
			? boolval($debug)
			: self::magicValueEquals($debug, self::AV_ALWAYS);
		// show('Debug final: ', $debug);
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

	public static function checkSeparator(PPFrame $frame, array $magicArgs)
	{
		$separator = $frame->expand(ParserHelper::arrayGet($magicArgs, self::NA_SEPARATOR, "\n"));
		if (strlen($separator) > 1) {
			$separator = stripcslashes($separator);
			$first = $separator[0];
			if (in_array($first, ['\'', '`', '"']) && $first === substr($separator, -1, 1)) {
				return substr($separator, 1, -1);
			}

			return '';
		}
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
	public static function formatPFForDebug($output, $parser, $magicArgs)
	{
		if (ParserHelper::checkDebug($parser, $magicArgs)) {
			return ['<pre>' . htmlspecialchars($output) . '</pre>', 'noparse' => false];
		}

		return [$output, 'noparse' => false];
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
	public static function formatTagForDebug($output, $debug)
	{
		// It ended up that for both the cases of this so far, we needed to process the debug value before getting
		// here, so I made the debug check a simple boolean.
		if ($debug) {
			return ['<pre>' . htmlspecialchars($output) . '</pre>', 'markerType' => 'nowiki'];
		}

		return [$output, 'markerType' => 'none'];
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
			foreach ($args as $arg) {
				list($name, $value) = self::getKeyValue($frame, $arg);
				if (is_null($name)) {
					// show('Add anon: ', $frame->expand($value));
					$values[] = $value;
				} else {
					$magKey = $allowedArray->matchStartToEnd($name);
					if ($magKey) {
						if (isset($magic[$magKey])) {
							// If a key already exists and is one of the allowed keys, add it to the dupes list. This
							// allows for the possibility of merging the duplicate back into values later on for cases
							// like #splitargs where it may be desirable to have keys for the called template
							// (e.g., separator) that overlap with those of the template.
							$dupes[$name] = $value;
						} else {
							$magic[$magKey] = $frame->expand($value);
						}
					} else {
						// show('Add fake k=v: ', $frame->expand($arg));
						$values[] = $arg;
					}
				}
			}
		}

		return [$magic, $values, $dupes];
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
	public static function getArgumentValue($word, array $args, $default = false)
	{
		foreach ($args as $key => $value) {
			$match = self::$mwArray->matchStartToEnd($key);
			// show('Match: ', $match);
			if ($match === $word) {
				// show($key, ' == ', $word);
				return $value;
			}

			// show($key, '=', $value, ' != ', $word);
		}

		return $default;
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
			self::NA_SEPARATOR,

			self::NA_NSBASE, // These are shared here for now. There may be a better way to integrate
			self::NA_NSID,   // them later as Riven, MetaTemplate and UespCustomCode develop.
		]);
	}

	public static function magicWordIn($word, $allowedKeys)
	{
		$allowedMagic = new MagicWordArray($allowedKeys);
		$key = $allowedMagic->matchStartToEnd($word);
		return $key;
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

/**
 * Where to log to for the global functions that need it.
 */
define('PH_LOG_FILE', 'ParserHelperLog.txt');

/**
 * Tries to send a popup message via Javascript.
 *
 * @param mixed $msg The message to send.
 *
 * @return void
 */
function alert($msg)
{
	if (!isDev()) {
		return;
	}

	echo "<script>alert(\" $msg\")</script>";
}

/**
 * Returns the last query run along with the number of rows affected, if any.
 *
 * @param IDatabase $db
 * @param ResultWrapper|null $result
 *
 * @return string The text of the query and the result count.
 *
 */
function formatQuery(IDatabase $db, ResultWrapper $result = null)
{
	if (!isDev()) {
		return;
	}

	// MW 1.28+: $db = $result->getDB();
	$retval = $result ? $db->numRows($result) . ' rows returned.' : '';
	return $db->lastQuery() . "\n\n" . $retval;
}

function isDev()
{
	return in_array($_SERVER['SERVER_NAME'], ['content3.uesp.net', 'dev.uesp.net', 'rob-centos']);
}

/**
 * Logs text to the file provided in the PH_LOG_FILE define.
 *
 * @param string $text The text to add to the log.
 *
 * @return void
 *
 */
function logFunctionText($text = '')
{
	if (!isDev()) {
		return;
	}

	$caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1];
	$method = $caller['function'];
	if (isset($caller['class'])) {
		$method = $caller['class'] . '::' . $method;
	}

	writeFile($method, ': ', $text);
}

/**
 * Displays the provided message(s) on-screen, if possible.
 *
 * @param mixed ...$msgs
 *
 * @return void
 *
 */
function show(...$msgs)
{
	if (!isDev()) {
		return;
	}

	echo '
    <pre>';
	foreach ($msgs as $msg) {
		if ($msg) {
			print_r(htmlspecialchars(print_r($msg, true)));
		}
	}

	echo '</pre>';
}

/**
 * Writes the provided text to the log file specified in PH_LOG_FILE.
 *
 * @param mixed ...$msgs What to log.
 *
 * @return void
 *
 */
function writeFile(...$msgs)
{
	writeAnyFile(PH_LOG_FILE, ...$msgs);
}

/**
 * Logs the provided text to the specified file.
 *
 * @param mixed $file The file to output to.
 * @param mixed ...$msgs What to log.
 *
 * @return void
 *
 */
function writeAnyFile($file, ...$msgs)
{
	if (!isDev()) {
		return;
	}

	$handle = fopen($file, 'a') or die("Cannot open file: $file");
	foreach ($msgs as $msg) {
		$msg2 = print_r($msg, true);
		fwrite($handle, $msg2);
	}

	fwrite($handle, "\n");
	fflush($handle);
	fclose($handle);
}
