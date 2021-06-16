<?php
// namespace MediaWiki\Extension\MetaTemplate;
use MediaWiki\MediaWikiServices;
// use MediaWiki\DatabaseUpdater;

// TODO: Add {{#define/local/preview:a=b|c=d}}
/**
 * [Description MetaTemplateHooks]
 */
class ParserHelperHooks
{
	// Register any render callbacks with the parser
	/**
	 * onParserFirstCallInit
	 *
	 * @param Parser $parser
	 *
	 * @return void
	 */
	public static function onParserFirstCallInit(Parser $parser)
	{
		ParserHelper::init();
	}
}
