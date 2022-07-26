<?php

use MediaWiki\MediaWikiServices;

/**
 * Provides a number of library routines, mostly related to the parser along with a few generic global methods.
 */
class ParserHelper35 extends ParserHelper
{
    public function arrayGet(array $array, $key, $default = null)
    {
        return $array[$key] ?? $default;
    }

    public function getKeyValue(PPFrame $frame, $arg)
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

    public function getMagicWord($id)
    {
        return MediaWikiServices::getInstance()->getMagicWordFactory()->get($id);
    }
}
