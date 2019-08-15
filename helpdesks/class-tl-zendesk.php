<?php
namespace TrustedLogin;

/**
 * Class: TrustedLogin - ZenDesk Integration
 *
 * @package tl-support-side
 * @version 0.0
 **/
class ZenDesk extends HelpDesk {

	const name = 'ZenDesk';

	const slug = 'zendesk';

	const version = '0.0';

	const is_active = false;
}

$hl = new ZenDesk();
