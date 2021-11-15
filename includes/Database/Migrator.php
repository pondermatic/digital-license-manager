<?php

namespace IdeoLogix\DigitalLicenseManager\Database;

use IdeoLogix\DigitalLicenseManager\Setup;

defined( 'ABSPATH' ) || exit;

/**
 * Class Migrator
 * @package IdeoLogix\DigitalLicenseManager\Database
 */
class Migrator {

	/**
	 * The migraitons path
	 * @var string
	 */
	protected $path;

	/**
	 * The OLD database version
	 * @var string
	 */
	protected $oldVersion;

	/**
	 * The NEW database version
	 * @var string
	 */
	protected $newVersion;

	/**
	 * Migration mode UP
	 */
	const MODE_UP = 1;

	/**
	 * Migration mode Down
	 */
	const MODE_DOWN = 2;

	/**
	 * Migrator constructor.
	 *
	 * @param $path
	 * @param $oldVersion
	 * @param $newVersion
	 */
	public function __construct( $path, $oldVersion, $newVersion ) {
		$this->path       = $path;
		$this->oldVersion = $oldVersion;
		$this->newVersion = $newVersion;
	}

	/**
	 * Performs a database upgrade.
	 */
	public function up() {
		$migrationMode = self::MODE_UP;
		$regExFileName = '/(\d{14})_(.*?)_(.*?)\.php/';
		foreach ( glob( $this->path ) as $fileName ) {
			if ( 'index.php' === $fileName ) {
				continue;
			}
			if ( preg_match( $regExFileName, basename( $fileName ), $match ) ) {
				$fileBasename    = $match[0];
				$fileDateTime    = $match[1];
				$fileVersion     = $match[2];
				$fileDescription = $match[3];
				global $wpdb;
				if ( ( (int) $fileVersion <= $this->newVersion ) && (int) $fileVersion > $this->oldVersion ) {
					require_once $fileName;
				}
			}
		}

		update_option( 'dlm_db_version', $this->newVersion, true );
	}

	/**
	 * Performs a database downgrade (Currently not in use).
	 */
	public function down() {
		$migrationMode = self::MODE_DOWN;
		// TODO: Not implemented.
	}

	/**
	 * Run the database migrator
	 */
	public function run() {
		if ( $this->oldVersion < $this->newVersion ) {
			$this->up();
		} else if ( $this->oldVersion > $this->newVersion ) {
			$this->down();
		}
	}
}
