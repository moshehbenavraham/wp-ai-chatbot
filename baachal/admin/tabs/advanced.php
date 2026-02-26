<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<h2>Advanced Settings</h2>
<p>Advanced options for developers and troubleshooting.</p>

<table class="form-table" role="presentation">
    <tr>
        <th scope="row">
            <label for="baachal_debug_mode">Debug Mode</label>
        </th>
        <td>
            <input type="checkbox" id="baachal_debug_mode" name="baachal_debug_mode" value="1" <?php checked($debug_mode, '1'); ?> />
            <p class="description">Enable to see detailed error messages in browser console (for troubleshooting)</p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="baachal_preserve_data_on_uninstall">Preserve Data on Uninstall</label>
        </th>
        <td>
            <input type="checkbox" id="baachal_preserve_data_on_uninstall" name="baachal_preserve_data_on_uninstall" value="1" <?php checked(get_option('baachal_preserve_data_on_uninstall', '1'), '1'); ?> />
            <p class="description">Keep all plugin data (settings, conversations, indexed content) when uninstalling the plugin</p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="baachal_conversation_memory">Conversational Memory</label>
        </th>
        <td>
            <input type="checkbox" id="baachal_conversation_memory" name="baachal_conversation_memory" value="1" <?php checked(get_option('baachal_conversation_memory', '0'), '1'); ?> />
            <p class="description">Enable the AI to remember previous messages in the conversation for more contextual responses</p>
        </td>
    </tr>
    <tr>
        <th scope="row">
            <label for="baachal_memory_limit">Memory Limit</label>
        </th>
        <td>
            <input type="number" id="baachal_memory_limit" name="baachal_memory_limit" value="<?php echo esc_attr(get_option('baachal_memory_limit', '10')); ?>" min="1" max="50" class="small-text" />
            <p class="description">Maximum number of previous messages to include in conversation context (1-50). Higher values provide more context but may increase API costs.</p>
        </td>
    </tr>
</table>