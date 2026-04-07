<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AGLDC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var AGLDC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * LearnDash service.
	 *
	 * @var AGLDC_LearnDash_Service
	 */
	private $learndash;

	/**
	 * PDF service.
	 *
	 * @var AGLDC_PDF_Generator
	 */
	private $pdf_generator;

	/**
	 * Returns the plugin singleton.
	 *
	 * @return AGLDC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->learndash     = new AGLDC_LearnDash_Service();
		$this->pdf_generator = new AGLDC_PDF_Generator();

		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );
		add_action( 'admin_post_agldc_generate_certificate', array( $this, 'handle_certificate_request' ) );
		add_action( 'admin_post_nopriv_agldc_generate_certificate', array( $this, 'handle_certificate_request' ) );
	}

	/**
	 * Registers the settings object.
	 *
	 * @return void
	 */
	public function register_settings() {
		AGLDC_Settings::register();
	}

	/**
	 * Registers the frontend shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( 'agld_certificados', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Registers the plugin admin page.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_menu_page(
			__( 'Certificados LearnDash', 'ag-learndash-certificates' ),
			__( 'Certificados LD', 'ag-learndash-certificates' ),
			'manage_options',
			'agldc-settings',
			array( $this, 'render_admin_page' ),
			'dashicons-awards',
			58
		);
	}

	/**
	 * Enqueues assets only on the plugin admin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_agldc-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'agldc-admin',
			AGLDC_URL . 'assets/admin.css',
			array( 'wp-color-picker' ),
			AGLDC_VERSION
		);
		wp_enqueue_script(
			'agldc-admin',
			AGLDC_URL . 'assets/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			AGLDC_VERSION,
			true
		);
	}

	/**
	 * Enqueues the public stylesheet used by the shortcode output.
	 *
	 * @return void
	 */
	public function enqueue_public_assets() {
		wp_enqueue_style(
			'agldc-public',
			AGLDC_URL . 'assets/public.css',
			array(),
			AGLDC_VERSION
		);
	}

	/**
	 * Renders a notice if LearnDash is not active.
	 *
	 * @return void
	 */
	public function render_dependency_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->learndash->is_available() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'AG LearnDash Certificates precisa do LearnDash ativo para calcular o progresso e liberar os certificados.', 'ag-learndash-certificates' )
			);
		}

		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagejpeg' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'AG LearnDash Certificates precisa da extensão GD do PHP para montar o PDF com a imagem do certificado.', 'ag-learndash-certificates' )
			);
		}
	}

	/**
	 * Renders the plugin settings page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$active_tab = in_array( $active_tab, array( 'general', 'courses' ), true ) ? $active_tab : 'general';

		?>
		<div class="wrap agldc-admin-page">
			<h1><?php esc_html_e( 'Gerador de Certificados LearnDash', 'ag-learndash-certificates' ); ?></h1>
			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'general', remove_query_arg( 'tab' ) ) ); ?>" 
				   class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Configurações Globais', 'ag-learndash-certificates' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'courses', remove_query_arg( 'tab' ) ) ); ?>" 
				   class="nav-tab <?php echo 'courses' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Certificados por Curso', 'ag-learndash-certificates' ); ?>
				</a>
			</h2>

			<?php if ( 'general' === $active_tab ) : ?>
				<?php $this->render_general_tab(); ?>
			<?php else : ?>
				<?php $this->render_courses_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the general settings tab.
	 *
	 * @return void
	 */
	private function render_general_tab() {
		$settings       = AGLDC_Settings::get();
		$image_id       = absint( $settings['certificate_image_id'] );
		$image_url      = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
		$font_options   = AGLDC_Settings::font_family_options();
		$align_options  = AGLDC_Settings::alignment_options();
		$shortcode      = '[agld_certificados]';
		$image_mime_tip = __( 'Formatos aceitos: JPG ou PNG.', 'ag-learndash-certificates' );
		?>
		<p><?php esc_html_e( 'Defina o percentual mínimo, envie a arte do certificado e ajuste como o nome do aluno será desenhado no PDF.', 'ag-learndash-certificates' ); ?></p>

		<div class="agldc-admin-card">
			<p>
				<strong><?php esc_html_e( 'Shortcode:', 'ag-learndash-certificates' ); ?></strong>
				<code><?php echo esc_html( $shortcode ); ?></code>
			</p>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'agldc_settings_group' ); ?>

			<div class="agldc-admin-grid">
				<div class="agldc-admin-card">
					<h2><?php esc_html_e( 'Regras de liberação', 'ag-learndash-certificates' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="agldc-completion-percentage"><?php esc_html_e( 'Percentual mínimo', 'ag-learndash-certificates' ); ?></label>
							</th>
							<td>
								<input
									id="agldc-completion-percentage"
									name="<?php echo esc_attr( AGLDC_Settings::OPTION_KEY ); ?>[completion_percentage]"
									type="number"
									min="1"
									max="100"
									class="small-text"
									value="<?php echo esc_attr( $settings['completion_percentage'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Exemplo: 70 libera o certificado quando o aluno concluir 70% das aulas.', 'ag-learndash-certificates' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="agldc-admin-card">
					<h2><?php esc_html_e( 'Arte do certificado', 'ag-learndash-certificates' ); ?></h2>
					<input
						type="hidden"
						id="agldc-certificate-image-id"
						name="<?php echo esc_attr( AGLDC_Settings::OPTION_KEY ); ?>[certificate_image_id]"
						value="<?php echo esc_attr( $image_id ); ?>"
					/>
					<div class="agldc-image-preview-wrapper">
						<?php if ( $image_url ) : ?>
							<img id="agldc-image-preview" src="<?php echo esc_url( $image_url ); ?>" alt="" />
						<?php else : ?>
							<img id="agldc-image-preview" src="" alt="" style="display:none;" />
						<?php endif; ?>
						<div id="agldc-image-placeholder" class="agldc-image-placeholder" <?php echo $image_url ? 'style="display:none;"' : ''; ?>>
							<?php esc_html_e( 'Nenhuma imagem selecionada.', 'ag-learndash-certificates' ); ?>
						</div>
					</div>
					<p class="description"><?php echo esc_html( $image_mime_tip ); ?></p>
					<p class="agldc-image-actions">
						<button type="button" class="button button-secondary" id="agldc-upload-image">
							<?php esc_html_e( 'Selecionar imagem', 'ag-learndash-certificates' ); ?>
						</button>
						<button type="button" class="button-link-delete" id="agldc-remove-image">
							<?php esc_html_e( 'Remover imagem', 'ag-learndash-certificates' ); ?>
						</button>
					</p>
				</div>

				<div class="agldc-admin-card agldc-admin-card-full">
					<h2><?php esc_html_e( 'Nome do aluno', 'ag-learndash-certificates' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="agldc-font-family"><?php esc_html_e( 'Fonte', 'ag-learndash-certificates' ); ?></label>
							</th>
							<td>
								<select id="agldc-font-family" name="<?php echo esc_attr( AGLDC_Settings::OPTION_KEY ); ?>[font_family]">
									<?php foreach ( $font_options as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['font_family'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Fontes nativas de PDF - sem dependências externas.', 'ag-learndash-certificates' ); ?></p>
							</td>
							<th scope="row">
								<label for="agldc-font-size"><?php esc_html_e( 'Tamanho', 'ag-learndash-certificates' ); ?></label>
							</th>
							<td>
								<input
									id="agldc-font-size"
									name="<?php echo esc_attr( AGLDC_Settings::OPTION_KEY ); ?>[font_size]"
									type="number"
									min="10"
									max="120"
									class="small-text"
									value="<?php echo esc_attr( $settings['font_size'] ); ?>"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="agldc-font-color"><?php esc_html_e( 'Cor', 'ag-learndash-certificates' ); ?></label>
							</th>
							<td>
								<input
									id="agldc-font-color"
									class="agldc-color-field"
									name="<?php echo esc_attr( AGLDC_Settings::OPTION_KEY ); ?>[font_color]"
									type="text"
									value="<?php echo esc_attr( $settings['font_color'] ); ?>"
								/>
							</td>
							<th scope="row">
								<label for="agldc-name-alignment"><?php esc_html_e( 'Alinhamento', 'ag-learndash-certificates' ); ?></label>
							</th>
							<td>
								<select id="agldc-name-alignment" name="<?php echo esc_attr( AGLDC_Settings::OPTION_KEY ); ?>[name_alignment]">
									<?php foreach ( $align_options as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['name_alignment'], $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="agldc-name-position-x"><?php esc_html_e( 'Posição X (%)', 'ag-learndash-certificates' ); ?></label>
							</th>
							<td>
								<input
									id="agldc-name-position-x"
									name="<?php echo esc_attr( AGLDC_Settings::OPTION_KEY ); ?>[name_position_x]"
									type="number"
									step="0.1"
									min="0"
									max="100"
									class="small-text"
									value="<?php echo esc_attr( $settings['name_position_x'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Percentual horizontal da largura.', 'ag-learndash-certificates' ); ?></p>
							</td>
							<th scope="row">
								<label for="agldc-name-position-y"><?php esc_html_e( 'Posição Y (%)', 'ag-learndash-certificates' ); ?></label>
							</th>
							<td>
								<input
									id="agldc-name-position-y"
									name="<?php echo esc_attr( AGLDC_Settings::OPTION_KEY ); ?>[name_position_y]"
									type="number"
									step="0.1"
									min="0"
									max="100"
									class="small-text"
									value="<?php echo esc_attr( $settings['name_position_y'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Percentual vertical da altura.', 'ag-learndash-certificates' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<?php submit_button( __( 'Salvar configurações', 'ag-learndash-certificates' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders the courses tab for per-course certificate settings.
	 *
	 * @return void
	 */
	private function render_courses_tab() {
		$courses = $this->learndash->get_all_courses();
		$global_settings = AGLDC_Settings::get();

		if ( empty( $courses ) ) :
			?>
			<div class="agldc-admin-card">
				<p><?php esc_html_e( 'Nenhum curso encontrado. Certifique-se de que o LearnDash está ativo e que existem cursos publicados.', 'ag-learndash-certificates' ); ?></p>
			</div>
			<?php
			return;
		endif;

		if ( isset( $_POST['agldc_save_course_certificates'] ) && check_admin_referer( 'agldc_course_certificates_nonce' ) ) {
			$this->save_course_certificates();
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Configurações dos certificados salvas com sucesso!', 'ag-learndash-certificates' ); ?></p>
			</div>
			<?php
		}

		$font_options  = AGLDC_Settings::font_family_options();
		$align_options = AGLDC_Settings::alignment_options();
		?>
		<p><?php esc_html_e( 'Configure certificados específicos para cada curso. Deixe em branco para usar as configurações globais.', 'ag-learndash-certificates' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'agldc_course_certificates_nonce' ); ?>
			<input type="hidden" name="agldc_save_course_certificates" value="1" />

			<div class="agldc-courses-list">
				<?php foreach ( $courses as $course ) :
					$course_settings = AGLDC_Settings::get_course_certificate( $course['course_id'] );
					$image_id        = absint( $course_settings['certificate_image_id'] );
					$image_url       = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
					$has_custom      = $image_id > 0 && $image_id !== absint( $global_settings['certificate_image_id'] );
					$field_prefix    = 'agldc_course_' . $course['course_id'];
					?>
					<div class="agldc-admin-card agldc-course-card">
						<div class="agldc-course-header">
							<h3><?php echo esc_html( $course['course_title'] ); ?></h3>
							<?php if ( $has_custom ) : ?>
								<span class="agldc-badge-custom"><?php esc_html_e( 'Personalizado', 'ag-learndash-certificates' ); ?></span>
							<?php else : ?>
								<span class="agldc-badge-global"><?php esc_html_e( 'Usando global', 'ag-learndash-certificates' ); ?></span>
							<?php endif; ?>
						</div>

						<div class="agldc-course-fields">
							<div class="agldc-course-image-section">
								<h4><?php esc_html_e( 'Arte do certificado', 'ag-learndash-certificates' ); ?></h4>
								<input
									type="hidden"
									id="<?php echo esc_attr( $field_prefix ); ?>_image_id"
									name="<?php echo esc_attr( $field_prefix ); ?>[certificate_image_id]"
									value="<?php echo esc_attr( $image_id ); ?>"
									class="agldc-course-image-id"
								/>
								<div class="agldc-image-preview-wrapper agldc-course-preview">
									<?php if ( $image_url ) : ?>
										<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
									<?php else : ?>
										<div class="agldc-image-placeholder">
											<?php esc_html_e( 'Usando imagem global', 'ag-learndash-certificates' ); ?>
										</div>
									<?php endif; ?>
								</div>
								<p class="agldc-image-actions">
									<button type="button" class="button button-secondary agldc-upload-course-image" data-course-id="<?php echo esc_attr( $course['course_id'] ); ?>">
										<?php esc_html_e( 'Selecionar imagem', 'ag-learndash-certificates' ); ?>
									</button>
									<?php if ( $image_id > 0 ) : ?>
										<button type="button" class="button-link-delete agldc-remove-course-image" data-course-id="<?php echo esc_attr( $course['course_id'] ); ?>">
											<?php esc_html_e( 'Remover', 'ag-learndash-certificates' ); ?>
										</button>
									<?php endif; ?>
								</p>
							</div>

							<div class="agldc-course-text-section">
								<h4><?php esc_html_e( 'Posição do nome', 'ag-learndash-certificates' ); ?></h4>
								<div class="agldc-course-text-fields">
									<div class="agldc-field-group">
										<label for="<?php echo esc_attr( $field_prefix ); ?>_pos_x"><?php esc_html_e( 'X (%)', 'ag-learndash-certificates' ); ?></label>
										<input
											id="<?php echo esc_attr( $field_prefix ); ?>_pos_x"
											name="<?php echo esc_attr( $field_prefix ); ?>[name_position_x]"
											type="number"
											step="0.1"
											min="0"
											max="100"
											class="small-text"
											value="<?php echo esc_attr( $course_settings['name_position_x'] ); ?>"
										/>
									</div>
									<div class="agldc-field-group">
										<label for="<?php echo esc_attr( $field_prefix ); ?>_pos_y"><?php esc_html_e( 'Y (%)', 'ag-learndash-certificates' ); ?></label>
										<input
											id="<?php echo esc_attr( $field_prefix ); ?>_pos_y"
											name="<?php echo esc_attr( $field_prefix ); ?>[name_position_y]"
											type="number"
											step="0.1"
											min="0"
											max="100"
											class="small-text"
											value="<?php echo esc_attr( $course_settings['name_position_y'] ); ?>"
										/>
									</div>
									<div class="agldc-field-group">
										<label for="<?php echo esc_attr( $field_prefix ); ?>_font_size"><?php esc_html_e( 'Tamanho', 'ag-learndash-certificates' ); ?></label>
										<input
											id="<?php echo esc_attr( $field_prefix ); ?>_font_size"
											name="<?php echo esc_attr( $field_prefix ); ?>[font_size]"
											type="number"
											min="10"
											max="120"
											class="small-text"
											value="<?php echo esc_attr( $course_settings['font_size'] ); ?>"
										/>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php submit_button( __( 'Salvar certificados dos cursos', 'ag-learndash-certificates' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Saves course-specific certificate settings.
	 *
	 * @return void
	 */
	private function save_course_certificates() {
		foreach ( $_POST as $key => $value ) {
			if ( ! is_array( $value ) || ! str_starts_with( $key, 'agldc_course_' ) ) {
				continue;
			}

			$course_id = absint( str_replace( 'agldc_course_', '', $key ) );
			if ( ! $course_id ) {
				continue;
			}

			$settings = array(
				'certificate_image_id' => absint( $value['certificate_image_id'] ?? 0 ),
				'name_position_x'      => (float) ( $value['name_position_x'] ?? 50 ),
				'name_position_y'      => (float) ( $value['name_position_y'] ?? 56 ),
				'font_size'            => absint( $value['font_size'] ?? 32 ),
				'font_family'          => sanitize_key( $value['font_family'] ?? 'helvetica' ),
				'font_color'           => sanitize_hex_color( $value['font_color'] ?? '#1f2937' ),
				'name_alignment'       => sanitize_key( $value['name_alignment'] ?? 'center' ),
			);

			AGLDC_Settings::save_course_certificate( $course_id, $settings );
		}
	}

	/**
	 * Renders the certificate shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Faça login para visualizar seus certificados disponíveis.', 'ag-learndash-certificates' ) . '</p>';
		}

		if ( ! $this->learndash->is_available() ) {
			return '<p>' . esc_html__( 'O LearnDash precisa estar ativo para listar os certificados.', 'ag-learndash-certificates' ) . '</p>';
		}

		$global_settings = AGLDC_Settings::get();
		$user_id         = get_current_user_id();
		$courses         = $this->learndash->get_user_course_progress( $user_id );
		$eligible        = array();

		foreach ( $courses as $course ) {
			$course_settings = AGLDC_Settings::get_course_certificate( $course['course_id'] );
			$image_id        = absint( $course_settings['certificate_image_id'] );

			// Skip if no certificate image configured (neither global nor course-specific)
			if ( empty( $image_id ) && empty( $global_settings['certificate_image_id'] ) ) {
				continue;
			}

			if ( $this->learndash->user_is_eligible_for_certificate( $user_id, $course['course_id'], $global_settings['completion_percentage'] ) ) {
				$course['certificate_url'] = $this->build_certificate_url( (int) $course['course_id'] );
				$eligible[]                = $course;
			}
		}

		if ( empty( $eligible ) ) {
			return '<p>' . esc_html__( 'Nenhum certificado disponível no momento. Assim que você atingir o percentual mínimo configurado, ele aparecerá aqui.', 'ag-learndash-certificates' ) . '</p>';
		}

		ob_start();
		?>
		<div class="agldc-certificate-list">
			<?php foreach ( $eligible as $course ) : ?>
				<div class="agldc-certificate-item">
					<h3><?php echo esc_html( $course['course_title'] ); ?></h3>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: completed lessons, 2: total lessons, 3: percentage. */
								__( '%1$d de %2$d aulas concluídas (%3$s%%).', 'ag-learndash-certificates' ),
								(int) $course['completed'],
								(int) $course['total'],
								number_format_i18n( (float) $course['percentage'], 2 )
							)
						);
						?>
					</p>
					<p>
						<a class="button" href="<?php echo esc_url( $course['certificate_url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Abrir certificado em PDF', 'ag-learndash-certificates' ); ?>
						</a>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Handles the PDF generation endpoint.
	 *
	 * @return void
	 */
	public function handle_certificate_request() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0;

		if ( ! $course_id ) {
			wp_die( esc_html__( 'Curso inválido.', 'ag-learndash-certificates' ), 400 );
		}

		check_admin_referer( 'agldc_generate_certificate_' . $course_id );

		$global_settings = AGLDC_Settings::get();
		$course_settings = AGLDC_Settings::get_course_certificate( $course_id );
		$user_id         = get_current_user_id();

		if ( ! $this->learndash->user_is_eligible_for_certificate( $user_id, $course_id, $global_settings['completion_percentage'] ) ) {
			wp_die( esc_html__( 'Você ainda não atingiu o percentual mínimo para emitir este certificado.', 'ag-learndash-certificates' ), 403 );
		}

		// Use course-specific image if set, otherwise fall back to global
		$image_id = absint( $course_settings['certificate_image_id'] );
		if ( empty( $image_id ) ) {
			$image_id = absint( $global_settings['certificate_image_id'] );
		}

		$path = $image_id ? get_attached_file( $image_id ) : '';

		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'A imagem do certificado ainda não foi configurada pelo administrador.', 'ag-learndash-certificates' ), 500 );
		}

		$user_name = $this->get_user_full_name( $user_id );
		$pdf_data  = $this->pdf_generator->build_certificate_pdf( $path, $user_name, $course_settings );

		if ( '' === $pdf_data ) {
			wp_die( esc_html__( 'Não foi possível gerar o PDF do certificado.', 'ag-learndash-certificates' ), 500 );
		}

		$this->clean_output_buffers();

		if ( function_exists( 'header_remove' ) ) {
			header_remove( 'Content-Type' );
		}

		if ( function_exists( 'ini_set' ) ) {
			@ini_set( 'zlib.output_compression', 'Off' );
			@ini_set( 'output_buffering', 'Off' );
		}

		status_header( 200 );
		nocache_headers();

		$filename = sanitize_title( get_the_title( $course_id ) );
		$filename = $filename ? $filename . '-certificado.pdf' : 'certificado.pdf';

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Accept-Ranges: none' );
		header( 'Content-Length: ' . strlen( $pdf_data ) );

		echo $pdf_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Builds the secure URL that opens the certificate in a new tab.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private function build_certificate_url( $course_id ) {
		$url = add_query_arg(
			array(
				'action'    => 'agldc_generate_certificate',
				'course_id' => absint( $course_id ),
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'agldc_generate_certificate_' . $course_id );
	}

	/**
	 * Returns the student's best available full name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_user_full_name( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return '';
		}

		$full_name = trim( $user->first_name . ' ' . $user->last_name );

		if ( '' !== $full_name ) {
			return $full_name;
		}

		return $user->display_name;
	}

	/**
	 * Clears any active output buffer before streaming the PDF.
	 *
	 * @return void
	 */
	private function clean_output_buffers() {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}
}
