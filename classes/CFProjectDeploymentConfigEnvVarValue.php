<?php
class CFProjectDeploymentConfigEnvVarValue {
    const TYPE_PLAIN_TEXT  = 'plain_text';
    const TYPE_SECRET_TEXT = 'secret_text';

    protected string $_value;
    protected bool $_secret;

    function __construct( string $value, bool $secret ) {
        $this->_value = $value;
        $this->_secret = $secret;
    }

    public static function from_response( array $response ): CFProjectDeploymentConfigEnvVarValue {
        return new self( $response['value'], $response['type'] === self::TYPE_SECRET_TEXT);
    }

    public function to_response(): array {
        return array(
            'value' => $this->_value,
            'type' => $this->_secret ? 'secret_text' : 'plain_text',
        );
    }
}
