<?php
/**
 * LLM provider registry.
 *
 * To add a new model vendor:
 *   1. Create a class in includes/AI/Providers that extends AbstractProvider
 *      (or OpenAICompatible if the vendor speaks the OpenAI chat format).
 *   2. Add one line here.
 * Third-party add-ons can do the same with the `terax_ai_config_providers` filter.
 *
 * `models` is only a fallback list shown before the admin clicks
 * "Refresh models" (which asks the vendor's /models API). Any model ID can also
 * be typed in manually, so new releases work without a plugin update.
 *
 * @package TeraxAI
 */

use TeraxAI\AI\Providers\Anthropic;
use TeraxAI\AI\Providers\Gemini;
use TeraxAI\AI\Providers\Mistral;
use TeraxAI\AI\Providers\OpenAI;
use TeraxAI\AI\Providers\OpenAICompatible;

defined( 'ABSPATH' ) || exit;

return array(
	'anthropic'         => array(
		'class'  => Anthropic::class,
		'label'  => 'Anthropic Claude',
		'models' => array( 'claude-sonnet-4-5', 'claude-haiku-4-5' ),
	),
	'openai'            => array(
		'class'  => OpenAI::class,
		'label'  => 'OpenAI GPT',
		'models' => array( 'gpt-4.1-mini', 'gpt-4o-mini' ),
	),
	'gemini'            => array(
		'class'  => Gemini::class,
		'label'  => 'Google Gemini',
		'models' => array( 'gemini-2.5-flash', 'gemini-2.5-pro' ),
	),
	'mistral'           => array(
		'class'  => Mistral::class,
		'label'  => 'Mistral AI (EU-hosted)',
		'models' => array( 'mistral-small-latest', 'mistral-large-latest' ),
	),
	'openai_compatible' => array(
		'class'    => OpenAICompatible::class,
		'label'    => 'OpenAI-compatible (custom base URL)',
		'models'   => array(),
		'base_url' => true, // Admin UI shows a base URL field.
	),
);
