<?php
/**
 * Plugin Name: Auto Product Categories in Menu
 * Description: Automatically adds WooCommerce product categories (and their children) to Appearance > Menus.
 * Version: 1.0
 * Author: Emanuel Dev
 */

if (!defined('ABSPATH')) exit;

/**
 * Add a custom metabox in Appearance > Menus
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
add_action('admin_head-nav-menus.php', 'apcm_register_metabox');

/**
 * Display product categories list
 */
function apcm_metabox_content() {
    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => 0
    ]);

    echo '<div class="apcm-box">';
    echo '<p>Select a product category. All children will be added automatically.</p>';
    echo '<ul>';

    foreach ($terms as $term) {
        echo '<li>';
        echo '<label>';
        echo '<input type="checkbox" name="apcm_cats[]" value="' . esc_attr($term->term_id) . '"> ';
        echo esc_html($term->name);
        echo '</label>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';

    submit_button(__('Add to Menu'), 'primary', 'apcm_add_to_menu');
}

/**
 * Handle adding categories to menu
 */
function apcm_process_add($menu_id) {
    if (!isset($_POST['apcm_add_to_menu']) || empty($_POST['apcm_cats'])) {
        return;
    }

    foreach ($_POST['apcm_cats'] as $cat_id) {
        apcm_add_category_with_children($menu_id, intval($cat_id));
    }
}
add_action('wp_update_nav_menu', 'apcm_process_add');

/**
 * Add a category and all its children recursively
 */
function apcm_add_category_with_children($menu_id, $cat_id, $parent_item_id = 0) {

    // Add the category to the menu
    $item_id = wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-title'     => get_term($cat_id)->name,
        'menu-item-object'    => 'product_cat',
        'menu-item-object-id' => $cat_id,
        'menu-item-type'      => 'taxonomy',
        'menu-item-parent-id' => $parent_item_id,
        'menu-item-status'    => 'publish'
    ]);

    // Get children
    $children = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => $cat_id
    ]);

    // Recursively add children
    foreach ($children as $child) {
        apcm_add_category_with_children($menu_id, $child->term_id, $item_id);
    }
}
