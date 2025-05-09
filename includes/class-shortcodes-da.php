<?php

class My_Plugin_Shortcodes
{

    public function __construct()
    {
        add_shortcode('daniel_ld_all_courses', [$this, 'Daniel_learndash_all_courses_shortcode']);
        add_shortcode('daniel_ld_enrolled_courses', [$this, 'Daniel_learndash_enrolled_courses_shortcode']);
        add_shortcode('daniel_ld_custom_course_button', [$this, 'Daniel_learndash_custom_course_button_shortcode']);
        add_shortcode('daniel_ld_get_the_price_or_no_return_nothing', [$this, 'Daniel_learndash_get_the_price_or_no_return_nothing']);
        
        // Registrar acción AJAX para cargar más cursos
        add_action('wp_ajax_load_more_enrolled_courses', [$this, 'ajax_load_more_enrolled_courses']);
        add_action('wp_ajax_nopriv_load_more_enrolled_courses', [$this, 'ajax_load_more_enrolled_courses']);
    }

    public function Daniel_learndash_all_courses_shortcode()
    {
        $args = array(
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
            'orderby' => 'term_order', // Ordenar por el orden de términos
            'order' => 'ASC',
            'tax_query' => array(
                array(
                    'taxonomy' => 'ld_course_category',
                    'field'    => 'term_id',
                    'terms'    => get_terms(array(
                        'taxonomy' => 'ld_course_category',
                        'fields' => 'ids',
                        'orderby' => 'name',
                        'order' => 'ASC',
                    )),
                    'operator' => 'IN'
                )
            )
        );

        $courses = get_posts($args);

        $html = '<div class="learndash-all-courses">';

        foreach ($courses as $course) {
            $course_id = $course->ID;
            $course_title = get_the_title($course_id);
            $course_permalink = get_permalink($course_id);
            $featured_image = get_the_post_thumbnail_url($course_id, 'medium_large');

            // Obtener las categorías del curso
            $categories = get_the_terms($course_id, 'ld_course_category');
            $category_names = array();

            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $category) {
                    $category_names[] = $category->name;
                }
            }

            $isProximo = in_array('proximamente', $category_names);
            $proximoSpan = $isProximo ? '<div class="ribbon ribbon-top-left"><span>Próximamente</span></div>' : '';

            // Obtener los metadatos del curso y deserializarlos
            $course_meta = get_post_meta($course_id, '_sfwd-courses', true);
            $course_meta = maybe_unserialize($course_meta);

            // Obtener el precio del curso y la URL de pago
            $course_price = isset($course_meta['sfwd-courses_course_price']) ? $course_meta['sfwd-courses_course_price'] : '';

