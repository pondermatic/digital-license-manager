<?php


namespace IdeoLogix\DigitalLicenseManager\Controllers;

use IdeoLogix\DigitalLicenseManager\Abstracts\SettingsFields;
use IdeoLogix\DigitalLicenseManager\Abstracts\Singleton;
use IdeoLogix\DigitalLicenseManager\Database\Models\Resources\ApiKey as ApiKeyResourceModel;
use IdeoLogix\DigitalLicenseManager\Database\Repositories\Resources\ApiKey as ApiKeyResourceRepository;
use IdeoLogix\DigitalLicenseManager\Database\Repositories\Users;
use IdeoLogix\DigitalLicenseManager\Enums\PageSlug;
use IdeoLogix\DigitalLicenseManager\Integrations\WooCommerce\Stock;
use IdeoLogix\DigitalLicenseManager\ListTables\ApiKeys;

/**
 * Class Settings
 * @package IdeoLogix\DigitalLicenseManager\Controllers
 */
class Settings extends Singleton {

	use SettingsFields;

	/**
	 * Settings constructor.
	 */
	public function __construct() {
		add_action( 'dlm_settings_sanitized', array( $this, 'afterSanitize' ), 10, 2 );
	}

	/**
	 * The settings url
	 * @return string|void
	 */
	public static function getSettingsUrl() {
		return admin_url( sprintf( 'admin.php?page=%s', PageSlug::SETTINGS ) );
	}

	/**
	 * List of tabs
	 * @return mixed|void
	 */
	public function all() {

		$baseUrl = self::getSettingsUrl();
		$tabList = apply_filters( 'dlm_settings_fields', array(
			'general'  => array(
				'name'              => __( 'General', 'digital-license-manager' ),
				'slug'              => 'general',
				'url'               => add_query_arg( 'tab', 'general', $baseUrl ),
				'priority'          => 10,
				'sanitize_callback' => array( $this, 'sanitizeGeneral' ),
				'sections'          => array(
					'licenses' => array(
						'name'     => __( 'Licenses', 'digital-license-manager' ),
						'page'     => 'licenses',
						'priority' => 10,
						'fields'   => array(
							10 => array(
								'id'       => 'hide_license_keys',
								'title'    => __( 'Obscure licenses', 'digital-license-manager' ),
								'callback' => array( $this, 'fieldCheckbox' ),
								'args'     => array(
									'label'   => __( 'Hide license keys in the admin dashboard.', 'digital-license-manager' ),
									'explain' => __( "All license keys will be hidden and only displayed when the 'Show' action is clicked.", 'digital-license-manager' ),
								)
							),
							40 => array(
								'id'       => 'allow_duplicates',
								'title'    => __( 'Duplicate license keys', 'digital-license-manager' ),
								'callback' => array( $this, 'fieldCheckbox' ),
								'args'     => array(
									'label'   => __( 'Allow duplicate license keys inside the licenses database table.', 'digital-license-manager' ),
									'explain' => __( 'If enabled the system will store new license keys in the database, even if the same key exist.', 'digital-license-manager' ),
								)
							),
						)
					),
					'rest_api' => array(
						'name'     => __( 'REST API', 'digital-license-manager' ),
						'page'     => 'rest_api',
						'priority' => 20,
						'fields'   => array(
							10 => array(
								'id'       => 'disable_api_ssl',
								'title'    => __( 'API & SSL', 'digital-license-manager' ),
								'callback' => array( $this, 'fieldCheckbox' ),
								'args'     => array(
									'label'   => __( "Enable the plugin API routes over insecure HTTP connections.", 'digital-license-manager' ),
									'explain' => __( "This should only be activated for development purposes.", 'digital-license-manager' ),
								)
							)
						)
					),
					'other'    => array(
						'name'     => __( 'Other', 'digital-license-manager' ),
						'page'     => 'other',
						'priority' => 30,
						'fields'   => array(
							10 => array(
								'id'       => 'safeguard_data',
								'title'    => __( 'Data safety', 'digital-license-manager' ),
								'callback' => array( $this, 'fieldCheckbox' ),
								'args'     => array(
									'label'   => __( "Enable this option to safe guard the data on plugin removal/uninstallation.", 'digital-license-manager' ),
									'explain' => __( "If enabled your data will NOT be removed once this plugin is uninstalled. This is usually prefered option in case you want to use the plugin again in future.", 'digital-license-manager' ),
								)
							)
						),
					)
				),
			),
			'rest_api' => array(
				'slug'     => 'rest_api',
				'name'     => __( 'API Keys', 'digital-license-manager' ),
				'url'      => add_query_arg( 'tab', 'rest_api', $baseUrl ),
				'priority' => 20,
				'callback' => array( $this, 'renderRestApi' ),
			),

		), $baseUrl );

		uasort( $tabList, array( $this, 'prioritySort' ) );

		foreach ( $tabList as $i => $tab ) {
			if ( isset( $tab['sections'] ) && is_array( $tab['sections'] ) && count( $tab['sections'] ) > 1 ) {
				$sections = $tab['sections'];
				uasort( $sections, array( $this, 'prioritySort' ) );
				$tabList[ $i ]['sections'] = $sections;
			}
		}

		return $tabList;

	}

