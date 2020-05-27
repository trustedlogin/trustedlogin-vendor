<?php
namespace TrustedLogin\Vendor;

/**
 * Class: TrustedLogin - ZenDesk Integration
 *
 * @package tl-support-side
 * @version 0.0
 **/
class ZenDesk extends HelpDesk {

	const NAME = 'ZenDesk';

	const SLUG = 'zendesk';

	const VERSION = '0.0';

	const IS_ACTIVE = false;
}

$hl = new ZenDesk();
