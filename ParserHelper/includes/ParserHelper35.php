<?php

use MediaWiki\MediaWikiServices;

/**
 * Provides a number of library routines, mostly related to the parser along with a few generic global methods.
 */
class ParserHelper35 extends ParserHelper
{
    public function getKeyValue(PPFrame $frame, mixed $arg): array
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

    public function getMagicWord($id): MagicWord
    {
        return MediaWikiServices::getInstance()->getMagicWordFactory()->get($id);
    }

    public function getStripState(Parser $parser): StripState
    {
        return $parser->getStripState();
    }

    public function replaceLinkHoldersText(Parser $parser, string $output): string
    {
        // Make $parser->replaceLinkHoldersText() available via reflection. This is blatantly "a bad thing", but as of
        // MW 1.35, it's the only way to implement the functionality which was previously public. Failing this, it's
        // likely we'll have to go back to Regex. Alternatively, the better version may be possible to implement via
        // preprocessor methods as originally designed, but with a couple of adaptations...needs to be re-checked.
        // Code derived from top answer here: https://stackoverflow.com/questions/2738663/call-private-methods-and-private-properties-from-outside-a-class-in-php
        $reflector = new ReflectionObject($parser);
        $replaceLinks = $reflector->getMethod('replaceLinkHoldersText');
        $replaceLinks->setAccessible(true);
        $output = $replaceLinks->invoke($parser, $output);
        return $output;
    }
}
