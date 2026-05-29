<?php
/**
 * APARTAMENTOS ESTANQUES - SISTEMA BLOG COMPLETO v2.0
 * GeneratePress Child Theme Functions
 * Optimizado para SEO y reservas directas
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// 1. CONFIGURACIÓN INICIAL DEL TEMA
// =============================================================================
add_filter('wp_mail_from', function($email){
    return 'admin@websitesdaddy.com';
});
add_action('after_setup_theme', 'apartamentos_estanques_setup');
function apartamentos_estanques_setup() {
    // Soporte para imágenes destacadas
    add_theme_support('post-thumbnails');
    
    // Soporte para HTML5
    add_theme_support('html5', array(
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
    ));
    
    // Soporte para títulos automáticos
    add_theme_support('title-tag');
    
    // Tamaños de imagen personalizados para el blog
    add_image_size('blog-featured', 800, 400, true);
    add_image_size('blog-thumbnail', 400, 250, true);
    add_image_size('blog-card', 380, 250, true);
    
    // Soporte para idiomas
    load_theme_textdomain('apartamentos-estanques', get_stylesheet_directory() . '/languages');
}
// Add Booking.com Multiplier Setting
add_action('admin_init', function () {
    add_settings_section(
        'booking_direct_section',
        'Booking.com Direct Settings',
        '__return_false',
        'general'
    );

    add_settings_field(
        'booking_direct_multiplier',
        'Booking.com Direct Multiplier',
        function () {
            $value = get_option('booking_direct_multiplier', '1.15');
            echo '<input type="number" step="0.01" min="1" name="booking_direct_multiplier" value="' . esc_attr($value) . '" />';
        },
        'general',
        'booking_direct_section'
    );

    register_setting('general', 'booking_direct_multiplier');
});
function check_room_availability($arrival, $departure, $listing_json, $room_json, $room_id) {

    if (empty($arrival) || empty($departure)) {
        return "Please select arrival and departure dates.";
    }

    $start = strtotime($arrival);
    $end   = strtotime($departure); // end date NOT included
    $nights = ($end - $start) / 86400;
    
    // ----------------------------
    // 3️⃣ Minimum stay check (per room JSON)
    // ----------------------------
    $required_min = 1;

    foreach ($room_json as $date => $info) {
        // min_stay applies only inside the stay range
        for ($d = $start; $d < $end; $d += 86400) {
            if ($date == date("Ymd", $d)) {
                $required_min = max($required_min, intval($info['min_stay']));
            }
        }
    }

    if ($nights < $required_min) {
        return "Minimum stay is {$required_min} nights.";
    }

    // ----------------------------
    // 1️⃣ Availability map for this room
    // ----------------------------
    $avail_map = [];

    if (!empty($listing_json['rooms'][$room_id])) {
        foreach ($listing_json['rooms'][$room_id] as $row) {
            $avail_map[$row['date']] = intval($row['available']);
        }
    }

    // ----------------------------
    // 2️⃣ Check each night for availability
    // ----------------------------
    for ($d = $start; $d < $end; $d += 86400) {

        $date_dash = date("Y-m-d", $d);   // for listing JSON
        $date_ymd  = date("Ymd", $d);     // for room JSON

        // Missing availability data
        if (!isset($avail_map[$date_dash])) {
            return "No availability data for {$date_dash}.";
        }

        if ($avail_map[$date_dash] == 0) {
            return "Not available on {$date_dash}.";
        }
    }

    return true;
}

function get_combined_room_prices() {
    $upload_dir = wp_upload_dir();
    $json_dir   = $upload_dir['basedir'] . '/beds24-availability/';
    $combined = [];

    foreach (glob($json_dir . "room-*.json") as $file) {
        $json = json_decode(file_get_contents($file), true);
        foreach ($json as $date => $data) {
            $price = floatval($data['lowest_price']);
            if (!isset($combined[$date]) || $price < $combined[$date]) {
                $combined[$date] = $price;
            }
        }
    }
    //echo "<pre>";print_r($combined);exit();
    return $combined;
}
/* ---------------------------------------------------------
   AJAX: Load Flexible Dates Section
--------------------------------------------------------- */
add_action('wp_ajax_load_flexible_dates', 'ajax_load_flexible_dates');
add_action('wp_ajax_nopriv_load_flexible_dates', 'ajax_load_flexible_dates');

function ajax_load_flexible_dates() {

    // Required input values
    $arrive          = sanitize_text_field($_POST['arrive'] ?? '');
    $depart          = sanitize_text_field($_POST['depart'] ?? '');
    $combined_prices = json_decode(stripslashes($_POST['combined_prices'] ?? ''), true);
    $listing_json    = json_decode(stripslashes($_POST['listing'] ?? ''), true);
    $minstay_json    = json_decode(stripslashes($_POST['minstay'] ?? ''), true);

    if (!$arrive || !$depart || empty($combined_prices) || empty($listing_json)) {
        wp_send_json_success(['html' => '']);
    }

    // Calculate nights
    $requested_nights = (strtotime($depart) - strtotime($arrive)) / 86400;

    // Helper - check availability across full range
    $is_available = function($arrive, $depart) use ($listing_json, $minstay_json) {

        $start = strtotime($arrive);
        $end   = strtotime($depart);
        while ($start <= $end) {

            $keyYMD = date("Ymd", $start);
            $dateIso = date("Y-m-d", $start);

            // 1. Check listing.json availability (any room available)
            $available_any = false;
            foreach ($listing_json['rooms'] as $room_id => $days) {
                foreach ($days as $row) {
                    if ($row['date'] == $dateIso && intval($row['available']) > 0) {
                        $available_any = true;
                        break 2;
                    }
                }
            }
            //echo $keyYMD.": ".$available_any."==";

            if (!$available_any) {
                return false;
            }

            // 2. Check min stay constraint if exists
            if (isset($minstay_json[$keyYMD])) {
                $min_stay = intval($minstay_json[$keyYMD]['min_stay'] ?? 1);
                if ($min_stay > 1) {
                    // If min stay exceeds current nights → invalid
                    // We check only the arrival day
                    if ($start == strtotime($arrive) && $min_stay > (($end - $start) / 86400)) {
                        return false;
                    }
                }
            }

            $start = strtotime("+1 day", $start);
        }

        return true;
    };

    // Price calculator unchanged
    $get_price_for_range = function($arrive, $depart, $combined_prices) {

        $start = strtotime($arrive);
        $end = strtotime($depart);
        $total = 0;

        while ($start < $end) {
            $key = date("Ymd", $start);
            if (isset($combined_prices[$key])) {
                $total += floatval($combined_prices[$key]);
            }
            $start = strtotime("+1 day", $start);
        }

        return $total;
    };

    // Build alternatives: forward only until we find 6 good results
    $alternatives = [];
    $shift = 1;
    while (count($alternatives) < 6 && $shift <= 120) {

        $new_arrive = date("Y-m-d", strtotime("$arrive +$shift day"));
        $new_depart = date("Y-m-d", strtotime("$depart +$shift day"));

        // Check nights match
        $nights_calc = (strtotime($new_depart) - strtotime($new_arrive)) / 86400;
        if ($nights_calc != $requested_nights) {
            $shift++;
            continue;
        }

        // Check availability rules
        if (!$is_available($new_arrive, $new_depart)) {
            $shift++;
            continue;
        }

        // Price calc
        $total_price = $get_price_for_range($new_arrive, $new_depart, $combined_prices);

        $alternatives[] = [
            "arrive" => $new_arrive,
            "depart" => $new_depart,
            "nights" => $requested_nights,
            "price"  => $total_price,
        ];

        $shift++;
    }

    if (empty($alternatives)) {
        wp_send_json_success(['html' => '']);
    }

    // Build HTML output
    ob_start();
    
    // First alternative date (earliest available)
    $first_alt_date = $alternatives[0]['arrive'];
    $nice_date = date("F j", strtotime($first_alt_date));
    ?>
    <div class="alt-dates-wrapper" style="padding:0px 10px;margin:25px auto;max-width:100%;">
    
        <!-- MESSAGE BOX LIKE THE SCREENSHOT -->
        <div class="no-avail-message">
            <div style="display:flex;align-items:flex-start;gap:15px;flex:1;min-width:250px;">
    
                <div style="font-size:28px;color:#c5924c;"><svg class="hotel-icon" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M7 11h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2zM5 21q-.825 0-1.412-.587T3 19V7q0-.825.588-1.412T5 5h1V3h2v2h8V3h2v2h1q.825 0 1.413.588T21 7v12q0 .825-.587 1.413T19 21zM5 19h14V10H5z"></path>
                    </svg></div>
    
                <div>
                    <div style="font-size:18px;font-weight:700;color:#b07a34;margin-bottom:5px;">
                        <?php echo sprintf(pll__('Sorry. No availability until %s.'), $nice_date); ?>
                    </div>
                    <div style="font-size:14px;color:#555;">
                        <?php echo pll__('If you want more information, contact us through'); ?>
                        <a href="mailto:info@apartamentosestanques.com" style="color:#3a0c1b;text-decoration:underline;">
                            info@apartamentosestanques.com
                        </a>
                        or
                        <a href="tel:+34971007008" style="color:#3a0c1b;text-decoration:underline;">
                            +34 679 897 868
                        </a>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Flexible Dates Title -->
        <h3 style="margin-bottom:15px;font-size:16px;font-weight:700;text-align: center;">
            <?php echo pll__('Flexible with your dates?'); ?>
        </h3>


        <div class="alt-dates-grid" style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(120px,1fr));
            gap:15px;
        ">
            <?php foreach ($alternatives as $opt): ?>
                <?php
                $a = date('M j', strtotime($opt['arrive']));
                $d = date('M j', strtotime($opt['depart']));

                $page_url = sanitize_text_field($_POST['page_url'] ?? home_url());
                $url = add_query_arg([
                    "arrive"   => $opt['arrive'],
                    "depart"   => $opt['depart'],
                    "nights"   => $opt['nights'],
                    "adults"   => $_GET['adults']   ?? 2,
                    "children" => $_GET['children'] ?? 0,
                    "guests"   => $_GET['guests']   ?? 2,
                ], $page_url);
                ?>

                <div class="alt-card" style="
                    padding:12px;
                    border:1px solid #ddd;
                    border-radius:8px;
                    background:#fafafa;
                ">
                    <div style="font-weight:600;font-size:12px;"><?php echo "$a – $d"; ?></div>
                    <div style="color:#666;margin:3px 0;"><?php echo $opt['nights'] . ' ' . pll__('nights'); ?></div>
                    <div style="font-size:12px;font-weight:700;color:#2b2622;">
                        <?php echo pll__('From'); ?> €<?php echo number_format($opt['price'], 2); ?>
                    </div>

                    <a href="<?php echo esc_url($url); ?>">
                        <button style="
                            margin-top:10px;
                            width:100%;
                            padding:8px;
                            background:#3d3732;
                            border-radius:6px;
                            border:none;
                            color:#fff;
                            font-size:12px;
                            cursor:pointer;
                        ">
                            <?php echo pll__('Check availability'); ?>
                        </button>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}

