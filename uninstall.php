<?php
/**
 * Uninstall script for TPAK DQ System
 * 
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data from the database.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include WordPress functions
require_once(ABSPATH . 'wp-admin/includes/user.php');

/**
 * Remove all plugin data
 */
function tpak_dq_system_uninstall() {
    global $wpdb;
    
    // Get all verification batch posts
    $posts = get_posts(array(
        'post_type' => 'verification_batch',
        'numberposts' => -1,
        'post_status' => 'any'
    ));
    
    // Delete all verification batch posts and their meta
    foreach ($posts as $post) {
        wp_delete_post($post->ID, true);
    }
    
    // Delete all terms in verification_status taxonomy
    $terms = get_terms(array(
        'taxonomy' => 'verification_status',
        'hide_empty' => false
    ));
    
    foreach ($terms as $term) {
        wp_delete_term($term->term_id, 'verification_status');
    }
    
    // Remove user roles
    remove_role('interviewer');
    remove_role('supervisor');
    remove_role('examiner');
    
    // Remove capabilities from administrator role
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('manage_tpak_settings');
        $admin_role->remove_cap('edit_others_verification_batches');
    }
    
    // Delete plugin options
    delete_option('tpak_dq_system_options');
    
    // Clear scheduled cron events
    wp_clear_scheduled_hook('tpak_dq_cron_import_data');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Execute uninstall function
tpak_dq_system_uninstall(); 