<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<h2>UI Styling</h2>
<p>Customize the appearance and behavior of your chatbot widget.</p>

<table class="form-table" role="presentation">
    <tr>
        <th scope="row">
            <label for="baachal_primary_color">Primary Color</label>
        </th>
        <td>
            <div class="baachal-color-picker-container">
                <div class="baachal-color-picker-input">
                    <input type="color" id="baachal_primary_color" name="baachal_primary_color" value="<?php echo esc_attr($primary_color); ?>" class="baachal-color-input" />
                    <div class="baachal-color-swatch" style="background-color: <?php echo esc_attr($primary_color); ?>"></div>
                    <input type="text" class="baachal-color-hex" value="<?php echo esc_attr($primary_color); ?>" maxlength="7" />
                </div>
                <div class="baachal-color-presets">
                    <span class="baachal-preset-color" data-color="#007cba" style="background-color: #007cba;" title="WordPress Blue"></span>
                    <span class="baachal-preset-color" data-color="#2271b1" style="background-color: #2271b1;" title="Admin Blue"></span>
                    <span class="baachal-preset-color" data-color="#135e96" style="background-color: #135e96;" title="Dark Blue"></span>
                    <span class="baachal-preset-color" data-color="#3858e9" style="background-color: #3858e9;" title="Royal Blue"></span>
                    <span class="baachal-preset-color" data-color="#9b51e0" style="background-color: #9b51e0;" title="Purple"></span>
                    <span class="baachal-preset-color" data-color="#e91e63" style="background-color: #e91e63;" title="Pink"></span>
                    <span class="baachal-preset-color" data-color="#f44336" style="background-color: #f44336;" title="Red"></span>
                    <span class="baachal-preset-color" data-color="#ff9800" style="background-color: #ff9800;" title="Orange"></span>
                    <span class="baachal-preset-color" data-color="#4caf50" style="background-color: #4caf50;" title="Green"></span>
                    <span class="baachal-preset-color" data-color="#00bcd4" style="background-color: #00bcd4;" title="Cyan"></span>
                </div>
                <p class="description">Main color for the chatbot (header, buttons, links)</p>
            </div>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_secondary_color">Secondary Color</label>
        </th>
        <td>
            <div class="baachal-color-picker-container">
                <div class="baachal-color-picker-input">
                    <input type="color" id="baachal_secondary_color" name="baachal_secondary_color" value="<?php echo esc_attr($secondary_color); ?>" class="baachal-color-input" />
                    <div class="baachal-color-swatch" style="background-color: <?php echo esc_attr($secondary_color); ?>"></div>
                    <input type="text" class="baachal-color-hex" value="<?php echo esc_attr($secondary_color); ?>" maxlength="7" />
                </div>
                <div class="baachal-color-presets">
                    <span class="baachal-preset-color" data-color="#f0f0f1" style="background-color: #f0f0f1;" title="Light Gray"></span>
                    <span class="baachal-preset-color" data-color="#ffffff" style="background-color: #ffffff; border: 1px solid #ddd;" title="White"></span>
                    <span class="baachal-preset-color" data-color="#f8f9fa" style="background-color: #f8f9fa;" title="Off White"></span>
                    <span class="baachal-preset-color" data-color="#e9ecef" style="background-color: #e9ecef;" title="Light"></span>
                    <span class="baachal-preset-color" data-color="#dee2e6" style="background-color: #dee2e6;" title="Silver"></span>
                    <span class="baachal-preset-color" data-color="#ced4da" style="background-color: #ced4da;" title="Light Gray"></span>
                    <span class="baachal-preset-color" data-color="#adb5bd" style="background-color: #adb5bd;" title="Gray"></span>
                    <span class="baachal-preset-color" data-color="#6c757d" style="background-color: #6c757d;" title="Medium Gray"></span>
                    <span class="baachal-preset-color" data-color="#495057" style="background-color: #495057;" title="Dark Gray"></span>
                    <span class="baachal-preset-color" data-color="#343a40" style="background-color: #343a40;" title="Charcoal"></span>
                </div>
                <p class="description">Background color for chat messages and secondary elements</p>
            </div>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_position">Position</label>
        </th>
        <td>
            <select id="baachal_position" name="baachal_position">
                <option value="bottom-right" <?php selected($position, 'bottom-right'); ?>>Bottom Right</option>
                <option value="bottom-left" <?php selected($position, 'bottom-left'); ?>>Bottom Left</option>
                <option value="top-right" <?php selected($position, 'top-right'); ?>>Top Right</option>
                <option value="top-left" <?php selected($position, 'top-left'); ?>>Top Left</option>
            </select>
            <p class="description">Where to position the chatbot on your website</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_size">Widget Size</label>
        </th>
        <td>
            <select id="baachal_size" name="baachal_size">
                <option value="small" <?php selected($size, 'small'); ?>>Small (300px width)</option>
                <option value="medium" <?php selected($size, 'medium'); ?>>Medium (350px width)</option>
                <option value="large" <?php selected($size, 'large'); ?>>Large (400px width)</option>
                <option value="extra-large" <?php selected($size, 'extra-large'); ?>>Extra Large (450px width)</option>
            </select>
            <p class="description">Overall size of the chatbot widget</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_chat_height">Chat Area Height</label>
        </th>
        <td>
            <input type="number" id="baachal_chat_height" name="baachal_chat_height" value="<?php echo esc_attr($chat_height); ?>" min="200" max="600" step="10" />
            <span>pixels</span>
            <p class="description">Height of the chat messages area (200-600 pixels)</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_border_radius">Border Radius</label>
        </th>
        <td>
            <input type="number" id="baachal_border_radius" name="baachal_border_radius" value="<?php echo esc_attr($border_radius); ?>" min="0" max="50" />
            <span>pixels</span>
            <p class="description">Roundness of the chatbot corners (0 = square, 50 = very round)</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_font_size">Font Size</label>
        </th>
        <td>
            <input type="number" id="baachal_font_size" name="baachal_font_size" value="<?php echo esc_attr($font_size); ?>" min="10" max="20" />
            <span>pixels</span>
            <p class="description">Text size in the chat (10-20 pixels)</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_animation_enabled">Enable Animations</label>
        </th>
        <td>
            <input type="checkbox" id="baachal_animation_enabled" name="baachal_animation_enabled" value="1" <?php checked($animation_enabled, '1'); ?> />
            <p class="description">Enable smooth animations when opening/closing the chatbot</p>
        </td>
    </tr>
</table>
