<?php
class CFPluginException extends CFException {
    const GitHubPluginURILabel = 'GitHub Plugin URI';

    public function __construct( string $message, int $code ) {
        /* translators: %1$d is the error code, %2$s is the root error */
        parent::__construct( sprintf( __( 'Internal error (code %1$d): %2$s', 'pass2cf' ), $code, $message ), $code );
    }

    protected function bug_report_url() {
        $plugin = get_plugin_data( Pass2CF_PLUGIN_FILE );
        $site_url = site_url();
        $plugin_name = $plugin['Name'] ?: 'unknown';
        $version = $plugin['Version'] ?: 'unknown';

        $trace = Pass2CFOptions::load()->sanitize( $this->getTraceAsString() );
        try {
            $project = CFProject::load();

            foreach ( $project->deployment_configs as $config ) {
                foreach ( $config->env_vars as $var ) {
                    if ( $var->value->value && $var->value->is_secret ) {
                        $trace = str_replace( $var->value->value, '*secret-value*', $trace );
                    }
                }
            }
        } catch ( Exception $e ) {
            // nothing to do
        }

        $url = parse_url( $plugin[self::GitHubPluginURILabel] );


        $url['scheme'] = 'https';
        $url['host']   = 'github.com';

        $url['query'] = isset( $url['query'] ) ? $url['query'] . '&' : '';
        $url['query'] .= http_build_query( array(
            'body'  => <<<EOF
            Hello,

            Cloudflare returned an error not supported by the plugin.
            
            wordpress url: <{$site_url}>
            name: `{$plugin_name}`
            version: `{$version}`
            error code: `{$this->getCode()}`
            error message: `{$this->getMessage()}`

            ```trace
            {$trace}
            ```
            
            Can you please help me to fix it?
            EOF,
            'title' => "Unsupported Cloudflare error code: {$this->getCode()}",
            'labels' => 'bug',
        ) );

        return "{$url['scheme']}://{$url['host']}/{$url['path']}/issues/new?{$url['query']}";
    }

    public function display_admin_notice() {
        $report_url = $this->bug_report_url();
        ?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $this->getMessage() ); ?></p>
                <p><a target="_blank" href="<?php echo esc_attr( $report_url ); ?>"><?php _e( 'Please report the bug', 'pass2cf' ); ?></a></p>
            </div>
        <?php
    }

    public static function extra_bug_report_field( array $fields ): array {
        if ( ! in_array( self::GitHubPluginURILabel, $fields ) ) {
            $fields[] = self::GitHubPluginURILabel;
        }

        return $fields;
    }
}

add_filter( 'extra_plugin_headers', array( 'CFPluginException', 'extra_bug_report_field' ) );
