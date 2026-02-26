=== Baachal AI Chatbot ===
Contributors: webdevshojib
Tags: chatbot, ai, customer support, woocommerce, chat widget
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Intelligent AI chatbot with conversational product search, multi-provider support (Gemini, OpenAI, Claude, Grok) and automatic content indexing.

== Description ==

**Baachal AI Chatbot** is an AI chatbot for WooCommerce that combines conversational product search with intelligent customer support powered by leading AI providers (Google Gemini, OpenAI, Anthropic Claude, xAI Grok). It delivers instant, accurate responses about your products and content so shoppers find what they need without waiting.

### 🚀 Key Features

**� Multi-AI Provider Support**
* Google Gemini, OpenAI ChatGPT, Anthropic Claude, xAI Grok
* Choose the best AI model for your needs
* Automatic failover and model switching

**📚 Smart Content Understanding**
* Automatic website content indexing
* Contextual responses based on your actual content
* Real-time content synchronization

**🛒 WooCommerce Integration**
* Product recommendations and inventory awareness
* Smart product search and filtering
* Seamless e-commerce support

**🎨 Customizable & Responsive**
* Modern color picker with preset palettes
* Mobile-optimized chat widget
* Multiple positioning and styling options

### 🎯 Perfect For

* **E-commerce stores** - Product recommendations and support
* **Service businesses** - FAQ automation and lead generation  
* **Content websites** - Visitor engagement and information delivery
* **SaaS platforms** - User onboarding and support automation

### 🔒 Privacy & Security

* API keys stored securely
* No data sent to third parties (except your selected AI provider)
* User conversations can be optionally stored locally
* GDPR compliant with data control options

### 💡 How It Works

1. **Content Indexing**: The plugin automatically scans and indexes your website content
2. **Smart Understanding**: When users ask questions, it searches your indexed content and products
3. **AI Processing**: Your chosen AI provider processes the query with your website context
4. **Intelligent Responses**: Users receive accurate, helpful answers with relevant links

### 🛠️ Requirements

* At least one AI provider API key (most offer free tiers)
* WordPress 5.0 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher with FULLTEXT support

### 🎬 Quick Start

1. Install and activate the plugin
2. Get your AI provider API key (Google Gemini, OpenAI, Claude, or Grok)
3. Configure basic settings in WordPress Admin → Settings → Baachal AI bot
4. Index your content in the "Content Indexing" tab
5. Customize the appearance in "UI Styling"
6. Your intelligent chatbot is ready!

== External Services ==

This plugin connects to external AI service APIs to process chat messages and provide intelligent responses. Here are the external services used:

**Google Gemini AI API**
* Service: Google's Gemini AI language model (https://ai.google.dev/)
* Purpose: Process user chat messages and generate intelligent responses based on your website content
* Data sent: User messages, website content (as context), and configuration parameters
* When: Every time a user sends a message through the chatbot when Gemini is selected as the AI provider
* Privacy Policy: https://policies.google.com/privacy
* Terms of Service: https://ai.google.dev/gemini-api/terms

**OpenAI API**
* Service: OpenAI's ChatGPT language models (https://openai.com/)
* Purpose: Process user chat messages and generate intelligent responses based on your website content
* Data sent: User messages, website content (as context), and model parameters
* When: Every time a user sends a message through the chatbot when OpenAI is selected as the AI provider
* Privacy Policy: https://openai.com/privacy/
* Terms of Service: https://openai.com/terms/

**Anthropic Claude API**
* Service: Anthropic's Claude AI language model (https://www.anthropic.com/)
* Purpose: Process user chat messages and generate intelligent responses based on your website content
* Data sent: User messages, website content (as context), and model parameters
* When: Every time a user sends a message through the chatbot when Claude is selected as the AI provider
* Privacy Policy: https://www.anthropic.com/privacy
* Terms of Service: https://www.anthropic.com/terms

**xAI Grok API**
* Service: xAI's Grok language model (https://x.ai/)
* Purpose: Process user chat messages and generate intelligent responses based on your website content
* Data sent: User messages, website content (as context), and model parameters
* When: Every time a user sends a message through the chatbot when Grok is selected as the AI provider
* Privacy Policy: https://x.ai/privacy
* Terms of Service: https://x.ai/terms

**Important Notes:**
* You must provide your own API keys for any service you wish to use
* No personal data is stored by these external services beyond the duration of the API call
* Your website content may be sent as context to help the AI provide relevant responses
* All API communications are secured via HTTPS
* You can choose which AI provider to use in the plugin settings

== Installation ==

### Automatic Installation

1. Log in to your WordPress admin panel
2. Go to Plugins → Add New
3. Search for "Baachal AI Chatbot"
4. Click "Install Now" and then "Activate"

### Manual Installation

1. Download the plugin zip file
2. Upload to `/wp-content/plugins/` directory
3. Extract the files
4. Activate the plugin through the 'Plugins' menu in WordPress

### Setup

