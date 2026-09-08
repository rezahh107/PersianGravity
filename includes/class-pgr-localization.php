<?php
/**
 * Request-driven, explicitly scoped fa_IR catalog overlay.
 *
 * @package PersianGravity
 */

defined( 'ABSPATH' ) || exit;

/** Shared core; standard products are declarative data. */
final class PGR_Localization {
	/**
	 * Approved domain data.
	 *
	 * @var array
	 */
	private $products;

	/**
	 * Provider-owned generated catalog root.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Targeted PHP re-entry guards, by domain.
	 *
	 * @var array
	 */
	private $loading = array();

	/**
	 * Request-local JSON read cache. No directory scan.
	 *
	 * @var array
	 */
	private $json = array();

	/**
	 * Construct without loading any translations or optional vendor classes.
	 *
	 * @param array  $products Explicit domain manifests.
	 * @param string $root     Provider artifact root.
	 */
	public function __construct( array $products, string $root ) {
		$this->products = $products;
		$this->root     = rtrim( $root, '/' );
	}

	/** Register synchronously from the main plugin file. */
	public function hooks() {
		add_filter( 'lang_dir_for_domain', array( $this, 'discover' ), 10, 3 );
		add_filter( 'load_translation_file', array( $this, 'php_file' ), 10, 3 );
		add_filter( 'load_script_translations', array( $this, 'script_content' ), 10, 4 );
		add_filter( 'pre_load_script_translations', array( $this, 'script_fallback' ), 10, 4 );
	}

	/**
	 * Preserve upstream registry information; supply a directory only if absent.
	 *
	 * @param string|false $path   Core's original directory.
	 * @param string       $domain Requested domain.
	 * @param string       $locale Requested locale.
	 * @return string|false
	 */
	public function discover( $path, $domain, $locale ) {
		if ( $path || ! $this->catalog( $domain, $locale ) ) {
			return $path;
		}

		return dirname( $this->catalog( $domain, $locale ) ) . '/';
	}

	/**
	 * Load provider first, then let Core load the original upstream file.
	 *
	 * Core's translation controller resolves the first loaded value. Guarded
	 * load_textdomain() is necessary to establish both its runtime object and
	 * first-provider success, even when Core's original file does not exist.
	 * No unload or replacement of an already-loaded foreign domain occurs.
	 *
	 * @param string $file   Actual PHP/MO candidate supplied by Core.
	 * @param string $domain Requested domain.
	 * @param string $locale Requested locale.
	 * @return string
	 */
	public function php_file( $file, $domain, $locale ) {
		$provider = $this->catalog( $domain, $locale );
		if ( ! $provider || isset( $this->loading[ $domain ] ) ) {
			return $file;
		}

		$this->loading[ $domain ] = true;
		try {
			$loaded = load_textdomain( $domain, $this->base( $domain ) . '.mo', $locale );
		} finally {
			unset( $this->loading[ $domain ] );
		}

		if ( ! $loaded ) {
			return $file;
		}

		// Data-only prefix mapping applies solely to Core's exact JIT filename.
		$prefix = $this->products[ $domain ]['prefix'];
		$suffix = str_ends_with( $file, '.l10n.php' ) ? '.l10n.php' : '.mo';
		if ( $prefix !== $domain && basename( $file ) === $domain . '-fa_IR' . $suffix ) {
			$mapped = dirname( $file ) . '/' . $prefix . '-fa_IR' . $suffix;
			if ( is_readable( $mapped ) ) {
				$file = $mapped;
			}
		}

		if ( is_readable( $file ) ) {
			return $file;
		}

		// Do not mask Core's second (.mo) attempt with a provider-only success.
		if ( '.l10n.php' === $suffix ) {
			$mo = substr( $file, 0, -9 ) . '.mo';
			if ( is_readable( $mo ) || is_readable( dirname( $file ) . '/' . $prefix . '-fa_IR.mo' ) ) {
				return $file;
			}
		}

		return '.mo' === $suffix && is_readable( $this->base( $domain ) . '.mo' ) ? $this->base( $domain ) . '.mo' : $provider;
	}

	/**
	 * Overlay only a real, readable upstream script result.
	 *
	 * @param string $translations Upstream JSON.
	 * @param string $file         Upstream path (not replaced).
	 * @param string $handle       Registered handle.
	 * @param string $domain       Registered domain.
	 * @return string|false
	 */
	public function script_content( $translations, $file, $handle, $domain ) {
		unset( $file );
		$provider = $this->script_catalog( $handle, $domain );
		if ( false === $provider ) {
			return $translations;
		}

		return self::merge_json( $translations, $provider, $domain );
	}