            $html .= <<<HTML
            <article class="course-item">
                <a href="{$course_permalink}" class="course-item-link">
                    {$proximoSpan}
                    <div class="course-image">
                        <img loading="lazy" src="{$featured_image}" alt="{$course_title}">
                    </div>
                    <div class="info-container">
                        <h2>{$course_title}</h2>
                        <footer class="footer-link">
                            <span class="footer__price">{$course_price}</span>
                            <span>Ver →</span>
                        </footer>
                    </div>
                </a>
            </article>
            HTML;
        }

        $html .= '</div>';

        return $html;
    }

    public function Daniel_learndash_enrolled_courses_shortcode($atts = [])
    {
        global $wpdb;
        $user_id = get_current_user_id(); // Obtener el ID del usuario actual

        // Obtener el idioma actual de Polylang
        $current_language = '';
        if (function_exists('pll_current_language')) {
            $current_language = pll_current_language('slug');
        } else {
            $current_language = 'es'; // Valor predeterminado
        }

        // Textos según idioma para el botón "Cargar más"
        $load_more_text = ($current_language == 'es') ? 'Cargar más cursos' : 'Load more courses';

        // Preparar y ejecutar la consulta SQL para obtener cursos por idioma
        $sql = $wpdb->prepare(
            "SELECT DISTINCT posts.ID, posts.post_title 
            FROM {$wpdb->posts} AS posts 
            INNER JOIN {$wpdb->prefix}learndash_user_activity AS learndash_user_activity 
                ON posts.ID = learndash_user_activity.course_id 
            INNER JOIN {$wpdb->postmeta} AS postmeta 
                ON posts.ID = postmeta.post_id 
            INNER JOIN {$wpdb->term_relationships} AS term_relationships 
                ON posts.ID = term_relationships.object_id 
            INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy 
                ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id 
            INNER JOIN {$wpdb->terms} AS terms 
                ON term_taxonomy.term_id = terms.term_id  
            WHERE learndash_user_activity.user_id = %d 
            AND learndash_user_activity.activity_type = 'access' 
            AND postmeta.meta_key = '_thumbnail_id' 
            AND term_taxonomy.taxonomy = 'language'
            AND terms.slug = %s",
            $user_id,
            $current_language
        );

        $enrolled_courses = $wpdb->get_results($sql);
        $total_courses = count($enrolled_courses);
        
        // Inicializar contenedor con atributos para AJAX
        $html = '<div class="learndash-enrolled-courses" 
                    data-user-id="'.esc_attr($user_id).'" 
                    data-language="'.esc_attr($current_language).'" 
                    data-total="'.esc_attr($total_courses).'" 
                    data-loaded="0">';

        // Verificar si el usuario está inscrito en algún curso
        if (!empty($enrolled_courses)) {
            // Mostrar solo los primeros 6 cursos inicialmente
            $courses_to_show = array_slice($enrolled_courses, 0, 6);
            
            $html .= $this->render_enrolled_courses($courses_to_show);
            
            // Agregar botón "Cargar más" si hay más de 6 cursos
            if ($total_courses > 6) {
                $html .= '<div class="load-more-container">';
                $html .= '<button id="load-more-courses" class="btn btn-primary load-more-button" data-offset="6">' . esc_html($load_more_text) . '</button>';
                $html .= '</div>';
                
                // Agregar script inline para manejar AJAX
                add_action('wp_footer', [$this, 'add_load_more_script']);
            }
        } else {
            $html .= '<p class="alert-daniel">No estás inscrito en ningún curso para este idioma actualmente.</p>';
        }

        $html .= '</div>';

        return $html;
    }
    
    /**
     * Renderiza los cursos inscritos
     */
    private function render_enrolled_courses($courses)
    {
        $html = '';
        foreach ($courses as $course) {
            $course_id = $course->ID;
            $course_title = get_the_title($course_id);
            $course_permalink = get_permalink($course_id);
            $featured_image = get_the_post_thumbnail_url($course_id, 'medium_large');

            $html .= '<article class="course-item">';
            $html .= '<a href="' . esc_url($course_permalink) . '" class="course-item-link">';
            $html .= '<div class="course-image">';
            $html .= '<img loading="lazy" src="' . esc_url($featured_image) . '" alt="' . esc_attr($course_title) . '">';
            $html .= '</div>';
            $html .= '<div class="course-title"><h2>' . esc_html($course_title) . '</h2></div>';
            $html .= '</a>';
            $html .= '</article>';
        }
        return $html;
    }
    
    /**
     * Añade el script JavaScript para cargar más cursos
     */
    public function add_load_more_script()
    {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Manejar el clic en el botón "Cargar más"
            $(document).on('click', '#load-more-courses', function() {
                var button = $(this);
                var container = $('.learndash-enrolled-courses');
                var offset = parseInt(button.attr('data-offset'));
                var userId = container.data('user-id');
                var language = container.data('language');
                var total = parseInt(container.data('total'));
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'load_more_enrolled_courses',
                        offset: offset,
                        user_id: userId,
                        language: language
                    },
                    beforeSend: function() {
                        button.text('<?php echo ($current_language == 'es') ? 'Cargando...' : 'Loading...'; ?>');
                        button.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            // Insertar nuevos cursos antes del contenedor del botón
                            button.closest('.load-more-container').before(response.data.html);
                            
                            // Actualizar el offset para la próxima carga
                            var newOffset = offset + 6;
                            button.attr('data-offset', newOffset);
                            
                            // Si ya se cargaron todos los cursos, ocultar el botón
                            if (newOffset >= total) {
                                button.closest('.load-more-container').remove();
                            } else {
                                button.text('<?php echo ($current_language == 'es') ? 'Cargar más cursos' : 'Load more courses'; ?>');
                                button.prop('disabled', false);
                            }
                        }
                    },
                    error: function() {
                        button.text('<?php echo ($current_language == 'es') ? 'Error. Intentar de nuevo' : 'Error. Try again'; ?>');
                        button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }


    public function Daniel_learndash_custom_course_button_shortcode($atts)
    {
        // Obtener el ID del curso
        $course_id = get_the_ID();

        // Obtener el usuario actual
        $user_id = get_current_user_id();

        // Verificar si el usuario está inscrito en el curso
        $is_enrolled = sfwd_lms_has_access($course_id, $user_id);

        // Obtener los metadatos del curso y deserializarlos
        $course_meta = get_post_meta($course_id, '_sfwd-courses', true);
        $course_meta = maybe_unserialize($course_meta);

        // Obtener el precio del curso y la URL de pago
        $course_price = isset($course_meta['sfwd-courses_course_price']) ? $course_meta['sfwd-courses_course_price'] : '';
        $payment_url = isset($course_meta['sfwd-courses_custom_button_url']) ? $course_meta['sfwd-courses_custom_button_url'] : '';

        // Obtener el idioma actual de Polylang
        $current_language = '';
        if (function_exists('pll_current_language')) {
            $current_language = pll_current_language('slug');
        } else {
            $current_language = 'es'; // Valor predeterminado
        }

        // Textos según idioma
        $texts = array(
            'es' => array(
                'continue' => 'Continuar',
                'go_to_courses' => 'Ir a todos los cursos',
                'back_to_start' => 'Volver al inicio',
                'buy_course' => 'Comprar curso',
                'login' => 'Inicia sesión',
                'already_purchased' => '¿Ya compraste este curso?'
            ),
            'en' => array(
                'continue' => 'Continue',
                'go_to_courses' => 'Go to all courses',
                'back_to_start' => 'Back to start',
                'buy_course' => 'Buy course',
                'login' => 'Log in',
                'already_purchased' => 'Already purchased this course?'
            )
        );

        // Asegurarse de que el idioma tenga textos definidos
        if (!isset($texts[$current_language])) {
            $current_language = 'es'; // Usar español como fallback si no existe el idioma
        }

        // get the categories
        $categories = get_the_terms($course_id, 'ld_course_category');
        $category_names = array();

        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $category_names[] = strtolower($category->name);
            }
        }

        // Verificar si el curso tiene la categoría "proximamente" (español) o "comming soon" (inglés)
        $isProximo = in_array('proximamente', $category_names) || in_array('comming soon', $category_names);


        if ($is_enrolled) {
            // Obtener todas las lecciones del curso
            $course_steps = learndash_course_get_steps_by_type($course_id, 'sfwd-lessons');

            // Encontrar la próxima lección no completada
            $next_lesson_id = null;
            foreach ($course_steps as $lesson_id) {
                $completed = learndash_is_lesson_complete($user_id, $lesson_id);
                if (!$completed) {
                    $next_lesson_id = $lesson_id;
                    break;
                }
            }

            if ($next_lesson_id) {
                $button_text = $texts[$current_language]['continue'];
                $button_url = get_permalink($next_lesson_id);
            } else {
                // Si todas las lecciones están completadas, redirige a la primera lección
                if ($isProximo) {
                    $button_text = $texts[$current_language]['go_to_courses'];
                    $button_url = home_url('/cursos');
                } else {
                    $button_text = $texts[$current_language]['back_to_start'];
                    $first_lesson_id = reset($course_steps);
                    $button_url = get_permalink($first_lesson_id);
                }
            }

            // Construir el botón
            $output = '<div class="daniel-custom-course-button">';
            $output .= '<a href="' . esc_url($button_url) . '" class="btn btn-primary">' . esc_html($button_text) . '</a>';
            $output .= '</div>';

            return $output;
        }


        if ($isProximo) {
            $button_text = $texts[$current_language]['go_to_courses'];
            $button_url = home_url('/cursos');
        } else {
            $button_text = $texts[$current_language]['buy_course'];
            $button_url = esc_url($payment_url);
        }


        $login_text = $texts[$current_language]['login'];
        $login_url = get_permalink(get_option('woocommerce_myaccount_page_id'));
        // Construir el botón
        $output = '<div class="daniel-custom-course-button">';
        $output .= '<a href="' . esc_url($button_url) . '" class="btn btn-primary">' . esc_html($button_text) . '</a>';
        $output .= '<div class="daniel-custom-course-button-login">';
        $output .= '<p>' . esc_html($texts[$current_language]['already_purchased']) . '</p>';
        $output .= '<a href="' . esc_url($login_url) . '" class="btn btn-secondary">' . esc_html($login_text) . '</a>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    public function Daniel_learndash_get_the_price_or_no_return_nothing($atts)
    {
        // Obtener el ID del curso
        $course_id = get_the_ID();

        // Obtener el usuario actual
        $user_id = get_current_user_id();

        // Verificar si el usuario está inscrito en el curso
        $is_enrolled = sfwd_lms_has_access($course_id, $user_id);

        // Obtener los metadatos del curso y deserializarlos
        $course_meta = get_post_meta($course_id, '_sfwd-courses', true);
        $course_meta = maybe_unserialize($course_meta);

        // Obtener el precio del curso y la URL de pago
        $course_price = isset($course_meta['sfwd-courses_course_price']) ? $course_meta['sfwd-courses_course_price'] : '';

        if ($is_enrolled) {
            $button_text = '';
        } else {
            $button_text = '<p class="price_text_shortcode">' . $course_price . '</p>';
        }

        // Construir el botón
        $output = '<div class="daniel-custom-course-price">';
        $output .= $button_text;
        $output .= '</div>';

        return $output;
    }

    /**
     * Función AJAX para cargar más cursos
     */
    public function ajax_load_more_enrolled_courses()
    {
        global $wpdb;

        // Verificar nonce para seguridad (puedes agregar esto más adelante)
        // if (!check_ajax_referer('load_more_courses_nonce', 'nonce', false)) {
        //    wp_send_json_error(['message' => 'Verificación de seguridad fallida']);
        // }

        // Obtener parámetros
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : 'es';

        // Preparar y ejecutar la consulta SQL para obtener cursos por idioma con límite y offset
        $sql = $wpdb->prepare(
            "SELECT DISTINCT posts.ID, posts.post_title 
            FROM {$wpdb->posts} AS posts 
            INNER JOIN {$wpdb->prefix}learndash_user_activity AS learndash_user_activity 
                ON posts.ID = learndash_user_activity.course_id 
            INNER JOIN {$wpdb->postmeta} AS postmeta 
                ON posts.ID = postmeta.post_id 
            INNER JOIN {$wpdb->term_relationships} AS term_relationships 
                ON posts.ID = term_relationships.object_id 
            INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy 
                ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id 
            INNER JOIN {$wpdb->terms} AS terms 
                ON term_taxonomy.term_id = terms.term_id  
            WHERE learndash_user_activity.user_id = %d 
            AND learndash_user_activity.activity_type = 'access' 
            AND postmeta.meta_key = '_thumbnail_id' 
            AND term_taxonomy.taxonomy = 'language'
            AND terms.slug = %s
            LIMIT %d OFFSET %d",
            $user_id,
            $language,
            6,  // Límite de 6 cursos por carga
            $offset
        );

        $courses = $wpdb->get_results($sql);

        if (!empty($courses)) {
            $html = $this->render_enrolled_courses($courses);
            wp_send_json_success(['html' => $html]);
        } else {
            wp_send_json_error(['message' => 'No hay más cursos para cargar']);
        }
    }
}
