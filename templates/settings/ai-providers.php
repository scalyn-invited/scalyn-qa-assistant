<?php
/**
 * Template: Settings — AI Providers Tab.
 *
 * Renders the AI provider configuration with API key management,
 * model selection, and role assignment for OpenAI, Claude, Gemini,
 * OpenRouter, and Custom Endpoint.
 *
 * @package Scalyn\QA\Templates
 * @since   1.0.0
 *
 * @var array  $settings    Current plugin settings array.
 * @var array  $tabs        Tab navigation data (slug => [label, url, active]).
 * @var string $current_tab The current active tab slug.
 */

defined( 'ABSPATH' ) || exit;

$settings    = isset( $settings ) ? $settings : array();
$tabs        = isset( $tabs ) ? $tabs : array();
$current_tab = isset( $current_tab ) ? $current_tab : 'ai-providers';

// AI settings.
$ai_enabled = ! empty( $settings['enable_ai'] );

// Provider configurations.
$providers = array(
	'openai' => array(
		'name'          => __( 'OpenAI', 'scalyn-qa-assistant' ),
		'key_field'     => 'openai_api_key',
		'model_field'   => 'openai_model',
		'role_field'    => 'openai_role',
		'type'          => 'standard',
		'models'        => array(
			'gpt-4o-mini'  => 'GPT-4o Mini',
			'gpt-4o'       => 'GPT-4o',
			'gpt-4.1-mini' => 'GPT-4.1 Mini',
			'gpt-4.1-nano' => 'GPT-4.1 Nano',
		),
		'default_model' => 'gpt-4o-mini',
		'docs_url'      => 'https://platform.openai.com/docs',
	),
	'claude' => array(
		'name'          => __( 'Claude (Anthropic)', 'scalyn-qa-assistant' ),
		'key_field'     => 'claude_api_key',
		'model_field'   => 'claude_model',
		'role_field'    => 'claude_role',
		'type'          => 'standard',
		'models'        => array(
			'claude-sonnet-4-20250514'       => 'Claude Sonnet 4',
			'claude-3-5-sonnet-20241022'     => 'Claude 3.5 Sonnet',
			'claude-3-haiku-20240307'        => 'Claude 3 Haiku',
		),
		'default_model' => 'claude-sonnet-4-20250514',
		'docs_url'      => 'https://docs.anthropic.com',
	),
	'gemini' => array(
		'name'          => __( 'Gemini (Google)', 'scalyn-qa-assistant' ),
		'key_field'     => 'gemini_api_key',
		'model_field'   => 'gemini_model',
		'role_field'    => 'gemini_role',
		'type'          => 'standard',
		'models'        => array(
			'gemini-2.0-flash'  => 'Gemini 2.0 Flash',
			'gemini-2.5-flash'  => 'Gemini 2.5 Flash',
		),
		'default_model' => 'gemini-2.0-flash',
		'docs_url'      => 'https://ai.google.dev/docs',
	),
	'openrouter' => array(
		'name'          => __( 'OpenRouter', 'scalyn-qa-assistant' ),
		'key_field'     => 'openrouter_api_key',
		'model_field'   => 'openrouter_model',
		'role_field'    => 'openrouter_role',
		'type'          => 'standard',
		'models'        => array(
			'anthropic/claude-sonnet-4'          => 'Claude Sonnet 4',
			'anthropic/claude-3.5-sonnet'        => 'Claude 3.5 Sonnet',
			'openai/gpt-4o'                      => 'GPT-4o',
			'openai/gpt-4o-mini'                 => 'GPT-4o Mini',
			'google/gemini-2.0-flash-exp'        => 'Gemini 2.0 Flash',
			'deepseek/deepseek-chat'             => 'DeepSeek V3',
			'mistralai/mistral-large-latest'     => 'Mistral Large',
			'meta-llama/llama-3.1-70b-instruct'  => 'Llama 3.1 70B',
			'qwen/qwen-2.5-72b-instruct'        => 'Qwen 2.5 72B',
		),
		'default_model' => 'anthropic/claude-sonnet-4',
		'docs_url'      => 'https://openrouter.ai/docs',
	),
	'custom' => array(
		'name'          => __( 'Custom Endpoint', 'scalyn-qa-assistant' ),
		'key_field'     => 'custom_api_key',
		'model_field'   => 'custom_model',
		'role_field'    => 'custom_role',
		'type'          => 'custom',
		'models'        => array(),
		'default_model' => '',
		'docs_url'      => '',
	),
);

/**
 * Mask an API key for display, showing only the last 4 characters.
 *
 * @param string $key The API key to mask.
 * @return string Masked key or empty string.
 */
