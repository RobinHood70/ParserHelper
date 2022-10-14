<?php

/**
 * See base class for documentation.
 */
class ParserHelper28 extends ParserHelper
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

    public function getMagicWord(string $id): MagicWord
    {
        return MagicWord::get($id);
    }

    public function getStripState(Parser $parser): StripState
    {
        return $parser->mStripState;
    }

    public function replaceLinkHoldersText(Parser $parser, string $output): string
    {
        return $parser->replaceLinkHoldersText($output);
    }
}
