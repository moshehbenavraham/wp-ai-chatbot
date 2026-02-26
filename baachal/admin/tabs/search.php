<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<h2>Product Search Settings</h2>
<p>Configure how the chatbot analyzes your product catalog for better search results.</p>

<table class="form-table" role="presentation">
    <tr>
        <th scope="row">
            <label for="baachal_max_terms">Maximum Terms</label>
        </th>
        <td>
            <input type="number" id="baachal_max_terms" name="baachal_max_terms" value="<?php echo esc_attr($max_terms); ?>" min="10" max="200" />
            <p class="description">Maximum number of important terms to extract from your products (10-200)</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_min_term_length">Minimum Term Length</label>
        </th>
        <td>
            <input type="number" id="baachal_min_term_length" name="baachal_min_term_length" value="<?php echo esc_attr($min_term_length); ?>" min="2" max="10" />
            <p class="description">Minimum length of terms to consider important (2-10 characters)</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_cache_duration">Cache Duration (seconds)</label>
        </th>
        <td>
            <input type="number" id="baachal_cache_duration" name="baachal_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="3600" max="604800" />
            <p class="description">How long to cache extracted terms (3600 = 1 hour, 86400 = 1 day, 604800 = 1 week)</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_exclude_terms">Exclude Terms</label>
        </th>
        <td>
            <textarea id="baachal_exclude_terms" name="baachal_exclude_terms" rows="3" cols="50"><?php echo esc_textarea($exclude_terms_string); ?></textarea>
            <p class="description">Comma-separated list of words to ignore when extracting terms (e.g., "the,and,or,size,color")</p>
        </td>
    </tr>
</table>

<h3>Cache Management</h3>
<p>Clear cached product terms to force the system to re-analyze your products.</p>

<?php
// Check if we should clear cache
if (isset($_POST['action']) && $_POST['action'] === 'clear_cache') {
    check_admin_referer('clear_dynamic_terms_cache');
    delete_transient('baachal_dynamic_terms');
    echo '<div class="notice notice-success"><p>Product terms cache cleared successfully!</p></div>';
}
?>

<table class="form-table">
    <tr>
        <th scope="row">Dynamic Product Terms</th>
        <td>
            <?php
            $cached_terms = get_transient('baachal_dynamic_terms');
            if ($cached_terms !== false) {
                echo '<p style="color: green;">✅ Cache active (' . count($cached_terms) . ' terms stored)</p>';
                echo '<details><summary>View cached terms</summary><pre>' . esc_html(implode(', ', array_slice($cached_terms, 0, 20))) . '...</pre></details>';
            } else {
                echo '<p style="color: orange;">⚠️ No cached terms (will be generated on next product search)</p>';
            }
            ?>
            <p>
                <button type="button" id="clear-cache-btn" class="button">Clear Product Terms Cache</button>
            </p>
            <p class="description">This will force the system to re-analyze your products and extract new terms based on current settings.</p>
        </td>
    </tr>
</table>

<h3>How Dynamic Search Works</h3>
<ul>
    <li><strong>Categories:</strong> Extracts terms from product categories and sub-categories</li>
    <li><strong>Attributes:</strong> Analyzes product attributes (color, size, material, etc.)</li>
    <li><strong>Tags:</strong> Uses product tags as search terms</li>
    <li><strong>Title Phrases:</strong> Finds common multi-word phrases from product titles</li>
    <li><strong>Smart Filtering:</strong> Automatically excludes common words that don't help with search</li>
</ul>
