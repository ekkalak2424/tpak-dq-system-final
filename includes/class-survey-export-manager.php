<?php
/**
 * TPAK DQ System - Survey Export Manager
 * 
 * ระบบส่งออกและส่งต่อข้อมูลแบบสอบถามครบถ้วน
 * รองรับ PDF, Excel, JSON, Email และ API forwarding
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Survey_Export_Manager {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_export_survey_pdf', array($this, 'export_pdf'));
        add_action('wp_ajax_export_survey_excel', array($this, 'export_excel'));
        add_action('wp_ajax_export_survey_json', array($this, 'export_json'));
        add_action('wp_ajax_send_survey_email', array($this, 'send_email'));
        add_action('wp_ajax_bulk_export_surveys', array($this, 'bulk_export'));
        
        // WP Cron สำหรับ scheduled exports
        add_action('tpak_scheduled_export', array($this, 'handle_scheduled_export'));
    }
    
    /**
     * Export แบบสอบถามเป็น PDF พร้อมกราฟและตาราง
     */
    public function export_pdf() {
        check_ajax_referer('export_pdf_nonce', 'nonce');
        
        if (!current_user_can('export_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการ Export');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $include_charts = isset($_POST['include_charts']) && $_POST['include_charts'] === 'true';
        $include_history = isset($_POST['include_history']) && $_POST['include_history'] === 'true';
        $template = sanitize_text_field($_POST['template'] ?? 'standard');
        
        try {
            // ดึงข้อมูลสมบูรณ์
            $survey_data = $this->get_complete_survey_data($response_id);
            if (!$survey_data) {
                wp_send_json_error('ไม่พบข้อมูลแบบสอบถาม');
            }
            
            // สร้าง PDF
            $pdf_url = $this->generate_pdf($survey_data, array(
                'include_charts' => $include_charts,
                'include_history' => $include_history,
                'template' => $template
            ));
            
            // บันทึก log
            $this->log_export($response_id, 'pdf', $pdf_url);
            
            wp_send_json_success(array(
                'file_url' => $pdf_url,
                'message' => 'Export PDF เรียบร้อย',
                'file_name' => basename($pdf_url)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * Export แบบสอบถามเป็น Excel พร้อมข้อมูลวิเคราะห์
     */
    public function export_excel() {
        check_ajax_referer('export_excel_nonce', 'nonce');
        
        if (!current_user_can('export_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการ Export');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $include_analysis = isset($_POST['include_analysis']) && $_POST['include_analysis'] === 'true';
        $format = sanitize_text_field($_POST['format'] ?? 'xlsx');
        
        try {
            // ดึงข้อมูลสมบูรณ์
            $survey_data = $this->get_complete_survey_data($response_id);
            if (!$survey_data) {
                wp_send_json_error('ไม่พบข้อมูลแบบสอบถาม');
            }
            
            // สร้าง Excel
            $excel_url = $this->generate_excel($survey_data, array(
                'include_analysis' => $include_analysis,
                'format' => $format
            ));
            
            // บันทึก log
            $this->log_export($response_id, 'excel', $excel_url);
            
            wp_send_json_success(array(
                'file_url' => $excel_url,
                'message' => 'Export Excel เรียบร้อย',
                'file_name' => basename($excel_url)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * Export แบบสอบถามเป็น JSON
     */
    public function export_json() {
        check_ajax_referer('export_json_nonce', 'nonce');
        
        if (!current_user_can('export_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการ Export');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $pretty_print = isset($_POST['pretty_print']) && $_POST['pretty_print'] === 'true';
        $include_metadata = isset($_POST['include_metadata']) && $_POST['include_metadata'] === 'true';
        
        try {
            // ดึงข้อมูลสมบูรณ์
            $survey_data = $this->get_complete_survey_data($response_id);
            if (!$survey_data) {
                wp_send_json_error('ไม่พบข้อมูลแบบสอบถาม');
            }
            
            // สร้าง JSON
            $json_url = $this->generate_json($survey_data, array(
                'pretty_print' => $pretty_print,
                'include_metadata' => $include_metadata
            ));
            
            // บันทึก log
            $this->log_export($response_id, 'json', $json_url);
            
            wp_send_json_success(array(
                'file_url' => $json_url,
                'message' => 'Export JSON เรียบร้อย',
                'file_name' => basename($json_url)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * ส่งแบบสอบถามทาง Email
     */
    public function send_email() {
        check_ajax_referer('send_email_nonce', 'nonce');
        
        if (!current_user_can('forward_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการส่ง Email');
        }
        
        $response_id = sanitize_text_field($_POST['response_id']);
        $recipients = array_map('sanitize_email', $_POST['recipients']);
        $subject = sanitize_text_field($_POST['subject']);
        $message = sanitize_textarea_field($_POST['message']);
        $attach_pdf = isset($_POST['attach_pdf']) && $_POST['attach_pdf'] === 'true';
        $attach_excel = isset($_POST['attach_excel']) && $_POST['attach_excel'] === 'true';
        
        try {
            // ดึงข้อมูลสมบูรณ์
            $survey_data = $this->get_complete_survey_data($response_id);
            if (!$survey_data) {
                wp_send_json_error('ไม่พบข้อมูลแบบสอบถาม');
            }
            
            // เตรียม attachments
            $attachments = array();
            
            if ($attach_pdf) {
                $pdf_path = $this->generate_pdf($survey_data, array('template' => 'email'));
                $attachments[] = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $pdf_path);
            }
            
            if ($attach_excel) {
                $excel_path = $this->generate_excel($survey_data, array('format' => 'xlsx'));
                $attachments[] = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $excel_path);
            }
            
            // ส่ง Email
            $sent_count = 0;
            $errors = array();
            
            foreach ($recipients as $email) {
                if (empty($email)) continue;
                
                $personalized_message = $this->personalize_email_message($message, $email, $survey_data);
                
                $result = wp_mail(
                    $email,
                    $subject,
                    $personalized_message,
                    array('Content-Type: text/html; charset=UTF-8'),
                    $attachments
                );
                
                if ($result) {
                    $sent_count++;
                } else {
                    $errors[] = $email;
                }
            }
            
            // บันทึก log
            $this->log_export($response_id, 'email', array(
                'recipients' => $recipients,
                'sent_count' => $sent_count,
                'errors' => $errors
            ));
            
            wp_send_json_success(array(
                'sent_count' => $sent_count,
                'total_recipients' => count($recipients),
                'errors' => $errors,
                'message' => "ส่ง Email สำเร็จ $sent_count จาก " . count($recipients) . " ผู้รับ"
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * Bulk export หลายแบบสอบถาม
     */
    public function bulk_export() {
        check_ajax_referer('bulk_export_nonce', 'nonce');
        
        if (!current_user_can('export_survey_responses')) {
            wp_send_json_error('ไม่มีสิทธิ์ในการ Export');
        }
        
        $response_ids = array_map('sanitize_text_field', $_POST['response_ids']);
        $export_format = sanitize_text_field($_POST['export_format']);
        $merge_files = isset($_POST['merge_files']) && $_POST['merge_files'] === 'true';
        
        try {
            $exported_files = array();
            $errors = array();
            
            if ($merge_files) {
                // รวมไฟล์เป็นไฟล์เดียว
                $merged_data = array();
                
                foreach ($response_ids as $response_id) {
                    $survey_data = $this->get_complete_survey_data($response_id);
                    if ($survey_data) {
                        $merged_data[] = $survey_data;
                    } else {
                        $errors[] = $response_id;
                    }
                }
                
                if (!empty($merged_data)) {
                    $file_url = $this->generate_merged_export($merged_data, $export_format);
                    $exported_files[] = $file_url;
                }
                
            } else {
                // Export แยกไฟล์
                foreach ($response_ids as $response_id) {
                    $survey_data = $this->get_complete_survey_data($response_id);
                    
                    if ($survey_data) {
                        switch ($export_format) {
                            case 'pdf':
                                $file_url = $this->generate_pdf($survey_data);
                                break;
                            case 'excel':
                                $file_url = $this->generate_excel($survey_data);
                                break;
                            case 'json':
                                $file_url = $this->generate_json($survey_data);
                                break;
                            default:
                                throw new Exception('รูปแบบไฟล์ไม่ถูกต้อง');
                        }
                        
                        $exported_files[] = $file_url;
                        
                    } else {
                        $errors[] = $response_id;
                    }
                }
            }
            
            // สร้าง ZIP ถ้ามีหลายไฟล์
            if (count($exported_files) > 1) {
                $zip_url = $this->create_zip_archive($exported_files, 'bulk_export_' . time());
                $exported_files = array($zip_url);
            }
            
            // บันทึก log
            $this->log_export('bulk_' . implode(',', $response_ids), $export_format, $exported_files);
            
            wp_send_json_success(array(
                'exported_files' => $exported_files,
                'total_processed' => count($response_ids),
                'successful' => count($exported_files),
                'errors' => $errors,
                'message' => 'Bulk Export เรียบร้อย'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    
    /**
     * สร้าง PDF จากข้อมูลแบบสอบถาม
     */
    private function generate_pdf($survey_data, $options = array()) {
        // ใช้ TCPDF หรือ mPDF
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'libs/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // ตั้งค่า PDF
        $pdf->SetCreator('TPAK DQ System');
        $pdf->SetAuthor('TPAK Survey System');
        $pdf->SetTitle('Survey Report - ' . $survey_data['response_id']);
        
        // กำหนด font สำหรับภาษาไทย
        $pdf->SetFont('thsarabunnew', '', 14);
        
        // Header และ Footer
        $pdf->SetHeaderData('', 0, 'รายงานแบบสอบถาม', 'Response ID: ' . $survey_data['response_id']);
        $pdf->setFooterData();
        
        // เพิ่มหน้า
        $pdf->AddPage();
        
        // เนื้อหา PDF
        $html = $this->generate_pdf_content($survey_data, $options);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // สร้างไฟล์
        $upload_dir = wp_upload_dir();
        $file_name = 'survey_' . $survey_data['response_id'] . '_' . time() . '.pdf';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        // สร้างโฟลเดอร์ถ้ายังไม่มี
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        // บันทึกไฟล์
        $pdf->Output($file_path, 'F');
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * สร้าง Excel จากข้อมูลแบบสอบถาม
     */
    private function generate_excel($survey_data, $options = array()) {
        // ใช้ PHPSpreadsheet
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'libs/phpspreadsheet/vendor/autoload.php';
        
        use PhpOffice\PhpSpreadsheet\Spreadsheet;
        use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
        use PhpOffice\PhpSpreadsheet\Style\Color;
        use PhpOffice\PhpSpreadsheet\Style\Fill;
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // หัวข้อ
        $sheet->setTitle('Survey Response');
        $sheet->setCellValue('A1', 'รายงานแบบสอบถาม');
        $sheet->setCellValue('A2', 'Response ID: ' . $survey_data['response_id']);
        $sheet->setCellValue('A3', 'วันที่: ' . date('Y-m-d H:i:s'));
        
        // จัดรูปแบบหัวข้อ
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2:A3')->getFont()->setSize(12);
        
        // ข้อมูลแบบสอบถาม
        $row = 5;
        $response_data = json_decode($survey_data['response_data'], true);
        $responses = $response_data['responses'] ?? array();
        
        // หัวตาราง
        $sheet->setCellValue('A' . $row, 'คำถาม');
        $sheet->setCellValue('B' . $row, 'คำตอบ');
        $sheet->setCellValue('C' . $row, 'ประเภท');
        
        // จัดรูปแบบหัวตาราง
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA']
            ]
        ];
        $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($headerStyle);
        
        $row++;
        
        // ข้อมูลคำตอบ
        foreach ($responses as $field => $value) {
            $question_text = $this->get_question_text($field, $survey_data);
            $formatted_value = $this->format_answer_for_export($value);
            $question_type = $this->get_question_type($field);
            
            $sheet->setCellValue('A' . $row, $question_text);
            $sheet->setCellValue('B' . $row, $formatted_value);
            $sheet->setCellValue('C' . $row, $question_type);
            
            $row++;
        }
        
        // ปรับขนาดคอลัมน์
        $sheet->getColumnDimension('A')->setWidth(50);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(15);
        
        // เพิ่ม sheet วิเคราะห์ถ้าต้องการ
        if (isset($options['include_analysis']) && $options['include_analysis']) {
            $this->add_analysis_sheet($spreadsheet, $survey_data);
        }
        
        // บันทึกไฟล์
        $upload_dir = wp_upload_dir();
        $file_name = 'survey_' . $survey_data['response_id'] . '_' . time() . '.xlsx';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($file_path);
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * สร้าง JSON จากข้อมูลแบบสอบถาม
     */
    private function generate_json($survey_data, $options = array()) {
        $export_data = $survey_data;
        
        // เพิ่ม metadata ถ้าต้องการ
        if (isset($options['include_metadata']) && $options['include_metadata']) {
            $export_data['export_metadata'] = array(
                'exported_at' => current_time('mysql'),
                'exported_by' => get_current_user_id(),
                'export_version' => '1.0',
                'system_info' => array(
                    'plugin_version' => TPAK_DQ_SYSTEM_VERSION,
                    'wordpress_version' => get_bloginfo('version'),
                    'php_version' => PHP_VERSION
                )
            );
        }
        
        // กำหนดรูปแบบ JSON
        $json_flags = JSON_UNESCAPED_UNICODE;
        if (isset($options['pretty_print']) && $options['pretty_print']) {
            $json_flags |= JSON_PRETTY_PRINT;
        }
        
        // บันทึกไฟล์
        $upload_dir = wp_upload_dir();
        $file_name = 'survey_' . $survey_data['response_id'] . '_' . time() . '.json';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        file_put_contents($file_path, json_encode($export_data, $json_flags));
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * สร้าง ZIP archive
     */
    private function create_zip_archive($file_urls, $zip_name) {
        $upload_dir = wp_upload_dir();
        $zip_file_name = $zip_name . '.zip';
        $zip_file_path = $upload_dir['basedir'] . '/tpak-exports/' . $zip_file_name;
        
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file_path, ZipArchive::CREATE) === TRUE) {
            foreach ($file_urls as $url) {
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
                if (file_exists($file_path)) {
                    $zip->addFile($file_path, basename($file_path));
                }
            }
            $zip->close();
            
            return $upload_dir['baseurl'] . '/tpak-exports/' . $zip_file_name;
        }
        
        throw new Exception('ไม่สามารถสร้างไฟล์ ZIP ได้');
    }
    
    /**
     * ดึงข้อมูลแบบสอบถามสมบูรณ์
     */
    private function get_complete_survey_data($response_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tpak_survey_responses';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE response_id = %s",
            $response_id
        ), ARRAY_A);
        
        if ($data) {
            // เพิ่มข้อมูล survey structure
            $response_data = json_decode($data['response_data'], true);
            $survey_id = $data['survey_id'];
            
            // ดึง LSS structure
            $lss_structure = get_option('tpak_lss_structure_' . $survey_id, false);
            if ($lss_structure) {
                $data['survey_structure'] = $lss_structure;
            }
            
            // ดึง audit trail
            $data['audit_trail'] = $this->get_audit_trail($response_id);
            
            return $data;
        }
        
        return null;
    }
    
    /**
     * ดึง audit trail
     */
    private function get_audit_trail($response_id) {
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_survey_audit';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $audit_table 
             WHERE response_id = %s 
             ORDER BY created_at DESC",
            $response_id
        ), ARRAY_A);
    }
    
    /**
     * สร้างเนื้อหา PDF
     */
    private function generate_pdf_content($survey_data, $options = array()) {
        $html = '<style>
            body { font-family: "TH Sarabun New", sans-serif; }
            .header { text-align: center; margin-bottom: 20px; }
            .question { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; }
            .question-title { font-weight: bold; color: #333; }
            .answer { color: #666; margin-top: 5px; }
            .modified { background-color: #fff3cd; }
        </style>';
        
        $html .= '<div class="header">';
        $html .= '<h1>รายงานแบบสอบถาม</h1>';
        $html .= '<p>Response ID: ' . esc_html($survey_data['response_id']) . '</p>';
        $html .= '<p>วันที่สร้าง: ' . esc_html($survey_data['created_at']) . '</p>';
        $html .= '</div>';
        
        $response_data = json_decode($survey_data['response_data'], true);
        $responses = $response_data['responses'] ?? array();
        $modifications = $response_data['modifications'] ?? array();
        
        $html .= '<div class="content">';
        
        foreach ($responses as $field => $value) {
            $question_text = $this->get_question_text($field, $survey_data);
            $formatted_value = $this->format_answer_for_export($value);
            $is_modified = $this->is_field_modified($field, $modifications);
            
            $html .= '<div class="question' . ($is_modified ? ' modified' : '') . '">';
            $html .= '<div class="question-title">' . esc_html($question_text) . '</div>';
            $html .= '<div class="answer">' . esc_html($formatted_value) . '</div>';
            
            if ($is_modified) {
                $html .= '<div class="modification-note">* ข้อมูลนี้ได้รับการแก้ไข</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // เพิ่มประวัติการแก้ไขถ้าต้องการ
        if (isset($options['include_history']) && $options['include_history'] && !empty($survey_data['audit_trail'])) {
            $html .= '<div class="history">';
            $html .= '<h2>ประวัติการแก้ไข</h2>';
            
            foreach ($survey_data['audit_trail'] as $entry) {
                $html .= '<div class="history-item">';
                $html .= '<strong>' . esc_html($entry['created_at']) . '</strong> - ';
                $html .= esc_html($entry['action']) . ' โดย ' . esc_html($entry['user_name']);
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * เพิ่ม analysis sheet ใน Excel
     */
    private function add_analysis_sheet($spreadsheet, $survey_data) {
        $analysisSheet = $spreadsheet->createSheet();
        $analysisSheet->setTitle('Analysis');
        
        // TODO: เพิ่มการวิเคราะห์ข้อมูล
        $analysisSheet->setCellValue('A1', 'การวิเคราะห์ข้อมูล');
        $analysisSheet->setCellValue('A2', 'จำนวนคำถามทั้งหมด: ');
        $analysisSheet->setCellValue('A3', 'จำนวนคำตอบที่กรอก: ');
        $analysisSheet->setCellValue('A4', 'เปอร์เซ็นต์ความสมบูรณ์: ');
    }
    
    /**
     * Personalize email message
     */
    private function personalize_email_message($message, $email, $survey_data) {
        $user = get_user_by('email', $email);
        $display_name = $user ? $user->display_name : $email;
        
        $placeholders = array(
            '{{name}}' => $display_name,
            '{{response_id}}' => $survey_data['response_id'],
            '{{survey_id}}' => $survey_data['survey_id'],
            '{{date}}' => date('Y-m-d H:i:s')
        );
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $message);
    }
    
    /**
     * ดึงข้อความคำถาม
     */
    private function get_question_text($field, $survey_data) {
        // ลองดึงจาก survey structure
        if (isset($survey_data['survey_structure'])) {
            $structure = $survey_data['survey_structure'];
            $qid = str_replace('question_', '', $field);
            
            if (isset($structure['question_texts'][$qid])) {
                return $structure['question_texts'][$qid]['question'];
            }
        }
        
        return $field;
    }
    
    /**
     * Format คำตอบสำหรับ export
     */
    private function format_answer_for_export($value) {
        if (is_array($value)) {
            return implode(', ', $value);
        }
        
        // ใช้ Question Dictionary
        require_once TPAK_DQ_SYSTEM_PLUGIN_DIR . 'includes/class-question-dictionary.php';
        $dictionary = TPAK_Question_Dictionary::getInstance();
        
        $formatted = $dictionary->getAnswerText($value);
        return $formatted !== $value ? $formatted : $value;
    }
    
    /**
     * ดึงประเภทคำถาม
     */
    private function get_question_type($field) {
        // TODO: ดึงจาก survey structure
        return 'text';
    }
    
    /**
     * ตรวจสอบว่าฟิลด์ถูกแก้ไขหรือไม่
     */
    private function is_field_modified($field, $modifications) {
        foreach ($modifications as $mod) {
            if ($mod['field'] === $field) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * สร้าง merged export
     */
    private function generate_merged_export($merged_data, $format) {
        switch ($format) {
            case 'excel':
                return $this->generate_merged_excel($merged_data);
            case 'json':
                return $this->generate_merged_json($merged_data);
            default:
                throw new Exception('รูปแบบ Merged Export ไม่ถูกต้อง');
        }
    }
    
    /**
     * สร้าง merged Excel
     */
    private function generate_merged_excel($merged_data) {
        // TODO: Implement merged Excel generation
        return $this->generate_excel($merged_data[0]); // ตัวอย่าง
    }
    
    /**
     * สร้าง merged JSON
     */
    private function generate_merged_json($merged_data) {
        $upload_dir = wp_upload_dir();
        $file_name = 'merged_survey_' . time() . '.json';
        $file_path = $upload_dir['basedir'] . '/tpak-exports/' . $file_name;
        
        wp_mkdir_p($upload_dir['basedir'] . '/tpak-exports/');
        
        file_put_contents($file_path, json_encode($merged_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $upload_dir['baseurl'] . '/tpak-exports/' . $file_name;
    }
    
    /**
     * บันทึก export log
     */
    private function log_export($response_id, $format, $result) {
        global $wpdb;
        $current_user = wp_get_current_user();
        
        $wpdb->insert(
            $wpdb->prefix . 'tpak_survey_audit',
            array(
                'response_id' => $response_id,
                'action' => 'exported',
                'action_data' => json_encode(array(
                    'format' => $format,
                    'result' => $result
                )),
                'user_id' => $current_user->ID,
                'user_name' => $current_user->display_name,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Schedule export
     */
    public function schedule_export($response_id, $format, $schedule_time) {
        wp_schedule_single_event(
            $schedule_time,
            'tpak_scheduled_export',
            array($response_id, $format)
        );
    }
    
    /**
     * Handle scheduled export
     */
    public function handle_scheduled_export($response_id, $format) {
        $survey_data = $this->get_complete_survey_data($response_id);
        
        if ($survey_data) {
            switch ($format) {
                case 'pdf':
                    $this->generate_pdf($survey_data);
                    break;
                case 'excel':
                    $this->generate_excel($survey_data);
                    break;
                case 'json':
                    $this->generate_json($survey_data);
                    break;
            }
            
            // ส่ง notification
            $this->send_export_notification($response_id, $format);
        }
    }
    
    /**
     * ส่ง export notification
     */
    private function send_export_notification($response_id, $format) {
        // TODO: ส่ง email แจ้งเตือนเมื่อ export เสร็จ
    }
}