	/**
	 * Render rest api keys
	 */
	public function renderRestApi() {

		if ( isset( $_GET['create_key'] ) ) {
			$action = 'create';
		} elseif ( isset( $_GET['edit_key'] ) ) {
			$action = 'edit';
		} elseif ( isset( $_GET['show_key'] ) ) {
			$action = 'show';
		} else {
			$action = 'list';
		}

		switch ( $action ) {
			case 'create':
			case 'edit':

				$cap = isset( $_GET['create_key'] ) && (int) $_GET['create_key'] ? 'dlm_create_api_keys' : 'dlm_edit_api_keys';

				if ( ! current_user_can( $cap ) ) {
					wp_die(
						esc_html__(
							'You do not have permission to edit this API Key',
							'digital-license-manager'
						)
					);
				}

				$keyId   = 0;
				$keyData = new ApiKeyResourceModel();
				$userId  = null;
				$date    = null;

				if ( array_key_exists( 'edit_key', $_GET ) ) {
					$keyId = absint( $_GET['edit_key'] );
				}

				if ( $keyId !== 0 ) {
					/** @var ApiKeyResourceModel $keyData */
					$keyData     = ApiKeyResourceRepository::instance()->find( $keyId );
					$userId      = (int) $keyData->getUserId();
					$date_format = get_option( 'date_format' );
					$time_format = get_option( 'time_formt' );
					$date        = sprintf(
						esc_html__( '%1$s at %2$s', 'digital-license-manager' ),
						date_i18n( $date_format, strtotime( $keyData->getLastAccess() ) ),
						date_i18n( $time_format, strtotime( $keyData->getLastAccess() ) )
					);
				}

				$users       = Users::getUsers();
				$permissions = array(
					'read'       => __( 'Read', 'digital-license-manager' ),
					'write'      => __( 'Write', 'digital-license-manager' ),
					'read_write' => __( 'Read/Write', 'digital-license-manager' ),
				);
				break;
			case 'list':
				if ( ! current_user_can( 'dlm_read_api_keys' ) ) {
					wp_die(
						esc_html__(
							'You do not have permission to view this API Key',
							'digital-license-manager'
						)
					);
				}
				$keys = new ApiKeys();
				break;
			case 'show':
				if ( ! current_user_can( 'dlm_read_api_keys' ) ) {
					wp_die(
						esc_html__(
							'You do not have permission to view this API Key',
							'digital-license-manager'
						)
					);
				}
				$keyData     = get_transient( 'dlm_api_key' );
				$consumerKey = get_transient( 'dlm_consumer_key' );

				delete_transient( 'dlm_api_key' );
				delete_transient( 'dlm_consumer_key' );
				break;
		}

		if ( 'list' === $action ) {
			include_once DLM_TEMPLATES_DIR . 'admin/settings/page-list.php';
		} else if ( 'show' === $action ) {
			include_once DLM_TEMPLATES_DIR . 'admin/settings/page-show.php';
		} else {
			include_once DLM_TEMPLATES_DIR . 'admin/settings/page-edit.php';
		}
	}


	/**
	 * Render tab
	 *
	 * @param $tab
	 */
	public function renderTab( $tab ) {

		if ( isset( $tab['callback'] ) && is_callable( $tab['callback'] ) ) {
			call_user_func( $tab['callback'] );
		} else {
			echo '<form action="' . admin_url( 'options.php' ) . '" method="POST">';
			settings_fields( sprintf( 'dlm_settings_%s_group', $tab['slug'] ) );
			$sections = isset( $tab['sections'] ) ? $tab['sections'] : array();
			foreach ( $sections as $page => $section ) {
				do_settings_sections( 'dlm_' . $page );
			}
			submit_button();
			echo '</form>';
		}

	}