// =============================================================================
// 2. CUSTOM POST TYPE PARA BLOG MULTIIDIOMA
// =============================================================================
function t_($string) {
    $lang = pll_current_language(); // 'es', 'de', 'en'

    // ----------------------------
    // SPANISH (default)
    // ----------------------------
    $es = [
        "Choose stay" => "Elegir estancia",
        "Personal details" => "Datos personales",
        "Confirmation" => "Confirmación",
        "Check-in → Check-out" => "Entrada → Salida",
        "Nights" => "Noches",
        "Guest" => "Huésped",
        "Guests" => "Huéspedes",
        "Search" => "Buscar",

        // VIP CLUB
        "Join apartamento sestanques VIP Club" => "Únete al VIP Club de Apartamentos Estanques",
        "As a member of our loyalty club, you can benefit from exclusive advantages when booking online." =>
            "Como miembro de nuestro club de fidelidad, puedes disfrutar de ventajas exclusivas al reservar online.",
        "Members' benefits applied" => "Beneficios de miembros aplicados",
        "10% extra discount on all your bookings" => "10% de descuento extra en todas tus reservas",
        "Access to exclusive services in all our hotels" => "Acceso a servicios exclusivos en todos nuestros hoteles",
        "Welcome gift upon arrival" => "Regalo de bienvenida al llegar",
        "Free in-room safe" => "Caja fuerte gratuita en la habitación",
        "10% discount on Estanques merchandise" => "10% de descuento en artículos de Estanques",
        "Free parking in establishments where available" => "Aparcamiento gratuito donde esté disponible",
        "And many more benefits!" => "¡Y muchos beneficios más!",

        // FOOTER
        "Apartments Ponds" => "Apartamentos Estanques",
        "Legal Notice" => "Aviso Legal",
        "Cookies Policy" => "Política de Cookies",
        "Privacy Policy" => "Política de Privacidad",
    ];

    // ----------------------------
    // GERMAN
    // ----------------------------
    $de = [
        "Choose stay" => "Aufenthalt wählen",
        "Personal details" => "Persönliche Angaben",
        "Confirmation" => "Bestätigung",
        "Check-in → Check-out" => "Check-in → Check-out",
        "Nights" => "Nächte",
        "Guest" => "Gast",
        "Guests" => "Gäste",
        "Search" => "Suchen",

        // VIP CLUB
        "Join apartamento sestanques VIP Club" => "Treten Sie dem Apartamentos Estanques VIP Club bei",
        "As a member of our loyalty club, you can benefit from exclusive advantages when booking online." =>
            "Als Mitglied unseres Treueclubs profitieren Sie von exklusiven Vorteilen bei Online-Buchungen.",
        "Members' benefits applied" => "Mitgliedervorteile angewendet",
        "10% extra discount on all your bookings" => "10% zusätzlicher Rabatt auf alle Ihre Buchungen",
        "Access to exclusive services in all our hotels" => "Zugang zu exklusiven Services in all unseren Hotels",
        "Welcome gift upon arrival" => "Willkommensgeschenk bei der Ankunft",
        "Free in-room safe" => "Kostenloser Safe im Zimmer",
        "10% discount on Estanques merchandise" => "10% Rabatt auf Estanques-Artikel",
        "Free parking in establishments where available" => "Kostenloses Parken, wo verfügbar",
        "And many more benefits!" => "Und viele weitere Vorteile!",

        // FOOTER
        "Apartments Ponds" => "Apartments Ponds",
        "Legal Notice" => "Rechtlicher Hinweis",
        "Cookies Policy" => "Cookie-Richtlinie",
        "Privacy Policy" => "Datenschutzrichtlinie",
    ];

    // Return matching translation
    if ($lang === 'es' && isset($es[$string])) return $es[$string];
    if ($lang === 'de' && isset($de[$string])) return $de[$string];

    return $string; // default (English)
}
add_action('init', function() {

    $strings = [
        'Choose stay',
        'Personal details',
        'Confirmation',
        'Check-in → Check-out',
        'Nights',
        'Guest',
        'Guests',
        'Search',
        
        // VIP CLUB
        'Join apartamento sestanques VIP Club',
        'As a member of our loyalty club, you can benefit from exclusive advantages when booking online.',
        "Members' benefits applied",
        '10% extra discount on all your bookings',
        'Access to exclusive services in all our hotels',
        'Welcome gift upon arrival',
        'Free in-room safe',
        '10% discount on Estanques merchandise',
        'Free parking in establishments where available',
        'And many more benefits!',

        // FOOTER
        'Apartments Ponds',
        'Legal Notice',
        'Cookies Policy',
        'Privacy Policy',

        // Confirmation page
        'Thank you for your booking!',
        'Your reservation has been successfully confirmed.',
        'Your stay',
        'Check-in',
        'Check-out',
        'Total nights',
        'Your rooms',
        'Adults',
        'Children',
        'Payment summary',
        'Total paid',
        'Return to homepage'
    ];

    foreach ($strings as $str) {
        pll_register_string('booking_texts', $str, true);
    }
});
add_action('init', function() {

    $strings = [
        'Choose stay',
        'Personal details',
        'Confirmation',

        'First name (required)',
        'Last name (required)',
        'Email (required)',
        'Phone (required)',
        'Cardholder name (required)',
        'Comments',

        'Not sure yet', 'Morning', 'Afternoon', 'Evening',

        'I agree and accept the payment terms, cancellation, other conditions and privacy policies.',
        'I would like to receive future offers and news.',
        'I want to become a member and accept terms.',

        'Online secure payment',
        'Additional Information',
        'Booking details',

        'Check-in', 'Check-out',
        'Your reservation',
        'Base price', 'Offer discount', '10% VAT',
        'Local Tax from 16 years',
        'Total',

        'Pay now',
        "You'll be redirected to complete your payment.",
        'We will try to handle your requests, but we cannot always guarantee it.',
        'Your information is protected with SSL encryption.',

        'Legal Notice', 'Cookies Policy', 'Privacy Policy'
    ];

    foreach ($strings as $s) {
        pll_register_string('instant_booking', $s);
    }
});

add_action('init', function() {
    pll_register_string('rooms', 'No rooms found');
    pll_register_string('rooms', 'Please try different dates');
    pll_register_string('rooms', 'More info');
    pll_register_string('rooms', 'DirectBooking 10% Benefits applied');
    pll_register_string('rooms', 'Includes taxes. Excludes fees');
    pll_register_string('rooms', 'Book');
    pll_register_string('rooms', 'Book Now');
    pll_register_string('rooms', 'Living Room');
    pll_register_string('rooms', 'Heating');
    pll_register_string('rooms', 'Bathrobe');
    pll_register_string('rooms', 'Please select Adults and Children for all units.');
    pll_register_string('rooms', 'Please select at least one unit to book.');
    pll_register_string('rooms', 'Description');
    pll_register_string('rooms', 'Selected occupation');
    pll_register_string('rooms', 'Possible bed arrangements');
    pll_register_string('rooms', 'Top amenities');
    pll_register_string('rooms', 'More services');
    pll_register_string('rooms', '24h room service');
    pll_register_string('rooms', 'Eco-friendly');
    pll_register_string('rooms', 'Free safe');
    pll_register_string('rooms', 'Mattress');
});
add_action('init', function() {

    $strings = [
        'Choose stay',
        'Personal details',
        'Confirmation',

        'Payment Failed',
        'Unfortunately your payment could not be completed.',
        'This may happen due to insufficient balance, incorrect card details, or a network issue.',
        'Please try again or choose a different payment method.',
        'Try Again',
        'Return Home',

        'Legal Notice',
        'Cookies Policy',
        'Privacy Policy'
    ];

    foreach ($strings as $s) {
        pll_register_string('booking_failed', $s);
    }
});

add_action('init', 'apartamentos_blog_post_type');
function apartamentos_blog_post_type() {
    $labels = array(
        'name' => '📝 Blog Apartamentos',
        'singular_name' => 'Post Blog',
        'add_new' => 'Añadir Post',
        'add_new_item' => 'Nuevo Post Blog',
        'edit_item' => 'Editar Post',
        'new_item' => 'Nuevo Post',
        'view_item' => 'Ver Post',
        'search_items' => 'Buscar Posts',
        'not_found' => 'No se encontraron posts',
        'not_found_in_trash' => 'No hay posts en la papelera',
        'menu_name' => '📝 Blog Multiidioma',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'blog'),
        'capability_type' => 'post',
        'hierarchical' => false,
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'comments', 'revisions'),
        'menu_icon' => 'dashicons-palmtree',
        'menu_position' => 5,
        'can_export' => true,
        'elementor_cpt_support' => true, // Soporte para Elementor
    );

    register_post_type('blog_post', $args);
}

// =============================================================================
// 3. TAXONOMÍAS PARA CATEGORIZACIÓN AVANZADA
// =============================================================================