1. **Get API Key**: Visit [Google AI Studio](https://makersuite.google.com/app/apikey) to get your free Gemini API key
2. **Configure Plugin**: Go to Settings → Baachal AI bot in your WordPress admin
3. **Enter API Key**: Paste your API key in the General tab
4. **Index Content**: Go to Content Indexing tab and click "Reindex All Content"
5. **Customize**: Adjust colors, position, and behavior in other tabs
6. **Test**: Use the search test feature to verify everything works

== Frequently Asked Questions ==

= Do I need an AI provider API key? =

Yes, you need at least one API key from supported providers: Google Gemini, OpenAI, Anthropic Claude, or xAI Grok. Most providers offer free tiers with generous limits suitable for most websites. You can get API keys from:
- Google Gemini: [Google AI Studio](https://makersuite.google.com/app/apikey)
- OpenAI: [OpenAI Platform](https://platform.openai.com/api-keys)
- Anthropic: [Anthropic Console](https://console.anthropic.com/)
- xAI: [xAI Console](https://console.x.ai/)

= Which AI provider should I choose? =

Each provider has different strengths:
- **Google Gemini**: Great balance of performance and cost, excellent for general use
- **OpenAI**: Advanced reasoning capabilities, best for complex queries
- **Anthropic Claude**: Excellent for detailed explanations and creative tasks
- **xAI Grok**: Fast responses with reasoning capabilities

You can configure multiple providers and switch between them.

= Will this work with my theme? =

Yes! The chatbot is designed to work with any WordPress theme. It uses a floating widget that doesn't interfere with your theme's layout.

= Does it work with WooCommerce? =

Absolutely! The plugin includes deep WooCommerce integration with product recommendations, inventory awareness, and conversational product search capabilities.

= How does the content indexing work? =

The plugin automatically scans your posts, pages, and other content types, creating a searchable index. This allows the AI to provide accurate answers about your specific content.

= Can I customize the chatbot appearance? =

Yes! You can customize colors, position, size, animations, and more through the intuitive admin interface with beautiful preset color palettes.

= Is it mobile-friendly? =

Yes, the chatbot is fully responsive and optimized for mobile devices with touch-friendly controls and appropriate sizing.

= What about privacy and GDPR? =

The plugin is GDPR compliant. You control what data is stored and can enable/disable conversation logging. Only necessary data is sent to your chosen AI provider for processing.

= Can I exclude specific pages from indexing? =

Yes! You can exclude any post or page from indexing using the meta box that appears when editing content.

= Does it affect site performance? =

No, the plugin is highly optimized with intelligent caching, minimal database queries, and lightweight assets.

= Can I see conversation statistics? =

Yes, the admin interface provides comprehensive statistics about indexed content and search performance.

== Screenshots ==

1. **Chat Widget** - Clean, professional chat interface with your brand colors
2. **Admin Settings** - Intuitive tabbed interface for easy configuration  
3. **Content Indexing** - Powerful content management with statistics dashboard
4. **WooCommerce Integration** - Smart product recommendations with full details

== Changelog ==

= 1.0.4 =
* Feature: Added previous messages to the request so that ai remebers the context
* Feature: Added product card instead of showing products in plain text

= 1.0.3 =
* Security: Fixed all SQL injection vulnerabilities using proper wpdb::prepare() methods
* Security: Removed PHP session usage to improve server-side cache compatibility
* Improvement: Implemented client-side session management using localStorage
* Fix: All database queries now use proper parameterized statements
* Compatibility: Plugin now works with nginx, Varnish, and other server-side caching systems

= 1.0.2 =
* Added multi-provider AI support (OpenAI, Claude, Grok)
* Enhanced content indexing with better search algorithms
* Improved WooCommerce integration
* Bug fixes and performance improvements

= 1.0.1 =
* Minor bug fixes
* Improved error handling
* Enhanced UI responsiveness

= 1.0.0 =
* Initial release
* Google Gemini AI integration
* Automatic content indexing system
* WooCommerce integration
* Customizable chat widget
* Admin settings interface
* Mobile responsive design
* Performance optimization
* Security enhancements
* Content exclusion options
* Real-time search testing
* Conversation logging
* Multiple positioning options
* Custom color schemes
* Animation controls

== Upgrade Notice ==

= 1.0.0 =
Initial release of Baachal AI Chatbot. Transform your website with intelligent AI-powered customer support.

== Support ==

For support, feature requests, or bug reports, please visit:

* [Plugin Support Forum](https://wordpress.org/support/plugin/baachal/)
* [GitHub Repository](https://github.com/kshojib/baachal)
* [Developer Website](https://www.shojibkhan.com)

== Credits ==

* Powered by multiple leading AI providers (Google Gemini, OpenAI, Anthropic Claude, xAI Grok)
* Developed by Shojib Khan
* Icons from various open-source projects

== Privacy Policy ==

This plugin connects to AI service providers to process chat messages. When users interact with the chatbot:

* User messages are sent to your selected AI provider's API for processing
* Website content (as context) may be included in API requests
* No personal data is stored by AI providers beyond the API call processing
* Conversations can optionally be logged locally on your website
* API keys are stored securely in your WordPress database
* No data is shared with third parties other than your selected AI provider

For privacy policies of supported providers:
- Google Gemini: https://policies.google.com/privacy
- OpenAI: https://openai.com/privacy/
- Anthropic: https://www.anthropic.com/privacy
- xAI: https://x.ai/privacy