<?php

class CFProjectDeploymentConfigEnvVarValueTest extends WP_UnitTestCase {
	public function test_null() {
		require_once __DIR__ . '/../classes/CFProjectDeploymentConfigEnvVarValue.php';

		$value = new CFProjectDeploymentConfigEnvVarValue( null );

		$this->assertNull( $value->to_response() );
	}

	public function test_secret() {
		require_once __DIR__ . '/../classes/CFProjectDeploymentConfigEnvVarValue.php';

		$value = new CFProjectDeploymentConfigEnvVarValue( "my-secret", true );
		$response = $value->to_response();

		$this->assertIsArray( $response );
		$this->assertEquals( array(
            'type'  => 'secret_text',
            'value' => 'my-secret',
        ), $response );
	}
}
