<?php

namespace IdeoLogix\DigitalLicenseManager\RestAPI\Controllers;

use IdeoLogix\DigitalLicenseManager\Abstracts\RestController;
use IdeoLogix\DigitalLicenseManager\Enums\LicenseSource;
use IdeoLogix\DigitalLicenseManager\Database\Repositories\Resources\LicenseActivation as LicenseActivationResourceRepository;
use IdeoLogix\DigitalLicenseManager\Database\Models\Resources\LicenseActivation as LicenseActivationResourceModel;
use IdeoLogix\DigitalLicenseManager\Utils\Data\License as LicenseUtil;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Licenses
 * @package IdeoLogix\DigitalLicenseManager\RestAPI\V1
 */
class Licenses extends RestController {

	/**
	 * @var string
	 */
	protected $namespace = 'dlm/v1';

	/**
	 * @var string
	 */
	protected $rest_base = '/licenses';

	/**
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Licenses constructor.
	 */
	public function __construct() {
		$this->settings = (array) get_option( 'dlm_settings_general', array() );
	}

	/**
	 * Register all the needed routes for this resource.
	 */
	public function register_routes() {
		/**
		 * GET licenses
		 *
		 * Retrieves all the available licenses from the database.
		 */
		register_rest_route(
			$this->namespace, $this->rest_base, array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getLicenses' ),
					'permission_callback' => array( $this, 'permissionCallback' )
				)
			)
		);

		/**
		 * GET licenses/{license_key}
		 *
		 * Retrieves a single licenses from the database.
		 */
		register_rest_route(
			$this->namespace, $this->rest_base . '/(?P<license_key>[\w-]+)', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getLicense' ),
					'permission_callback' => array( $this, 'permissionCallback' ),
					'args'                => array(
						'license_key' => array(
							'description' => 'License Key',
							'type'        => 'string',
						)
					)
				)
			)
		);

		/**
		 * POST licenses
		 *
		 * Creates a new license in the database
		 */
		register_rest_route(
			$this->namespace, $this->rest_base, array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'createLicense' ),
					'permission_callback' => array( $this, 'permissionCallback' )
				)
			)
		);

		/**
		 * PUT licenses/{license_key}
		 *
		 * Updates an already existing license in the database
		 */
		register_rest_route(
			$this->namespace, $this->rest_base . '/(?P<license_key>[\w-]+)', array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'updateLicense' ),
					'permission_callback' => array( $this, 'permissionCallback' ),
					'args'                => array(
						'license_key' => array(
							'description' => 'License Key',
							'type'        => 'string',
						),
					),
				)
			)
		);

		/**
		 * GET licenses/activate/{license_key}
		 *
		 * Activates a license key
		 */
		register_rest_route(
			$this->namespace, $this->rest_base . '/activate/(?P<license_key>[\w-]+)', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'activateLicense' ),
					'permission_callback' => array( $this, 'permissionCallback' ),
					'args'                => array(
						'license_key' => array(
							'description' => 'License Key',
							'type'        => 'string',
						),
					),
				)
			)
		);

		/**
		 * GET licenses/deactivate/{activation_token}
		 *
		 * Deactivates a license key
		 */
		register_rest_route(
			$this->namespace, $this->rest_base . '/deactivate/(?P<activation_token>[\w-]+)', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'deactivateLicense' ),
					'permission_callback' => array( $this, 'permissionCallback' ),
					'args'                => array(
						'license_key' => array(
							'description' => 'License Key',
							'type'        => 'string'
						)
					)
				)
			)
		);

		/**
		 * PUT licenses/validate/{activation_token}
		 *
		 * Validates a license key
		 */
		register_rest_route(
			$this->namespace, $this->rest_base . '/validate/(?P<activation_token>[\w-]+)', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'validateLicense' ),
					'permission_callback' => array( $this, 'permissionCallback' ),
					'args'                => array(
						'license_key' => array(
							'description' => 'License Key',
							'type'        => 'string',
						),
					),
				)
			)
		);
	}

	/**
	 * Callback for the GET licenses route. Retrieves all license keys from the database.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function getLicenses() {

		if ( ! $this->isRouteEnabled( $this->settings, '010' ) ) {
			return $this->routeDisabledError();
		}

		if ( ! $this->capabilityCheck( 'dlm_read_licenses' ) ) {
			return $this->responseError(
				'cannot_view',
				__( 'Sorry, you cannot list resources.', 'digital-license-manager' ),
				array(
					'status' => $this->authorizationRequiredCode()
				)
			);
		}

		$licenses = LicenseUtil::get();
		$prepared = array();

		foreach ( $licenses as $license ) {
			$prepared[] = self::prepareLicense( $license );
		}

		return $this->response( true, $prepared, 200, 'v1/licenses' );
	}

	/**
	 * Callback for the GET licenses/{license_key} route. Retrieves a single license key from the database.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function getLicense( WP_REST_Request $request ) {

		if ( ! $this->isRouteEnabled( $this->settings, '011' ) ) {
			return $this->routeDisabledError();
		}

		if ( ! $this->capabilityCheck( 'dlm_read_licenses' ) ) {
			return $this->responseError(
				'cannot_view',
				__( 'Sorry, you cannot view this resource.', 'digital-license-manager' ),
				array(
					'status' => $this->authorizationRequiredCode()
				)
			);
		}

		$licenseKey = sanitize_text_field( $request->get_param( 'license_key' ) );
		$license    = LicenseUtil::find( $licenseKey );
		if ( is_wp_error( $license ) ) {
			return $this->maybeErrorResponse( $license );
		}
		$licenseData = self::prepareLicense( $license, true );

		return $this->response( true, $licenseData, 200, 'v1/licenses/{license_key}' );
	}

	/**
	 * Callback for the POST licenses route. Creates a new license key in the
	 * database.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function createLicense( WP_REST_Request $request ) {

		if ( ! $this->isRouteEnabled( $this->settings, '012' ) ) {
			return $this->routeDisabledError();
		}

		if ( ! $this->capabilityCheck( 'dlm_create_licenses' ) ) {
			return $this->responseError(
				'cannot_create',
				__( 'Sorry, you are not allowed to create resources.', 'digital-license-manager' ),
				array(
					'status' => $this->authorizationRequiredCode()
				)
			);
		}

		$body = $request->get_params();

		$orderId           = isset( $body['order_id'] ) ? absint( $body['order_id'] ) : null;
		$productId         = isset( $body['product_id'] ) ? absint( $body['product_id'] ) : null;
		$userId            = isset( $body['user_id'] ) ? absint( $body['user_id'] ) : null;
		$licenseKey        = isset( $body['license_key'] ) ? sanitize_text_field( $body['license_key'] ) : null;
		$validFor          = isset( $body['valid_for'] ) ? absint( $body['valid_for'] ) : null;
		$expiresAt         = isset( $body['expires_at'] ) ? sanitize_text_field( $body['expires_at'] ) : null;
		$activationsLimit = isset( $body['activations_limit'] ) ? absint( $body['activations_limit'] ) : null;
		$status            = isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : null;

		$license = LicenseUtil::create( $licenseKey, array(
			'order_id'            => $orderId,
			'product_id'          => $productId,
			'user_id'             => $userId,
			'valid_for'           => $validFor,
			'expires_at'          => $expiresAt,
			'source'              => LicenseSource::API,
			'status'              => $status,
			'activations_limit' => $activationsLimit
		) );

		if ( is_wp_error( $license ) ) {
			return $this->maybeErrorResponse( $license );
		}

		$licenseData = self::prepareLicense( $license );

		return $this->response( true, $licenseData, 200, 'v1/licenses' );
	}

	/**
	 * Callback for the PUT licenses/{license_key} route. Updates an existing license key in the database.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function updateLicense( WP_REST_Request $request ) {
		if ( ! $this->isRouteEnabled( $this->settings, '013' ) ) {
			return $this->routeDisabledError();
		}

		if ( ! $this->capabilityCheck( 'dlm_edit_licenses' ) ) {
			return $this->responseError(
				'cannot_edit',
				__( 'Sorry, you are not allowed to edit resources.', 'digital-license-manager' ),
				array(
					'status' => $this->authorizationRequiredCode()
				)
			);
		}

		$urlParams = $request->get_url_params();

		$licenseKey = isset( $urlParams['license_key'] ) ? sanitize_text_field( $urlParams['license_key'] ) : '';

		if ( $this->isJson( $request->get_body() ) ) {
			$updateData = json_decode( $request->get_body() );
		} else {
			$updateData = $request->get_params();
		}

		$updatedLicense = LicenseUtil::update( $licenseKey, $updateData );

		if ( is_wp_error( $updatedLicense ) ) {
			return $this->maybeErrorResponse( $updatedLicense );
		}

		$licenseData = self::prepareLicense( $updatedLicense );

		return $this->response( true, $licenseData, 200, 'v1/licenses/{license_key}' );
	}

	/**
	 * Callback for the GET licenses/activate/{license_key} route. This will activate a license key (if possible)
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function activateLicense( WP_REST_Request $request ) {

		if ( ! $this->isRouteEnabled( $this->settings, '014' ) ) {
			return $this->routeDisabledError();
		}

		if ( ! $this->capabilityCheck( 'dlm_activate_licenses' ) ) {
			return $this->responseError(
				'cannot_edit',
				__( 'Sorry, you are not allowed to edit this resource.', 'digital-license-manager' ),
				array(
					'status' => $this->authorizationRequiredCode()
				)
			);
		}

		$licenseKey      = sanitize_text_field( $request->get_param( 'license_key' ) );
		$activationMeta  = $request->get_param( 'meta' );
		$activationLabel = $request->get_param( 'label' );

		$licenseActivation = LicenseUtil::activate( $licenseKey, array(
			'label' => $activationLabel,
			'meta'  => $activationMeta
		) );

		if ( is_wp_error( $licenseActivation ) ) {
			return $this->maybeErrorResponse( $licenseActivation );
		}

		$licenseActivation = self::prepareActivation( $licenseActivation, true );

		return $this->response( true, $licenseActivation, 200, 'v1/licenses/activate/{license_key}' );

	}

	/**
	 * Callback for the GET licenses/deactivate/{activation_token} route. This will deactivate the activation that was created before.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivateLicense( WP_REST_Request $request ) {

		if ( ! $this->isRouteEnabled( $this->settings, '015' ) ) {
			return $this->routeDisabledError();
		}

		if ( ! $this->capabilityCheck( 'dlm_deactivate_licenses' ) ) {
			return $this->responseError(
				'cannot_edit',
				__( 'Sorry, you are not allowed to edit this resource.', 'digital-license-manager' ),
				array(
					'status' => $this->authorizationRequiredCode()
				)
			);
		}

		$activationToken   = sanitize_text_field( $request->get_param( 'activation_token' ) );
		$licenseActivation = LicenseUtil::deactivate( $activationToken );

		if ( is_wp_error( $licenseActivation ) ) {
			return $this->maybeErrorResponse( $licenseActivation );
		}

		$licenseActivation = self::prepareActivation( $licenseActivation, true );

		return $this->response( true, $licenseActivation, 200, 'v1/licenses/deactivate/{activation_token}' );
	}

	/**
	 * Callback for the GET licenses/validate/{activation_token} route.
	 * This check and verify the activation status of a given license key.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function validateLicense( WP_REST_Request $request ) {

		if ( ! $this->isRouteEnabled( $this->settings, '016' ) ) {
			return $this->routeDisabledError();
		}

		if ( ! $this->capabilityCheck( 'dlm_validate_licenses' ) ) {
			return $this->responseError(
				'cannot_view',
				__( 'Sorry, you cannot view this resource.', 'digital-license-manager' ),
				array(
					'status' => $this->authorizationRequiredCode()
				)
			);
		}

		$urlParams = $request->get_url_params();

		if ( ! array_key_exists( 'activation_token', $urlParams ) ) {
			return $this->responseError(
				'data_error',
				'License key is invalid.',
				array( 'status' => 404 )
			);
		}

		$activationToken = sanitize_text_field( $urlParams['activation_token'] );

		/* @var LicenseActivationResourceModel $activation */
		$activation = LicenseActivationResourceRepository::instance()->findBy( array(
			'token' => $activationToken,
		) );

		if ( ! $activation ) {
			return $this->responseError(
				'data_error',
				'License activation not found.',
				array( 'status' => 404 )
			);
		}

		if ( ! empty( $activation->getDeactivatedAt() ) ) {
			return $this->responseError(
				'data_error',
				'License activation is deactivated.',
				array( 'status' => 404 )
			);
		}

		$license = $activation->getLicense();

		if ( ! $license ) {
			return $this->responseError(
				'data_error',
				'License is invalid.',
				array( 'status' => 404 )
			);
		}

		$result = $activation->toArray();
		if ( ! empty( $result['licenseId'] ) ) {
			$result['license'] = self::prepareLicense( $license, false );
		}

		return $this->response( true, $result, 200, 'v1/licenses/validate/{activation_token}' );
	}

	/**
	 * Prepare activations
	 *
	 * @param LicenseActivationResourceModel|bool $activation
	 * @param false $withLicense
	 *
	 * @return array|string
	 */
	public static function prepareActivation( $activation, $withLicense = false ) {
		if ( empty( $activation ) ) {
			return $activation;
		}
		$activationData = $activation->toArray();

		if ( $withLicense ) {
			unset( $activationData['licenseId'] );
			$license = $activation->getLicense();
			if ( ! empty( $license ) ) {
				$activationData['license'] = self::prepareLicense( $license, false );
			}
		}

		return $activationData;
	}

	/**
	 * Prepares license
	 *
	 * @param $license
	 * @param bool $withActivations
	 *
	 * @return array
	 */
	public static function prepareLicense( $license, $withActivations = false ) {

		if ( empty( $license ) ) {
			return $license;
		}

		$licenseData = $license->toArray();
		unset( $licenseData['hash'] );
		$licenseData['license_key'] = $license->getDecryptedLicenseKey();
		if ( is_wp_error( $licenseData['license_key'] ) ) {
			$licenseData['license_key'] = '';
		}

		if ( $withActivations ) {
			// Query activations
			$activations = $license->getActivations( array(
				'active' => true,
			) );
			// Convert activations
			$licenseData['activations'] = array();
			foreach ( $activations as $activation ) {
				$activationData = self::prepareActivation( $activation, false );
				if ( ! empty( $activationData ) ) {
					array_push( $licenseData['activations'], $activationData );
				}
			}
		}

		return $licenseData;
	}

}
