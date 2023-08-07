<?php
class CFException extends Exception {
    const NO_ROUTE = 7000;
    const MISSING_CREDENTIALS = 9106;
    const AUTHENTICATION = 10000;
    const QUTHORIZATION = 'authorization';
    const INVALID_REQUEST = 'invalid_request';
    const UNKNOWN_ERROR = 8000000;
    const PROJECT_NOT_FOUND = 8000007;

    public static function from_response( array $response ): CFException {
        $code = $response['errors'][0]['code'];
        $message = $response['errors'][0]['message'];

        switch ( $code ) {
            case self::NO_ROUTE:
            case self::UNKNOWN_ERROR:
            case self::MISSING_CREDENTIALS:
                return new CFPluginException( $message, $code );
            case self::AUTHENTICATION:
                return new CFAuthenticationException( $message, $code );
            case self::PROJECT_NOT_FOUND:
                return new CFProjectNotFoundException( $message, $code );
            default:
                return new CFException( sprintf( __( 'Unknown error (code %d): %s', 'p2cf' ), $code, $message ), $code );
        }
    }
}

class CFPluginException extends CFException {
    public function __construct( string $message, int $code ) {
        parent::__construct( sprintf( __( 'Internal error (code %d). Please report the bug.', 'p2cf' ), $code ), $code );
    }
}
class CFAuthenticationException extends CFException {
    public function __construct( string $message, int $code ) {
        parent::__construct( sprintf( __( 'Authentication failure: %s', 'p2cf' ), $message ), $code );
    }
}
class CFProjectNotFoundException extends CFException {
    public function __construct( string $message, int $code ) {
        parent::__construct( sprintf( __( 'Project not found: %s', 'p2cf' ), $message ), $code );
    }
}
