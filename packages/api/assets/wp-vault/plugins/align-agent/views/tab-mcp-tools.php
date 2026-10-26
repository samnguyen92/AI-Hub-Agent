<?php
/**
 * Align Agent Gateway - Tab: MCP Tools
 *
 * Lists the tools exposed by Align_Agent_Tool_Registry (same filter as the
 * OpenAI schema: hidden tools and builder-only tools without the builder are
 * skipped) with their planned MCP call target and a short description.
 *
 * @package Align_Agent
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mcp_builder_active = class_exists( 'Align_Agent_Tool_Registry' ) && Align_Agent_Tool_Registry::is_builder_available();
$mcp_tools          = array();

if ( class_exists( 'Align_Agent_Tool_Registry' ) ) {
	foreach ( Align_Agent_Tool_Registry::get_all() as $mcp_tool ) {
		if ( ! empty( $mcp_tool['hidden'] ) || ( ! empty( $mcp_tool['requires_builder'] ) && ! $mcp_builder_active ) ) {
			continue;
		}
		$mcp_tools[] = $mcp_tool;
	}
}

usort(
	$mcp_tools,
	static function ( array $a, array $b ): int {
		return array( $a['group'], $a['name'] ) <=> array( $b['group'], $b['name'] );
	}
);
?>
<div class="align-agent-card">
	<div class="align-agent-card-header">
		<h2 class="align-agent-card-title">
			<?php
			/* translators: %d: number of available tools. */
			echo esc_html( sprintf( __( 'MCP Tools (%d)', 'align-agent' ), count( $mcp_tools ) ) );
			?>
		</h2>
		<p class="align-agent-card-desc">
			<?php esc_html_e( 'All registered tools below are actively exposed via the MCP JSON-RPC 2.0 endpoint: POST /wp-json/align-agent/v1/mcp', 'align-agent' ); ?>
		</p>
	</div>

	<div class="align-agent-card-body">
		<?php if ( empty( $mcp_tools ) ) : ?>
			<p><em><?php esc_html_e( 'No tools registered.', 'align-agent' ); ?></em></p>
		<?php else : ?>
			<div class="align-mcp-tools-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'MCP tools list', 'align-agent' ); ?>">
				<table class="widefat striped align-agent-endpoints-table align-mcp-tools-table">
					<thead>
						<tr>
							<th scope="col" class="align-mcp-col-name"><?php esc_html_e( 'Name', 'align-agent' ); ?></th>
							<th scope="col" class="align-mcp-col-endpoint"><?php esc_html_e( 'Endpoint', 'align-agent' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Description', 'align-agent' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $mcp_tools as $mcp_tool ) : ?>
							<tr data-tool="<?php echo esc_attr( $mcp_tool['name'] ); ?>" data-group="<?php echo esc_attr( $mcp_tool['group'] ); ?>" data-type="<?php echo esc_attr( $mcp_tool['type'] ); ?>">
								<td><code><?php echo esc_html( $mcp_tool['name'] ); ?></code></td>
								<td>
									<code>tools/call &rarr; <?php echo esc_html( $mcp_tool['name'] ); ?></code>
									<?php if ( ! empty( $mcp_tool['danger'] ) ) : ?>
										<span class="align-badge align-badge-danger"><?php esc_html_e( 'danger', 'align-agent' ); ?></span>
									<?php elseif ( 'write' === $mcp_tool['type'] ) : ?>
										<span class="align-badge align-badge-put"><?php esc_html_e( 'write', 'align-agent' ); ?></span>
									<?php else : ?>
										<span class="align-badge align-badge-get"><?php esc_html_e( 'read', 'align-agent' ); ?></span>
									<?php endif; ?>
								</td>
								<td title="<?php echo esc_attr( $mcp_tool['description'] ); ?>"><?php echo esc_html( wp_trim_words( $mcp_tool['description'], 16 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
