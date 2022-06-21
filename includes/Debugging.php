<?php

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
    // MW 1.28+: $db = $result->getDB();
    $retval = $result ? $db->numRows($result) . ' rows returned.' : '';
    return $db->lastQuery() . "\n\n" . $retval;
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
    $handle = fopen($file, 'a') or die("Cannot open file: $file");
    foreach ($msgs as $msg) {
        $msg2 = print_r($msg, true);
        fwrite($handle, $msg2);
    }

    fwrite($handle, "\n");
    fflush($handle);
    fclose($handle);
}
