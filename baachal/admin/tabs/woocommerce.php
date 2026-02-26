<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<h2>WooCommerce Integration</h2>
<p>Configure how the chatbot interacts with your WooCommerce store.</p>

<?php if (class_exists('WooCommerce')): ?>
    <div class="notice notice-info inline" style="margin: 15px 0;">
        <p><strong>WooCommerce Detected!</strong> The chatbot can now help customers find products from your store. <?php 
        $product_count = wp_count_posts('product')->publish;
        echo esc_html("Found {$product_count} published products.");
        ?></p>
    </div>
<?php else: ?>
    <div class="notice notice-warning inline" style="margin: 15px 0;">
        <p><strong>WooCommerce Not Detected!</strong> Install and activate WooCommerce to enable product integration features.</p>
    </div>
<?php endif; ?>

<table class="form-table" role="presentation">
    <tr>
        <th scope="row">
            <label for="baachal_woocommerce_integration">Enable WooCommerce Integration</label>
        </th>
        <td>
            <input type="checkbox" id="baachal_woocommerce_integration" name="baachal_woocommerce_integration" value="1" <?php checked($woocommerce_integration, '1'); ?> <?php echo !class_exists('WooCommerce') ? 'disabled' : ''; ?> />
            <p class="description">Allow the chatbot to help customers find products from your store</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_message_persistence">Message Persistence</label>
        </th>
        <td>
            <input type="checkbox" id="baachal_message_persistence" name="baachal_message_persistence" value="1" <?php checked($message_persistence, '1'); ?> />
            <p class="description">Save chat messages in browser so they persist across page reloads</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_show_clear_history">Show Clear History Button</label>
        </th>
        <td>
            <input type="checkbox" id="baachal_show_clear_history" name="baachal_show_clear_history" value="1" <?php checked($show_clear_history, '1'); ?> />
            <p class="description">Show the clear chat history button in the chatbot header</p>
        </td>
    </tr>
</table>