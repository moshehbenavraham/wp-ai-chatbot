# Incident Response

## Severity Levels

| Level | Description | Response Time |
|-------|-------------|---------------|
| P0 | Plugin crashes or breaks WordPress | Immediate |
| P1 | Chat functionality down | < 1 hour |
| P2 | Feature degraded (e.g., slow responses) | < 4 hours |
| P3 | Cosmetic or minor issue | Next business day |

## Common Incidents

### Plugin Activation Failure

**Symptoms**: White screen or PHP fatal error on activation.

**Resolution**:
1. Check PHP version meets minimum (>=7.4)
2. Check WordPress version compatibility
3. Review `wp-content/debug.log` for error details
4. Deactivate via WP-CLI: `wp plugin deactivate wp-ai-chatbot`

### AI Provider API Errors

**Symptoms**: Chat returns error messages or timeouts.

**Resolution**:
1. Verify API key is valid in plugin settings
2. Check provider status page (OpenAI, Anthropic, Google)
3. Review rate limiting: `lib/rate-limiter.php` transient values
4. Check health endpoint: `GET /wp-json/wp-ai-chatbot/v1/health`

### High Memory Usage

**Symptoms**: PHP memory limit errors during indexing or chat.

**Resolution**:
1. Check health endpoint memory report
2. Increase PHP memory limit if needed
3. Review background processing queue size

### Database Issues

**Symptoms**: Settings not saving, chat history lost.

**Resolution**:
1. Check health endpoint DB connectivity report
2. Verify WordPress database credentials
3. Check `wp_options` table for plugin prefix entries