add_action('init', 'apartamentos_blog_taxonomies');
function apartamentos_blog_taxonomies() {
    
    // Categorías del blog (Playas, Restaurantes, Actividades, etc.)
    register_taxonomy('blog_category', 'blog_post', array(
        'labels' => array(
            'name' => 'Categorías Blog',
            'singular_name' => 'Categoría',
            'add_new_item' => 'Añadir Categoría',
            'edit_item' => 'Editar Categoría',
            'update_item' => 'Actualizar Categoría',
            'view_item' => 'Ver Categoría',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'categoria'),
        'show_admin_column' => true,
    ));
    
    // Idiomas
    register_taxonomy('blog_language', 'blog_post', array(
        'labels' => array(
            'name' => 'Idiomas',
            'singular_name' => 'Idioma',
            'add_new_item' => 'Añadir Idioma',
            'edit_item' => 'Editar Idioma',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'idioma'),
        'show_admin_column' => true,
    ));
    
    // Dificultad (Fácil, Moderado, Difícil)
    register_taxonomy('blog_difficulty', 'blog_post', array(
        'labels' => array(
            'name' => 'Dificultad',
            'singular_name' => 'Nivel',
        ),
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
}

// =============================================================================
// 4. METABOXES PARA SEO Y DATOS ESPECÍFICOS
// =============================================================================

add_action('add_meta_boxes', 'apartamentos_add_meta_boxes');
function apartamentos_add_meta_boxes() {
    
    // Metabox principal para SEO y datos de ubicación
    add_meta_box(
        'apartamentos_seo_data',
        '🎯 SEO & Datos de Ubicación - Apartamentos Estanques',
        'apartamentos_seo_metabox_callback',
        'blog_post',
        'normal',
        'high'
    );
    
    // Metabox para mapas y coordenadas
    add_meta_box(
        'apartamentos_maps_data',
        '🗺️ Información de Mapas y Rutas',
        'apartamentos_maps_metabox_callback',
        'blog_post',
        'side',
        'default'
    );
    
    // Metabox para configuración de CTA
    add_meta_box(
        'apartamentos_cta_config',
        '📢 Configuración CTA Reservas',
        'apartamentos_cta_metabox_callback',
        'blog_post',
        'side',
        'default'
    );
}

// Callback para metabox SEO y ubicación
function apartamentos_seo_metabox_callback($post) {
    wp_nonce_field('apartamentos_save_meta', 'apartamentos_meta_nonce');
    
    $keyword = get_post_meta($post->ID, '_apartamentos_keyword', true);
    $destination = get_post_meta($post->ID, '_apartamentos_destination', true);
    $distance_car = get_post_meta($post->ID, '_apartamentos_distance_car', true);
    $distance_walk = get_post_meta($post->ID, '_apartamentos_distance_walk', true);
    $time_car = get_post_meta($post->ID, '_apartamentos_time_car', true);
    $time_walk = get_post_meta($post->ID, '_apartamentos_time_walk', true);
    $difficulty = get_post_meta($post->ID, '_apartamentos_difficulty', true);
    $meta_title = get_post_meta($post->ID, '_apartamentos_meta_title', true);
    $meta_desc = get_post_meta($post->ID, '_apartamentos_meta_desc', true);
    
    ?>
    <style>
        .apartamentos-meta-table { width: 100%; border-spacing: 0; }
        .apartamentos-meta-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .apartamentos-meta-table td:first-child { font-weight: bold; width: 200px; background: #f9f9f9; }
        .apartamentos-meta-input { width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
        .apartamentos-tip { background: #e7f3ff; padding: 10px; border-radius: 5px; margin-top: 15px; }
    </style>
    
    <table class="apartamentos-meta-table">
        <tr>
            <td>🎯 Keyword Principal:</td>
            <td><input type="text" name="apartamentos_keyword" value="<?php echo esc_attr($keyword); ?>" 
                      class="apartamentos-meta-input" placeholder="ej: como llegar es trenc desde apartamentos estanques" /></td>
        </tr>
        <tr>
            <td>📍 Destino:</td>
            <td><input type="text" name="apartamentos_destination" value="<?php echo esc_attr($destination); ?>" 
                      class="apartamentos-meta-input" placeholder="ej: Playa Es Trenc" /></td>
        </tr>
        <tr>
            <td>🚗 Distancia en coche:</td>
            <td><input type="text" name="apartamentos_distance_car" value="<?php echo esc_attr($distance_car); ?>" 
                      class="apartamentos-meta-input" placeholder="ej: 2.8 km" /></td>
        </tr>
        <tr>
            <td>🚶‍♂️ Distancia caminando:</td>
            <td><input type="text" name="apartamentos_distance_walk" value="<?php echo esc_attr($distance_walk); ?>" 
                      class="apartamentos-meta-input" placeholder="ej: 2.9 km" /></td>
        </tr>
        <tr>
            <td>⏱️ Tiempo en coche:</td>
            <td><input type="text" name="apartamentos_time_car" value="<?php echo esc_attr($time_car); ?>" 
                      class="apartamentos-meta-input" placeholder="ej: 4 minutos" /></td>
        </tr>
        <tr>
            <td>⏱️ Tiempo caminando:</td>
            <td><input type="text" name="apartamentos_time_walk" value="<?php echo esc_attr($time_walk); ?>" 
                      class="apartamentos-meta-input" placeholder="ej: 35 minutos" /></td>
        </tr>
        <tr>
            <td>📊 Dificultad:</td>
            <td>
                <select name="apartamentos_difficulty" class="apartamentos-meta-input">
                    <option value="">Seleccionar...</option>
                    <option value="Fácil" <?php selected($difficulty, 'Fácil'); ?>>Fácil</option>
                    <option value="Moderado" <?php selected($difficulty, 'Moderado'); ?>>Moderado</option>
                    <option value="Difícil" <?php selected($difficulty, 'Difícil'); ?>>Difícil</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>📝 Meta Title (60 chars):</td>
            <td><input type="text" name="apartamentos_meta_title" value="<?php echo esc_attr($meta_title); ?>" 
                      class="apartamentos-meta-input" maxlength="60" placeholder="Se genera automáticamente si se deja vacío" /></td>
        </tr>
        <tr>
            <td>📄 Meta Description (160 chars):</td>
            <td><textarea name="apartamentos_meta_desc" class="apartamentos-meta-input" maxlength="160" 
                         placeholder="Se genera automáticamente si se deja vacío"><?php echo esc_textarea($meta_desc); ?></textarea></td>
        </tr>
    </table>
    
    <div class="apartamentos-tip">
        <strong>💡 Tips SEO:</strong><br>
        • La keyword debe incluir "apartamentos estanques" para mejor posicionamiento<br>
        • Los datos de distancia y tiempo aparecerán automáticamente en el post<br>
        • Si dejas Meta Title y Description vacíos, se generarán automáticamente<br>
        • Origen fijo: <strong>Carrer Estanys, 33, 07638 Colònia de Sant Jordi</strong>
    </div>
    <?php
}

// Callback para metabox de mapas
function apartamentos_maps_metabox_callback($post) {
    $coordinates = get_post_meta($post->ID, '_apartamentos_coordinates', true);
    $google_maps_embed = get_post_meta($post->ID, '_apartamentos_maps_embed', true);
    $show_map = get_post_meta($post->ID, '_apartamentos_show_map', true);
    
    ?>
    <table style="width:100%;">
        <tr>
            <td style="font-weight:bold;">📍 Coordenadas:</td>
        </tr>
        <tr>
            <td><input type="text" name="apartamentos_coordinates" value="<?php echo esc_attr($coordinates); ?>" 
                      style="width:100%; padding:8px;" placeholder="39.3262, 2.9956" /></td>
        </tr>
        <tr>
            <td style="font-weight:bold; padding-top:15px;">🗺️ Embed Google Maps:</td>
        </tr>
        <tr>
            <td><textarea name="apartamentos_maps_embed" style="width:100%; height:80px; padding:8px;" 
                         placeholder="Pegar código embed de Google Maps (opcional)"><?php echo esc_textarea($google_maps_embed); ?></textarea></td>
        </tr>
        <tr>
            <td style="padding-top:15px;">
                <label>
                    <input type="checkbox" name="apartamentos_show_map" value="1" <?php checked($show_map, '1'); ?> />
                    Mostrar mapa en el post
                </label>
            </td>
        </tr>
    </table>
    
    <p style="background:#f0f8ff; padding:10px; margin-top:15px; border-radius:5px; font-size:12px;">
        <strong>Ayuda:</strong><br>
        • Obtén coordenadas desde Google Maps haciendo clic derecho<br>
        • Para embed: Google Maps > Compartir > Incorporar mapa<br>
        • Si no añades embed, se generará automáticamente con las coordenadas
    </p>
    <?php
}

// Callback para metabox de CTA
function apartamentos_cta_metabox_callback($post) {
    $cta_enabled = get_post_meta($post->ID, '_apartamentos_cta_enabled', true);
    $cta_custom_text = get_post_meta($post->ID, '_apartamentos_cta_custom', true);
    $cta_button_text = get_post_meta($post->ID, '_apartamentos_cta_button', true);
    
    ?>
    <table style="width:100%;">
        <tr>
            <td>
                <label>
                    <input type="checkbox" name="apartamentos_cta_enabled" value="1" <?php checked($cta_enabled, '1'); ?> />
                    Mostrar CTA de reservas
                </label>
            </td>
        </tr>
        <tr>
            <td style="padding-top:15px;">
                <strong>Texto personalizado CTA:</strong><br>
                <textarea name="apartamentos_cta_custom" style="width:100%; height:60px; padding:8px;" 
                         placeholder="Texto personalizado (opcional)"><?php echo esc_textarea($cta_custom_text); ?></textarea>
            </td>
        </tr>
        <tr>
            <td style="padding-top:10px;">
                <strong>Texto del botón:</strong><br>
                <input type="text" name="apartamentos_cta_button" value="<?php echo esc_attr($cta_button_text); ?>" 
                       style="width:100%; padding:8px;" placeholder="Reservar Ahora" />
            </td>
        </tr>
    </table>
    <?php
}

// =============================================================================
// 5. GUARDAR DATOS DE METABOXES
// =============================================================================

add_action('save_post', 'apartamentos_save_meta');
function apartamentos_save_meta($post_id) {
    // Verificaciones de seguridad
    if (!isset($_POST['apartamentos_meta_nonce']) || !wp_verify_nonce($_POST['apartamentos_meta_nonce'], 'apartamentos_save_meta')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Array de campos a guardar
    $fields = array(
        'apartamentos_keyword' => '_apartamentos_keyword',
        'apartamentos_destination' => '_apartamentos_destination',
        'apartamentos_distance_car' => '_apartamentos_distance_car',
        'apartamentos_distance_walk' => '_apartamentos_distance_walk',
        'apartamentos_time_car' => '_apartamentos_time_car',
        'apartamentos_time_walk' => '_apartamentos_time_walk',
        'apartamentos_difficulty' => '_apartamentos_difficulty',
        'apartamentos_meta_title' => '_apartamentos_meta_title',
        'apartamentos_meta_desc' => '_apartamentos_meta_desc',
        'apartamentos_coordinates' => '_apartamentos_coordinates',
        'apartamentos_maps_embed' => '_apartamentos_maps_embed',
        'apartamentos_cta_custom' => '_apartamentos_cta_custom',
        'apartamentos_cta_button' => '_apartamentos_cta_button',
    );
    
    // Guardar campos de texto
    foreach ($fields as $field => $meta_key) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
        }
    }
    
    // Guardar checkboxes
    $checkbox_fields = array(
        'apartamentos_show_map' => '_apartamentos_show_map',
        'apartamentos_cta_enabled' => '_apartamentos_cta_enabled',
    );
    
    foreach ($checkbox_fields as $field => $meta_key) {
        $value = isset($_POST[$field]) ? '1' : '0';
        update_post_meta($post_id, $meta_key, $value);
    }
    
    // Generar meta title y description automáticamente si están vacíos
    apartamentos_generate_seo_data($post_id);
}

// =============================================================================
// 6. GENERAR DATOS SEO AUTOMÁTICAMENTE
// =============================================================================

function apartamentos_generate_seo_data($post_id) {
    $destination = get_post_meta($post_id, '_apartamentos_destination', true);
    $keyword = get_post_meta($post_id, '_apartamentos_keyword', true);
    $meta_title = get_post_meta($post_id, '_apartamentos_meta_title', true);
    $meta_desc = get_post_meta($post_id, '_apartamentos_meta_desc', true);
    
    // Generar Meta Title si está vacío
    if (empty($meta_title) && !empty($destination)) {
        $auto_title = "Cómo llegar a {$destination} desde Apartamentos Estanques";
        if (strlen($auto_title) > 60) {
            $auto_title = "{$destination} desde Apartamentos Estanques | Guía";
        }
        update_post_meta($post_id, '_apartamentos_meta_title', $auto_title);
    }
    
    // Generar Meta Description si está vacía
    if (empty($meta_desc) && !empty($destination)) {
        $auto_desc = "Descubre cómo llegar a {$destination} desde Apartamentos Estanques en Colonia Sant Jordi. Distancias, rutas y consejos. ¡Reserva directo!";
        if (strlen($auto_desc) > 160) {
            $auto_desc = "Guía completa para llegar a {$destination} desde Apartamentos Estanques. Rutas, distancias y tips. ¡Reserva directo!";
        }
        update_post_meta($post_id, '_apartamentos_meta_desc', $auto_desc);
    }
}

// =============================================================================
// 7. CTA AUTOMÁTICO MEJORADO
// =============================================================================

add_filter('the_content', 'apartamentos_add_enhanced_cta');
function apartamentos_add_enhanced_cta($content) {
    if (is_singular('blog_post') && !is_admin() && in_the_loop() && is_main_query()) {
        
        $post_id = get_the_ID();
        $cta_enabled = get_post_meta($post_id, '_apartamentos_cta_enabled', true);
        
        // Solo añadir CTA si está habilitado (por defecto está habilitado)
        if ($cta_enabled !== '0') {
            
            $destination = get_post_meta($post_id, '_apartamentos_destination', true);
            $distance_car = get_post_meta($post_id, '_apartamentos_distance_car', true);
            $distance_walk = get_post_meta($post_id, '_apartamentos_distance_walk', true);
            $time_car = get_post_meta($post_id, '_apartamentos_time_car', true);
            $time_walk = get_post_meta($post_id, '_apartamentos_time_walk', true);
            $difficulty = get_post_meta($post_id, '_apartamentos_difficulty', true);
            $custom_text = get_post_meta($post_id, '_apartamentos_cta_custom', true);
            $button_text = get_post_meta($post_id, '_apartamentos_cta_button', true);
            
            if (empty($button_text)) {
                $button_text = 'Reservar Ahora - Mejor Precio Garantizado';
            }
            
            $cta = '<div class="apartamentos-cta-reservas">';
            
            if (!empty($custom_text)) {
                $cta .= '<div style="position: relative; z-index: 2;">' . wp_kses_post($custom_text) . '</div>';
            } else {
                $cta .= '<h3>¿Te ha gustado esta guía?</h3>';
                $cta .= '<p>Alójate en <strong>Apartamentos Estanques</strong> y vive esta experiencia</p>';
            }
            
            $cta .= '<div class="cta-benefits">';
            
            if ($destination) {
                $cta .= '🏖️ Base perfecta para visitar <strong>' . esc_html($destination) . '</strong><br>';
            }
            if ($distance_car && $time_car) {
                $cta .= '🚗 A solo <strong>' . esc_html($distance_car) . '</strong> (' . esc_html($time_car) . ')<br>';
            }
            if ($distance_walk && $time_walk) {
                $cta .= '🚶‍♂️ <strong>' . esc_html($time_walk) . '</strong> caminando<br>';
            }
            if ($difficulty) {
                $cta .= '📊 Dificultad: <strong>' . esc_html($difficulty) . '</strong><br>';
            }
            
            $cta .= '💰 <strong>Reserva directa = Mejor precio garantizado</strong><br>';
            $cta .= '🗺️ Guías exclusivas como esta incluidas<br>';
            $cta .= '🅿️ Parking gratuito para nuestros huéspedes<br>';
            $cta .= '📍 Ubicación privilegiada en el centro';
            $cta .= '</div>';
            
            $cta .= '<a href="https://apartamentosestanques.com/reservas" class="cta-button" target="_blank">' . esc_html($button_text) . '</a>';
            $cta .= '<p class="cta-subtitle">Carrer Estanys, 33 - 07638 Colònia de Sant Jordi, Mallorca</p>';
            $cta .= '</div>';
            
            $content .= $cta;
        }
    }
    
    return $content;
}

// =============================================================================
// 8. SHORTCODES PARA BLOG
// =============================================================================

// Shortcode principal para mostrar grid de posts
add_shortcode('apartamentos_blog_grid', 'apartamentos_blog_grid_shortcode');
function apartamentos_blog_grid_shortcode($atts) {
    $atts = shortcode_atts(array(
        'posts' => 6,
        'category' => '',
        'language' => '',
        'columns' => 3,
        'show_excerpt' => 'true',
        'show_date' => 'true',
        'order' => 'DESC',
        'orderby' => 'date',
    ), $atts);
    
    $args = array(
        'post_type' => 'blog_post',
        'posts_per_page' => intval($atts['posts']),
        'post_status' => 'publish',
        'order' => $atts['order'],
        'orderby' => $atts['orderby'],
    );
    
    // Filtrar por categoría
    if (!empty($atts['category'])) {
        $args['tax_query'][] = array(
            'taxonomy' => 'blog_category',
            'field' => 'slug',
            'terms' => sanitize_text_field($atts['category']),
        );
    }
    
    // Filtrar por idioma
    if (!empty($atts['language'])) {
        if (isset($args['tax_query'])) {
            $args['tax_query']['relation'] = 'AND';
        }
        $args['tax_query'][] = array(
            'taxonomy' => 'blog_language',
            'field' => 'slug',
            'terms' => sanitize_text_field($atts['language']),
        );
    }
    
    $query = new WP_Query($args);
    
    ob_start();
    
    if ($query->have_posts()) {
        echo '<div class="blog-grid" style="grid-template-columns: repeat(auto-fit, minmax(' . (380 / intval($atts['columns'])) . 'px, 1fr));">';
        
        while ($query->have_posts()) {
            $query->the_post();
            
            $categories = get_the_terms(get_the_ID(), 'blog_category');
            $category_name = $categories ? $categories[0]->name : 'Sin categoría';
            
            $languages = get_the_terms(get_the_ID(), 'blog_language');
            $language_name = $languages ? strtoupper($languages[0]->name) : '';
            
            $destination = get_post_meta(get_the_ID(), '_apartamentos_destination', true);
            $distance_walk = get_post_meta(get_the_ID(), '_apartamentos_distance_walk', true);
            $time_walk = get_post_meta(get_the_ID(), '_apartamentos_time_walk', true);
            
            echo '<div class="blog-card">';
            
            // Imagen destacada
            if (has_post_thumbnail()) {
                echo '<div class="featured-image" style="background-image: url(' . get_the_post_thumbnail_url(get_the_ID(), 'blog-card') . ');">';
            } else {
                echo '<div class="featured-image" style="background: linear-gradient(135deg, #2E8B57 0%, #20B2AA 100%);">';
                echo '<span style="font-size: 3rem;">🏖️</span>';
            }
            
            echo '<span class="category-badge">' . esc_html($category_name) . '</span>';
            if ($language_name) {
                echo '<span class="language-badge">' . esc_html($language_name) . '</span>';
            }
            echo '</div>';
            
            // Contenido
            echo '<div class="content">';
            echo '<h3><a href="' . get_permalink() . '">' . get_the_title() . '</a></h3>';
            
            if ($atts['show_excerpt'] === 'true') {
                $excerpt = get_the_excerpt() ? get_the_excerpt() : wp_trim_words(get_the_content(), 20, '...');
                echo '<p class="excerpt">' . esc_html($excerpt) . '</p>';
            }
            
            // Meta información
            echo '<div class="meta">';
            echo '<div class="meta-info">';
            
            if ($atts['show_date'] === 'true') {
                echo '<span>📅 ' . get_the_date('j M Y') . '</span>';
            }
            
            if ($time_walk) {
                echo '<span>⏱️ ' . esc_html($time_walk) . '</span>';
            }
            
            echo '</div>';
            echo '<a href="' . get_permalink() . '" class="read-more">Leer Guía</a>';
            echo '</div>';
            
            echo '</div>'; // .content
            echo '</div>'; // .blog-card
        }
        
        echo '</div>'; // .blog-grid
    } else {
        echo '<div class="blog-grid">';
        echo '<p style="text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">';
        echo '📝 <strong>Próximamente</strong><br>Estamos preparando guías increíbles sobre Colonia de Sant Jordi.<br>¡Vuelve pronto!';
        echo '</p>';
        echo '</div>';
    }
    
    wp_reset_postdata();
    
    return ob_get_clean();
}

// Shortcode para mostrar información de distancias
add_shortcode('apartamentos_distance_info', 'apartamentos_distance_shortcode');
function apartamentos_distance_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_id' => get_the_ID(),
    ), $atts);
    
    $post_id = intval($atts['post_id']);
    $destination = get_post_meta($post_id, '_apartamentos_destination', true);
    $distance_car = get_post_meta($post_id, '_apartamentos_distance_car', true);
    $distance_walk = get_post_meta($post_id, '_apartamentos_distance_walk', true);
    $time_car = get_post_meta($post_id, '_apartamentos_time_car', true);
    $time_walk = get_post_meta($post_id, '_apartamentos_time_walk', true);
    
    if (empty($destination)) {
        return '';
    }
    
    ob_start();
    ?>
    <div class="distance-info">
        <h4>🗺️ Desde Apartamentos Estanques a <?php echo esc_html($destination); ?></h4>
        
        <?php if ($distance_car && $time_car): ?>
        <div class="distance-item">
            <strong>🚗 En coche:</strong>
            <span><?php echo esc_html($distance_car); ?> (<?php echo esc_html($time_car); ?>)</span>
        </div>
        <?php endif; ?>
        
        <?php if ($distance_walk && $time_walk): ?>
        <div class="distance-item">
            <strong>🚶‍♂️ Caminando:</strong>
            <span><?php echo esc_html($distance_walk); ?> (<?php echo esc_html($time_walk); ?>)</span>
        </div>
        <?php endif; ?>
        
        <p style="margin-top: 15px; font-size: 0.9rem; opacity: 0.9;">
            📍 <em>Origen: Carrer Estanys, 33 - Colònia de Sant Jordi</em>
        </p>
    </div>
    <?php
    return ob_get_clean();
}

// Shortcode para mapa de Google Maps
add_shortcode('apartamentos_map', 'apartamentos_map_shortcode');
function apartamentos_map_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_id' => get_the_ID(),
        'height' => '400px',
        'width' => '100%',
    ), $atts);
    
    $post_id = intval($atts['post_id']);
    $show_map = get_post_meta($post_id, '_apartamentos_show_map', true);
    $coordinates = get_post_meta($post_id, '_apartamentos_coordinates', true);
    $maps_embed = get_post_meta($post_id, '_apartamentos_maps_embed', true);
    $destination = get_post_meta($post_id, '_apartamentos_destination', true);
    
    if ($show_map !== '1') {
        return '';
    }
    
    ob_start();
    ?>
    <div class="apartamentos-map-container" style="margin: 30px 0; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.1);">
        <?php if (!empty($maps_embed)): ?>
            <?php echo wp_kses($maps_embed, array(
                'iframe' => array(
                    'src' => array(),
                    'width' => array(),
                    'height' => array(),
                    'style' => array(),
                    'frameborder' => array(),
                    'allowfullscreen' => array(),
                )
            )); ?>
        <?php elseif (!empty($coordinates) && !empty($destination)): ?>
            <iframe 
                width="<?php echo esc_attr($atts['width']); ?>" 
                height="<?php echo esc_attr($atts['height']); ?>"
                frameborder="0" 
                style="border:0" 
                src="https://www.google.com/maps/embed/v1/directions?key=<?php echo get_option('google_maps_api_key', ''); ?>&origin=Carrer Estanys, 33, 07638 Colònia de Sant Jordi&destination=<?php echo urlencode($destination . ', Mallorca'); ?>&mode=walking&language=es"
                allowfullscreen>
            </iframe>
        <?php else: ?>
            <div style="background: #f0f8ff; padding: 40px; text-align: center; color: #666;">
                <p>🗺️ <strong>Mapa interactivo próximamente</strong></p>
                <p>Configura las coordenadas en el editor del post para mostrar el mapa.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// =============================================================================
