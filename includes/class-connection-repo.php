<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Red_Headed_Connection_Repo — reusable, named, testable destination connections.
 *
 * PHASE 1 of the "reusable connections" refactor (see SPEC-red-headed-reusable-connections.md).
 * A connection holds a destination's transport config ONCE (host/port/user/password/path for SFTP,
 * etc.), secrets encrypted at rest. Profiles reference a connection by id + optional non-secret
 * overrides — they never re-store the password. This kills the "password wiped on every profile
 * save" bug at the root (the secret lives here, entered & tested once).
 *
 * Backend only in Phase 1: table + CRUD + secret encryption (preserve-on-blank) + test().
 * UI (Settings manager, profile multi-select) = Phases 2-3, see spec.
 *
 * @package Red_Headed_Pro
 */
class Red_Headed_Connection_Repo {

	const DB_VERSION = 1;

	/** Secret field names per destination type — encrypted at rest, preserved when blank. */
	protected static function secret_keys( $type ) {
		switch ( $type ) {
			case 'sftp':   return array( 'pass' );
			case 'gdrive': return array( 'access_token', 'refresh_token' );
			case 'rest':   return array( 'token', 'secret', 'auth_pass' );
			default:       return array();
		}
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rh_connections';
	}

	/** Create the table. Call from the installer (idempotent via dbDelta). */
	public static function maybe_create_table() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			type VARCHAR(40) NOT NULL DEFAULT '',
			config LONGTEXT NULL,
			created_at DATETIME NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY type (type)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function all() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY name ASC', ARRAY_A );
		return array_map( array( __CLASS__, 'decode' ), (array) $rows );
	}

	/**
	 * Connections for UI/client display — secrets stripped, with a per-secret "is set"
	 * flag so the form can show "•••• saved (leave blank to keep)". Never exposes a secret.
	 */
	public static function public_list() {
		$out = array();
		foreach ( self::all() as $c ) {
			$cfg = is_array( $c['config'] ) ? $c['config'] : array();
			$has = array();
			foreach ( self::secret_keys( $c['type'] ) as $k ) {
				$has[ $k ] = ! empty( $cfg[ $k . '_enc' ] );
				unset( $cfg[ $k ], $cfg[ $k . '_enc' ] );
			}
			$out[] = array(
				'id'         => (int) $c['id'],
				'name'       => $c['name'],
				'type'       => $c['type'],
				'config'     => $cfg,
				'has_secret' => $has,
			);
		}
		return $out;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A );
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Create or update a connection. Secrets are encrypted at rest; a blank secret on update
	 * PRESERVES the stored one ("leave blank = unchanged"), never wipes it.
	 *
	 * @param array $data { id?, name, type, config:{...} }
	 * @return int|\WP_Error  Connection id.
	 */
	public static function save( $data ) {
		global $wpdb;
		$type   = sanitize_key( $data['type'] ?? '' );
		if ( $type === '' ) return new \WP_Error( 'rh_conn_type', __( 'Connection type is required.', 'red-headed-pro' ) );
		$config = is_array( $data['config'] ?? null ) ? $data['config'] : array();
		$id     = ! empty( $data['id'] ) ? (int) $data['id'] : 0;

		$config = self::encrypt_secrets( $config, $type, $id );

		$row = array(
			'name'       => sanitize_text_field( $data['name'] ?? 'Untitled connection' ),
			'type'       => $type,
			'config'     => wp_json_encode( $config ),
			'updated_at' => current_time( 'mysql' ),
		);
		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => $id ) );
			return $id;
		}
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Resolve a connection's config for delivery, merged with per-profile non-secret overrides.
	 * Returns a destination config array ready for the dispatcher (type + decrypt-on-use fields).
	 *
	 * @param int   $id        Connection id.
	 * @param array $overrides Non-secret per-profile overrides (path, filename_pattern, subject…).
	 * @return array|null
	 */
	public static function resolve_for_delivery( $id, $overrides = array() ) {
		$conn = self::get( $id );
		if ( ! $conn ) return null;
		$config         = is_array( $conn['config'] ) ? $conn['config'] : array();
		$config['type'] = $conn['type'];
		/* Overrides may only touch non-secret keys. */
		$secret = self::secret_keys( $conn['type'] );
		foreach ( (array) $overrides as $k => $v ) {
			if ( in_array( $k, $secret, true ) || $k === 'pass_enc' ) continue;
			$config[ $k ] = $v;
		}
		return $config;
	}

	/**
	 * Test a connection live. Returns true|WP_Error with a human message.
	 * SFTP: connect + auth + check the remote path is reachable. Email: send a test message.
	 */
	public static function test( $id ) {
		$conn = self::get( $id );
		if ( ! $conn ) return new \WP_Error( 'rh_conn_404', __( 'Connection not found.', 'red-headed-pro' ) );
		$type = $conn['type'];
		$c    = is_array( $conn['config'] ) ? $conn['config'] : array();

		if ( 'sftp' === $type ) {
			$host = sanitize_text_field( $c['host'] ?? '' );
			$port = (int) ( $c['port'] ?? 22 );
			$user = sanitize_text_field( $c['user'] ?? '' );
			$pass = isset( $c['pass_enc'] ) ? Red_Headed_Destination_Base::decrypt( $c['pass_enc'] ) : (string) ( $c['pass'] ?? '' );
			$dir  = rtrim( sanitize_text_field( $c['path'] ?? '/' ), '/' );
			if ( '' === $host || '' === $user ) return new \WP_Error( 'rh_conn_cfg', __( 'Missing SFTP host or user.', 'red-headed-pro' ) );
			if ( ! class_exists( '\phpseclib3\Net\SFTP' ) ) {
				return new \WP_Error( 'rh_conn_lib', __( 'phpseclib3 not available on this server.', 'red-headed-pro' ) );
			}
			try {
				$sftp = new \phpseclib3\Net\SFTP( $host, $port );
				if ( ! $sftp->login( $user, $pass ) ) {
					return new \WP_Error( 'rh_conn_auth', __( 'Connected, but authentication failed (check user / password / port).', 'red-headed-pro' ) );
				}
				$probe = $dir === '' ? '.' : $dir;
				if ( false === $sftp->stat( $probe ) ) {
					return new \WP_Error( 'rh_conn_path', sprintf(
						/* translators: %s remote path */
						__( 'Authenticated, but the remote path "%s" does not exist or is not reachable for this user (try a relative path without a leading slash).', 'red-headed-pro' ),
						$dir
					) );
				}
				return true;
			} catch ( \Throwable $e ) {
				return new \WP_Error( 'rh_conn_ex', sprintf( __( 'Connection error: %s', 'red-headed-pro' ), $e->getMessage() ) );
			}
		}

		if ( 'email' === $type ) {
			$to = sanitize_email( $c['to'] ?? ( $c['from_email'] ?? '' ) );
			if ( ! is_email( $to ) ) return new \WP_Error( 'rh_conn_email', __( 'No valid test recipient.', 'red-headed-pro' ) );
			$ok = wp_mail( $to, 'Red-Headed — connection test', 'This is a Red-Headed connection test email.' );
			return $ok ? true : new \WP_Error( 'rh_conn_mail', __( 'wp_mail() returned false — check your mailer.', 'red-headed-pro' ) );
		}

		/* local_folder / others: a no-op "ok" for now (Phase 2 fills the rest). */
		return true;
	}

	/* ── internals ─────────────────────────────────────────────────────── */

	/** Encrypt secret fields; preserve the stored encrypted secret when the submitted value is blank. */
	protected static function encrypt_secrets( $config, $type, $id ) {
		$prev = $id ? self::get( $id ) : null;
		$prev_cfg = ( $prev && is_array( $prev['config'] ) ) ? $prev['config'] : array();
		foreach ( self::secret_keys( $type ) as $k ) {
			$plain = isset( $config[ $k ] ) ? (string) $config[ $k ] : '';
			$enc_k = $k . '_enc';
			if ( '' !== $plain ) {
				$config[ $enc_k ] = Red_Headed_Destination_Base::encrypt( $plain );
			} elseif ( ! empty( $prev_cfg[ $enc_k ] ) ) {
				$config[ $enc_k ] = $prev_cfg[ $enc_k ]; // blank = keep stored secret
			}
			unset( $config[ $k ] ); // never persist the plaintext
		}
		return $config;
	}

	protected static function decode( $row ) {
		if ( ! is_array( $row ) ) return $row;
		$row['config'] = ! empty( $row['config'] ) ? ( json_decode( $row['config'], true ) ?: array() ) : array();
		return $row;
	}
}
