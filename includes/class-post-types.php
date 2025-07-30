<?php
/**
 * TPAK DQ System - Post Types and Taxonomies
 * 
 * Handles the registration of custom post types and taxonomies
 * for the verification batch system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Post_Types {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        // Register verification_batch post type
        register_post_type('verification_batch', array(
            'labels' => array(
                'name' => __('ชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'singular_name' => __('ชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'menu_name' => __('ชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'add_new' => __('เพิ่มใหม่', 'tpak-dq-system'),
                'add_new_item' => __('เพิ่มชุดข้อมูลตรวจสอบใหม่', 'tpak-dq-system'),
                'edit_item' => __('แก้ไขชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'new_item' => __('ชุดข้อมูลตรวจสอบใหม่', 'tpak-dq-system'),
                'view_item' => __('ดูชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'search_items' => __('ค้นหาชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'not_found' => __('ไม่พบชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'not_found_in_trash' => __('ไม่พบชุดข้อมูลตรวจสอบในถังขยะ', 'tpak-dq-system'),
                'all_items' => __('ชุดข้อมูลตรวจสอบทั้งหมด', 'tpak-dq-system'),
                'archives' => __('ชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'attributes' => __('คุณสมบัติชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'insert_into_item' => __('แทรกลงในชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'uploaded_to_this_item' => __('อัปโหลดไปยังชุดข้อมูลตรวจสอบนี้', 'tpak-dq-system'),
                'featured_image' => __('รูปภาพหลัก', 'tpak-dq-system'),
                'set_featured_image' => __('ตั้งรูปภาพหลัก', 'tpak-dq-system'),
                'remove_featured_image' => __('ลบรูปภาพหลัก', 'tpak-dq-system'),
                'use_featured_image' => __('ใช้เป็นรูปภาพหลัก', 'tpak-dq-system'),
                'filter_items_list' => __('กรองรายการชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'items_list_navigation' => __('นำทางรายการชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
                'items_list' => __('รายการชุดข้อมูลตรวจสอบ', 'tpak-dq-system'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => true,
            'menu_position' => 30,
            'menu_icon' => 'dashicons-clipboard',
            'hierarchical' => false,
            'supports' => array(
                'title',
                'editor',
                'custom-fields',
                'revisions'
            ),
            'has_archive' => false,
            'rewrite' => array(
                'slug' => 'verification-batch',
                'with_front' => false
            ),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'capabilities' => array(
                'edit_post' => 'edit_verification_batch',
                'read_post' => 'read_verification_batch',
                'delete_post' => 'delete_verification_batch',
                'edit_posts' => 'edit_verification_batches',
                'edit_others_posts' => 'edit_others_verification_batches',
                'publish_posts' => 'publish_verification_batches',
                'read_private_posts' => 'read_private_verification_batches',
                'delete_posts' => 'delete_verification_batches',
                'delete_private_posts' => 'delete_private_verification_batches',
                'delete_published_posts' => 'delete_published_verification_batches',
                'delete_others_posts' => 'delete_others_verification_batches',
                'edit_private_posts' => 'edit_private_verification_batches',
                'edit_published_posts' => 'edit_published_verification_batches',
            ),
        ));
    }
    
    /**
     * Register custom taxonomies
     */
    public function register_taxonomies() {
        // Register verification_status taxonomy
        register_taxonomy('verification_status', array('verification_batch'), array(
            'labels' => array(
                'name' => __('สถานะการตรวจสอบ', 'tpak-dq-system'),
                'singular_name' => __('สถานะการตรวจสอบ', 'tpak-dq-system'),
                'search_items' => __('ค้นหาสถานะ', 'tpak-dq-system'),
                'all_items' => __('สถานะทั้งหมด', 'tpak-dq-system'),
                'parent_item' => __('สถานะหลัก', 'tpak-dq-system'),
                'parent_item_colon' => __('สถานะหลัก:', 'tpak-dq-system'),
                'edit_item' => __('แก้ไขสถานะ', 'tpak-dq-system'),
                'update_item' => __('อัปเดตสถานะ', 'tpak-dq-system'),
                'add_new_item' => __('เพิ่มสถานะใหม่', 'tpak-dq-system'),
                'new_item_name' => __('ชื่อสถานะใหม่', 'tpak-dq-system'),
                'menu_name' => __('สถานะการตรวจสอบ', 'tpak-dq-system'),
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rewrite' => array(
                'slug' => 'verification-status',
                'with_front' => false
            ),
            'capabilities' => array(
                'manage_terms' => 'manage_verification_status',
                'edit_terms' => 'edit_verification_status',
                'delete_terms' => 'delete_verification_status',
                'assign_terms' => 'assign_verification_status',
            ),
        ));
        
        // Create default terms if they don't exist
        $this->create_default_terms();
    }
    
    /**
     * Create default verification status terms
     */
    private function create_default_terms() {
        $default_terms = array(
            'pending_a' => array(
                'name' => __('รอการตรวจสอบ A', 'tpak-dq-system'),
                'slug' => 'pending_a',
                'description' => __('รอการตรวจสอบจาก Interviewer', 'tpak-dq-system')
            ),
            'pending_b' => array(
                'name' => __('รอการตรวจสอบ B', 'tpak-dq-system'),
                'slug' => 'pending_b',
                'description' => __('รอการตรวจสอบจาก Supervisor', 'tpak-dq-system')
            ),
            'pending_c' => array(
                'name' => __('รอการตรวจสอบ C', 'tpak-dq-system'),
                'slug' => 'pending_c',
                'description' => __('รอการตรวจสอบจาก Examiner', 'tpak-dq-system')
            ),
            'rejected_by_b' => array(
                'name' => __('ส่งกลับจาก B', 'tpak-dq-system'),
                'slug' => 'rejected_by_b',
                'description' => __('ส่งกลับจาก Supervisor เพื่อแก้ไข', 'tpak-dq-system')
            ),
            'rejected_by_c' => array(
                'name' => __('ส่งกลับจาก C', 'tpak-dq-system'),
                'slug' => 'rejected_by_c',
                'description' => __('ส่งกลับจาก Examiner เพื่อแก้ไข', 'tpak-dq-system')
            ),
            'finalized' => array(
                'name' => __('ตรวจสอบเสร็จสมบูรณ์', 'tpak-dq-system'),
                'slug' => 'finalized',
                'description' => __('ตรวจสอบเสร็จสมบูรณ์แล้ว', 'tpak-dq-system')
            ),
            'finalized_by_sampling' => array(
                'name' => __('เสร็จสมบูรณ์โดยการสุ่ม', 'tpak-dq-system'),
                'slug' => 'finalized_by_sampling',
                'description' => __('เสร็จสมบูรณ์โดยการสุ่มตรวจสอบ', 'tpak-dq-system')
            )
        );
        
        foreach ($default_terms as $term_key => $term_data) {
            $existing_term = get_term_by('slug', $term_data['slug'], 'verification_status');
            if (!$existing_term) {
                wp_insert_term(
                    $term_data['name'],
                    'verification_status',
                    array(
                        'slug' => $term_data['slug'],
                        'description' => $term_data['description']
                    )
                );
            }
        }
    }
} 