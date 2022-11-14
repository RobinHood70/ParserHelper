<?php

/**
 * See base class for documentation.
 */
class ParserHelper28 extends ParserHelper
{
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
