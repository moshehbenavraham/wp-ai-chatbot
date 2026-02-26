# MCP (Model Context Protocol) Add-on

## Overview
This add-on allows AxiaChat AI bots to connect to external MCP servers and expose their tools to the AI models. MCP is a standardized protocol (JSON-RPC 2.0) for tool integration across different platforms.

## Features
- **HTTP Transport**: Connect to remote MCP servers over HTTP/HTTPS
- **Transport Hardening**: Local STDIO subprocess support has been removed for security and compatibility
- **Authentication**: Bearer tokens, API keys, custom headers
- **Tool Discovery**: Automatically list and register tools from connected servers
- **Per-Bot Configuration**: Enable/disable specific MCP servers per bot (TODO)
- **Admin UI**: Visual interface to add, edit, test, and delete servers
- **Test Connection**: Verify connectivity and view available tools before saving

## Directory Structure
```
includes/add-ons/mcp/
├── loader.php                      # Entry point, enable check
├── class-mcp-transport.php         # Abstract base class for transports
├── transports/
│   └── class-http-transport.php    # HTTP/HTTPS transport
├── class-mcp-client-manager.php    # Singleton managing all server connections
├── integration.php                 # Registers MCP tools into AI Tools system
├── admin-settings.php              # Admin page UI
├── admin-ajax.php                  # AJAX handlers for CRUD operations
└── README.md                       # This file
```

## Usage

### 1. Enable the Add-on
Go to **AI Chat → Settings → Add-ons** and enable:
- ✅ Enable AI Tools (required)
- ✅ Enable MCP (Model Context Protocol)

Save changes.

### 2. Add an MCP Server
Navigate to **AI Chat → MCP Servers** and click "Add Server".

#### HTTP Server Example (Sentry, Notion, etc.)
- **Server Name**: Sentry Tools
- **Transport Type**: HTTP/HTTPS
- **Server URL**: `https://api.sentry.io/mcp` (example)
- **Authentication**: Bearer Token
- **Token**: `your-sentry-api-token`

### 3. Test Connection
Click **Test** to verify the server is reachable and view available tools.

### 4. Use in Bots
Once a server is connected, its tools are automatically registered in the AI Tools system with the prefix `mcp_servername_toolname`. A macro `mcp_servername` is also created grouping all tools from that server.

Enable the macro in **AI Chat → Bots → [Your Bot] → Capabilities → Macros**.

## Architecture

### Transport Layer
- **AIChat_MCP_Transport** (abstract): Defines interface for `connect()`, `send_request()`, `close()`
- **AIChat_MCP_HTTP_Transport**: Uses `wp_remote_post()` for HTTP requests

### Client Manager
- **AIChat_MCP_Client_Manager** (singleton): Manages all active sessions
  - `connect_server()`: Creates transport, performs MCP handshake
  - `discover_tools()`: Calls `tools/list` and caches results
  - `execute_tool()`: Routes tool calls to appropriate server
  - `get_tools_for_bot()`: Returns tools filtered by bot configuration

### Integration
- Tools registered via `aichat_register_tool_safe()`
- Callback uses proxy pattern to route to Client Manager
- Macros auto-created per server for easy bot configuration

## MCP Protocol Details

### Handshake Sequence
1. Client → Server: `initialize` (protocol version, capabilities)
2. Server → Client: `result` (server info, capabilities)
3. Client → Server: `notifications/initialized`
4. Connection established

### Tool Discovery
- Client → Server: `tools/list`
- Server → Client: Array of tool definitions (name, description, inputSchema)

### Tool Execution
- Client → Server: `tools/call` (name, arguments)
- Server → Client: Content array (text, image, resource)

## Supported MCP Capabilities
- ✅ **tools**: Tool discovery and execution
- ⏳ **resources**: Context/data injection (planned Phase 3)
- ⏳ **prompts**: Template/instruction sharing (planned Phase 3)

## Security Considerations
- All API keys/tokens stored in WordPress options (not version controlled)
- HTTP transport validates URLs via `esc_url_raw()`
- AJAX endpoints protected with nonce (`aichat_mcp_ajax`)
- Admin UI restricted to `manage_options` capability

## Troubleshooting

### Connection Fails (HTTP)
- Verify URL is correct and reachable
- Check authentication credentials
- Review WordPress error logs for HTTP API errors

### Tools Not Appearing
- Confirm add-on is enabled in Settings → Add-ons
- Check that MCP server test shows tools in discovery
- Verify bot has the `mcp_servername` macro enabled in Capabilities

## Roadmap

### Phase 1: Foundation (Current) ✅
- Transport abstraction (HTTP)
- Client manager with session handling
- MCP handshake and tool discovery
- Admin UI for server management
- Basic integration with AI Tools

### Phase 2: Per-Bot Configuration (Next)
- Filter tools by bot in `get_tools_for_bot()`
- UI to select which servers each bot can access
- Per-server enable/disable toggle

### Phase 3: Resources & Prompts
- Implement `resources/list` and `resources/read`
- Inject resource content as context
- Implement `prompts/list` and `prompts/get`
- Use MCP prompts as instruction templates

### Phase 4: Advanced Features
- Connection pooling and health checks
- Automatic reconnection on failure
- Rate limiting per server
- Tool execution logging and analytics

## Development Notes

### Extending the Transport Layer
While only HTTP is bundled, the architecture still allows additional transports if requirements change:
1. Create a class extending `AIChat_MCP_Transport`
2. Implement `connect()`, `send_request()`, `close()`
3. Handle errors via `$this->last_error`
4. Load the new transport in `loader.php`

### Registering Custom MCP Servers Programmatically
```php
$servers = get_option( 'aichat_mcp_servers', [] );
$servers['my_server'] = [
    'name'      => 'My MCP Server',
    'transport' => 'http',
    'url'       => 'https://api.example.com/mcp',
    'auth_type' => 'bearer',
    'auth_token' => 'secret-token',
];
update_option( 'aichat_mcp_servers', $servers );
```

## References
- [Model Context Protocol Specification](https://spec.modelcontextprotocol.io/)
- [MCP Protocol Version: 2025-06-18](https://spec.modelcontextprotocol.io/specification/2025-06-18/)
- [JSON-RPC 2.0 Specification](https://www.jsonrpc.org/specification)

## Support
For issues or feature requests, contact the AxiaChat AI development team or open an issue in the project repository.
