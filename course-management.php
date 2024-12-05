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
        $discipline = sanitize_text_field($_POST['discipline']);
        $status = sanitize_text_field($_POST['status']);
        $days = intval($_POST['days']);

        if (!current_user_can('manage_options') || !$user_id) {
            wp_send_json_error('Permissão negada');
        }

        $course_data = json_decode(get_user_meta($user_id, 'course_data', true), true);
        if (!$course_data) {
            wp_send_json_error('Dados do curso não encontrados');
        }

        $hours = $days * 10;
        $required_hours = $this->course_requirements[$course_data['curso']][$discipline] ?? 
                         $this->course_requirements['default'][$discipline];
        
        $course_data['disciplinas'][$discipline] = [
            $status,
            $days,
            $hours >= $required_hours ? 'completo' : 'incompleto'
        ];

        update_user_meta($user_id, 'course_data', json_encode($course_data));
        wp_send_json_success('Progresso atualizado com sucesso');
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    new CourseManagementSystem();
});