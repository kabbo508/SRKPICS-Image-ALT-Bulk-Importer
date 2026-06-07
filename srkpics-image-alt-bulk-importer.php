<?php
/**
 * Plugin Name: SRKPICS Image ALT Bulk Importer
 * Description: Bulk import Media Library image ALT text from CSV or XLSX files with live AJAX batch processing.
 * Version: 2.2.0
 * Author: SRKPICS
 * Author URI: https://sumonrahmankabbo.com/
 * License: GPLv2 or later
 * Text Domain: srkpics-image-alt-importer
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SRKPICS_Image_ALT_Bulk_Importer {
    const VERSION = '2.2.0';
    const MENU_SLUG = 'srkpics-image-alt-importer';
    const OPTION_KEY = 'srkpics_alt_import_jobs';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_srkpics_alt_prepare_import', [$this, 'ajax_prepare_import']);
        add_action('wp_ajax_srkpics_alt_process_batch', [$this, 'ajax_process_batch']);
        add_action('wp_ajax_srkpics_alt_download_failed', [$this, 'download_failed_csv']);
    }

    public function admin_menu() {
        add_menu_page(
            'SRKPICS Image ALT Bulk Importer',
            'Image ALT Importer',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_admin_page'],
            'dashicons-format-image',
            58
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style('srkpics-alt-importer-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', [], null);
        wp_enqueue_script('srkpics-alt-importer-admin', plugin_dir_url(__FILE__) . 'assets/admin.js', ['jquery'], null, true);

        wp_localize_script('srkpics-alt-importer-admin', 'SRKPICSAltImporter', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('srkpics_alt_import_nonce'),
            'batchSize' => 50,
        ]);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap srkpics-alt-wrap">
            <h1>SRKPICS Image ALT Bulk Importer</h1>
            <p>Upload CSV or XLSX files and update Media Library image ALT text with live AJAX processing.</p>

            <div class="srkpics-card">
                <h2>Required File Columns</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Required</th>
                            <th>Accepted Names</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>Image URL</code></td>
                            <td>Yes</td>
                            <td>Image URL, Image, URL, Image Link</td>
                            <td>https://example.com/wp-content/uploads/image.jpg</td>
                        </tr>
                        <tr>
                            <td><code>Suggested ALT Tag</code></td>
                            <td>Yes</td>
                            <td>Suggested ALT Tag, ALT Tag, ALT Text, Image ALT</td>
                            <td>Elegant red dress for women</td>
                        </tr>
                        <tr>
                            <td><code>Source URLs</code></td>
                            <td>No</td>
                            <td>Source URLs, Source URL, Page URL</td>
                            <td>Page where the image appears</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="srkpics-card">
                <h2>Upload Import File</h2>
                <form id="srkpics-alt-upload-form" enctype="multipart/form-data" method="post">
                    <input type="file" id="srkpics_alt_file" name="import_file" accept=".csv,.xlsx" required>
                    <label class="srkpics-check"><input type="checkbox" id="skip_existing" name="skip_existing" value="1"> Skip images that already have ALT text</label>
                    <label class="srkpics-check"><input type="checkbox" id="dry_run" name="dry_run" value="1"> Dry run only, test matching without updating ALT tags</label>
                    <button type="submit" class="button button-primary button-large" id="srkpics-alt-upload-btn">Upload and Prepare Import</button>
                </form>
                <div id="srkpics-upload-message"></div>
            </div>

            <div class="srkpics-card srkpics-hidden" id="srkpics-progress-card">
                <h2>Live Import Progress</h2>

                <div class="srkpics-stats">
                    <div><strong id="stat-total">0</strong><span>Total Rows</span></div>
                    <div><strong id="stat-processed">0</strong><span>Processed</span></div>
                    <div><strong id="stat-updated">0</strong><span>Updated</span></div>
                    <div><strong id="stat-skipped">0</strong><span>Skipped</span></div>
                    <div><strong id="stat-failed">0</strong><span>Failed</span></div>
                </div>

                <div class="srkpics-progress">
                    <div id="srkpics-progress-bar">0%</div>
                </div>

                <p>
                    <button type="button" class="button button-primary" id="srkpics-start-import">Start Live Import</button>
                    <a href="#" class="button srkpics-hidden" id="srkpics-download-failed">Download Failed Rows</a>
                </p>

                <h3>Live Log</h3>
                <div id="srkpics-live-log"></div>
            </div>
        </div>
        <?php
    }

    private function verify_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        check_ajax_referer('srkpics_alt_import_nonce', 'nonce');
    }

    public function ajax_prepare_import() {
        $this->verify_ajax();

        if (empty($_FILES['import_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'Please choose a CSV or XLSX file first.']);
        }

        $file = $_FILES['import_file'];
        $filename = sanitize_file_name($file['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            wp_send_json_error(['message' => 'Invalid file type. Please upload CSV or XLSX only.']);
        }

        $rows = ($ext === 'csv') ? $this->parse_csv($file['tmp_name']) : $this->parse_xlsx($file['tmp_name']);

        if (is_wp_error($rows)) {
            wp_send_json_error(['message' => $rows->get_error_message()]);
        }

        if (empty($rows)) {
            wp_send_json_error(['message' => 'No valid rows found. Please check the file columns and rows.']);
        }

        $job_id = wp_generate_uuid4();
        $job = [
            'id' => $job_id,
            'created' => current_time('mysql'),
            'rows' => $rows,
            'total' => count($rows),
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'failed_rows' => [],
            'skip_existing' => !empty($_POST['skip_existing']),
            'dry_run' => !empty($_POST['dry_run']),
        ];

        $jobs = get_option(self::OPTION_KEY, []);
        if (!is_array($jobs)) {
            $jobs = [];
        }

        $jobs[$job_id] = $job;
        update_option(self::OPTION_KEY, $jobs, false);

        wp_send_json_success([
            'message' => 'File prepared successfully. Found ' . count($rows) . ' valid rows.',
            'job_id' => $job_id,
            'total' => count($rows),
        ]);
    }

    public function ajax_process_batch() {
        $this->verify_ajax();

        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        $offset = isset($_POST['offset']) ? max(0, absint($_POST['offset'])) : 0;
        $batch_size = isset($_POST['batch_size']) ? max(1, min(100, absint($_POST['batch_size']))) : 50;

        $jobs = get_option(self::OPTION_KEY, []);
        if (empty($jobs[$job_id])) {
            wp_send_json_error(['message' => 'Import job not found. Please upload the file again.']);
        }

        $job = $jobs[$job_id];
        $rows = $job['rows'];
        $total = (int) $job['total'];
        $batch = array_slice($rows, $offset, $batch_size);
        $logs = [];

        foreach ($batch as $row) {
            $image_url = esc_url_raw($row['image_url']);
            $alt_text = sanitize_text_field($row['alt_text']);
            $attachment_id = $this->find_attachment_id_by_url($image_url);

            if (!$attachment_id) {
                $job['failed']++;
                $job['failed_rows'][] = [
                    'Image URL' => $image_url,
                    'Suggested ALT Tag' => $alt_text,
                    'Source URLs' => $row['source_url'],
                    'Reason' => 'Image not found in Media Library',
                ];
                $logs[] = 'FAILED: Image not found, ' . $image_url;
                continue;
            }

            $current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

            if (!empty($job['skip_existing']) && trim((string) $current_alt) !== '') {
                $job['skipped']++;
                $logs[] = 'SKIPPED: Existing ALT found for attachment #' . $attachment_id;
                continue;
            }

            if (trim($alt_text) === '') {
                $job['skipped']++;
                $logs[] = 'SKIPPED: Empty ALT text for ' . $image_url;
                continue;
            }

            if (empty($job['dry_run'])) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
            }

            $job['updated']++;
            $logs[] = (empty($job['dry_run']) ? 'UPDATED' : 'DRY RUN MATCH') . ': Attachment #' . $attachment_id . ' ALT = ' . $alt_text;
        }

        $new_offset = $offset + count($batch);
        $job['processed'] = min($new_offset, $total);
        $done = $new_offset >= $total;

        $jobs[$job_id] = $job;
        update_option(self::OPTION_KEY, $jobs, false);

        wp_send_json_success([
            'done' => $done,
            'offset' => $new_offset,
            'total' => $total,
            'processed' => $job['processed'],
            'updated' => $job['updated'],
            'skipped' => $job['skipped'],
            'failed' => $job['failed'],
            'logs' => $logs,
            'has_failed' => !empty($job['failed_rows']),
        ]);
    }

    public function download_failed_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        $job_id = isset($_GET['job_id']) ? sanitize_text_field($_GET['job_id']) : '';
        $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';

        if (!wp_verify_nonce($nonce, 'srkpics_alt_import_nonce')) {
            wp_die('Invalid nonce.');
        }

        $jobs = get_option(self::OPTION_KEY, []);
        if (empty($jobs[$job_id]['failed_rows'])) {
            wp_die('No failed rows found.');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=failed-image-alt-import.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Image URL', 'Suggested ALT Tag', 'Source URLs', 'Reason']);

        foreach ($jobs[$job_id]['failed_rows'] as $row) {
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }

    private function parse_csv($path) {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return new WP_Error('csv_open_failed', 'Could not open CSV file.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new WP_Error('csv_no_header', 'CSV header row not found.');
        }

        $header = $this->strip_bom_from_header($header);
        $map = $this->map_headers($header);

        if (!isset($map['image_url']) || !isset($map['alt_text'])) {
            fclose($handle);
            return new WP_Error('missing_columns', 'Required columns missing. Need Image URL and Suggested ALT Tag.');
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = $this->normalize_row($data, $map);
            if ($row) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        return $rows;
    }

    private function parse_xlsx($path) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('zip_missing', 'Your server needs PHP ZipArchive enabled to read XLSX files. Please upload CSV instead or enable ZipArchive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return new WP_Error('xlsx_open_failed', 'Could not open XLSX file. Please save as CSV UTF-8 and try again.');
        }

        $shared_strings = [];
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared_xml !== false) {
            $xml = simplexml_load_string($shared_xml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $text = '';
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                    }
                    $shared_strings[] = $text;
                }
            }
        }

        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet_xml === false) {
            return new WP_Error('xlsx_sheet_missing', 'Could not read first worksheet from XLSX.');
        }

        $xml = simplexml_load_string($sheet_xml);
        if (!$xml) {
            return new WP_Error('xlsx_parse_failed', 'Could not parse XLSX worksheet.');
        }

        $table = [];
        foreach ($xml->sheetData->row as $row) {
            $row_values = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $col = preg_replace('/\d+/', '', $ref);
                $index = $this->column_index($col);
                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's' && isset($shared_strings[(int) $value])) {
                    $value = $shared_strings[(int) $value];
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                }

                $row_values[$index] = $value;
            }

            if (!empty($row_values)) {
                ksort($row_values);
                $max = max(array_keys($row_values));
                $normalized = [];
                for ($i = 0; $i <= $max; $i++) {
                    $normalized[] = $row_values[$i] ?? '';
                }
                $table[] = $normalized;
            }
        }

        if (empty($table)) {
            return new WP_Error('xlsx_empty', 'XLSX file is empty.');
        }

        $header = $this->strip_bom_from_header(array_shift($table));
        $map = $this->map_headers($header);

        if (!isset($map['image_url']) || !isset($map['alt_text'])) {
            return new WP_Error('missing_columns', 'Required columns missing. Need Image URL and Suggested ALT Tag.');
        }

        $rows = [];
        foreach ($table as $data) {
            $row = $this->normalize_row($data, $map);
            if ($row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function strip_bom_from_header($headers) {
        foreach ($headers as $index => $header) {
            $headers[$index] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        }
        return $headers;
    }

    private function map_headers($headers) {
        $map = [];

        foreach ($headers as $index => $header) {
            $key = strtolower(trim((string) $header));
            $key = preg_replace('/\x{FEFF}/u', '', $key);
            $key = preg_replace('/\s+/', ' ', $key);

            if (in_array($key, ['image url', 'image', 'url', 'image link'], true)) {
                $map['image_url'] = $index;
            }

            if (in_array($key, ['suggested alt tag', 'alt tag', 'alt text', 'image alt', 'alt'], true)) {
                $map['alt_text'] = $index;
            }

            if (in_array($key, ['source urls', 'source url', 'page url', 'page'], true)) {
                $map['source_url'] = $index;
            }
        }

        return $map;
    }

    private function normalize_row($data, $map) {
        $image_url = isset($data[$map['image_url']]) ? trim((string) $data[$map['image_url']]) : '';
        $alt_text = isset($data[$map['alt_text']]) ? trim((string) $data[$map['alt_text']]) : '';
        $source_url = isset($map['source_url'], $data[$map['source_url']]) ? trim((string) $data[$map['source_url']]) : '';

        if ($image_url === '') {
            return null;
        }

        return [
            'image_url' => $image_url,
            'alt_text' => $alt_text,
            'source_url' => $source_url,
        ];
    }

    private function column_index($letters) {
        $letters = strtoupper($letters);
        $num = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $num = $num * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $num - 1;
    }

    private function find_attachment_id_by_url($url) {
        global $wpdb;

        $url = trim($url);
        if ($url === '') {
            return 0;
        }

        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            return (int) $attachment_id;
        }

        $upload_dir = wp_upload_dir();
        $baseurl = trailingslashit($upload_dir['baseurl']);

        $url_path = parse_url($url, PHP_URL_PATH);
        $filename = $url_path ? basename($url_path) : basename($url);

        $possible_filenames = [$filename];
        $original_filename = preg_replace('/-\d+x\d+(?=\.[a-zA-Z0-9]+$)/', '', $filename);
        if ($original_filename && $original_filename !== $filename) {
            $possible_filenames[] = $original_filename;
        }

        $relative_path = str_replace($baseurl, '', $url);
        $relative_path = preg_replace('/-\d+x\d+(?=\.[a-zA-Z0-9]+$)/', '', $relative_path);

        if ($relative_path && $relative_path !== $url) {
            $found = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
                    ltrim($relative_path, '/')
                )
            );
            if ($found) {
                return (int) $found;
            }
        }

        foreach ($possible_filenames as $file) {
            if (!$file) {
                continue;
            }

            $found = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
                    '%' . $wpdb->esc_like($file)
                )
            );

            if ($found) {
                return (int) $found;
            }

            $found = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1",
                    '%' . $wpdb->esc_like($file) . '%'
                )
            );

            if ($found) {
                return (int) $found;
            }
        }

        return 0;
    }
}

new SRKPICS_Image_ALT_Bulk_Importer();
