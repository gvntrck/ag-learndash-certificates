<?php
/**
 * Plugin Name: LearnDash Certificates Gvntrck
 * Plugin URI: https://projetoalfa.org
 * Description: Gera certificados em PDF para cursos do LearnDash com base em percentual configurável de aulas concluídas.
 * Version: 1.1.3
 * Author: Giovani Tureck
 * Author URI: https://projetoalfa.org
 * Text Domain: learndash-certificates-gvntrck
 */

if (!defined('ABSPATH')) {
	exit;
}




define('AGLDC_VERSION', '1.1.3');
define('AGLDC_FILE', __FILE__);
define('AGLDC_DIR', plugin_dir_path(__FILE__));
define('AGLDC_URL', plugin_dir_url(__FILE__));

require_once AGLDC_DIR . 'includes/class-agldc-settings.php';
require_once AGLDC_DIR . 'includes/class-agldc-learndash-service.php';
require_once AGLDC_DIR . 'includes/class-agldc-pdf-generator.php';
require_once AGLDC_DIR . 'includes/class-agldc-plugin.php';

function agldc_plugin()
{
	return AGLDC_Plugin::instance();
}

agldc_plugin();
