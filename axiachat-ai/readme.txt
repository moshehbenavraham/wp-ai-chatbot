=== AxiaChat AI – Free AI Chatbot (Answers Customers Automatically) ===
Contributors: estebandezafra, freemius
Tags: chatbot, ai, openai, chat, assistant
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free AI chatbot with multiple bots, OpenAI & Claude & Gemini, contextual embeddings (local or Pinecone), PDF ingestion, usage limits & GDPR tools. RAG

== Description ==

**Transform your WordPress site into a 24/7 AI-powered customer service hub.** AxiaChat AI delivers instant, intelligent responses using OpenAI (GPT-4, GPT-5), Anthropic Claude, and Google Gemini.

#### 🚀 Key Features

**Smart Training with RAG**
Train your bot on YOUR content using Retrieval Augmented Generation. Unlike generic chatbots, AxiaChat AI learns from your website pages, PDFs, and documentation to provide accurate, contextual answers specific to your business.

**Multiple AI Providers & Models**
Choose between OpenAI, Claude, or Gemini. Create multiple specialized bots—one for sales, one for support, each with unique configurations and training.

**MCP Server Integration**
Connect Model Context Protocol servers to extend your bot beyond Q&A. Perform actions, fetch live data, and integrate with business systems.

**Enterprise Features**
✅ Context-aware responses (local or Pinecone embeddings)
✅ PDF ingestion for knowledge bases
✅ AI tools: web search, email notifications, custom actions
✅ Usage limits and cost controls
✅ GDPR-compliant consent management
✅ Full UI customization (colors, avatars, positioning)
✅ Floating widget or inline shortcode
✅ Complete conversation analytics

#### 🎯 Real Benefits

- Reduce support workload by automating common questions
- Capture leads 24/7 even when your team is offline
- Improve engagement with instant, helpful responses
- Scale support without scaling headcount

**Perfect for e-commerce, service businesses, SaaS, educational sites, and corporate websites.**

