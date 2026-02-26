<?php
/**
 * Admin UI: Import PDF/Data
 * File: contexto-pdf-template.php
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined('AICHAT_PDF_PAGE_SLUG') ) define('AICHAT_PDF_PAGE_SLUG', 'aichat-contexto-pdf');
if ( ! defined('AICHAT_PLUGIN_VER') )   define('AICHAT_PLUGIN_VER', '1.0.0');

add_action('admin_enqueue_scripts', function( $hook ){
    $page = '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing param; no state change.
    if ( isset( $_GET['page'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing param; no state change.
        $page = sanitize_key( wp_unslash( $_GET['page'] ) );
    }
    if ( $page !== AICHAT_PDF_PAGE_SLUG ) { return; }

    // === CSS: corrige ancho ===
    $css = "
    .wrap.aichat-container{max-width:none;padding-left:0;padding-right:0}
    /* Full-bleed para ocupar todo el ancho visible del área de contenido */
    .aichat-fullwidth{margin-left:-20px;margin-right:-20px}
    @media (max-width:782px){.aichat-fullwidth{margin-left:-12px;margin-right:-12px}}

    /* Nuestras cards a 100%: anulamos el max-width del admin */
    .aichat-container .aichat-card-full{width:100%!important;max-width:none!important}
    .aichat-container .aichat-card-full.card{width:100%!important;max-width:none!important}

    .aichat-drop{border:2px dashed #c3c4c7;border-radius:10px;padding:28px;text-align:center;background:#fafafa;transition:.15s;cursor:pointer}
    .aichat-drop.dragover{background:#eef7ff;border-color:#0d6efd}
    .aichat-drop .big-icon{font-size:2.2rem}
    .aichat-drop-inner{pointer-events:none} /* Evitar interferencia con eventos de click */
    .aichat-drop-inner .button{pointer-events:auto} /* Permitir clicks en botones */

    .table-responsive{width:100%!important}
    .table{width:100%!important}

    .aichat-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,'Liberation Mono','Courier New',monospace;font-size:12px}

    /* Capabilities (abajo y discretas) */
    .aichat-cap-wrap{margin-top:18px}
    .aichat-cap-grid{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .aichat-cap-card{border:1px solid #eef0f2;border-radius:10px;background:#fff}
    .aichat-cap-card .card-body{display:flex;align-items:center;gap:10px}
    .aichat-cap-icon{font-size:1.2rem}
    .aichat-ok{color:#198754}.aichat-no{color:#dc3545}
    .aichat-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:2px 8px;font-size:11px}
    .aichat-badge.ok{background:#e8f5ee;color:#198754}
    .aichat-badge.no{background:#fdecea;color:#dc3545}
    .aichat-cap-muted{color:#6c757d;font-size:12px}
    ";
    wp_add_inline_style('wp-admin', $css);

    // Ensure wp-i18n is in deps for translation support
    $deps = array('jquery', 'wp-i18n');
    wp_enqueue_script(
        'aichat-contexto-pdf',
        plugin_dir_url(__FILE__) . '../assets/js/contexto-pdf.js',
        $deps,
        AICHAT_PLUGIN_VER,
        true
    );
    wp_set_script_translations('aichat-contexto-pdf', 'axiachat-ai', plugin_dir_path(__FILE__) . '../languages');

    $caps = aichat_pdf_detect_capabilities();

    wp_localize_script('aichat-contexto-pdf','aichat_pdf_ajax',array(
        'ajax_url'=>admin_url('admin-ajax.php'),
        'nonce'=>wp_create_nonce('aichat_pdf_nonce'),
        'max_mb'=>apply_filters('aichat_pdf_max_mb',20),
        'allowed_mimes'=>array('application/pdf','text/plain'),
        'allowed_exts'=>array('pdf','txt'),
        'caps'=>$caps,
        'i18n'=>array(
            'drop_here'=>__('Drop PDF/TXT files here or click to select','axiachat-ai'),
            'uploading'=>__('Uploading…','axiachat-ai'),
            'parsing'=>__('Parsing…','axiachat-ai'),
            'chunking'=>__('Chunking…','axiachat-ai'),
            'ready'=>__('Ready','axiachat-ai'),
            'error'=>__('Error','axiachat-ai'),
            'delete_q'=>__('Delete this file and its chunks?','axiachat-ai'),
            'reparse_q'=>__('Re-parse this file?','axiachat-ai'),
            'add_ctx_q'=>__('Add all chunks to the current context selection?','axiachat-ai'),
        ),
    ));
});

function aichat_pdf_detect_capabilities(){
    $has_shell = function_exists('shell_exec');
    $pdftotext = $has_shell ? aichat_bin_exists('pdftotext') : false;
    $pdftoppm  = $has_shell ? aichat_bin_exists('pdftoppm')  : false;
    $tesseract = $has_shell ? aichat_bin_exists('tesseract') : false;
    $php_fallback = class_exists('\Smalot\PdfParser\Parser');
    return array(
        'shell_exec'=>$has_shell,'pdftotext'=>$pdftotext,
        'pdftoppm'=>$pdftoppm,'tesseract'=>$tesseract,'php_fallback'=>$php_fallback,
    );
}
function aichat_bin_exists($bin){
    if(!function_exists('shell_exec')) return false;
    $out=@shell_exec('command -v '.escapeshellcmd($bin).' 2>/dev/null');
    if(is_string($out) && strlen(trim($out))>0) return true;
    $out=@shell_exec('where '.escapeshellcmd($bin).' 2>NUL');
    return (is_string($out) && strlen(trim($out))>0);
}

function aichat_contexto_pdf_page(){
    if(!current_user_can('manage_options')) return;
    $caps=aichat_pdf_detect_capabilities();
    $max_mb=apply_filters('aichat_pdf_max_mb',20);
    ?>
    <div class="wrap aichat-container">

        <!-- Bootstrap Icons (local) -->
        <?php /* Eliminado el enlace CDN; ya se encola via aichat_admin_enqueue_scripts */ ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url(admin_url('admin.php?page=aichat-contexto-settings')); ?>">
                    <i class="bi bi-gear me-1"></i><?php echo esc_html__('Context','axiachat-ai'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url(admin_url('admin.php?page=aichat-contexto-create')); ?>">
                    <i class="bi bi-plus-circle me-1"></i><?php echo esc_html__('Add New','axiachat-ai'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button">
                    <i class="bi bi-filetype-pdf me-1"></i><?php echo esc_html__('Import PDF/Data','axiachat-ai'); ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo esc_url( admin_url( 'admin.php?page=aichat-contexto-modify' ) ); ?>">
                    <i class="bi bi-pencil-square me-1"></i><?php echo esc_html__('Modify Context','axiachat-ai'); ?>
                </a>
            </li>
        </ul>

        <!-- Header -->
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-filetype-pdf fs-3 me-2 text-danger"></i>
            <h1 class="m-0"><?php echo esc_html__('Import PDF/Data','axiachat-ai'); ?></h1>
        </div>
        <p class="text-muted">
            <?php echo esc_html__('Upload PDF or TXT files, extract and split them into chunks. Chunks are saved as private CPT posts to reuse the current indexing pipeline.','axiachat-ai'); ?>
        </p>

        <!-- Dropzone -->
        <div class="card shadow-sm mb-4 aichat-card-full">
            <div class="card-body">
                <div id="aichat-pdf-dropzone" class="aichat-drop" tabindex="0">
                    <div class="aichat-drop-inner">
                        <div class="mb-2"><i class="bi bi-cloud-arrow-up big-icon text-primary"></i></div>
                        <p class="mb-1 fw-semibold"><?php echo esc_html__('Drop PDF/TXT files here or click to select','axiachat-ai'); ?></p>
                        <p class="aichat-mono text-muted mb-3">
                            <?php
                            /* translators: %d: maximum file size in megabytes */
                            echo sprintf( esc_html__( 'Allowed: .pdf, .txt — Max %d MB / file', 'axiachat-ai' ), intval( $max_mb ) );
                            ?>
                        </p>
                        <input id="aichat-file-input" type="file" accept=".pdf,.txt" multiple style="display:none;">
                        <button type="button" class="button button-secondary btn btn-outline-primary" id="aichat-file-select" data-no-propagate="true">
                            <i class="bi bi-folder2-open me-1"></i><?php echo esc_html__('Select files','axiachat-ai'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Your Uploads full width -->
        <div class="aichat-fullwidth">
            <div class="card shadow-sm aichat-card-full">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="card-title m-0">
                            <i class="bi bi-collection me-2 text-secondary"></i><?php echo esc_html__('Your Uploads','axiachat-ai'); ?>
                        </h5>
                        <div class="d-flex gap-2">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="aichat-upload-search" class="form-control" placeholder="<?php echo esc_attr__('Search by filename…','axiachat-ai'); ?>">
                            </div>
                            <button type="button" class="button btn btn-outline-secondary" id="aichat-refresh-uploads">
                                <i class="bi bi-arrow-clockwise me-1"></i><?php echo esc_html__('Refresh','axiachat-ai'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle wp-list-table widefat striped">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="bi bi-file-earmark-text me-1"></i><?php echo esc_html__('File','axiachat-ai'); ?></th>
                                    <th><i class="bi bi-type me-1"></i><?php echo esc_html__('Type','axiachat-ai'); ?></th>
                                    <th><i class="bi bi-hdd me-1"></i><?php echo esc_html__('Size','axiachat-ai'); ?></th>
                                    <th><i class="bi bi-bar-chart-line me-1"></i><?php echo esc_html__('Status','axiachat-ai'); ?></th>
                                    <th><i class="bi bi-diagram-3 me-1"></i><?php echo esc_html__('Chunks','axiachat-ai'); ?></th>
                                    <th><i class="bi bi-clock-history me-1"></i><?php echo esc_html__('Updated','axiachat-ai'); ?></th>
                                    <th><i class="bi bi-tools me-1"></i><?php echo esc_html__('Actions','axiachat-ai'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="aichat-upload-list">
                                <tr><td colspan="7" class="text-muted"><?php echo esc_html__('No files yet. Upload some to get started.','axiachat-ai'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="aichat-upload-pagination" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Capacidades técnicas (abajo, discretas) -->
        <div class="aichat-cap-wrap">
            <div class="text-muted aichat-cap-muted mb-2">
                <i class="bi bi-info-circle me-1"></i><?php echo esc_html__('Processing capabilities (technical details)','axiachat-ai'); ?>
            </div>
            <div class="aichat-cap-grid">
                <div class="card aichat-cap-card"><div class="card-body">
                    <i class="bi bi-file-earmark-text aichat-cap-icon <?php echo $caps['pdftotext']?'aichat-ok':'aichat-no'; ?>"></i>
                    <div><div class="fw-semibold">pdftotext</div>
                        <div class="aichat-badge <?php echo $caps['pdftotext']?'ok':'no'; ?>">
                            <i class="bi <?php echo $caps['pdftotext']?'bi-check-circle':'bi-x-circle'; ?>"></i>
                            <?php echo $caps['pdftotext']?esc_html__('Available','axiachat-ai'):esc_html__('Unavailable','axiachat-ai'); ?>
                        </div>
                    </div>
                </div></div>
                <div class="card aichat-cap-card"><div class="card-body">
                    <i class="bi bi-images aichat-cap-icon <?php echo $caps['pdftoppm']?'aichat-ok':'aichat-no'; ?>"></i>
                    <div><div class="fw-semibold">pdftoppm</div>
                        <div class="aichat-badge <?php echo $caps['pdftoppm']?'ok':'no'; ?>">
                            <i class="bi <?php echo $caps['pdftoppm']?'bi-check-circle':'bi-x-circle'; ?>"></i>
                            <?php echo $caps['pdftoppm']?esc_html__('Available','axiachat-ai'):esc_html__('Unavailable','axiachat-ai'); ?>
                        </div>
                    </div>
                </div></div>
                <div class="card aichat-cap-card"><div class="card-body">
                    <i class="bi bi-eye aichat-cap-icon <?php echo $caps['tesseract']?'aichat-ok':'aichat-no'; ?>"></i>
                    <div><div class="fw-semibold">Tesseract OCR</div>
                        <div class="aichat-badge <?php echo $caps['tesseract']?'ok':'no'; ?>">
                            <i class="bi <?php echo $caps['tesseract']?'bi-check-circle':'bi-x-circle'; ?>"></i>
                            <?php echo $caps['tesseract']?esc_html__('Available','axiachat-ai'):esc_html__('Unavailable','axiachat-ai'); ?>
                        </div>
                    </div>
                </div></div>
                <div class="card aichat-cap-card"><div class="card-body">
                    <i class="bi bi-gear aichat-cap-icon <?php echo $caps['php_fallback']?'aichat-ok':'aichat-no'; ?>"></i>
                    <div><div class="fw-semibold">PHP Fallback</div>
                        <div class="aichat-badge <?php echo $caps['php_fallback']?'ok':'no'; ?>">
                            <i class="bi <?php echo $caps['php_fallback']?'bi-check-circle':'bi-x-circle'; ?>"></i>
                            <?php echo $caps['php_fallback']?esc_html__('Enabled','axiachat-ai'):esc_html__('Disabled','axiachat-ai'); ?>
                        </div>
                    </div>
                </div></div>
            </div>
        </div>

        <div id="aichat-pdf-modals" style="display:none"></div>
    </div>
    <?php
}