// 9. FUNCIONES ADICIONALES PARA MEJORAR EL BLOG
// =============================================================================

// Calcular tiempo de lectura
function apartamentos_reading_time($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $content = get_post_field('post_content', $post_id);
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200); // 200 palabras por minuto
    
    return $reading_time;
}

// Añadir datos estructurados Schema.org
add_action('wp_head', 'apartamentos_add_schema_data');
function apartamentos_add_schema_data() {
    if (is_singular('blog_post')) {
        $post_id = get_the_ID();
        $destination = get_post_meta($post_id, '_apartamentos_destination', true);
        $coordinates = get_post_meta($post_id, '_apartamentos_coordinates', true);
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title(),
            'description' => get_the_excerpt() ? get_the_excerpt() : wp_trim_words(get_the_content(), 25),
            'author' => array(
                '@type' => 'Organization',
                'name' => 'Apartamentos Estanques'
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => 'Apartamentos Estanques',
                'logo' => array(
                    '@type' => 'ImageObject',
                    'url' => get_site_url() . '/wp-content/uploads/logo-apartamentos-estanques.png'
                )
            ),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'mainEntityOfPage' => get_permalink(),
        );
        
        if (has_post_thumbnail()) {
            $schema['image'] = get_the_post_thumbnail_url($post_id, 'full');
        }
        
        if ($destination && $coordinates) {
            $coords = explode(',', $coordinates);
            if (count($coords) == 2) {
                $schema['about'] = array(
                    '@type' => 'Place',
                    'name' => $destination,
                    'geo' => array(
                        '@type' => 'GeoCoordinates',
                        'latitude' => trim($coords[0]),
                        'longitude' => trim($coords[1])
                    )
                );
            }
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}

// Crear taxonomías por defecto al activar el tema
add_action('after_switch_theme', 'apartamentos_create_default_terms');
function apartamentos_create_default_terms() {
    
    // Categorías predefinidas
    $categories = array(
        'playas' => 'Playas',
        'restaurantes' => 'Restaurantes',
        'actividades' => 'Actividades',
        'transporte' => 'Transporte',
        'servicios' => 'Servicios',
    );
    
    foreach ($categories as $slug => $name) {
        if (!term_exists($slug, 'blog_category')) {
            wp_insert_term($name, 'blog_category', array('slug' => $slug));
        }
    }
    
    // Idiomas predefinidos
    $languages = array(
        'es' => 'Español',
        'en' => 'English',
        'de' => 'Deutsch',
    );
    
    foreach ($languages as $slug => $name) {
        if (!term_exists($slug, 'blog_language')) {
            wp_insert_term($name, 'blog_language', array('slug' => $slug));
        }
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// =============================================================================
// 10. OPTIMIZACIONES ADICIONALES
// =============================================================================

// Mejorar SEO con meta tags personalizados
add_action('wp_head', 'apartamentos_custom_meta_tags');
function apartamentos_custom_meta_tags() {
    if (is_singular('blog_post')) {
        $post_id = get_the_ID();
        $meta_title = get_post_meta($post_id, '_apartamentos_meta_title', true);
        $meta_desc = get_post_meta($post_id, '_apartamentos_meta_desc', true);
        $keyword = get_post_meta($post_id, '_apartamentos_keyword', true);
        
        if ($meta_title) {
            echo '<meta property="og:title" content="' . esc_attr($meta_title) . '">' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($meta_title) . '">' . "\n";
        }
        
        if ($meta_desc) {
            echo '<meta property="og:description" content="' . esc_attr($meta_desc) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($meta_desc) . '">' . "\n";
        }
        
        if ($keyword) {
            echo '<meta name="keywords" content="' . esc_attr($keyword) . ', apartamentos estanques, colonia sant jordi, mallorca">' . "\n";
        }
        
        if (has_post_thumbnail()) {
            $thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');
            echo '<meta property="og:image" content="' . esc_url($thumbnail_url) . '">' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($thumbnail_url) . '">' . "\n";
        }
    }
}

// Limpiar head de WordPress
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');

// Optimizar carga de recursos
add_action('wp_enqueue_scripts', 'apartamentos_optimize_scripts');
function apartamentos_optimize_scripts() {
    if (is_singular('blog_post') || is_post_type_archive('blog_post')) {
        // Preload de fuentes importantes
        echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
        echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600&display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
    }
}

// Mensaje de administrador con instrucciones
add_action('admin_notices', 'apartamentos_admin_notice');
function apartamentos_admin_notice() {
    if (get_current_screen()->id === 'edit-blog_post') {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>🏖️ Sistema Blog Apartamentos Estanques activado!</strong></p>';
        echo '<p>📝 Usa <code>[apartamentos_blog_grid]</code> para mostrar posts en cualquier página.</p>';
        echo '<p>🗺️ Configura tu Google Maps API Key en Ajustes > General para mapas automáticos.</p>';
        echo '<p>💡 Los datos SEO se generan automáticamente si los dejas vacíos.</p>';
        echo '</div>';
    }
}

// =============================================================================
// 11. OPTIMIZACIONES PARA EDITOR WORDPRESS
// =============================================================================

// Añadir botones personalizados al editor
add_action('media_buttons', 'apartamentos_add_custom_buttons');
function apartamentos_add_custom_buttons() {
    global $post;
    
    if ($post && $post->post_type == 'blog_post') {
        echo '<button type="button" class="button apartamentos-quick-btn" data-shortcode="[apartamentos_distance_info]">
                📏 Añadir Distancias
              </button>';
        
        echo '<button type="button" class="button apartamentos-quick-btn" data-shortcode="[apartamentos_map]">
                🗺️ Añadir Mapa
              </button>';
        
        echo '<button type="button" class="button apartamentos-quick-btn" data-template="playa">
                🏖️ Plantilla Playa
              </button>';
        
        echo '<button type="button" class="button apartamentos-quick-btn" data-template="restaurante">
                🍽️ Plantilla Restaurante
              </button>';
    }
}

// JavaScript para los botones rápidos
add_action('admin_footer', 'apartamentos_editor_scripts');
function apartamentos_editor_scripts() {
    global $post;
    
    if ($post && $post->post_type == 'blog_post') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            
            // Insertar shortcodes
            $('.apartamentos-quick-btn[data-shortcode]').click(function() {
                var shortcode = $(this).data('shortcode');
                
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                    tinymce.activeEditor.insertContent('\n\n' + shortcode + '\n\n');
                } else {
                    var textarea = $('#content');
                    var pos = textarea.prop('selectionStart');
                    var content = textarea.val();
                    textarea.val(content.substring(0, pos) + '\n\n' + shortcode + '\n\n' + content.substring(pos));
                }
            });
            
            // Insertar plantillas
            $('.apartamentos-quick-btn[data-template]').click(function() {
                var template = $(this).data('template');
                var content = apartamentosGetTemplate(template);
                
                if (confirm('¿Quieres reemplazar el contenido actual con la plantilla ' + template + '?')) {
                    if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                        tinymce.activeEditor.setContent(content);
                    } else {
                        $('#content').val(content);
                    }
                }
            });
            
            // Plantillas de contenido
            function apartamentosGetTemplate(type) {
                var templates = {
                    'playa': `¿Quieres visitar esta increíble playa desde Apartamentos Estanques? En esta guía completa te explicamos paso a paso cómo llegar, las mejores rutas, distancias exactas y consejos prácticos para que disfrutes al máximo de tu visita.

## 🚗 Distancias y Tiempos desde Apartamentos Estanques

[apartamentos_distance_info]

## 🗺️ Ruta Detallada Paso a Paso

### Opción 1: En Coche (RECOMENDADO)
1. **Sal de Apartamentos Estanques** (Carrer Estanys, 33)
2. **Gira a la izquierda** hacia Carrer de Gabriel Roca
3. **Continúa recto** hasta conectar con la Ma-6040
4. **Sigue las indicaciones** hasta llegar al destino

### Opción 2: A Pie (Para los aventureros)
1. **Sal de Apartamentos Estanques** hacia el centro
2. **Sigue el sendero** señalizado
3. **Camina por el camino** natural hasta la playa

## 🗺️ Mapa Interactivo

[apartamentos_map]

## 🏖️ Qué Encontrarás en Esta Playa

### Características Principales:
- **Longitud:** [Completar] metros de arena
- **Tipo de arena:** [Completar - fina/gruesa]
- **Color del agua:** [Completar]
- **Profundidad:** [Completar - gradual/abrupta]
- **Servicios:** [Completar - chiringuito, duchas, etc.]

### Servicios Disponibles:
- **Chiringuitos:** [Número y nombres]
- **Hamacas y sombrillas:** [Disponibilidad y precio]
- **Duchas:** [Disponibles/No disponibles]
- **WC públicos:** [Disponibles/No disponibles]
- **Socorrismo:** [Horarios]
- **Acceso minusválidos:** [Sí/No]

## 💡 Consejos Prácticos para tu Visita

### Qué Llevar:
- ✅ **Protector solar SPF 50+** (imprescindible)
- ✅ **Sombrilla o parasol** (poca sombra natural)
- ✅ **Agua abundante** (mínimo 1.5L por persona)
- ✅ **Calzado cómodo** para caminar por la arena
- ✅ **Gorra y gafas de sol**
- ✅ **Snacks o picnic** (opciones locales pueden ser caras)

### Mejores Horarios:
- **Menos masificado:** [Completar horarios]
- **Evitar:** [Completar horarios de más gente]
- **Ideal para fotos:** Amanecer y atardecer

### Qué NO Hacer:
- ❌ **No dejar basura** (multas hasta 300€)
- ❌ **No coger arena o conchas** (está prohibido)
- ❌ **No aparcar en zonas restringidas**

## 🅿️ Aparcamiento y Acceso

### Aparcamiento Principal:
- **Ubicación:** [Completar]
- **Precio:** [Completar] €/día
- **Capacidad:** [Completar] plazas
- **Horario:** [Completar]

### Aparcamiento Gratuito:
- **Ubicación:** [Completar]
- **Tip:** Llegar antes de las 10:00

## 🍽️ Dónde Comer Cerca

### En la misma playa:
**[Nombre del chiringuito]**
- Especialidad: [Completar]
- Precio medio: [Completar] € por persona
- Horario: [Completar]

### Camino de vuelta:
**[Nombre del restaurante]**
- Especialidad: [Completar]
- Precio medio: [Completar] € por persona
- Distancia: [Completar] minutos desde la playa

## 🌊 Actividades Disponibles

### Deportes Acuáticos:
- **Paddle Surf:** [Precio] €/hora
- **Kayak:** [Precio] €/hora
- **Snorkel:** [Detalles de la zona]

### En Tierra:
- **Senderismo:** [Rutas disponibles]
- **Fotografía:** [Mejores spots]

## 🏠 ¿Por qué elegir Apartamentos Estanques como Base?

### Ventajas de nuestra ubicación:
- ✅ **A solo [X] minutos** de esta increíble playa
- ✅ **Parking gratuito** para nuestros huéspedes
- ✅ **Información local** actualizada
- ✅ **Kit playa disponible** (consultar)

¡Reserva directo con nosotros y disfruta de la ubicación perfecta para explorar las mejores playas de Colonia de Sant Jordi!`,

                    'restaurante': `¿Buscas dónde comer cerca de Apartamentos Estanques? Te presentamos una guía completa de este excelente restaurante, con todo lo que necesitas saber: cómo llegar, qué pedir, precios y nuestros consejos locales.

## 🗺️ Ubicación y Cómo Llegar

[apartamentos_distance_info]

### Desde Apartamentos Estanques:
1. **Sal del apartamento** (Carrer Estanys, 33)
2. **Dirígete hacia** [completar direcciones]
3. **El restaurante está** [completar ubicación exacta]

[apartamentos_map]

## 🍽️ Información del Restaurante

### Datos Básicos:
- **Nombre:** [Completar]
- **Tipo de cocina:** [Mallorquina/Internacional/etc.]
- **Precio medio:** [Completar] € por persona
- **Horario:** [Completar]
- **Teléfono:** [Completar]
- **Reservas:** [Necesarias/No necesarias]

### Ambiente:
- **Estilo:** [Familiar/Romántico/Casual]
- **Terraza:** [Sí/No - con descripción]
- **Vistas:** [Descripción de las vistas]
- **Capacidad:** [Número aproximado de comensales]

## 🌟 Especialidades y Platos Recomendados

### Imprescindibles:
- **[Plato 1]:** [Descripción y precio]
- **[Plato 2]:** [Descripción y precio]
- **[Plato 3]:** [Descripción y precio]

### Menús Especiales:
- **Menú del día:** [Precio y descripción]
- **Menú degustación:** [Precio y descripción si existe]

### Para Alérgicos/Intolerancias:
- **Opciones sin gluten:** [Disponibles/No disponibles]
- **Opciones veganas:** [Disponibles/No disponibles]
- **Alergias:** [Información sobre manejo de alergias]

## 🍷 Carta de Bebidas

### Vinos Locales:
- **Vinos mallorquines:** [Recomendaciones]
- **Maridajes:** [Sugerencias del sommelier]

### Otras Bebidas:
- **Sangría:** [Si la hacen y cómo]
- **Cócteles:** [Especialidades]

## 💰 Precios Orientativos

### Entrantes: [Rango de precios]
### Principales: [Rango de precios]  
### Postres: [Rango de precios]
### Bebidas: [Rango de precios]

**Presupuesto recomendado:** [X]-[Y] € por persona (con bebida)

## ⏰ Mejor Momento para Ir

### Temporada Alta:
- **Reservar:** Con [X] días de antelación
- **Horarios menos concurridos:** [Completar]

### Temporada Baja:
- **Sin reserva:** [Generalmente posible/No recomendado]
- **Ventajas:** [Menos gente, más atención, etc.]

## 💡 Consejos de los Propietarios de Apartamentos Estanques

### Tips Locales:
- 🏆 **Mejor plato:** [Recomendación personal]
- ⏰ **Mejor momento:** [Cuándo ir según experiencia]
- 💳 **Pago:** [Efectivo/Tarjeta - qué aceptan]
- 🚗 **Aparcamiento:** [Dónde aparcar cerca]

### Lo que Debes Saber:
- ✅ [Tip positivo 1]
- ✅ [Tip positivo 2]
- ⚠️ [Algo a tener en cuenta]

## 📞 Reservas y Contacto

### Cómo Reservar:
- **Teléfono:** [Número]
- **WhatsApp:** [Si tienen]
- **Online:** [Si tienen sistema de reservas]

### Idiomas:
- **Español:** ✅  
- **Inglés:** [Sí/No/Básico]
- **Alemán:** [Sí/No/Básico]

## 🚶‍♂️ Otros Restaurantes Cercanos

Si este está completo, aquí tienes alternativas cerca de Apartamentos Estanques:
- **[Restaurante 2]:** [Distancia] - [Tipo de cocina]
- **[Restaurante 3]:** [Distancia] - [Tipo de cocina]

## 🏠 Después de Cenar

### Regreso a Apartamentos Estanques:
- **A pie:** [Tiempo] - [Descripción del camino]
- **Taxi:** [Precio aproximado]

### Actividades Nocturnas:
- **Paseo nocturno:** [Sugerencias]
- **Bares cercanos:** [Si los hay]

¡Disfruta de la gastronomía local alojándote en Apartamentos Estanques, tu base perfecta en Colonia de Sant Jordi!`
                };
                
                return templates[type] || '';
            }
        });
        </script>
        
        <style>
        .apartamentos-quick-btn {
            margin-left: 5px !important;
            background: #2E8B57 !important;
            border-color: #2E8B57 !important;
            color: white !important;
            font-weight: 500 !important;
        }
        .apartamentos-quick-btn:hover {
            background: #F4A460 !important;
            border-color: #F4A460 !important;
        }
        </style>
        <?php
    }
}

