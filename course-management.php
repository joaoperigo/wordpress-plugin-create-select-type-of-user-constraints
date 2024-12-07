<?php
/*
Plugin Name: Course Management System
Description: Manages course assignments and progress for different professional areas
Version: 1.0
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CourseManagementSystem {
    private $courses = [
        'farmacia' => ['name' => 'Farmácia', 'total_hours' => 100],
        'biomedicina' => ['name' => 'Biomedicina', 'total_hours' => 100],
        'enfermagem' => ['name' => 'Enfermagem', 'total_hours' => 100],
        'biologia' => ['name' => 'Biologia', 'total_hours' => 360],
        'fisioterapia' => ['name' => 'Fisioterapia', 'total_hours' => 100]
    ];

    private $disciplines = [
        'proc_baixa_media' => 'Prática clínica de procedimentos de baixa e média complexidade',
        'toxina' => 'Prática clínica de toxina Botulínica',
        'preenchimento' => 'Prática Clínica de preenchimento básico',
        'bioestimuladores' => 'Prática clínica de bioestimuladores faciais e corporais',
        'fios' => 'Prática clínica de fios absorvíveis'
    ];

    private $course_requirements = [
        'biologia' => [
            'proc_baixa_media' => 360,
            'toxina' => 10,
            'preenchimento' => 10,
            'bioestimuladores' => 10,
            'fios' => 10,
            'workshop' => 220
        ],
        'fisioterapia' => [
            'proc_baixa_media' => 100,
            'toxina' => 30,
            'preenchimento' => 10,
            'bioestimuladores' => 10,
            'fios' => 10
        ],
        'default' => [
            'proc_baixa_media' => 100,
            'toxina' => 10,
            'preenchimento' => 10,
            'bioestimuladores' => 10,
            'fios' => 10
        ]
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_update_user_course', [$this, 'update_user_course']);
        add_action('wp_ajax_update_discipline_progress', [$this, 'update_discipline_progress']);
        add_action('wp_ajax_get_user_disciplines', [$this, 'get_user_disciplines']);
        
        // Registrar shortcode
        add_shortcode('course_progress', [$this, 'render_course_progress']);
    }

    public function render_course_progress($atts) {
        if (!is_user_logged_in()) {
            return '<p>Por favor, faça login para ver seu progresso no curso.</p>';
        }

        $user_id = get_current_user_id();
        $course_data = get_user_meta($user_id, 'course_data', true);
        
        if (!$course_data) {
            return '<p>Nenhum curso encontrado.</p>';
        }

        $course_data = json_decode($course_data, true);
        $course_type = $course_data['curso'];
        $requirements = $this->course_requirements[$course_type] ?? $this->course_requirements['default'];

        // Calcula totais
        $total_hours_required = array_sum($requirements);
        $total_hours_completed = 0;
        foreach ($course_data['disciplinas'] as $disc_key => $disc_data) {
            $total_hours_completed += $disc_data[1] * 10; // dias * 10 horas
        }

        $output = '<div class="course-progress">';
        $output .= sprintf(
            '<h3>Progresso do Curso: %s</h3>',
            esc_html($this->courses[$course_type]['name'])
        );
        
        $output .= sprintf(
            '<p class="course-totals">Total Cursado: %d horas de %d horas necessárias</p>',
            $total_hours_completed,
            $total_hours_required
        );

        $output .= '<div class="disciplines-list">';
        
        foreach ($course_data['disciplinas'] as $disc_key => $disc_data) {
            $status = $disc_data[0];
            $days = $disc_data[1];
            $complete = $disc_data[2];
            $current_hours = $days * 10;
            $required_hours = $requirements[$disc_key];
            
            $disc_class = $status === 'bloqueado' ? 'discipline-blocked' : 'discipline-' . $complete;
            
            $output .= sprintf(
                '<div class="discipline-item %s">
                    <h4>%s</h4>
                    <p class="discipline-hours">Horas cursadas: %d de %d necessárias</p>',
                $disc_class,
                esc_html($this->disciplines[$disc_key]),
                $current_hours,
                $required_hours
            );

            if ($status === 'bloqueado') {
                $output .= '<p class="discipline-status blocked">Disciplina Bloqueada</p>';
            } elseif ($complete === 'completo') {
                $output .= '<p class="discipline-status complete">Disciplina Completada</p>';
            } else {
                // Se está liberada e incompleta, procura um shortcode específico
                $shortcode = $this->get_discipline_shortcode($disc_key);
                if ($shortcode) {
                    $output .= do_shortcode($shortcode);
                }
            }

            $output .= '</div>';
        }

        $output .= '</div></div>';

        // Adiciona CSS
        $output .= '
        <style>
            .course-progress {
                max-width: 800px;
                margin: 20px auto;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            .discipline-item {
                background: #fff;
                border-radius: 8px;
                padding: 20px;
                margin: 10px 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .discipline-blocked {
                opacity: 0.6;
                background: #f5f5f5;
            }
            .discipline-item h4 {
                margin: 0 0 10px 0;
                color: #333;
            }
            .discipline-hours {
                color: #666;
                margin: 5px 0;
            }
            .discipline-status {
                font-weight: 500;
                margin: 10px 0;
            }
            .discipline-status.blocked {
                color: #888;
            }
            .discipline-status.complete {
                color: #4CAF50;
            }
            .course-totals {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                margin: 20px 0;
                text-align: center;
                font-weight: 500;
            }
        </style>';

        return $output;
    }

    private function get_discipline_shortcode($discipline_key) {
        $shortcodes = [
            'toxina' => '[latepoint_resources items="services" group_ids="1"]',
            'preenchimento' => '[latepoint_resources items="services" group_ids="2"]',
            'bioestimuladores' => '[latepoint_resources items="services" group_ids="3"]',
            'fios' => '[latepoint_resources items="services" group_ids="4"]',
            'proc_baixa_media' => '[latepoint_resources items="services" group_ids="5"]'
        ];

        return $shortcodes[$discipline_key] ?? '';
    }
    
    public function get_user_disciplines() {
        check_ajax_referer('course_management_nonce', 'nonce');

        $user_id = intval($_POST['user_id']);

        if (!current_user_can('manage_options') || !$user_id) {
            wp_send_json_error('Permissão negada');
        }

        $course_data = get_user_meta($user_id, 'course_data', true);
        if (!$course_data) {
            wp_send_json_error('Dados do curso não encontrados');
        }

        $course_data = json_decode($course_data, true);
        $course_type = $course_data['curso'];
        $requirements = $this->course_requirements[$course_type] ?? $this->course_requirements['default'];

        $response_data = [
            'user_id' => $user_id,
            'course' => $course_type,
            'disciplines' => []
        ];

        foreach ($course_data['disciplinas'] as $disc_key => $disc_data) {
            $response_data['disciplines'][$disc_key] = [
                'name' => $this->disciplines[$disc_key],
                'status' => $disc_data[0],
                'days' => $disc_data[1],
                'complete' => $disc_data[2],
                'required_hours' => $requirements[$disc_key],
                'current_hours' => $disc_data[1] * 10
            ];
        }

        wp_send_json_success($response_data);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Gerenciamento de Cursos',
            'Cursos',
            'manage_options',
            'course-management',
            [$this, 'render_admin_page'],
            'dashicons-welcome-learn-more'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_course-management' !== $hook) {
            return;
        }

        wp_enqueue_script('course-management', plugins_url('js/admin.js', __FILE__), ['jquery'], '1.0', true);
        wp_localize_script('course-management', 'courseManagement', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('course_management_nonce')
        ]);
    }

    public function render_admin_page() {
        $users = get_users(['role__not_in' => ['administrator']]);
        ?>
        <div class="wrap">
            <h1>Gerenciamento de Cursos</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Curso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        $current_course = get_user_meta($user->ID, 'course_data', true);
                        $course_data = $current_course ? json_decode($current_course, true) : [];
                    ?>
                    <tr>
                        <td><?php echo esc_html($user->display_name); ?></td>
                        <td>
                            <select class="course-select" data-user-id="<?php echo $user->ID; ?>">
                                <option value="">Selecionar curso</option>
                                <?php foreach ($this->courses as $key => $course): ?>
                                    <option value="<?php echo esc_attr($key); ?>" 
                                            <?php selected($course_data['curso'] ?? '', $key); ?>>
                                        <?php echo esc_html($course['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button class="button manage-disciplines" 
                                    data-user-id="<?php echo $user->ID; ?>"
                                    <?php echo empty($course_data) ? 'disabled' : ''; ?>>
                                Gerenciar Disciplinas
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal para gerenciar disciplinas -->
        <div id="discipline-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2>Gerenciar Disciplinas</h2>
                <div id="disciplines-form"></div>
            </div>
        </div>
        <?php
    }

    public function update_user_course() {
        check_ajax_referer('course_management_nonce', 'nonce');

        $user_id = intval($_POST['user_id']);
        $course = sanitize_text_field($_POST['course']);

        if (!current_user_can('manage_options') || !$user_id) {
            wp_send_json_error('Permissão negada');
        }

        $requirements = $this->course_requirements[$course] ?? $this->course_requirements['default'];
        
        $course_data = [
            'curso' => $course,
            'disciplinas' => []
        ];

        foreach ($requirements as $discipline => $hours) {
            $course_data['disciplinas'][$discipline] = [
                'bloqueado',
                0,
                'incompleto'
            ];
        }

        update_user_meta($user_id, 'course_data', json_encode($course_data));
        wp_send_json_success('Curso atualizado com sucesso');
    }

    public function update_discipline_progress() {
        check_ajax_referer('course_management_nonce', 'nonce');

        $user_id = intval($_POST['user_id']);
        $disciplines = json_decode(stripslashes($_POST['disciplines']), true);

        if (!current_user_can('manage_options') || !$user_id) {
            wp_send_json_error('Permissão negada');
        }

        $course_data = json_decode(get_user_meta($user_id, 'course_data', true), true);
        if (!$course_data) {
            wp_send_json_error('Dados do curso não encontrados');
        }

        foreach ($disciplines as $discipline_key => $discipline_data) {
            $status = sanitize_text_field($discipline_data['status']);
            $days = intval($discipline_data['days']);
            $hours = $days * 10;
            $required_hours = $this->course_requirements[$course_data['curso']][$discipline_key] ?? 
                            $this->course_requirements['default'][$discipline_key];
            
            $course_data['disciplinas'][$discipline_key] = [
                $status,
                $days,
                $hours >= $required_hours ? 'completo' : 'incompleto'
            ];
        }

        update_user_meta($user_id, 'course_data', json_encode($course_data));
        wp_send_json_success('Progresso atualizado com sucesso');
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    new CourseManagementSystem();
});