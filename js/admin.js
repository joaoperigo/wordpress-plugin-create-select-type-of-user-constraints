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
        const formData = $(this).serializeArray();
        
        $.ajax({
            url: courseManagement.ajax_url,
            type: 'POST',
            data: {
                action: 'update_discipline_progress',
                nonce: courseManagement.nonce,
                ...formData
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
        // Build the form HTML based on the discipline data
        // This would create inputs for status, days, etc.
        let html = `<form id="discipline-form">`;
        // Add form fields here
        html += `</form>`;
        return html;
    }
});