// Sugerir títulos automáticamente
add_action('admin_footer', 'apartamentos_title_suggestions');
function apartamentos_title_suggestions() {
    global $post;
    
    if ($post && $post->post_type == 'blog_post') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Sugerencias de títulos basadas en el destino
            $('#apartamentos_destination').on('blur', function() {
                var destination = $(this).val();
                var currentTitle = $('#title').val();
                
                if (destination && !currentTitle) {
                    var suggestions = [
                        'Cómo llegar a ' + destination + ' desde Apartamentos Estanques',
                        'Guía completa de ' + destination + ' desde Colonia Sant Jordi',
                        destination + ' desde Apartamentos Estanques: Ruta y Consejos',
                        'Todo sobre ' + destination + ' desde Apartamentos Estanques'
                    ];
                    
                    var suggested = suggestions[Math.floor(Math.random() * suggestions.length)];
                    
                    if (confirm('¿Quieres usar este título sugerido?\n\n"' + suggested + '"')) {
                        $('#title').val(suggested);
                    }
                }
            });
        });
        </script>
        <?php
    }
}

// Auto-llenar campos de Yoast SEO basados en metaboxes
add_action('save_post', 'apartamentos_sync_yoast_data', 20);
function apartamentos_sync_yoast_data($post_id) {
    if (get_post_type($post_id) != 'blog_post') return;
    
    // Solo si Yoast está instalado
    if (!function_exists('YoastSEO')) return;
    
    $keyword = get_post_meta($post_id, '_apartamentos_keyword', true);
    $meta_title = get_post_meta($post_id, '_apartamentos_meta_title', true);
    $meta_desc = get_post_meta($post_id, '_apartamentos_meta_desc', true);
    
    // Sincronizar con Yoast SEO
    if ($keyword) {
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $keyword);
    }
    
    if ($meta_title) {
        update_post_meta($post_id, '_yoast_wpseo_title', $meta_title);
    }
    
    if ($meta_desc) {
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_desc);
    }
}

// Contador de palabras y tiempo de lectura en vivo
add_action('admin_footer', 'apartamentos_writing_stats');
function apartamentos_writing_stats() {
    global $post;
    
    if ($post && $post->post_type == 'blog_post') {
        ?>
        <div id="apartamentos-writing-stats" style="background: #f1f1f1; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 13px;">
            <strong>📊 Estadísticas del Post:</strong>
            <span id="word-count">0 palabras</span> | 
            <span id="reading-time">0 min lectura</span> | 
            <span id="seo-score">SEO: Pendiente</span>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            function updateStats() {
                var content = '';
                
                // Obtener contenido del editor
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                    content = tinymce.activeEditor.getContent({format: 'text'});
                } else {
                    content = $('#content').val();
                }
                
                // Contar palabras
                var words = content.trim() ? content.trim().split(/\s+/).length : 0;
                var readingTime = Math.ceil(words / 200); // 200 palabras por minuto
                
                // Actualizar display
                $('#word-count').text(words + ' palabras');
                $('#reading-time').text(readingTime + ' min lectura');
                
                // SEO Score básico
                var seoScore = 'Pendiente';
                if (words >= 800) seoScore = 'Excelente';
                else if (words >= 500) seoScore = 'Bueno';
                else if (words >= 300) seoScore = 'Mejorable';
                else seoScore = 'Muy corto';
                
                $('#seo-score').text('SEO: ' + seoScore);
            }
            
            // Actualizar cada 2 segundos
            setInterval(updateStats, 2000);
            updateStats(); // Primera vez
        });
        </script>
        <?php
    }
}
/**
 * Register required taxonomies for Apartments Import
 */
add_action('init', 'register_apartment_taxonomies');
function register_apartment_taxonomies() {

    // 1. Amenity Taxonomy
    register_taxonomy(
        'listing_amenity',
        'apartment',
        array(
            'labels' => array(
                'name' => 'Amenities',
                'singular_name' => 'Amenity',
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => array('slug' => 'amenity')
        )
    );

    // 2. City Taxonomy
    register_taxonomy(
        'listing_city',
        'apartment',
        array(
            'labels' => array(
                'name' => 'Cities',
                'singular_name' => 'City',
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => array('slug' => 'city')
        )
    );

    // 3. Property Type (custom)
    register_taxonomy(
        'listing_type',
        'apartment',
        array(
            'labels' => array(
                'name' => 'Listing Types',
                'singular_name' => 'Listing Type',
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => array('slug' => 'listing-type')
        )
    );

    // 4. Extra propertyType taxonomy (because your code uses it)
    // If this is a mistake we can remove this later
    register_taxonomy(
        'propertyType',
        'apartment',
        array(
            'labels' => array(
                'name' => 'Property Type (API)',
                'singular_name' => 'Property Type',
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'rewrite' => array('slug' => 'propertytype')
        )
    );
}

// Lista de verificación pre-publicación
add_action('post_submitbox_misc_actions', 'apartamentos_pre_publish_checklist');
function apartamentos_pre_publish_checklist() {
    global $post;
    
    if ($post && $post->post_type == 'blog_post') {
        ?>
        <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;">
            <strong>✅ Lista de Verificación:</strong><br>
            <label><input type="checkbox" class="checklist-item"> Metaboxes completados</label><br>
            <label><input type="checkbox" class="checklist-item"> Imagen destacada añadida</label><br>
            <label><input type="checkbox" class="checklist-item"> Categoría e idioma seleccionados</label><br>
            <label><input type="checkbox" class="checklist-item"> Shortcodes añadidos</label><br>
            <label><input type="checkbox" class="checklist-item"> Mínimo 500 palabras</label><br>
            <label><input type="checkbox" class="checklist-item"> Revisado ortografía</label><br>
            
            <button type="button" id="quick-publish-check" class="button button-primary" style="margin-top: 10px; width: 100%;">
                🚀 Verificar y Publicar
            </button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#quick-publish-check').click(function() {
                var checkedItems = $('.checklist-item:checked').length;
                var totalItems = $('.checklist-item').length;
                
                if (checkedItems < totalItems) {
                    alert('⚠️ Faltan ' + (totalItems - checkedItems) + ' elementos por verificar.\n\nRevisa la lista antes de publicar para asegurar la calidad del post.');
                    return false;
                }
                
                if (confirm('✅ ¡Perfecto! Todo verificado.\n\n¿Publicar el post ahora?')) {
                    $('#publish').click();
                }
            });
        });
        </script>
        <?php
    }
}
register_post_type('apartment', [
  'label' => 'Apartments',
  'public' => true,
  'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
]);
function custom_get_booking_dates() {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $today      = date('Y-m-d');
    $threeDays  = date('Y-m-d', strtotime('+3 days'));

    // 1) GET overrides everything
    if (!empty($_GET['arrive']) && !empty($_GET['depart'])) {
        $_SESSION['arrive'] = $_GET['arrive'];
        $_SESSION['depart'] = $_GET['depart'];

        return [
            'arrive' => $_SESSION['arrive'],
            'depart' => $_SESSION['depart']
        ];
    }

    // 2) If session already has dates → use them
    if (!empty($_SESSION['arrive']) && !empty($_SESSION['depart'])) {
        return [
            'arrive' => $_SESSION['arrive'],
            'depart' => $_SESSION['depart']
        ];
    }

    // 3) First time → set default: today + 3 days
    $_SESSION['arrive'] = $today;
    $_SESSION['depart'] = $threeDays;

    return [
        'arrive' => $today,
        'depart' => $threeDays
    ];
}
add_action('rest_api_init', function () {

    register_rest_field('apartment', 'featured_image_url', [
        'get_callback' => function ($obj) {
            return get_the_post_thumbnail_url($obj['id'], 'large');
        }
    ]);

    register_rest_field('apartment', 'gallery_count', [
        'get_callback' => function ($obj) {
            $gallery = get_post_meta($obj['id'], 'listing_gallery', true);
            if (!$gallery) return 1;
            return count(explode(",", $gallery));
        }
    ]);

    register_rest_field('apartment', 'custom_max_adults', [
        'get_callback' => fn($obj) => get_post_meta($obj['id'], 'custom_max_adults', true)
    ]);

    register_rest_field('apartment', 'custom_max_children', [
        'get_callback' => fn($obj) => get_post_meta($obj['id'], 'custom_max_children', true)
    ]);

    register_rest_field('apartment', 'custom_listing_bedrooms', [
        'get_callback' => fn($obj) => get_post_meta($obj['id'], 'custom_listing_bedrooms', true)
    ]);

    register_rest_field('apartment', 'amenities', [
        'get_callback' => function ($obj) {
            $terms = wp_get_post_terms($obj['id'], 'listing_amenity');
            return wp_list_pluck($terms, 'name');
        }
    ]);

});

