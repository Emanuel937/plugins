<?php
/**
 * Plugin Name: Auto Product Categories in Menu
 * Description: Automatically adds WooCommerce product categories (and their children) to Appearance > Menus.
 * Version: 2.0
 * Author: Emanuel Dev
 */

if (!defined('ABSPATH')) exit;

/**
 * Register the metabox in Appearance > Menus
 */
function apcm_register_metabox() {
    add_meta_box(
        'apcm_product_categories',
        __('Product Categories (Auto Children)', 'apcm'),
        'apcm_metabox_content',
        'nav-menus',
        'side',
        'default'
    );
}
add_action('admin_menu', 'apcm_register_metabox');


/**
 * Metabox content
 */
function apcm_metabox_content() {

    // Nonce pour l’AJAX
    wp_nonce_field('apcm_add_to_menu_action', 'apcm_nonce');

    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => 0,
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        echo '<p>' . esc_html__('No product categories found.', 'apcm') . '</p>';
        return;
    }

    echo '<div class="apcm-box">';
    echo '<p>' . esc_html__('Select a product category. All children will be added automatically.', 'apcm') . '</p>';
    echo '<ul>';

    foreach ($terms as $term) {
        echo '<li>';
        echo '<label>';
        echo '<input type="checkbox" class="apcm-cat" name="apcm_cats[]" value="' . esc_attr($term->term_id) . '"> ';
        echo esc_html($term->name);
        echo '</label>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';

    // Bouton AJAX (pas un submit du formulaire)
    echo '<p class="button-controls wp-clearfix">';
    echo '  <span class="add-to-menu">';
    echo '      <button type="button" class="button button-primary right" id="apcm_add_to_menu_btn">';
    echo            esc_html__('Add to Menu', 'apcm');
    echo '      </button>';
    echo '      <span class="spinner" id="apcm_spinner" style="float:none;"></span>';
    echo '  </span>';
    echo '</p>';
}


/**
 * JS pour gérer le clic sur "Add to Menu" en AJAX
 */
add_action('admin_footer-nav-menus.php', function () {
    ?>
    <script>
        (function($){
            $(document).on('click', '#apcm_add_to_menu_btn', function (e) {
                e.preventDefault();

                const $btn     = $(this);
                const $spinner = $('#apcm_spinner');

                // Récupérer les catégories cochées
                const cats = [];
                $('.apcm-cat:checked').each(function () {
                    cats.push($(this).val());
                });

                if (!cats.length) {
                    alert('<?php echo esc_js(__('Please select at least one category.', 'apcm')); ?>');
                    return;
                }

                // ID du menu courant
                const menuIdField = $('#nav-menu-meta-object-id'); // hidden dans #nav-menu-meta
                const menuId = menuIdField.length ? menuIdField.val() : '';

                if (!menuId) {
                    alert('<?php echo esc_js(__('No menu selected.', 'apcm')); ?>');
                    return;
                }

                const nonce = $('#apcm_nonce').val();

                $btn.prop('disabled', true);
                $spinner.addClass('is-active');

                $.post(ajaxurl, {
                    action: 'apcm_add_to_menu',
                    menu_id: menuId,
                    cats: cats,
                    nonce: nonce
                }).done(function (response) {
                    if (!response || !response.success) {
                        alert('<?php echo esc_js(__('Error while adding categories to menu.', 'apcm')); ?>');
                        console.log(response);
                    } else {
                        // Recharger la page pour voir les éléments ajoutés
                        location.reload();
                    }
                }).fail(function (err) {
                    alert('<?php echo esc_js(__('AJAX request failed.', 'apcm')); ?>');
                    console.log(err);
                }).always(function () {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                });
            });
        })(jQuery);
    </script>
    <?php
});


/**
 * AJAX handler: add categories + children to menu
 */
add_action('wp_ajax_apcm_add_to_menu', function () {

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'apcm_add_to_menu_action')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    if (!current_user_can('edit_theme_options')) {
        wp_send_json_error(['message' => 'Not allowed']);
    }

    $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;
    if (!$menu_id) {
        wp_send_json_error(['message' => 'Invalid menu ID']);
    }

    $cats = isset($_POST['cats']) && is_array($_POST['cats']) ? array_map('intval', $_POST['cats']) : [];
    if (empty($cats)) {
        wp_send_json_error(['message' => 'No categories selected']);
    }

    foreach ($cats as $cat_id) {
        apcm_add_category_with_children($menu_id, $cat_id);
    }

    wp_send_json_success(['message' => 'Categories added']);
});


/**
 * Add a category and all its children recursively
 */
function apcm_add_category_with_children($menu_id, $cat_id, $parent_item_id = 0) {

    $term = get_term($cat_id, 'product_cat');
    if (!$term || is_wp_error($term)) {
        return;
    }

    $item_id = wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title'     => $term->name,
        'menu-item-object'    => 'product_cat',
        'menu-item-object-id' => $cat_id,
        'menu-item-type'      => 'taxonomy',
        'menu-item-parent-id' => $parent_item_id,
        'menu-item-status'    => 'publish',
    ]);

    if (is_wp_error($item_id) || !$item_id) {
        return;
    }

    $children = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => $cat_id,
    ]);

    if (empty($children) || is_wp_error($children)) {
        return;
    }

    foreach ($children as $child) {
        apcm_add_category_with_children($menu_id, $child->term_id, $item_id);
    }
}