	/**
	 * Render the navigation
	 */
	public function render() {

		$currentTab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

		if ( $currentTab == 'rest_api' ) {
			// Add screen option.
			add_screen_option(
				'per_page',
				array(
					'default' => 10,
					'option'  => 'dlm_keys_per_page',
				)
			);
		}

		echo '<div class="wrap dlm">';
		settings_errors();
		echo '<nav class="dlm-nav nav-tab-wrapper woo-nav-tab-wrapper">';
		foreach ( $this->all() as $tab ) {
			$url     = $tab['url'];
			$classes = isset( $tab['slug'] ) && $currentTab === $tab['slug'] ? 'nav-tab-active' : '';
			echo sprintf( '<a href="%s" class="nav-tab %s">%s</a>', esc_url( $url ), esc_attr( $classes ), esc_attr( $tab['name'] ) );
		}
		echo '</nav>';
		echo '<div class="dlm-main">';
		foreach ( $this->all() as $tab ) {
			if ( isset( $tab['slug'] ) && $tab['slug'] === $currentTab ) {
				$this->renderTab( $tab );
			}
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Register the settings.
	 */
	public function register() {

		$settings = array();

		foreach ( $this->all() as $tab ) {

			$slug        = $tab['slug'];
			$option_name = 'dlm_settings_' . $slug;

			/**
			 * Register option group
			 */
			$args = array();
			if ( isset( $tab['sanitize_callback'] ) && is_callable( $tab['sanitize_callback'] ) ) {
				$args['sanitize_callback'] = $tab['sanitize_callback'];
			}
			register_setting( sprintf( 'dlm_settings_%s_group', $slug ), $option_name, $args );
		}

		foreach ( $this->all() as $tab ) {

			$slug        = $tab['slug'];
			$option_name = 'dlm_settings_' . $slug;

			/**
			 * Validate sections
			 */
			if ( ! isset( $tab['sections'] ) || ! is_array( $tab['sections'] ) ) {
				continue;
			}

			/**
			 * Load options if not loaded.
			 */
			if ( ! isset( $settings[ $slug ] ) ) {
				$settings[ $slug ] = get_option( $option_name );
			}

			/**
			 * Loop over the sections add the settings
			 */
			foreach ( $tab['sections'] as $page => $section ) {
				$section_fields = isset( $section['fields'] ) ? $section['fields'] : array();
				if ( ! empty( $section_fields ) ) {
					ksort( $section_fields );
				}
				$section_name = isset( $section['name'] ) ? $section['name'] : '';
				$section_page = 'dlm_' . $page;
				$section_slug = sprintf( '%s_section', $page );
				add_settings_section(
					$section_slug,
					$section_name,
					null,
					$section_page
				);
				foreach ( $section_fields as $field ) {
					$field_callback      = isset( $field['callback'] ) && is_callable( $field['callback'] ) ? $field['callback'] : null;
					$field_args          = isset( $field['args'] ) ? $field['args'] : array();
					$field_args['key']   = $option_name;
					$field_args['field'] = $field['id'];
					$field_args['value'] = isset( $settings[ $slug ][ $field['id'] ] ) ? $settings[ $slug ][ $field['id'] ] : null;
					if ( ! is_null( $field_callback ) ) {
						add_settings_field(
							$field['id'],
							$field['title'],
							$field_callback,
							'dlm_' . $page,
							$section_slug,
							$field_args
						);
					}
				}
			}
		}
	}

	/**
	 * Sanitizes the settings input.
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function sanitizeGeneral( $settings ) {

		do_action( 'dlm_settings_sanitized', $settings );

		return $settings;
	}


	/**
	 * Priority sorting two arrays.
	 *
	 * @param $arr1
	 * @param $arr2
	 *
	 * @return int
	 */
	public function prioritySort( $arr1, $arr2 ) {

		$a = isset( $arr1['priority'] ) ? (int) $arr1['priority'] : 0;
		$b = isset( $arr2['priority'] ) ? (int) $arr2['priority'] : 0;

		if ( $a === $b ) {
			return 0;
		}

		return ( $a < $b ) ? - 1 : 1;

	}

	/**
	 * Fired after settings sanitization
	 * - Flush rewrite rules when "My Account" - Licenses page is enabled.
	 * @return void
	 */
	public function afterSanitize( $settings ) {
		if ( isset( $settings['myaccount_endpoint'] ) ) {
			flush_rewrite_rules( true );
		}
	}

}