function add_update_listings_with_beds24($target_listing_id = null){
    //ReadCSV into Array
    //Beds24_Feature_Code_Mapping-Sheet1.csv
    //$open = fopen(dirname(__FILE__) ."/Beds24_Feature_Code_Mapping-Sheet1.csv", "r") or die("cant open");
    $i=0;
    if(($open = fopen(dirname(__FILE__) ."/Beds24_Feature_Code_Mapping-Sheet1.csv", "r")) !== false) {
        while (($data = fgetcsv($open, 1000, ",")) !== false) {
            //echo $i."=="."<br/>";
            if($i == 0){
                $i++;
                continue;
            }else{
                $i++;
                $array[] = $data;
                $featured_names[$data[0]] = $data[1];
            }
        }
    }
    
    //$url = 'https://beds24.com/api/v2/properties?includeTexts=all&includePictures=true&includeOffers=true&includePriceRules=true&includeSearchCriteria=true&includeAllRooms=true&includeUnitDetails=true';

    if ($target_listing_id) {
        $url = 'https://beds24.com/api/v2/properties?id=' . $target_listing_id . '&includeTexts=all&includePictures=true&includeOffers=true&includePriceRules=true&includeSearchCriteria=true&includeAllRooms=true&includeUnitDetails=true';
    } else {
        $url = 'https://beds24.com/api/v2/properties?includeTexts=all&includePictures=true&includeOffers=true&includePriceRules=true&includeSearchCriteria=true&includeAllRooms=true&includeUnitDetails=true';
    }

    $listings_data = getCurlResponse($url);
    echo "Total Listings Retrieved: " . count($listings_data) . "<br/>";
	echo "<pre>";print_r($listings_data);exit();
    foreach($listings_data as $listing_data){
        $listing_id = $listing_data['id'];
        echo "Processing Listing ID: " . $listing_id . "<br/>";
        echo $listing_id."<br>";
        global $wpdb;
        
        $prepare_guery = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta where meta_key ='listing_beds24_id' and meta_value = $listing_id");
        $get_values = $wpdb->get_col( $prepare_guery );
        if(isset($get_values[0]) && $get_values[0]>0){
            echo "Update: ";   
            echo $id = $get_values[0];
            $url = 'https://beds24.com/api/v2/properties?id='.$listing_id.'&includeTexts=all&includePictures=true&includeOffers=true&includePriceRules=true&includeSearchCriteria=true&includeAllRooms=true&includeUnitDetails=true';
            $listing_data = getCurlResponse($url)[0];
			//echo $listing_data['roomTypes'][0]['rackRate'];
            //echo "<pre>";print_r($listing_data);exit();
            //print_r($listing_data['roomTypes'][0]['featureCodes']);
            //exit();
            echo "<br/>";
            //print_r($listing_data['templates']['template8']);
            if($listing_data['templates']['template8'] == "hide"){
                update_post_meta( $id, 'listing_website_status',  "hide" );
            }
            else if($listing_data['templates']['template8'] == "delete"){
                wp_delete_post( $id, true );
                continue;
            }else{
                update_post_meta( $id, 'listing_website_status',  "" );
            }
            
            $title_template =  $listing_data['roomTypes'][0]['templates']['template2'];echo "<br/>";                                        
            $description = $title_template."<br/><br/>".$listing_data["texts"][0]["propertyDescription1"]."<br/>".$listing_data['texts'][0]['locationDescription'];
            $description = $description."<br/>".nl2br($listing_data['roomTypes'][0]['texts'][0]['roomDescription']);
            $cleaningFees = $listing_data['roomTypes'][0]['cleaningFee'];
            $id = wp_update_post(array(
            'ID' => $id, 
            'post_title'=> $listing_data['name'], 
            'post_type'=> "apartment", 
            'post_content'=> $description,
            'post_status'   => 'publish'
            ));
            
			update_post_meta( $id, 'description',  $listing_data['texts'][0]['locationDescription'] );
            update_post_meta( $id, 'listing_beds24_id',  $listing_id );
            $term_ID = array();
            $count_bedroom=0;
            $count_bathroom=0;
            $count_bed = 0;
            foreach($listing_data['roomTypes'][0]['featureCodes'] as $featureCode){
                if (array_key_exists($featureCode[0], $featured_names)){
                    $featured_name =  $featured_names[$featureCode[0]];
                }else{
                    $featured_name = $featureCode[0];
                }
                $featured_name = ucfirst(strtolower($featured_name));
                if ($term = get_term_by('slug', strtolower($featured_name), 'listing_amenity')){
                    $term_ID[] = $term->term_id;
                }else{
                    $term_ID[] = wp_insert_term($featured_name, 'listing_amenity', array(
                        'slug' => strtolower($featured_name)
                    ));
                }   
                foreach($featureCode as $feature){
                    if ($feature == "BEDROOM"){
                        $count_bedroom =  $count_bedroom+1;
                    }
                    if ($feature == "BATHROOM"){
                        $count_bathroom =  $count_bathroom+1;
                    }
                    if ($feature == "BED_QUEEN"){
                        $count_bed =  $count_bed+1;
                    }
                }   
            }
            //print_r($term_ID);
            wp_set_post_terms( $id, $term_ID, "listing_amenity" );
            update_post_meta( $id, 'custom_listing_bedrooms', $count_bedroom);
            update_post_meta( $id, 'custom_baths', $count_bathroom);
            //update_post_meta( $id, 'custom_beds', $count_bed); 
            $city_name = ucfirst(strtolower($listing_data['city']));
		
			if($city_name!=""){
				if ($term = get_term_by('slug', strtolower($city_name), 'listing_city')){
					$term_ID = $term->term_id;
					wp_set_post_terms( $id, array($term_ID), "listing_city" );
				}else{
					$cid = wp_insert_term($city_name, 'listing_city', array(
						'slug' => strtolower($city_name)
					));
					print_r($cid);
					wp_set_post_terms( $id, array($cid['term_id']), "listing_city" );
				}
			}
            if($listing_data['propertyType']!=""){
				if ($term = get_term_by('slug', strtolower($listing_data['propertyType']), 'listing_type')){
					$term_ID = $term->term_id;
					wp_set_post_terms( $id, array($term_ID), "listing_type" );
				}else{
					$cid = wp_insert_term($listing_data['propertyType'], 'listing_type', array(
						'slug' => strtolower($listing_data['propertyType'])
					));
					wp_set_post_terms( $id, array($cid['term_id']), "listing_type" );
				} 
			   }
			wp_set_post_terms( $id, $listing_data['propertyType'], "propertyType" );

            update_post_meta( $id, 'custom_cleaning_fee', $cleaningFees);
            update_post_meta( $id, 'custom_tax_rate', 13);           
            if(isset($listing_data['checkInStart'])){
                $date = DateTime::createFromFormat('H:i', $listing_data['checkInStart']);  
                update_post_meta( $id, 'custom_checkin_after', $date->format('h:i A'));
            }
            if(isset($listing_data['checkOutEnd'])){
                $date = DateTime::createFromFormat('H:i', $listing_data['checkOutEnd']); 
                update_post_meta( $id, 'custom_checkout_before', $date->format('h:i A'));
            }
            
            update_post_meta( $id, 'custom_booking_type', 'per_day' );
            update_post_meta( $id, 'custom_smoke', '0' );
            update_post_meta( $id, 'custom_pets', '0' );
            update_post_meta( $id, 'custom_children', '1' );
            update_post_meta( $id, 'custom_party', '0' );
            update_post_meta( $id, 'custom_cancellation_policy', '4988' );

            $custom_listing_location = $listing_data['latitude'].",".$listing_data['longitude'];
            $custom_listing_address = $listing_data['address'].", ".$listing_data['city'].", ".$listing_data['state'].", ".$listing_data['postcode'].", ".$listing_data['country'];
            update_post_meta( $id, 'custom_allow_additional_guests', 'yes' );
            update_post_meta( $id, 'custom_zip', $listing_data['postcode'] );
            update_post_meta( $id, 'custom_listing_location',  $custom_listing_location);
            update_post_meta( $id, 'custom_listing_address', $custom_listing_address);
            update_post_meta( $id, 'custom_night_price', $listing_data['roomTypes'][0]['rackRate'] );
            update_post_meta( $id, 'custom_instant_booking', 1);
            update_post_meta( $id, 'custom_guests', $listing_data['roomTypes'][0]['maxPeople'] );
            update_post_meta( $id, 'custom_room_types', $listing_data['roomTypes'][0]['id']);
            //exit();
        }else{
            echo "Insert: ";
            if($listing_data['templates']['template8'] !=""){
                if($listing_data['templates']['template8'] == "delete"){
                    echo "Delete - Status".$listing_id."<br/>";
                    continue;
                }
            }
            
            $url = 'https://beds24.com/api/v2/properties?id='.$listing_id.'&includeTexts=all&includePictures=true&includeOffers=true&includePriceRules=true&includeSearchCriteria=true&includeAllRooms=true&includeUnitDetails=true';
            $listing_data = getCurlResponse($url)[0];
			//echo "<pre>";print_r($listing_data);//exit();
            
            $title_template =  $listing_data['roomTypes'][0]['templates']['template2'];echo "<br/>";                                     
            $description = $title_template."<br/><br/>".$listing_data["texts"][0]["propertyDescription1"]."<br/>".$listing_data['texts'][0]['locationDescription'];
            $description = $description."<br/>".nl2br($listing_data['roomTypes'][0]['texts'][0]['roomDescription']);
            $cleaningFees = $listing_data['roomTypes'][0]['cleaningFee'];
            
            $id = wp_insert_post(array(
            'post_title'=> $listing_data['name'], 
            'post_type'=> "apartment", 
            'post_content'=> $description,
            'post_author'  => 1,
            'post_status'   => 'publish'
            ));
			
			if (function_exists('pll_set_post_language')) {
				pll_set_post_language($id, 'en'); // Replace 'en' with your desired language slug, like 'fr', 'de', etc.
			}
			
			update_post_meta( $id, 'description',  $listing_data['texts'][0]['locationDescription'] );
            update_post_meta( $id, 'listing_beds24_id',  $listing_id );
            if($listing_data['templates']['template8'] !=""){
                if($listing_data['templates']['template8'] == "hide"){
                    update_post_meta( $id, 'listing_website_status',  "hide" );
                }else{
                    update_post_meta( $id, 'listing_website_status',  "" );
                }
            } 

            $term_ID = array();
            $count_bedroom=0;
            $count_bathroom=0;
            $count_bed = 0;
            foreach($listing_data['roomTypes'][0]['featureCodes'] as $featureCode){
                if (array_key_exists($featureCode[0], $featured_names)){
                    $featured_name =  $featured_names[$featureCode[0]];
                }else{
                    $featured_name = $featureCode[0];
                }
                $featured_name = ucfirst(strtolower($featured_name));
                if ($term = get_term_by('slug', strtolower($featured_name), 'listing_amenity')){
                    $term_ID[] = $term->term_id;
                }else{
                    $term_ID[] = wp_insert_term($featured_name, 'listing_amenity', array(
                        'slug' => strtolower($featured_name)
                    ));
                }  
                foreach($featureCode as $feature){
                    if ($feature == "BEDROOM"){
                        $count_bedroom =  $count_bedroom+1;
                    }
                    if ($feature == "BATHROOM"){
                        $count_bathroom =  $count_bathroom+1;
                    }
                    if ($feature == "BED_QUEEN"){
                        $count_bed =  $count_bed+1;
                    }
                }   
            }
            update_post_meta( $id, 'custom_listing_bedrooms', $count_bedroom);
            update_post_meta( $id, 'custom_baths', $count_bathroom);
            //update_post_meta( $id, 'custom_beds', $count_bed); 
            wp_set_post_terms( $id, $term_ID, "listing_amenity" );

            if ($term = get_term_by('slug', strtolower($listing_data['propertyType']), 'listing_type')){
                $term_ID = $term->term_id;
                wp_set_post_terms( $id, array($term_ID), "listing_type" );
            }else{
                $cid = wp_insert_term($listing_data['propertyType'], 'listing_type', array(
                    'slug' => strtolower($listing_data['propertyType'])
                ));
                wp_set_post_terms( $id, array($cid['term_id']), "listing_type" );
            } 
            
            $city_name = ucfirst(strtolower($listing_data['city']));
            if ($term = get_term_by('slug', strtolower($city_name), 'listing_city')){
                $term_ID = $term->term_id;
                wp_set_post_terms( $id, array($term_ID), "listing_city" );
            }else{
                $cid = wp_insert_term($city_name, 'listing_city', array(
                    'slug' => strtolower($city_name)
                ));
                wp_set_post_terms( $id, array($cid['term_id']), "listing_city" );
            } 
            
            
            wp_set_post_terms( $id, $listing_data['propertyType'], "propertyType" );

            if(isset($listing_data['checkInStart'])){
                $date = DateTime::createFromFormat('H:i', $listing_data['checkInStart']);
                update_post_meta( $id, 'custom_checkin_after', $date->format('h:i A'));
            }
            if(isset($listing_data['checkOutEnd'])){
                $date = DateTime::createFromFormat('H:i', $listing_data['checkOutEnd']);  
                update_post_meta( $id, 'custom_checkout_before', $date->format('h:i A'));
            }

            update_post_meta( $id, 'custom_smoke', '0' );
            update_post_meta( $id, 'custom_pets', '0' );
            update_post_meta( $id, 'custom_booking_type', 'per_day' );
            update_post_meta( $id, 'custom_children', '1' );
            update_post_meta( $id, 'custom_party', '0' );
            update_post_meta( $id, 'custom_cancellation_policy', '4988' );
            update_post_meta( $id, 'custom_cleaning_fee', $cleaningFees);
            update_post_meta( $id, 'custom_tax_rate', 13);

            update_post_meta( $id, 'custom_allow_additional_guests', 'yes' );
            update_post_meta( $id, 'custom_listing_location', $listing_data['latitude'].",".$listing_data['longitude'] );
            update_post_meta( $id, 'custom_zip', $listing_data['postcode'] );

            update_post_meta( $id, 'custom_listing_address', $listing_data['address'].", ".$listing_data['city'].", ".$listing_data['state'].", ".$listing_data['postcode'].", ".$listing_data['country']);
            update_post_meta( $id, 'custom_night_price', $listing_data['roomTypes'][0]['rackRate'] );
            update_post_meta( $id, 'custom_instant_booking', 1);
            update_post_meta( $id, 'custom_guests', $listing_data['roomTypes'][0]['maxPeople'] );
            update_post_meta( $id, 'custom_room_types', $listing_data['roomTypes'][0]['id']);			
        }
    }
}
function getCurlResponse($url){
    $request_headers = [
        'accept: application/json',
        'token:' . getToken()
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
    $season_data = curl_exec($ch);
    if (curl_errno($ch)) {
        //print "Curl Error: " . curl_error($ch);
        //echo "<br/> Please <a href='/contact-us-2'>contact</a> support";
        exit();
    }
    curl_close($ch);
    
    $json = json_decode($season_data, true);
    //echo "<pre>";print_r($json);exit();
    if(isset($json['type']) && $json['type']=='error' && $json['error']=="Occupancy not defined"){
		return $json['error'];
	}elseif(isset($json['code']) && $json['code']==401 && $json['error']=="Token is missing"){
		generateNewTokens();
		exit();
	}elseif(isset($json['code']) && $json['code']==429 && $json['error']=="Credit limit exceeded"){
		return "Beds24 Error: Credit limit exceeded";
	}elseif(isset($json['type']) && $json['type']=='error'){
		refreshToken();
		//return getCurlResponse($url);
	}elseif(isset($json['error']) && $json['error']!=""){
		refreshToken();
		//return getCurlResponse($url);
	}
    //print_r($json);
    return $listing_api_arr = $json['data'];
}
function refreshToken(){
    $url = 'https://beds24.com/api/v2/authentication/token';
	$fh = fopen(dirname(__FILE__).'/refresh_token/refresh_token0.txt','r');
    while ($line = fgets($fh)) {
         $refreshToken = $line;
    }
    $request_headers = [
        'accept: application/json',
        'refreshToken:' . $refreshToken
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    $season_data = curl_exec($ch);
    if (curl_errno($ch)) {
        print "Error: " . curl_error($ch);
        exit();
    }
    // Show me the result
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headersRaw = substr($season_data, 0, $headerSize);
    $body = substr($season_data, $headerSize);
    
    curl_close($ch);
    $json = json_decode($season_data, true);
    //print_r($json);
    //$json = json_decode($season_data, true);
    // check json limit error if yes then call getNextRefreshTokenNumber(), 
    // and use it by calling same function refreshToken()
    if(isset($json['type']) && $json['error'] == 'Credit limit exceeded'){
        echo 'API limit error11: '.$json['error'];
        // Extract and simplify relevant headers
        
        // Dump all headers for debugging
        echo "<pre>--- RAW HEADERS ---\n" . $headersRaw . "</pre>";
    
        // Extract headers into associative array
        $headers = [];
        foreach (explode("\r\n", $headersRaw) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }
    
        echo "<pre>";
        echo "Remaining Credits: " . ($headers['x-five-min-limit-remaining'] ?? 'Not found') . "\n";
        echo "Resets In: " . ($headers['x-five-min-limit-resets-in'] ?? 'Not found') . " seconds\n";
        echo "Request Cost: " . ($headers['x-request-cost'] ?? 'Not found') . "\n";
        echo "</pre>";

		//generateNewTokens();
		exit();
    }elseif(isset($json['type']) && $json['type'] == 'error'){
		//beds24-connection-token_number.txt
		echo 'API refresh token param error1: '.$json['error'];
		//generateNewTokens();
		exit();
    }else if(isset($json['error']) && $json['error']!=""){
        echo 'API refresh token param error2: '.$json['error'];
        //generateNewTokens();
        exit();
    }
    $fh = fopen(dirname(__FILE__).'/beds24-connection-new.txt','w');
    fwrite($fh, $json['token']);
    fclose($fh);
    return;
}
function getToken($t_no=0){
    $token = "";
    //echo "Token no: ".$t_no."<br/>";
    $fh = fopen(dirname(__FILE__).'/beds24-connection-new.txt','r');
    while ($line = fgets($fh)) {
      $token = $line;
    }
    fclose($fh);
    return $token;
}
function generateNewTokens(){
    $Invite_Code = "Kr9hIdGgrUhO3Oi3Tsp2/gBy3EPsMswEjFVoO4nHpmgIB0TBlmM8TPatqSD4J3DnlbhnbrGUdcxgYFecGLbuQj/fnETJ+JmuTg39inkWbwu6qOSc5dMgD7NYo+BcC/HdJK2ISc1YkVsAYstkjheOY4wpzqLv+AIKdhrf9Br5ebI=";
    $url = 'https://beds24.com/api/v2/authentication/setup';
    $request_headers = [
        'accept: application/json',
        'code:' . $Invite_Code
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
    $season_data = curl_exec($ch);
    if (curl_errno($ch)) {
        print "Error: " . curl_error($ch);
        exit();
    }
    // Show me the result
    curl_close($ch);
    $json = json_decode($season_data, true);
    
    //echo "hhh<pre>";print_r($json);echo "ddd";
    
    if(isset($json['token'])){
        $fh = fopen(dirname(__FILE__).'/beds24-connection-new.txt','w');
        //echo $json['token'];
        fwrite($fh, $json['token']);
        fclose($fh);
    }
    if(isset($json['refreshToken'])){
        $fh = fopen(dirname(__FILE__).'/refresh_token/refresh_token0.txt','w');
        //echo $json['token'];
        fwrite($fh, $json['refreshToken']);
        fclose($fh);
    }
}
add_action('wp_ajax_save_guest_field', 'save_guest_field');
add_action('wp_ajax_nopriv_save_guest_field', 'save_guest_field');

function save_guest_field() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_POST['field'])) {
        $_SESSION['guest_'.$field = sanitize_text_field($_POST['field'])] 
            = sanitize_text_field($_POST['value']);
    }

    wp_die();
}
function load_guest_value($field) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['guest_'.$field]) ? esc_attr($_SESSION['guest_'.$field]) : '';
}
function getPropKeyID($listings_id){
    $getPropKeyArray = getPropKey();
    foreach($getPropKeyArray->getProperties as $properties){
    	$prop_arr[$properties->propId] = $properties->propKey;
    }
    return $listing_prop_key = $prop_arr[$listings_id];
}
function getCurlResponseV1Rates($listings_id, $room_id, $from, $to){
    $prop_key = getPropKeyID($listings_id);
    $url = "https://www.beds24.com/api/json/getRoomDates";
    $ch = curl_init();
    $data = array(
        "authentication" => array(
            "apiKey"  => "DVKTFTUZ36NSJW0F",
            "propKey" => $prop_key
        ),
        "roomId"                 => (int) $room_id,
        "from"                   => $from,   // YYYYMMDD
        "to"                     => $to,     // YYYYMMDD
        "incMaxStay"             => 0,
        "incMultiplier"          => 1,
        "incOverride"            => 0,
        "allowInventoryNegative" => 0,
        "incChannelBookingLimit" => 0
    );
    $json_data = json_encode($data);
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json')
    ));

    $resp = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($resp);
    //echo "<pre>";print_r($decoded);exit();
    if (!$decoded) {
        return null;
    }

    $final = [];

    foreach ($decoded as $date => $info) {
        // collect p1..p10
        $prices = [];
        foreach ($info as $key => $value) {
            if (strpos($key, 'p') === 0 && is_numeric($value)) {
                $prices[] = floatval($value);
            }
        }
        $price = isset($info->p2) ? floatval($info->p2) : null;
        $finalPrice = $price;
        if ($price !== null && isset($info->x)) {
            // x = percentage multiplier (e.g., 180 → 1.8×)
            $multiplier = floatval($info->x) / 100;
            $finalPrice = $price * $multiplier;
        }
        //$lowest = min($prices);
        $minStay = isset($info->m) ? intval($info->m) : null;
        $final[$date] = [
            "lowest_price" => $finalPrice,
            "min_stay"     => $minStay
        ];
    }
    //echo "<pre>";print_r($final);
    // SAVE JSON FILE
    $upload_dir = wp_upload_dir();
    $json_dir   = $upload_dir['basedir'] . '/beds24-availability/';
    if (!file_exists($json_dir)) {
        wp_mkdir_p($json_dir);
    }
    $file = $json_dir . "room-" . $room_id . ".json";
    file_put_contents($file, json_encode($final, JSON_PRETTY_PRINT));
    return $final;
}
function getCurlResponseV1Images($pro_key){
    $url = "https://www.beds24.com/api/json/getPropertyContent";
    $ch = curl_init();
    $data = array(
        "authentication"=>array( 
            "apiKey"=> "DVKTFTUZ36NSJW0F", 
            "propKey"=> "$pro_key"
        ), 
        'images' => 'true'
    );
    $new_data = json_encode($data);
    $array_options = array(
        CURLOPT_URL=>$url,
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$new_data,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>array('Content-Type:application/json')
    );
    curl_setopt_array($ch,$array_options);
    $resp = curl_exec($ch);
    $final_decoded_data = json_decode($resp);
    return $final_decoded_data->getPropertyContent[0]->images;
}
function getPropKey(){
    $url = "https://www.beds24.com/api/json/getProperties";
    $ch = curl_init();
    $data = array(
        "authentication"=>array( 
            "apiKey"=> "DVKTFTUZ36NSJW0F"
        )
    );
    //echo get_option('apiKey')."<br/>";
    $new_data = json_encode($data);
    $array_options = array(
        CURLOPT_URL=>$url,
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$new_data,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>array('Content-Type:application/json')
    );
    curl_setopt_array($ch,$array_options);
    $resp = curl_exec($ch);
    return $final_decoded_data = json_decode($resp);
}
function beds24_request_get($endpoint){

    $url = "https://beds24.com/api/v2/" . $endpoint;
    error_log("Beds24 API [$endpoint] REQUEST: " . $url);
    $headers = [
        "Content-Type: application/json",
        "accept: application/json",
        "token: " . getToken()
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    error_log("Beds24 API [$endpoint] RESPONSE: " . $response);
    $json = json_decode($response, true);    
    if(isset($json['type']) && $json['type']=='error' && $json['error']=="Occupancy not defined"){
		return $json['error'];
	}elseif(isset($json['code']) && $json['code']==401 && $json['error']=="Token is missing"){
		generateNewTokens();
		exit();
	}elseif(isset($json['code']) && $json['code']==401 && $json['error']=="Token not valid"){
		refreshToken();
		exit();
	}elseif(isset($json['code']) && $json['code']==429 && $json['error']=="Credit limit exceeded"){
		return "Beds24 Error: Credit limit exceeded";
	}elseif(isset($json['type']) && $json['type']=='error'){
		refreshToken();
		//return getCurlResponse($url);
	}elseif(isset($json['error']) && $json['error']!=""){
		refreshToken();
		//return getCurlResponse($url);
	}

    curl_close($ch);

    return json_decode($response, true);
}
function beds24_request($endpoint, $payload){

    $url = "https://beds24.com/api/v2/" . $endpoint;

    $headers = [
        "Content-Type: application/json",
        "accept: application/json",
        "token: " . getToken()
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    error_log("Beds24 API [$endpoint] REQUEST: " . json_encode($payload, JSON_PRETTY_PRINT));
    error_log("Beds24 API [$endpoint] RESPONSE: " . $response);
    $json = json_decode($response, true);    
    if(isset($json['type']) && $json['type']=='error' && $json['error']=="Occupancy not defined"){
		return $json['error'];
	}elseif(isset($json['code']) && $json['code']==401 && $json['error']=="Token is missing"){
		generateNewTokens();
		exit();
	}elseif(isset($json['code']) && $json['code']==401 && $json['error']=="Token not valid"){
		refreshToken();
		exit();
	}elseif(isset($json['code']) && $json['code']==429 && $json['error']=="Credit limit exceeded"){
		return "Beds24 Error: Credit limit exceeded";
	}elseif(isset($json['type']) && $json['type']=='error'){
		refreshToken();
		//return getCurlResponse($url);
	}elseif(isset($json['error']) && $json['error']!=""){
		refreshToken();
		//return getCurlResponse($url);
	}

    curl_close($ch);

    return json_decode($response, true);
}

add_action("wp_ajax_beds24_create_booking_and_stripe", "beds24_create_booking_and_stripe");
add_action("wp_ajax_nopriv_beds24_create_booking_and_stripe", "beds24_create_booking_and_stripe");

function beds24_create_booking_and_stripe(){

    session_start();

    // Sanitize inputs
    $first   = sanitize_text_field($_POST['first_name']);
    $last    = sanitize_text_field($_POST['last_name']);
    $email   = sanitize_email($_POST['email']);
    $mobile  = sanitize_text_field($_POST['mobile']);
    $comment = sanitize_text_field($_POST['comment']);

    $check_in   = sanitize_text_field($_POST['check_in']);
    $check_out  = sanitize_text_field($_POST['check_out']);
    $total      = floatval($_POST['total']);
    $bookingSummary = $_POST['bookingSummary'];

    /* ============================================
       STEP 1 → CREATE BEDS24 BOOKINGS
       (new booking format, no offerId required)
    ============================================ */

    $bookingPayload = [];

    foreach($bookingSummary as $unit){
        $bookingPayload[] = [
            "roomId"     => intval($unit["roomId"]),
            "arrival"    => $check_in,
            "departure"  => $check_out,

            // Beds24 fields for guests
            "numAdult"   => intval($unit["adults"]),
            "numChild"   => intval($unit["childs"]),

            "firstName"  => $first,
            "lastName"   => $last,
            "email"      => $email,
            "mobile"     => $mobile,
            "comments"   => $comment,

            // Beds24 ignores price on createBooking, but send anyway
            "price"      => floatval($unit["price"]),
        ];
    }

    $bookingRes = beds24_request("bookings", $bookingPayload);

    if(!$bookingRes || !is_array($bookingRes)) {
        echo json_encode(["error" => "Beds24 returned invalid response"]);
        exit;
    }

    // Extract booking IDs
    $bookingIds = [];
    foreach($bookingRes as $item){
        if(isset($item["new"]["id"])){
            $bookingIds[] = $item["new"]["id"];
        }
    }

    if(empty($bookingIds)) {
        echo json_encode(["error" => "Beds24 booking creation failed"]);
        exit;
    }

    /* ======================================================
       STORE SESSION FOR CONFIRMATION PAGE
    ====================================================== */
    $sessionData = [
        "bookingIds"      => $bookingIds,
        "total"           => $total,
        "check_in"        => $check_in,
        "check_out"       => $check_out,
        "bookingSummary"  => $bookingSummary,
        "customer"        => [
            "first"  => $first,
            "last"   => $last,
            "email"  => $email,
            "mobile" => $mobile,
            "comment"=> $comment
        ]
    ];

    set_transient("beds24_booking_session_" . session_id(), $sessionData, 3600);


    /* ======================================================
       STEP 2 → STRIPE SESSION BASED ON rateType
       Logic:
         flexible → store card only
         nonrefundable → charge full
    ====================================================== */

    $flexibleAmount = 0;
    $savingsAmount  = 0;

    foreach ($bookingSummary as $unit) {
        if ($unit['rateType'] === 'flexible') {
            $flexibleAmount += $unit['price'];
        } 
        else { // any non-flexible = SAVINGS
            $savingsAmount += $unit['price'];
        }
    }

    /* ============================
       CASE 1 → ONLY FLEXIBLE
       Capture = false (store card)
    ============================ */
    if ($flexibleAmount > 0 && $savingsAmount == 0) {

        $stripePayload = [[
            "action" => "createStripeSession",
            "bookingId" => $bookingIds[0],

            "capture" => false, // store card only

            "line_items" => [[
                "price_data" => [
                    "currency" => "eur",
                    "product_data" => ["name" => "Flexible Rate (Card Authorization Only)"],
                    "unit_amount" => 50 // required minimum hold
                ],
                "quantity" => 1
            ]],

            "success_url" => home_url("/custom-booking-confirmation/"),
            "cancel_url"  => home_url("/booking-failed/")
        ]];
    }

    /* ============================
       CASE 2 → ONLY SAVINGS (NON REFUNDABLE)
       Charge full amount
    ============================ */
    else if ($savingsAmount > 0 && $flexibleAmount == 0) {

        $stripePayload = [[
            "action" => "createStripeSession",
            "bookingId" => $bookingIds[0],

            "capture" => true, // charge fully now

            "line_items" => [[
                "price_data" => [
                    "currency" => "eur",
                    "product_data" => ["name" => "Accommodation Booking"],
                    "unit_amount" => intval($savingsAmount * 100)
                ],
                "quantity" => 1
            ]],

            "success_url" => home_url("/custom-booking-confirmation/"),
            "cancel_url"  => home_url("/booking-failed/")
        ]];
    }

    /* ============================
       CASE 3 → MIXED 
       Charge savings, store card for flexible
    ============================ */
    else {

        $stripePayload = [[
            "action" => "createStripeSession",
            "bookingId" => $bookingIds[0],

            "capture" => true,

            "line_items" => [[
                "price_data" => [
                    "currency" => "eur",
                    "product_data" => ["name" => "Savings Rate Portion"],
                    "unit_amount" => intval($savingsAmount * 100)
                ],
                "quantity" => 1
            ]],

            "success_url" => home_url("/custom-booking-confirmation/?storeCardForFlexible=1"),
            "cancel_url"  => home_url("/booking-failed/")
        ]];
    }

    /* ============================
       CREATE STRIPE SESSION
    ============================ */

    $stripeRes = beds24_request("channels/stripe", $stripePayload);
    $checkoutUrl = $stripeRes[0]['new']['stripeSession']['url'] ?? null;

    if(!$checkoutUrl) {
        echo json_encode(["error" => "Stripe checkout creation failed"]);
        exit;
    }

    echo json_encode(["url" => $checkoutUrl]);
    exit;
}
function generate_beds24_availability_json_single($listing_id = 0) {

    if (!$listing_id) {
        echo "No Beds24 ID found for listing."; 
        return;
    }

    // Prepare JSON directory
    $upload_dir = wp_upload_dir();
    $json_dir   = $upload_dir['basedir'] . '/beds24-availability';

    if (!file_exists($json_dir)) {
        wp_mkdir_p($json_dir);
    }

    // Date range: Today → +180 days
    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d', strtotime('+180 days'));

    // Beds24 API URL
    $url = "https://beds24.com/api/v2/inventory/rooms/availability/?startDate={$startDate}&endDate={$endDate}&propertyId={$listing_id}";
    $api = getCurlResponse($url);

    if (empty($api[0]['availability'])) {
        echo "No availability returned for Beds24 property {$listing_id}";
        return;
    }
    
    echo "<pre>";print_r($api);
    /* -------------------------------------------------
       BUILD JSON STRUCTURE
       rooms → roomId → [{date, available}]
    --------------------------------------------------*/

    $result = [
        "rooms" => []
    ];
    foreach ($api as $room) {
        $roomId = $room['roomId'];
        $rawAvail = $room['availability'];
        foreach ($rawAvail as $date => $isAvailable) {
            // Each date returns array: roomId => 0/1
            $result["rooms"][$roomId][] = [
                "date"      => $date,
                "available" => intval($isAvailable)
            ];
        }
    }
    // Save JSON file
    $json_file = $json_dir . "/{$listing_id}.json";
    file_put_contents($json_file, json_encode($result, JSON_PRETTY_PRINT));

    echo "<strong>JSON generated:</strong> {$upload_dir['baseurl']}/beds24-availability/{$listing_id}.json<br>";
    echo "Rooms found: " . count($result['rooms']);
}
function render_room_calendar($roomId, $listing_id, $currentMonth) {
    // Load JSON file
    $upload_dir = wp_upload_dir();
    $json_file  = $upload_dir['basedir'] . '/beds24-availability/' . $listing_id . '.json';
    if (!file_exists($json_file)) return '';

    $json = json_decode(file_get_contents($json_file), true);
    if (empty($json['rooms'][$roomId])) return '';
    
    $price_file = $upload_dir['basedir'] . "/beds24-availability/room-" . $roomId . ".json";
    $roomPrices = [];
    
    if (file_exists($price_file)) {
        $roomPrices = json_decode(file_get_contents($price_file), true);
    }

    $rows = $json['rooms'][$roomId];

    // Convert to date → available map
    $map = [];
    foreach ($rows as $row) {
        $map[$row['date']] = intval($row['available']);
    }

    // Determine which months to show
    $startMonth = new DateTime($currentMonth . '-01');
    $secondMonth = (clone $startMonth)->modify('+1 month');

    $months = [$startMonth, $secondMonth];

    // Build calendar HTML
    $html  = '<div class="room-calendar">';
    $html .= '<div class="cal-header">';

    // Left arrow
    $prev = (clone $startMonth)->modify('-1 month')->format('Y-m');
    $html .= '<a class="cal-nav cal-prev" href="' . add_query_arg('cal_month', $prev) . '">❮</a>';

    // Title
    $html .= '<span class="cal-title">'
          . $startMonth->format('F Y') . ' &nbsp;&nbsp; ' 
          . $secondMonth->format('F Y')
          . '</span>';

    // Right arrow
    $next = (clone $startMonth)->modify('+1 month')->format('Y-m');
    $html .= '<a class="cal-nav cal-next" href="' . add_query_arg('cal_month', $next) . '">❯</a>';

    $html .= '</div><div class="cal-wrapper">';

    // Generate each month calendar
    foreach ($months as $monthObj) {
        //$html .= generate_single_month_calendar($monthObj, $map);
        $html .= generate_single_month_calendar($monthObj, $map, $roomPrices);
    }

    $html .= '</div></div>';

    return $html;
}

function generate_single_month_calendar($monthObj, $map, $roomPrices) {
    $year  = $monthObj->format('Y');
    $month = $monthObj->format('m');

    $firstDay = new DateTime("$year-$month-01");
    $daysInMonth = (int)$firstDay->format('t');

    $html = '<div class="cal-month">';
    $html .= '<div class="cal-month-title">' . $firstDay->format('F Y') . '</div>';
    $html .= '<div class="cal-grid">';
    $html .= '<div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>';

    // Pad empty cells before month starts (Monday = 1)
    $weekDay = (int)$firstDay->format('N');
    for ($i = 1; $i < $weekDay; $i++) {
        $html .= '<div class="cal-empty"></div>';
    }

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $priceKey = $year . $month . str_pad($d, 2, "0", STR_PAD_LEFT);
        $price = isset($roomPrices[$priceKey]['lowest_price'])
                ? round($roomPrices[$priceKey]['lowest_price'])
                : null;
        $date = "$year-$month-" . str_pad($d, 2, "0", STR_PAD_LEFT);

        $isToday = ($date == date("Y-m-d"));
        $avail   = isset($map[$date]) ? $map[$date] : 0;

        if ($isToday) {
            $class = "cal-today";
        } else {
            $class = $avail == 1 ? "cal-green" : "cal-red";
        }

        //$html .= "<div class='cal-day $class'>$d</div>";
        $html .= "<div class='cal-day $class'>";
        $html .= "<div class='cal-day-num'>$d</div>";
        
        if ($price) {
            //$html .= "<div class='cal-day-price'>€{$price}</div>";
        }
        
        $html .= "</div>";
    }

    $html .= '</div></div>';
    return $html;
}

?>