$mask_key = function ( string $key ): string {
	if ( strlen( $key ) <= 4 ) {
		return $key;
	}
	return str_repeat( '*', strlen( $key ) - 4 ) . substr( $key, -4 );
};
?>
<div class="scalyn-wrap">

	<div class="scalyn-page-header">
		<div class="scalyn-page-header__intro">
			<h1><?php esc_html_e( 'Settings', 'scalyn-qa-assistant' ); ?></h1>
			<p class="scalyn-page-header__description"><?php esc_html_e( 'Configure scanning, scoring, AI providers, and plugin behavior.', 'scalyn-qa-assistant' ); ?></p>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="scalyn-tabs" role="tablist">
		<?php foreach ( $tabs as $tab_slug => $tab ) : ?>
			<a
				href="<?php echo esc_url( $tab['url'] ); ?>"
				class="scalyn-tab <?php echo $tab['active'] ? 'scalyn-tab--active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $tab['active'] ? 'true' : 'false'; ?>"
			>
				<?php echo esc_html( $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- AI Master Toggle -->
	<div class="scalyn-card" id="scalyn-tab-panel-ai-providers" role="tabpanel">
		<div class="scalyn-toggle-row">
			<input
				type="checkbox"
				id="scalyn-ai-enabled"
				name="enable_ai"
				value="1"
				<?php checked( $ai_enabled ); ?>
				class="scalyn-toggle-checkbox"
			>
			<label for="scalyn-ai-enabled" class="scalyn-toggle-label">
				<strong><?php esc_html_e( 'Enable AI Features', 'scalyn-qa-assistant' ); ?></strong>
				<span class="scalyn-field-description">
					<?php esc_html_e( 'When enabled, AI-powered features such as meta description generation and content suggestions become available.', 'scalyn-qa-assistant' ); ?>
				</span>
			</label>
		</div>
		<div id="scalyn-ai-toggle-notice" class="scalyn-notice" style="display:none;margin-top:0.75rem;"></div>
	</div>

	<!-- Provider Cards -->
	<div class="scalyn-ai-providers" data-ai-enabled="<?php echo $ai_enabled ? '1' : '0'; ?>">

		<?php foreach ( $providers as $provider_slug => $provider ) : ?>
			<?php
			$stored_key    = isset( $settings[ $provider['key_field'] ] ) ? $settings[ $provider['key_field'] ] : '';
			$stored_model  = isset( $settings[ $provider['model_field'] ] ) ? $settings[ $provider['model_field'] ] : $provider['default_model'];
			$stored_role   = isset( $settings[ $provider['role_field'] ] ) ? $settings[ $provider['role_field'] ] : 'disabled';
			$has_key       = ( '__configured__' === $stored_key );
			$is_custom     = ( 'custom' === $provider['type'] );
			?>
			<div class="scalyn-card scalyn-provider-card" data-provider="<?php echo esc_attr( $provider_slug ); ?>">
				<h3 class="scalyn-card__title">
					<?php echo esc_html( $provider['name'] ); ?>
					<?php if ( ! empty( $provider['docs_url'] ) ) : ?>
						<a href="<?php echo esc_url( $provider['docs_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="scalyn-docs-link">
							<?php esc_html_e( 'Documentation', 'scalyn-qa-assistant' ); ?> &#8599;
						</a>
					<?php endif; ?>
				</h3>

				<table class="scalyn-form-table">
					<!-- API Key -->
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $provider_slug ); ?>-key">
								<?php esc_html_e( 'API Key', 'scalyn-qa-assistant' ); ?>
							</label>
						</th>
						<td>
							<div class="scalyn-input-group">
								<input
									type="password"
									id="<?php echo esc_attr( $provider_slug ); ?>-key"
									name="<?php echo esc_attr( $provider['key_field'] ); ?>"
									value=""
									class="scalyn-input scalyn-input--wide"
									placeholder="<?php echo $has_key ? esc_attr__( 'Key saved — leave empty to keep current', 'scalyn-qa-assistant' ) : esc_attr__( 'Enter API key...', 'scalyn-qa-assistant' ); ?>"
									autocomplete="off"
									data-configured="<?php echo $has_key ? '1' : '0'; ?>"
								>
								<button
									type="button"
									class="scalyn-btn scalyn-btn--secondary scalyn-test-key"
									data-provider="<?php echo esc_attr( $provider_slug ); ?>"
								>
									<?php esc_html_e( 'Test', 'scalyn-qa-assistant' ); ?>
								</button>
								<button
									type="button"
									class="scalyn-btn scalyn-btn--secondary scalyn-toggle-visibility"
									data-target="<?php echo esc_attr( $provider_slug ); ?>-key"
									aria-label="<?php esc_attr_e( 'Toggle key visibility', 'scalyn-qa-assistant' ); ?>"
								>
									<?php esc_html_e( 'Show', 'scalyn-qa-assistant' ); ?>
								</button>
								<span
									class="scalyn-status"
									id="<?php echo esc_attr( $provider_slug ); ?>-status"
									aria-live="polite"
								></span>
							</div>
							<p class="scalyn-field-description">
								<?php
								printf(
									/* translators: %s: Provider name. */
									esc_html__( 'Enter your %s API key. The key is stored encrypted in the database.', 'scalyn-qa-assistant' ),
									esc_html( $provider['name'] ),
								);
								?>
							</p>
						</td>
					</tr>

					<?php if ( ! $is_custom ) : ?>
						<!-- Model Selection (standard providers) -->
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $provider_slug ); ?>-model">
									<?php esc_html_e( 'Model', 'scalyn-qa-assistant' ); ?>
								</label>
							</th>
							<td>
								<select
									id="<?php echo esc_attr( $provider_slug ); ?>-model"
									name="<?php echo esc_attr( $provider['model_field'] ); ?>"
									class="scalyn-select"
								>
									<?php foreach ( $provider['models'] as $model_value => $model_label ) : ?>
										<option
											value="<?php echo esc_attr( $model_value ); ?>"
											<?php selected( $stored_model, $model_value ); ?>
										>
											<?php echo esc_html( $model_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php else : ?>
						<!-- Endpoint URL (custom provider) -->
						<tr>
							<th scope="row">
								<label for="custom-endpoint">
									<?php esc_html_e( 'Endpoint URL', 'scalyn-qa-assistant' ); ?>
								</label>
							</th>
							<td>
								<input type="url" id="custom-endpoint" name="custom_endpoint"
									value="<?php echo esc_attr( $settings['custom_endpoint'] ?? '' ); ?>"
									class="scalyn-input scalyn-input--wide"
									placeholder="http://localhost:11434/v1/chat/completions">
								<p class="scalyn-field-description">
									<?php esc_html_e( 'OpenAI-compatible API URL (Ollama, LM Studio, etc.)', 'scalyn-qa-assistant' ); ?>
								</p>
							</td>
						</tr>
						<!-- Model Name (free text, custom provider) -->
						<tr>
							<th scope="row">
								<label for="custom-model-name">
									<?php esc_html_e( 'Model Name', 'scalyn-qa-assistant' ); ?>
								</label>
							</th>
							<td>
								<input type="text" id="custom-model-name" name="custom_model_name"
									value="<?php echo esc_attr( $settings['custom_model_name'] ?? '' ); ?>"
									class="scalyn-input"
									placeholder="llama3">
								<p class="scalyn-field-description">
									<?php esc_html_e( 'The model identifier to send in API requests', 'scalyn-qa-assistant' ); ?>
								</p>
							</td>
						</tr>
						<!-- Custom Headers (custom provider) -->
						<tr>
							<th scope="row">
								<label for="custom-headers">
									<?php esc_html_e( 'Custom Headers', 'scalyn-qa-assistant' ); ?>
								</label>
							</th>
							<td>
								<textarea id="custom-headers" name="custom_headers"
									class="scalyn-input" rows="3"
									placeholder='{"X-Custom-Auth": "token123"}'><?php echo esc_textarea( $settings['custom_headers'] ?? '' ); ?></textarea>
								<p class="scalyn-field-description">
									<?php esc_html_e( 'Optional JSON object of additional HTTP headers', 'scalyn-qa-assistant' ); ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>

					<!-- Role Assignment -->
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Role', 'scalyn-qa-assistant' ); ?>
						</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text">
									<?php
									printf(
										/* translators: %s: Provider name. */
										esc_html__( '%s role', 'scalyn-qa-assistant' ),
										esc_html( $provider['name'] ),
									);
									?>
								</legend>
								<label class="scalyn-radio-label">
									<input
										type="radio"
										name="<?php echo esc_attr( $provider['role_field'] ); ?>"
										value="primary"
										<?php checked( $stored_role, 'primary' ); ?>
									>
									<?php esc_html_e( 'Primary', 'scalyn-qa-assistant' ); ?>
								</label>
								<label class="scalyn-radio-label">
									<input
										type="radio"
										name="<?php echo esc_attr( $provider['role_field'] ); ?>"
										value="fallback"
										<?php checked( $stored_role, 'fallback' ); ?>
									>
									<?php esc_html_e( 'Fallback', 'scalyn-qa-assistant' ); ?>
								</label>
								<label class="scalyn-radio-label">
									<input
										type="radio"
										name="<?php echo esc_attr( $provider['role_field'] ); ?>"
										value="disabled"
										<?php checked( $stored_role, 'disabled' ); ?>
									>
									<?php esc_html_e( 'Disabled', 'scalyn-qa-assistant' ); ?>
								</label>
							</fieldset>
							<p class="scalyn-field-description">
								<?php esc_html_e( 'Primary is the default provider. Fallback is used if the primary fails. Disabled turns this provider off.', 'scalyn-qa-assistant' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		<?php endforeach; ?>

	</div>

	<!-- Save Button -->
	<div class="scalyn-form-actions">
		<button type="button" id="scalyn-save-ai" class="scalyn-btn">
			<?php esc_html_e( 'Save AI Settings', 'scalyn-qa-assistant' ); ?>
		</button>
		<span class="scalyn-save-status" id="scalyn-ai-status" aria-live="polite"></span>
	</div>

</div>
