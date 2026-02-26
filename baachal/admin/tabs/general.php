<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<h2>General Settings</h2>
<p>Configure the basic settings for your chatbot.</p>

<table class="form-table" role="presentation">
    <tr>
        <th scope="row">
            <label for="baachal_enabled">Enable Chatbot</label>
        </th>
        <td>
            <input type="checkbox" id="baachal_enabled" name="baachal_enabled" value="1" <?php checked($enabled, '1'); ?> />
            <p class="description">Show the chatbot on your website</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_ai_provider">AI Provider</label>
        </th>
        <td>
            <select id="baachal_ai_provider" name="baachal_ai_provider">
                <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Google Gemini</option>
                <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI ChatGPT</option>
                <option value="claude" <?php selected($ai_provider, 'claude'); ?>>Anthropic Claude</option>
                <option value="grok" <?php selected($ai_provider, 'grok'); ?>>xAI Grok</option>
            </select>
            <p class="description">Choose your preferred AI provider</p>
        </td>
    </tr>
    
    <tr class="baachal-api-key-row baachal-gemini-key" style="<?php echo $ai_provider !== 'gemini' ? 'display:none;' : ''; ?>">
        <th scope="row">
            <label for="baachal_gemini_api_key">Gemini API Key</label>
        </th>
        <td>
            <input type="password" id="baachal_gemini_api_key" name="baachal_gemini_api_key" value="<?php echo esc_attr($gemini_api_key); ?>" class="regular-text" />
            <p class="description">Get your API key from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></p>
        </td>
    </tr>
    
    <tr class="baachal-api-key-row baachal-openai-key" style="<?php echo $ai_provider !== 'openai' ? 'display:none;' : ''; ?>">
        <th scope="row">
            <label for="baachal_openai_api_key">OpenAI API Key</label>
        </th>
        <td>
            <input type="password" id="baachal_openai_api_key" name="baachal_openai_api_key" value="<?php echo esc_attr($openai_api_key); ?>" class="regular-text" />
            <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>
        </td>
    </tr>
    
    <tr class="baachal-api-key-row baachal-claude-key" style="<?php echo $ai_provider !== 'claude' ? 'display:none;' : ''; ?>">
        <th scope="row">
            <label for="baachal_claude_api_key">Claude API Key</label>
        </th>
        <td>
            <input type="password" id="baachal_claude_api_key" name="baachal_claude_api_key" value="<?php echo esc_attr($claude_api_key); ?>" class="regular-text" />
            <p class="description">Get your API key from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a></p>
        </td>
    </tr>
    
    <tr class="baachal-api-key-row baachal-grok-key" style="<?php echo $ai_provider !== 'grok' ? 'display:none;' : ''; ?>">
        <th scope="row">
            <label for="baachal_grok_api_key">Grok API Key</label>
        </th>
        <td>
            <input type="password" id="baachal_grok_api_key" name="baachal_grok_api_key" value="<?php echo esc_attr($grok_api_key); ?>" class="regular-text" />
            <p class="description">Get your API key from <a href="https://console.x.ai/" target="_blank">xAI Console</a></p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_ai_model">AI Model</label>
        </th>
        <td>
            <select id="baachal_ai_model" name="baachal_ai_model">
                <!-- Gemini Models -->
                <optgroup label="Gemini Models" class="baachal-model-group baachal-gemini-models" style="<?php echo $ai_provider !== 'gemini' ? 'display:none;' : ''; ?>">
                    <option value="gemini-2.5-pro" <?php selected($ai_model, 'gemini-2.5-pro'); ?>>Gemini 2.5 Pro - Most Advanced</option>
                    <option value="gemini-2.5-flash" <?php selected($ai_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash - Best Price-Performance</option>
                    <option value="gemini-2.5-flash-lite" <?php selected($ai_model, 'gemini-2.5-flash-lite'); ?>>Gemini 2.5 Flash-Lite - Ultra Fast</option>
                    <option value="gemini-2.0-flash" <?php selected($ai_model, 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash - Workhorse</option>
                    <option value="gemini-2.0-flash-lite" <?php selected($ai_model, 'gemini-2.0-flash-lite'); ?>>Gemini 2.0 Flash-Lite - Small & Fast</option>
                </optgroup>
                
                <!-- OpenAI Models -->
                <optgroup label="OpenAI Models" class="baachal-model-group baachal-openai-models" style="<?php echo $ai_provider !== 'openai' ? 'display:none;' : ''; ?>">
                    <option value="gpt-5" <?php selected($ai_model, 'gpt-5'); ?>>GPT-5 - Best for Coding & Agentic Tasks</option>
                    <option value="gpt-5-pro" <?php selected($ai_model, 'gpt-5-pro'); ?>>GPT-5 Pro - Smarter & More Precise</option>
                    <option value="gpt-5-mini" <?php selected($ai_model, 'gpt-5-mini'); ?>>GPT-5 Mini - Fast & Cost-Efficient</option>
                    <option value="gpt-5-nano" <?php selected($ai_model, 'gpt-5-nano'); ?>>GPT-5 Nano - Fastest & Most Efficient</option>
                    <option value="gpt-4.1" <?php selected($ai_model, 'gpt-4.1'); ?>>GPT-4.1 - Smartest Non-Reasoning</option>
                    <option value="gpt-4o" <?php selected($ai_model, 'gpt-4o'); ?>>GPT-4o - Previous Flagship</option>
                </optgroup>
                
                <!-- Claude Models -->
                <optgroup label="Claude Models" class="baachal-model-group baachal-claude-models" style="<?php echo $ai_provider !== 'claude' ? 'display:none;' : ''; ?>">
                    <option value="claude-sonnet-4-5-20250929" <?php selected($ai_model, 'claude-sonnet-4-5-20250929'); ?>>Claude Sonnet 4.5 - Best for Complex Tasks</option>
                    <option value="claude-opus-4-1-20250805" <?php selected($ai_model, 'claude-opus-4-1-20250805'); ?>>Claude Opus 4.1 - Exceptional Reasoning</option>
                    <option value="claude-sonnet-4-20250514" <?php selected($ai_model, 'claude-sonnet-4-20250514'); ?>>Claude Sonnet 4 - High Performance</option>
                    <option value="claude-3-7-sonnet-20250219" <?php selected($ai_model, 'claude-3-7-sonnet-20250219'); ?>>Claude Sonnet 3.7 - Extended Thinking</option>
                    <option value="claude-3-5-haiku-20241022" <?php selected($ai_model, 'claude-3-5-haiku-20241022'); ?>>Claude Haiku 3.5 - Fastest</option>
                </optgroup>
                
                <!-- Grok Models -->
                <optgroup label="Grok Models" class="baachal-model-group baachal-grok-models" style="<?php echo $ai_provider !== 'grok' ? 'display:none;' : ''; ?>">
                    <option value="grok-4-fast" <?php selected($ai_model, 'grok-4-fast'); ?>>Grok 4 Fast - Latest (Reasoning)</option>
                    <option value="grok-4-fast-reasoning" <?php selected($ai_model, 'grok-4-fast-reasoning'); ?>>Grok 4 Fast Reasoning - Thinks Before Responding</option>
                    <option value="grok-4-fast-non-reasoning" <?php selected($ai_model, 'grok-4-fast-non-reasoning'); ?>>Grok 4 Fast Non-Reasoning - Direct Responses</option>
                </optgroup>
            </select>
            <p class="description">Choose the AI model. Different providers offer different capabilities and pricing.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_title">Chatbot Title</label>
        </th>
        <td>
            <input type="text" id="baachal_title" name="baachal_title" value="<?php echo esc_attr($chatbot_title); ?>" class="regular-text" />
            <p class="description">The title displayed in the chatbot header</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row">
            <label for="baachal_welcome_message">Welcome Message</label>
        </th>
        <td>
            <textarea id="baachal_welcome_message" name="baachal_welcome_message" rows="3" cols="50"><?php echo esc_textarea($welcome_message); ?></textarea>
            <p class="description">The first message users see when they open the chatbot</p>
        </td>
    </tr>
</table>
