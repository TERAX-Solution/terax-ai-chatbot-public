<?php
/**
 * Provider registry and factory.
 *
 * @package TeraxAI
 */

namespace TeraxAI\AI;

use TeraxAI\AI\Contracts\ProviderInterface;
use TeraxAI\Support\Features;
use TeraxAI\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProviderRegistry
 *
 * Reads config/providers.php (filterable) and builds the active provider
 * from settings + license. This is the ONLY place that knows which concrete
 * class to instantiate.
 */
class ProviderRegistry {

	use Base;

	/**
	 * All registered provider definitions.
	 *
	 * @return array id => definition
	 */
	public function all() {
		$providers = array();
		foreach ( terax_ai_config( 'providers' ) as $id => $def ) {
			if ( ! empty( $def['class'] ) && class_exists( $def['class'] ) && is_subclass_of( $def['class'], ProviderInterface::class ) ) {
				$providers[ sanitize_key( $id ) ] = wp_parse_args(
					$def,
					array(
						'label'    => $id,
						'models'   => array(),
						'base_url' => false,
					)
				);
			}
		}
		return $providers;
	}

	/**
	 * Public list for the admin UI.
	 *
	 * @return array
	 */
	public function describe() {
		$out = array();
		foreach ( $this->all() as $id => $def ) {
			$out[] = array(
				'id'       => $id,
				'label'    => $def['label'],
				'models'   => array_values( $def['models'] ),
				'base_url' => (bool) $def['base_url'],
			);
		}
		return $out;
	}

	/**
	 * Build a provider instance.
	 *
	 * @param string|null $provider_id Provider id (null = from settings).
	 * @param string|null $mode        'byok' | 'managed' (null = from settings).
	 * @param string|null $api_key     Override key (used by "test connection" before saving).
	 * @param array       $options     Override options (e.g. base_url) for testing unsaved values.
	 * @return ProviderInterface
	 * @throws ProviderException If the provider is unknown or not allowed by the license.
	 */
	public function make( $provider_id = null, $mode = null, $api_key = null, array $options = array() ) {
		$provider_id = $provider_id ? $provider_id : (string) terax_ai_setting( 'ai.provider' );
		$mode        = $mode ? $mode : (string) terax_ai_setting( 'ai.mode', 'byok' );

		// Managed-only plans (Praxis and up) never use an own key, whatever the saved setting says.
		if ( ! Features::can( 'byok' ) && Features::can( 'managed_ai' ) ) {
			$mode = 'managed';
		}

		if ( 'managed' === $mode ) {
			/**
			 * The provider for Managed AI. The free plugin has none: the Pro add-on returns one.
			 *
			 * @param ProviderInterface|null $provider    Provider or null.
			 * @param string                 $provider_id Vendor the proxy should use.
			 */
			$managed = Features::can( 'managed_ai' ) ? apply_filters( 'terax_ai_managed_provider', null, $provider_id ) : null;
			if ( ! $managed instanceof ProviderInterface ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not printed as HTML: the message is stored for the admin (the React admin escapes it) and sent in plain-text mails.
				throw new ProviderException( __( 'Managed AI is not included in your plan. Add your own API key or upgrade.', 'terax-ai-assistant' ), 402 );
			}
			return $managed;
		}

		$providers = $this->all();
		if ( ! isset( $providers[ $provider_id ] ) ) {
			/* translators: %s provider id */
			throw new ProviderException( sprintf( __( 'Unknown AI provider "%s".', 'terax-ai-assistant' ), $provider_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not printed as HTML: the message is stored for the admin (the React admin escapes it) and sent in plain-text mails.
		}

		$class = $providers[ $provider_id ]['class'];
		$key   = null !== $api_key && '' !== $api_key ? $api_key : \TeraxAI\Support\Settings::get_instance()->api_key( $provider_id );

		/**
		 * Filter the constructed provider (e.g. wrap it for logging or caching).
		 *
		 * @param ProviderInterface $provider    Provider.
		 * @param string            $provider_id Provider id.
		 */
		return apply_filters(
			'terax_ai_provider_instance',
			new $class( $provider_id, $key, array_merge( array( 'base_url' => terax_ai_setting( 'ai.base_url', '' ) ), array_filter( $options ) ) ),
			$provider_id
		);
	}
}