	/**
	 * Core 6.7 calls this with file=false AFTER exhausting upstream candidates.
	 * Returning a provider for an earlier missing file would suppress later
	 * hashed/custom-directory upstream catalogs and break partial fallback.
	 *
	 * @param string|false|null $translations Earlier short-circuit, if any.
	 * @param string|false      $file         Core candidate or final false sentinel.
	 * @param string            $handle       Registered handle.
	 * @param string            $domain       Registered domain.
	 * @return string|false|null
	 */
	public function script_fallback( $translations, $file, $handle, $domain ) {
		if ( null !== $translations || false !== $file ) {
			return $translations;
		}

		$provider = $this->script_catalog( $handle, $domain );
		return false === $provider ? null : self::merge_json( false, $provider, $domain );
	}

	/**
	 * Compose valid Jed messages, retaining context keys and plural arrays.
	 * Invalid upstream returns false so Core can try its next candidate.
	 * Incompatible plural rules retain upstream rather than reinterpret counts.
	 *
	 * @param string|false $upstream Upstream JSON or no upstream.
	 * @param string       $provider Provider JSON.
	 * @param string       $domain   Requested domain.
	 * @return string|false
	 */
	public static function merge_json( $upstream, $provider, $domain ) {
		$local  = self::jed( $provider, $domain );
		$remote = self::jed( $upstream, $domain );
		if ( false === $local ) {
			return $upstream;
		}
		if ( false === $remote ) {
			return false === $upstream ? wp_json_encode( $local ) : false;
		}

		$local_messages  = $local['locale_data'][ $domain ];
		$remote_messages = $remote['locale_data'][ $domain ];
		$local_rule      = preg_replace( '/\s+/', '', $local_messages['']['plural_forms'] );
		$remote_rule     = preg_replace( '/\s+/', '', $remote_messages['']['plural_forms'] );
		if ( $local_rule !== $remote_rule ) {
			return $upstream;
		}

		$metadata                         = array_replace( $remote_messages[''], $local_messages[''] );
		$messages                         = array_replace( $remote_messages, $local_messages );
		$messages['']                     = $metadata;
		$remote['locale_data'][ $domain ] = $messages;
		return wp_json_encode( $remote );
	}

	/**
	 * Decode only the bounded Jed shape consumed by WP_Scripts.
	 *
	 * @param string|false $json   JSON content.
	 * @param string       $domain Expected domain.
	 * @return array|false
	 */
	private static function jed( $json, $domain ) {
		if ( ! is_string( $json ) ) {
			return false;
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['locale_data'] ) || ! is_array( $data['locale_data'] ) ) {
			return false;
		}
		$messages = $data['locale_data'][ $domain ] ?? $data['locale_data']['messages'] ?? null;
		if ( ! is_array( $messages ) || ! isset( $messages['']['plural_forms'] ) || ! is_string( $messages['']['plural_forms'] ) ) {
			return false;
		}
		foreach ( $messages as $key => $value ) {
			if ( '' === $key ) {
				continue;
			}
			if ( ! is_array( $value ) || ! array_is_list( $value ) || array() === $value ) {
				return false;
			}
			foreach ( $value as $translation ) {
				if ( ! is_string( $translation ) ) {
					return false;
				}
			}
			if ( in_array( '', $value, true ) ) {
				unset( $messages[ $key ] );
			}
		}
		unset( $data['locale_data']['messages'] );
		$data['locale_data'][ $domain ] = $messages;
		return $data;
	}

	/**
	 * Resolve a single approved handle/domain pair for the effective locale.
	 *
	 * @param string $handle Requested script handle.
	 * @param string $domain Requested script domain.
	 * @return string|false
	 */
	private function script_catalog( $handle, $domain ) {
		if ( ! isset( $this->products[ $domain ]['scripts'][ $handle ] ) || 'fa_IR' !== determine_locale() ) {
			return false;
		}
		$file = dirname( $this->base( $domain ) ) . '/' . $domain . '-fa_IR-' . $handle . '.json';
		if ( ! array_key_exists( $file, $this->json ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local provider-owned file, never a URL.
			$this->json[ $file ] = is_readable( $file ) ? file_get_contents( $file ) : false;
		}
		return $this->json[ $file ];
	}

	/**
	 * Provider base path from trusted manifest data only.
	 *
	 * @param string $domain Managed domain.
	 * @return string
	 */
	private function base( $domain ) {
		$product = $this->products[ $domain ];
		return $this->root . '/' . $product['product'] . '/' . $product['prefix'] . '-fa_IR';
	}

	/**
	 * Resolve PHP/MO artifacts without reading/compiling PO at runtime.
	 *
	 * @param string $domain Requested domain.
	 * @param string $locale Requested locale.
	 * @return string|false
	 */
	private function catalog( $domain, $locale ) {
		if ( 'fa_IR' !== $locale || ! isset( $this->products[ $domain ] ) ) {
			return false;
		}
		$base = $this->base( $domain );
		foreach ( array( '.l10n.php', '.mo' ) as $suffix ) {
			if ( is_readable( $base . $suffix ) ) {
				return $base . $suffix;
			}
		}
		return false;
	}
}