[Visit axiachat.org](https://axiachat.org/) for plans, documentation, and support.

== Tutorial: Create Your First Bot with AxiaChat AI ==

Learn how to install and set up your **first AI chatbot in just 5 minutes** 
[youtube https://youtu.be/Th41gGUH7Es]

In this quick video, we cover:
- Create & customize your bot (text + voice)
- Train it with your site pages, PDFs, and FAQs
- Full appearance control + moderation


== Features ==

#### 🤖 AI & Model Support
* **Multiple AI Providers**: OpenAI (GPT-4, GPT-5), Anthropic Claude, Google Gemini
* **MCP Server Integration**: Connect Model Context Protocol servers for extended capabilities
* **Provider-native Web Search**: Enable live internet lookups with cited sources (OpenAI, Claude, Gemini)
* **Multiple independent bots** with unique configurations per bot (model, temperature, instructions, UI)
* **Advanced AI Tools**: per-bot capabilities including web search, email notifications, custom actions with admin logs

#### 🎓 Smart Training & Context
* **Retrieval Augmented Generation (RAG)**: Train your bot on your actual content
* **Context modes**: embeddings (local or Pinecone) / page-specific / none
* **PDF ingestion**: Upload and process PDF documents into your knowledge base
* **Auto-sync**: Automatically detect and update changed content
* **Smart chunking**: Overlapping content chunks for better retrieval precision
* **Easy Config Wizard**: Guided setup scans your site and creates optimized context automatically

#### 🎨 Customization & Deployment
* **Floating global widget** OR **inline embedding** via shortcode
* **Full UI control**: custom colors, avatars, positioning, window controls
* **Draggable, minimizable, closable** chat panel with state persistence
* **Multiple deployment options**: per-page shortcodes with attribute overrides
* **9 avatar designs** included + custom avatar upload
* **Responsive design**: works perfectly on desktop and mobile

#### 📊 Usage Control & Analytics
* **Conversation logging** with ON/OFF toggle (user/session tracking; IP only if limits enabled)
* **Daily usage limits**: per-user/IP and global caps
* **Flexible limit behaviors**: disable input or hide widget when exceeded
* **Cost tracking**: monitor token usage and API costs
* **Detailed conversation logs**: review all interactions with filtering and search

#### 🔒 Security & Compliance
* **GDPR-compliant**: built-in consent management with in-stream consent bubble
* **Privacy controls**: optional conversation logging, IP anonymization
* **WordPress security best practices**: nonces, capability checks, prepared queries, output escaping
* **Encrypted API keys**: secure storage of sensitive credentials
* **Data ownership**: all processing on your server, no external SaaS proxy
* **Local vendor assets**: Bootstrap & Icons bundled—no CDN dependencies
* **Safe Markdown rendering**: Parsedown in safe mode + final HTML allowlist (MIT license: includes/lib/parsedown/LICENSE.txt)

#### 🛠️ Developer-Friendly
* **Action hooks**: `aichat_after_response`, `aichat_conversation_saved` for custom integrations
* **Extensible architecture**: provider system for adding new AI services
* **Translation ready**: text domain `axiachat-ai` (Spanish included)
* **Clean uninstall**: removes options safely (conversation data preserved)
* **Well-documented code**: follows WordPress coding standards

#### 🌐 Language & Accessibility
* **Works with any language** (customizable responses)
* **RTL support** for right-to-left languages
* **Spanish translation** included (es_ES)
* **Translation ready** with .pot file for easy localization

**Need a custom capability?** Tell us what your bot should do. Custom development is available under project quote. [Learn more at axiachat.org →](https://axiachat.org/)

== Installation ==

#### Quick Start (5 minutes)

1. **Install the Plugin**
   - Upload the `axiachat-ai` folder to `/wp-content/plugins/`
   - OR install directly via **Plugins > Add New** in WordPress

2. **Activate**
   - Go to **Plugins** and activate AxiaChat AI

3. **Add Your API Key**
   - Navigate to **AxiaChat AI > Settings**
   - Enter your OpenAI API key (required)
   - Optionally add Claude or Gemini keys for those providers

4. **Run Easy Config Wizard** (Recommended for First-Time Users)
   - The wizard appears automatically after activation
   - It will scan your site content (posts, pages, products)
   - Create a context with embeddings automatically
   - Link your default bot to the new context
   - OR skip and configure manually

5. **Create or Customize Your Bot**
   - Go to **AxiaChat AI > Bots**
   - Edit the default bot or create a new one
   - Choose AI provider and model (GPT-4, Claude, Gemini)
   - Set bot instructions, UI appearance, and behavior
   - Enable AI capabilities (web search, tools) if needed

6. **Optional: Add More Context**
   - Visit **AxiaChat AI > Context**
   - Ingest PDFs or select specific posts/pages for embeddings
   - Your bot will use this content to provide accurate, contextual answers

7. **Deploy Your Bot**
   
   **Option A – Floating Global Widget:**
   - Enable **Global widget** in **AxiaChat AI > Settings**
   - Select which bot to display
   - Widget appears on all pages (unless excluded)
   
   **Option B – Inline Shortcode:**
   - Add `[aichat id="your-bot-slug"]` to any post or page
   - Customizable per-page with shortcode attributes

8. **Test & Optimize**
   - Visit your site and interact with the chatbot
   - Review conversation logs under **AxiaChat AI > Logs**
   - Adjust bot instructions and context as needed

**Need Help?** Visit [axiachat.org](https://axiachat.org/) for documentation, plans, and support options.

== Usage ==

#### Basic Usage

After installation, your chatbot is ready to start helping your visitors! Here's how to make the most of AxiaChat AI:

**For Site Owners:**
- Create specialized bots for different purposes (sales, support, FAQs)
- Train bots with relevant content using the Context manager
- Monitor conversations and improve responses based on real user interactions
- Set usage limits to control API costs
- Enable/disable logging as needed for privacy compliance

**For Visitors:**
- Click the floating chat icon to start a conversation
- Ask questions in natural language—the AI understands context
- Receive instant, accurate answers based on your site's content
- Get cited sources when web search is enabled
- Accept GDPR consent to start chatting (if enabled)

#### Advanced Configuration

**AI Tools & Capabilities:**
Enable advanced features per bot in the Tools section:
- **Web Search**: Let the bot search the internet for current information
- **Email Notifications**: Send notifications to admins or authorized recipients
- **Custom Actions**: Use MCP servers to extend functionality
- **Domain Restrictions**: Limit web search to trusted domains only

**Window Controls:**
Customize the chat experience:
- **Closable**: Users can dismiss the chat window
- **Minimizable**: Compact to icon when not in use
- **Draggable**: Let users move the window around the screen
- **Start Minimized**: Begin in compact mode

**Usage Limits:**
Protect your API budget:
- Set per-user or per-IP daily message limits
- Configure global daily limits across all users
- Choose behavior when limits are reached (disable input or hide widget)


**Need help with setup?** Configuration assistance is available for paid plan users. Visit [axiachat.org](https://axiachat.org/) for details.

== AI Tools (Capabilities, Macros & Rules) ==

#### Supercharge Your Chatbot with AI Tools

AxiaChat AI goes beyond simple Q&A. The optional AI Tools layer allows your assistant to **take actions**, **access live data**, and **integrate with external services**—all while maintaining strict security controls.

#### 🌐 Provider-Native Web Search

Enable real-time internet lookups so your bot can answer questions about current events, latest information, or topics beyond your site content.

**Supported Providers:**
- **OpenAI**: GPT-5* models with native `web_search` capability
- **Claude**: 4.x models with `web_search_20250305` tool
- **Gemini**: `google_search` integration (Live API only)

**Features:**
- Cited sources in responses
- Optional domain allowlist per bot (restrict to trusted sites)
- Automatic integration—no complex configuration needed

**When to Use:**
- News sites needing current information
- Product comparison queries
- Research and fact-checking
- Supplement your knowledge base with web data

#### 📧 Email Notifications

Send intelligent email notifications triggered by conversations.

**Safety Features:**
- Sends to site admin by default
- Rate limiting to prevent spam
- Policy gates for security
- Client emails require explicit server-side authorization via filter

**Use Cases:**
- Notify team when high-priority leads are captured
- Send follow-up emails based on conversation topics
- Alert staff to urgent support requests

#### 🔌 MCP Server Integration

**Model Context Protocol (MCP)** integration enables your chatbot to connect with external tools and data sources.

**What You Can Do:**
- Fetch live data from APIs
- Query databases
- Integrate with business systems
- Create custom workflows
- Extend capabilities without coding

**How It Works:**
1. Add MCP server configuration in the addon settings
2. Enable desired tools per bot
3. The AI automatically calls tools when appropriate
4. Results are seamlessly integrated into responses

#### 📋 Rules Engine (Optional)

Define simple conditions to trigger automatic actions:
- Ask follow-up questions based on user responses
- Speak a message at specific points
- Call tools automatically when certain conditions are met

#### 📊 Tools Logging

Every tool call is logged for transparency and debugging:
- Execution duration
- Output excerpts
- Error tracking
- Searchable admin interface

**Access logs:** AxiaChat AI > Logs > Tools

#### 🔒 Security by Design

- **Sandboxed execution**: Tools run in isolated contexts
- **Policy gates**: System-level restrictions prevent abuse
- **No secrets exposed**: API keys and sensitive data never sent to AI models
- **Admin authorization**: All tool capabilities require explicit enablement

**Note:** On OpenAI GPT-5* (Responses API), AxiaChat normalizes function tools to the required schema and passes the native `web_search` tool when enabled.

== Easy Config Wizard ==
Version 1.1.2 introduces an optional "Easy Config" wizard that appears after initial activation (or until completed). It performs:
1. Site scan: collects up to the most recent 200 published posts, pages (and products if WooCommerce detected).
2. Context creation: creates a local embeddings context record.
3. Batch indexing: chunks are created by generating embeddings for discovered items (10 per batch via AJAX) using your OpenAI API key.
4. Bot linking: links the default bot (slug "default") to the new context with context mode = embeddings.

Smart Mode :
If available the wizard now uses a smart discovery mode prioritizing:
* Homepage and its internal links
* Legal / FAQ / About / Policy pages (slug heuristics)
* WooCommerce top categories and a sample of recent products (if WooCommerce active)
* Fallback to a few recent posts/pages if the set is too small
Legacy behavior is still used internally as fallback.

Real Chunking:
From 1.1.2+ the indexing process splits long content into multiple overlapping chunks (~1000 words, ~180 overlap) each embedded separately, improving retrieval precision. Existing single-row entries will be transparently replaced on re‑index.

Selective Indexing:
The wizard lets you deselect discovered pages/products before indexing to reduce token usage and noise. At least one item must remain selected.

Notes:
* The wizard hides automatically after completion (flag stored in option `aichat_easy_config_completed`).
* You can abort and later ingest additional or different content via the Context screens.
* If you already manually configured a bot/context you can ignore the wizard and mark it complete by finishing or deleting the option.
* Current limit (for safety) is 200 items; extend manually if needed.

Troubleshooting:
* If embeddings fail, check that your OpenAI API key is valid and that the server can reach api.openai.com.
* Browser console (when AICHAT_DEBUG true) will include extra debug logs.

== Privacy ==
User prompts (and selected context snippets) are sent to OpenAI. Content may contain personal data if users type it. Inform users and obtain consent where required. Logging can be disabled; if enabled, data stays on your server.

== GDPR / Compliance Notes ==
* Data Processor: OpenAI
* Lawful basis: legitimate interest or consent (consult legal counsel)
* IP Storage: Binary IP kept only when enforcing per-user/IP limits
* Right to erasure: Delete rows manually (future export/anonymize tool planned)
* Consent: Optional in‑stream consent bubble blocks input until accepted
* Recommendation: Add a privacy note near the chat input

== Changelog ==
= 3.0.2 =
* Fixed: Tool continuation no longer triggers embedding generation (prevented 400 errors with Gemini provider).
* Fixed: Tool execution continuation now works reliably for Gemini and Claude providers (silent death resolved).
* Fixed: Suggestions chips now render correctly after tool continuation responses.
* Fixed: GPT-5.x hallucinated tool narration (e.g. "(executing tools…)") is detected and retried automatically.

= 3.0.1 =
🎨 **Redesign & User-Friendly Overhaul**
* Redesigned: Brand-new Easy Config wizard — set up a fully working bot in under 2 minutes with a guided, step-by-step flow.
* Redesigned: Training → Instructions now uses a clean card-based layout with built-in universal guidelines and a "Quick mode / Full editor" toggle.
* Redesigned: Training → Context page rebuilt with a modern batch-processing UI, real-time progress, and clearer status indicators.
* Redesigned: Bot Settings reorganized into logical tabs (General, Appearance, Window, Advanced) with live preview and simplified controls.
* Added: One-click AI-generated bot instructions — describe what you need and the AI writes the full system prompt for you.
* Added: Built-in prompt templates (Customer Support, Lead Generation, E-commerce Assistant, etc.) selectable from the Instructions page.
* Improved: AI Tools settings simplified — per-tool System Policy removed; all bot behavior is now centralized in Training → Instructions with clear usage tips and examples.
* Improved: Full JavaScript internationalization audit — all admin UI strings are now translatable.


= 2.0.9 =
* Added: Advanced Indexing Options per context — configure which fields are embedded (excerpt, URL, featured image, taxonomies, custom meta keys).
* Added: WooCommerce static fields in indexing — short description and attributes can now be included in embeddings.
* Added: WooCommerce dynamic enrichment — price, stock status and SKU are injected at query time with fresh data (no re-indexing needed when prices change).
* Added: Featured image URL option — when enabled, the AI can show product/post images directly in the chat using Markdown.
* Added: Image rendering support in chat widget — bot responses can now display images via Markdown syntax.
* Added: Post URL included by default in embeddings — the AI can now reference direct links to content. Configurable in Advanced Indexing Options.
* Fixed: Pinecone upsert metadata now includes content and post type — fixes empty context and enables WooCommerce enrichment for Pinecone-based contexts.
* Improved: Markdown-to-HTML fallback now supports image syntax `![alt](url)`.

= 2.0.8 =
* Added: Knowledge Sources Limit setting (3–10 slider) in Thresholds — control how many RAG chunks are included in each AI response (default 5).
* Added: History Persistence toggle per bot — when OFF, chat history is not restored on page refresh.
* Improved: Context Max Length default raised from 4096 to 6000 characters to avoid truncating rich RAG contexts.



= 2.0.7 =
* Contexts can now be modified.
* Preview page to test the bot before deploying it globally.
* Button in the widget to clear conversation history.

= 2.0.6 =
🛡️ **CSS Isolation – Theme-Proof Styling**
* Fixed: Chat widget text and background colors are now hardened with `!important` declarations to prevent WordPress themes from overriding them.
* Fixed: Dark themes can no longer cause invisible text (white-on-white) inside chat bubbles.
* Hardened: All critical UI elements protected — message bubbles, input field, send button, header, suggestions chips, GDPR consent block, branding, and super-minimized avatar.

🔧 **Diagnostics ZIP Export**
* Added: One-click "Export Diagnostics ZIP" button in Settings → Advanced tab for faster remote support.
* Includes: Full environment report (WP/PHP/MySQL versions, active theme, active plugins, bot & context summaries) plus plugin log files. Never includes API keys or secrets.

= 2.0.2 =
**Major Release: Business Automation Suite**

🎯 **Lead Capture System**
* Added: AI-powered lead capture tool (`guardar_lead`) - bot can collect customer info during conversations.
* Added: Multi-destination support: Internal database, WordPress CPT, Contact Form 7, WPForms, Google Sheets.
* Added: Customizable fields: name, email, phone, company, interest, notes, and custom fields.
* Added: Lead status tracking (new, contacted, converted, discarded).
* Added: Email notifications for new leads.
* Added: CSV export functionality.
* Added: GDPR-compliant options with optional IP hashing.

📅 **Appointment Booking System**
* Added: AI-powered appointment scheduling tool - bot can check availability and book appointments.
* Added: Internal calendar with time slot management.
* Added: Bookly integration adapter.
* Added: Amelia integration adapter.
* Added: Simply Schedule Appointments (SSA) integration adapter.
* Added: Google Calendar integration with OAuth2 authentication.
* Added: Admin dashboard for managing appointments.

💬 **LiveChat / Human Handoff**
* Added: Seamless escalation from AI bot to human agent.
* Added: Agent dashboard for managing live conversations.
* Added: Real-time polling for instant message delivery.
* Added: Session states: bot, pending_agent, agent_connected, closed.
* Added: Priority queue for visitor requests.
* Added: Agent availability controls.

🔗 **Custom Webhooks**
* Added: Create custom AI tools powered by external webhooks.
* Added: Visual webhook builder in admin.
* Added: Parameter mapping with JSON schema support.
* Added: Response parsing and error handling.
* Added: Free tier includes 1 webhook, unlimited with paid plans.


🛠️ **Other Improvements**
* Updated: Default model changed to GPT-5.2-chat-latest (December 2025).
* Improved: Provider registry architecture for easier extensibility.
* Improved: Tool execution with better error handling and logging.
* Fixed: Easy Config wizard now uses GPT-5.2-chat-latest as default model.
* Fixed: Model selection properly passed through Agency proxy.

= 1.2.9 =
* Added: Markdown rendering support for AI responses (safe HTML output).
* Added: Optional “Suggestions / Next actions” chips in the widget (configurable count and colors).
* Improved: Usage logging accuracy for OpenAI Responses API (GPT-5.x / GPT-5.2) and cost calculation.
* Improved: Usage screen with “Last Conversations” view for quick auditing.
* Fixed: Preview home mode now avoids inline CSS/JS injection (enqueue-based).


= 1.2.8 =
* Added: GPT-5.2 models support (Instant, Thinking) - OpenAI's December 2025 generation.
* Updated: Token limits and pricing for new GPT-5.2 models.

= 1.2.7 =
* Added: GPT-5.1 models support (Instant, Thinking) - OpenAI's latest generation.
* Tested: Fully compatible with WordPress 6.9.
* Updated: Token limits and pricing for new GPT-5.1 models.

= 1.2.6 =
* Added: WhatsApp as an input/output channel through integration with another plugin.
* Enhanced: Tabbed interface in settings for better navigation.
* Added: New "Advanced" tab in settings.

= 1.2.5 =
* Added: Support for Google Gemini as a new AI provider.

= 1.2.4 =
* Added: MCP Servers Addon, allowing the addition of MCP servers for enhanced functionality.
* Improved: Encryption of AI model API keys for better security.



= 1.2.3 =
* Added: YouTube tutorial video for quick setup walkthrough
* Enhanced: Flexible widget sizing for better responsive design
* Improved: Larger avatar display for better visual prominence

* GDPR: Redesigned the consent message as a full-width in-stream block with a darker background and a centered button for better visibility and UX. Inputs remain disabled until accepted.

= 1.2.2 =
* Admin: Improved Bots accordion behavior — clicking an open section now closes it (allowing zero-open state) while keeping “at most one open” when opening another.
* Admin: Reduced console noise; detailed accordion debug logs are now disabled by default and can be re-enabled with ?aichat_debug=1.
* Preview: Stabilized homepage preview and suppressed duplicate global widget when previewing a specific bot.


== Bundled Libraries ==
* Bootstrap (local)
* Bootstrap Icons (local)
* smalot/pdfparser (LGPLv3) for PDF extraction

== Translation ==
* Text Domain: `axiachat-ai`
* Domain Path: `/languages`

== External Services / Data Disclosure ==
This plugin can connect to the following third‑party APIs depending on which features you enable. You (the site owner) must supply the API keys. No keys are bundled and no traffic is proxied through a vendor server controlled by this plugin author.

=== 1. OpenAI ===
Used for: chat completions / responses, embeddings (context indexing), moderation (safety checks).

Endpoints used (HTTPS):
* https://api.openai.com/v1/chat/completions (legacy Chat Completions)
* https://api.openai.com/v1/responses (Responses API – new unified endpoint if configured)
* https://api.openai.com/v1/embeddings (document/post/PDF embedding + wizard indexing)
* https://api.openai.com/v1/moderations (content moderation)

Data Sent:
* User prompt text (per message) and limited conversation history (trimmed for token control)
* System / policy instructions (security + privacy policy + bot instructions)
* Optional retrieved context snippets (only the selected top‑N chunks or page excerpt – never the full original document)
* Embedding requests: raw chunk text produced from your site’s content or uploaded PDFs
* Moderation: only the user prompt text (not the entire history)

Data Retention (Your Server):
* Conversation log rows (if logging enabled) including user prompt, model reply, timestamps, bot slug, session id, optional user id. IP (binary) only stored when per‑IP limits are turned on. Disable logging to stop storing new rows.
* Embeddings table stores numeric vectors generated from your content (not reversible plaintext) plus the original chunk text for retrieval.

Recommendations:
* Update your privacy policy to disclose sending user prompts and limited site content to OpenAI for processing.
* Disable logging or periodically purge if you process personal data.

Legal / Docs:
* Terms: https://openai.com/policies/terms-of-use
* Privacy: https://openai.com/policies/privacy-policy
* Usage Policies: https://openai.com/policies/usage-policies

Opt‑Out / Control:
* Remove the OpenAI API key in Settings to stop all OpenAI calls (bots will refuse to answer).
* Disable conversation logging.
* Limit context ingestion to non‑sensitive pages.

=== 2. Anthropic (Claude) ===
Used for: alternative chat completions via Claude models (messages API) when a bot provider is set to Anthropic/Claude.

Endpoint used (HTTPS):
* https://api.anthropic.com/v1/messages

Data Sent:
* A rewritten message array: system instructions + user prompt + condensed prior turns (trimmed) + optional retrieved context snippets.
* Model identifier, max tokens / temperature style parameters.

Headers:
* `x-api-key` (your key) and `anthropic-version` (currently 2023-06-01 set in code).

Retention (Your Server):
* Same as OpenAI notes for conversation logging (the provider choice does not change local storage schema).

Legal / Docs:
* Terms: https://www.anthropic.com/legal/terms-of-service
* Privacy: https://www.anthropic.com/legal/privacy
* Usage Policy: https://www.anthropic.com/legal/aup

Opt‑Out / Control:
* Leave the Claude API key blank; those bots will fallback/refuse if provider requires it.
* Switch provider per bot back to OpenAI.

=== 3. Pinecone (Optional Remote Vector Store) ===
Only used if you explicitly create a Context with remote type "Pinecone". Local context mode (default) never contacts Pinecone.

Endpoint Pattern:
* Region/index specific HTTPS endpoints you enter (example placeholder: https://controller.pinecone.io and index query/upsert endpoints under *.pinecone.io). The plugin validates host against an allowlist containing pinecone.io (filter extendable).

Used For:
* Upserting embeddings (during context indexing / syncing)
* Querying similar vectors when answering a question in that context.

Data Sent:
* Vectors (embedding arrays) and associated metadata (post/page IDs, titles, short chunk text) for upsert.
* Query: the embedding vector of the user question + top‑K request.

Retention:
* Stored inside your Pinecone project (not on this plugin server). Local DB still keeps minimal reference metadata if you mix modes.

Legal / Docs:
* Terms: https://www.pinecone.io/terms/
* Privacy: https://www.pinecone.io/privacy/
* Security: https://www.pinecone.io/security/

Opt‑Out / Control:
* Do not configure a remote Pinecone context; use local mode or page mode instead.
* Delete the remote context to stop future upserts/queries.

=== 4. Embedded Loader (Your Site’s Frontend) ===
The public embed script served from your own domain loads the chat UI. It calls only your WordPress `admin-ajax.php` endpoint (dynamic URL) – no third‑party directly from the browser.
Data Flow (Browser → Your Server → Provider):


== FAQ ==

**Q: Why choose AxiaChat AI over other WordPress chatbot plugins?**
A: AxiaChat AI gives you complete control with support for multiple AI providers (OpenAI, Claude, Gemini), advanced RAG capabilities, MCP server integration, and enterprise features—all while keeping your data on your server. No monthly SaaS fees beyond your chosen AI provider's API costs.

**Q: Do I need an OpenAI API key?**
A: You need at least one AI provider API key (OpenAI, Claude, or Gemini). OpenAI is recommended for getting started. All keys are yours—we never charge markup or routing fees.

**Q: How much does it cost to run?**
A: The plugin is free. You only pay your AI provider's API usage (typically $0.001-0.01 per conversation). Usage limits help you control costs. A typical e-commerce site handles hundreds of conversations for under $10/month.

**Q: Can the bot learn from my website content?**
A: Yes! The Easy Config Wizard automatically scans and indexes your site content. You can also upload PDFs, select specific pages, or train it on your knowledge base. The bot uses Retrieval Augmented Generation (RAG) to provide accurate, contextual answers.

**Q: Will this work with my WooCommerce store?**
A: Absolutely. The wizard auto-detects WooCommerce and indexes your products. The bot can answer product questions, help with comparisons, and guide customers through your catalog.

**Q: Can I have multiple bots for different purposes?**
A: Yes! Create unlimited bots—each with unique training, personality, appearance, and capabilities. Use one for sales, another for support, and a third for FAQs, all running simultaneously.

**Q: How do I control API costs?**
A: Set daily usage limits (per-user, per-IP, or global). Choose what happens when limits are reached: disable input or hide the widget. Monitor usage in real-time via the built-in logs and cost tracking.

**Q: Is conversation data stored? Can I disable logging?**
A: Conversation logging is optional and fully controllable. Toggle it ON to review interactions and improve your bot, or OFF for maximum privacy. When enabled, data stays on YOUR server—never sent to us.

**Q: How is GDPR compliance handled?**
A: Built-in GDPR consent management with an in-stream consent bubble that blocks chat input until accepted. IP addresses are only stored if usage limits are enabled. You maintain full data control and can delete logs anytime.

**Q: What is MCP Server Integration?**
A: Model Context Protocol (MCP) lets your bot connect to external tools, databases, and APIs. Extend functionality beyond Q&A: fetch live data, integrate with your CRM, query inventory systems, and more—all without custom coding.

**Q: Does it support web search?**
A: Yes! Enable provider-native web search so your bot can access current information from the internet. Works with OpenAI GPT-5*, Claude 4.x, and Gemini. Optional domain allowlists let you restrict sources to trusted sites only.

**Q: Can I customize the appearance?**
A: Complete customization: colors, avatars (9 included + custom upload), position, size, window controls (draggable, minimizable, closable), button labels, and more. Match your brand perfectly.

**Q: Where can I deploy the chatbot?**
A: Two ways: (1) Floating global widget visible across your entire site, or (2) inline embedding via shortcode on specific pages/posts. Mix and match different bots for different pages.

**Q: What languages are supported?**
A: The AI models support 100+ languages naturally. The plugin UI is translation-ready (Spanish included) and works with RTL languages. Customize all bot responses for any language.

**Q: Do you offer setup assistance?**
A: Yes! Personalized setup assistance is available for users on paid plans. Our team can help configure your bot, optimize prompts, train it on your content, and ensure it works perfectly for your use case. [See available plans →](https://axiachat.org/)

**Q: Can visitors use voice input?**
A: Yes, voice input is supported for compatible bots. Users can speak their questions instead of typing.

**Q: Will more AI providers be added?**
A: Yes! Azure OpenAI, additional models, and more providers are on the roadmap. The plugin's extensible architecture makes adding new providers straightforward.

**Q: What if I need a custom capability not listed?**
A: We can build custom capabilities tailored to your business needs. Custom development is available under a project quote. [Contact us for a quote →](https://axiachat.org/)

**Q: Are there paid plans? What do they include?**
A: Yes! AxiaChat AI offers a free version with core features and premium plans that unlock advanced capabilities such as lead capture, appointment booking, live chat handoff, custom webhooks, priority support, and more. Visit [axiachat.org](https://axiachat.org/) to compare plans and choose the one that fits your needs.

**Q: Is support available?**
A: Free users can get community help via the WordPress.org support forum. Priority support with faster response times is available for paid plan subscribers. Visit [axiachat.org](https://axiachat.org/) for support options. When reporting issues, please include your WP version, PHP version, and logging status. You can also export a diagnostics ZIP from the plugin's Advanced settings to speed up troubleshooting.

== Screenshots ==
1. Bot configuration
2. Context ingestion & indexing
3. Usage / Costs

== Support ==
Use the WordPress.org support forum. Provide WP version, PHP version, logging status, and reproduction steps.

== Contributing ==
Feature suggestions and PRs welcome. Follow WP coding standards and supply clear commit messages.

== License ==
GPLv2 or later. See LICENSE.

== Disclaimer ==
AI output may be inaccurate. Do not rely on responses for legal, medical or financial decisions without human review.