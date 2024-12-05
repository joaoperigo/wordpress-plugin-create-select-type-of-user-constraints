jQuery(document).ready(function($) {
    // Handle course selection change
    $('.course-select').on('change', function() {
        const userId = $(this).data('user-id');
        const course = $(this).val();
        
        if (!course) {
            return;
        }

        if ($(this).find(':selected').text() !== $(this).data('previous-course')) {
            if ($(this).data('previous-course')) {
                if (!confirm('Mudar o curso irá deletar todo o progresso atual. Deseja continuar?')) {
                    $(this).val($(this).data('previous-value'));
                    return;
                }
            }
        }

        $.ajax({
            url: courseManagement.ajax_url,
            type: 'POST',
            data: {
                action: 'update_user_course',
                nonce: courseManagement.nonce,
                user_id: userId,
                course: course
            },
            success: function(response) {
                if (response.success) {
                    $(this).data('previous-course', $(this).find(':selected').text());
                    $(this).data('previous-value', course);
                    $('.manage-disciplines[data-user-id="' + userId + '"]').prop('disabled', false);
                } else {
                    alert('Erro ao atualizar o curso');
                }
            }.bind(this),
            error: function() {
                alert('Erro ao processar a requisição');
            }
        });
    });

    // Handle discipline management button click
    $('.manage-disciplines').on('click', function() {
        const userId = $(this).data('user-id');
        
        // Load disciplines data and show modal
        $.ajax({
            url: courseManagement.ajax_url,
            type: 'POST',
            data: {
                action: 'get_user_disciplines',
                nonce: courseManagement.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    $('#disciplines-form').html(buildDisciplineForm(response.data));
                    $('#discipline-modal').show();
                } else {
                    alert('Erro ao carregar disciplinas');
                }
            },
            error: function() {
                alert('Erro ao processar a requisição');
            }
        });
    });

    // Handle discipline form submission
    $(document).on('submit', '#discipline-form', function(e) {
        e.preventDefault();
        const userId = $(this).find('input[name="user_id"]').val();
        const disciplines = {};
        
        // Collect all discipline data
        $(this).find('tr').each(function() {
            const disciplineKey = $(this).data('discipline');
            if (disciplineKey) {
                disciplines[disciplineKey] = {
                    status: $(this).find(`select[name="status_${disciplineKey}"]`).val(),
                    days: parseInt($(this).find(`input[name="days_${disciplineKey}"]`).val(), 10)
                };
            }
        });
        
        $.ajax({
            url: courseManagement.ajax_url,
            type: 'POST',
            data: {
                action: 'update_discipline_progress',
                nonce: courseManagement.nonce,
                user_id: userId,
                disciplines: JSON.stringify(disciplines)
            },
            success: function(response) {
                if (response.success) {
                    $('#discipline-modal').hide();
                } else {
                    alert('Erro ao atualizar progresso');
                }
            },
            error: function() {
                alert('Erro ao processar a requisição');
            }
        });
    });

    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).is('#discipline-modal')) {
            $('#discipline-modal').hide();
        }
    });

    function buildDisciplineForm(data) {
        let html = `<form id="discipline-form">
            <input type="hidden" name="user_id" value="${data.user_id}">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Disciplina</th>
                        <th>Status</th>
                        <th>Dias de Presença</th>
                        <th>Horas Totais</th>
                        <th>Horas Necessárias</th>
                        <th>Status Conclusão</th>
                    </tr>
                </thead>
                <tbody>`;
        
        Object.entries(data.disciplines).forEach(([key, disc]) => {
            html += `
                <tr data-discipline="${key}">
                    <td>${disc.name}</td>
                    <td>
                        <select name="status_${key}">
                            <option value="bloqueado" ${disc.status === 'bloqueado' ? 'selected' : ''}>Bloqueado</option>
                            <option value="liberado" ${disc.status === 'liberado' ? 'selected' : ''}>Liberado</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" 
                               name="days_${key}" 
                               value="${disc.days}" 
                               min="0" 
                               max="${Math.ceil(disc.required_hours / 10)}"
                               style="width: 70px">
                    </td>
                    <td>${disc.current_hours}h</td>
                    <td>${disc.required_hours}h</td>
                    <td>${disc.complete}</td>
                </tr>`;
        });

        html += `</tbody>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Salvar Alterações">
            </p>
        </form>`;
        return html;
    }
});