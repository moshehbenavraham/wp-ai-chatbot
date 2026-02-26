# LiveChat Add-on - Arquitectura

## Resumen

El add-on de LiveChat permite que un agente humano tome el control de una conversación de chat, reemplazando temporalmente al bot de IA.

---

## Estados del Frontend

El widget del chat puede estar en **3 estados**:

| Estado | Código | Descripción | Polling |
|--------|--------|-------------|---------|
| **1. Bot** | `bot` | Conversación normal con el bot IA | ❌ No |
| **2. Pendiente** | `pending_agent` | Usuario solicitó agente, esperando que uno tome el control | ✅ Sí (3s) |
| **3. Conectado** | `agent_connected` | Agente humano ha tomado el control | ✅ Sí (3s) |

### Estado adicional (solo en BD):
| Estado | Código | Descripción |
|--------|--------|-------------|
| **Cerrado** | `closed` | Sesión finalizada por el agente |

---

## Tablas de Base de Datos

### 1. `wp_aichat_livechat_sessions`

Almacena el **estado de la sesión** de livechat para cada usuario.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | BIGINT | ID auto-incremental |
| `session_id` | VARCHAR(64) | **Clave principal**: mismo que la cookie `aichat_sid` del usuario |
| `status` | VARCHAR(32) | Estado: `bot`, `pending_agent`, `agent_connected`, `closed` |
| `agent_id` | BIGINT | ID del usuario WP del agente (null si no hay agente) |
| `agent_name` | VARCHAR(255) | Nombre del agente para mostrar |
| `priority` | TINYINT | Prioridad en cola (0-3) |
| `handoff_reason` | TEXT | Razón por la que se solicitó agente |
| `visitor_name` | VARCHAR(255) | Nombre del visitante (si se recopiló) |
| `created_at` | DATETIME | Cuándo se creó la sesión |
| `updated_at` | DATETIME | Última actualización |
| `closed_at` | DATETIME | Cuándo se cerró (null si activa) |

### 2. `wp_aichat_livechat_messages`

Almacena los **mensajes intercambiados** durante el modo livechat (cuando el agente tiene control).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | BIGINT | ID auto-incremental (usado como `last_id` para polling) |
| `session_id` | VARCHAR(64) | Referencia a la sesión |
| `sender_type` | ENUM | `visitor`, `agent`, `bot` |
| `sender_id` | BIGINT | ID del usuario WP (solo para agentes) |
| `sender_name` | VARCHAR(255) | Nombre para mostrar |
| `message` | TEXT | Contenido del mensaje |
| `created_at` | DATETIME | Timestamp del mensaje |

---

## Flujos de Comunicación

### Flujo 1: Usuario solicita agente

```
[Frontend Widget]                    [Backend PHP]                      [BD]
      |                                    |                              |
      |-- "Quiero hablar con humano" ---->|                              |
      |                                    |                              |
      |                        [Bot detecta intención]                   |
      |                        [Ejecuta tool: escalate_to_livechat]      |
      |                                    |                              |
      |                                    |-- INSERT/UPDATE session ---->|
      |                                    |   status = 'pending_agent'   |
      |                                    |                              |
      |<-- "Conectando con agente..." ----|                              |
      |                                    |                              |
      |== INICIA POLLING cada 3s =========|                              |
```

### Flujo 2: Agente toma control

```
[Dashboard Admin]                    [Backend PHP]                      [BD]
      |                                    |                              |
      |-- "Tomar sesión" ---------------->|                              |
      |                                    |                              |
      |                                    |-- UPDATE session ----------->|
      |                                    |   status = 'agent_connected' |
      |                                    |   agent_id = X               |
      |                                    |                              |
      |<-- "Sesión tomada" ---------------|                              |
      |                                    |                              |

[Frontend Widget]                                                        |
      |                                                                   |
      |== POLLING detecta cambio =========================================|
      |   status: pending_agent -> agent_connected                       |
      |                                                                   |
      |-- Muestra: "Conectado con [agente]"                              |
```

### Flujo 3: Agente envía mensaje

```
[Dashboard Admin]                    [Backend PHP]                      [BD]
      |                                    |                              |
      |-- "Hola, ¿en qué puedo ayudar?" ->|                              |
      |                                    |                              |
      |                                    |-- INSERT message ----------->|
      |                                    |   sender_type = 'agent'      |
      |                                    |   message = "Hola..."        |
      |                                    |                              |
      |<-- OK (message_id = 5) -----------|                              |

[Frontend Widget]                                                        |
      |                                                                   |
      |== POLLING: GET messages WHERE id > last_id =======================|
      |                                                                   |
      |<-- messages: [{id:5, sender_type:'agent', message:'Hola...'}] ---|
      |                                                                   |
      |-- Muestra mensaje en el widget                                   |
      |-- Actualiza last_id = 5                                          |
```

### Flujo 4: Usuario envía mensaje (con agente conectado)

```
[Frontend Widget]                    [Backend PHP]                      [BD]
      |                                    |                              |
      |-- "Tengo una duda sobre X" ------>|                              |
      |                                    |                              |
      |                        [Verifica status de session]              |
      |                        [Status = agent_connected]                |
      |                        [NO procesar con IA]                      |
      |                                    |                              |
      |                                    |-- INSERT message ----------->|
      |                                    |   sender_type = 'visitor'    |
      |                                    |   message = "Tengo..."       |
      |                                    |                              |
      |<-- OK (respuesta vacía) ----------|                              |

[Dashboard Admin]                                                        |
      |                                                                   |
      |== POLLING: GET messages WHERE sender_type = 'visitor' ============|
      |                                                                   |
      |<-- messages: [{sender_type:'visitor', message:'Tengo...'}] ------|
      |                                                                   |
      |-- Muestra mensaje en el dashboard                                |
```

---

## Endpoints AJAX

### Frontend (widget del visitante)

| Action | Descripción | Cuándo se usa |
|--------|-------------|---------------|
| `aichat_livechat_poll` | Obtiene status + mensajes nuevos del agente | Polling cada 3s en estados 2 y 3 |

**Request:**
```json
{
  "action": "aichat_livechat_poll",
  "session_id": "778648af-...",
  "last_id": 5
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "agent_connected",
    "agent_name": "Juan",
    "messages": [
      {"id": 6, "sender_type": "agent", "message": "Hola", "created_at": "..."}
    ]
  }
}
```

### Backend (dashboard del agente)

| Action | Descripción |
|--------|-------------|
| `aichat_livechat_get_sessions` | Lista sesiones pendientes/activas |
| `aichat_livechat_take_session` | Agente toma control de una sesión |
| `aichat_livechat_send_message` | Agente envía mensaje al visitante |
| `aichat_livechat_get_messages` | Obtiene mensajes del visitante (polling) |
| `aichat_livechat_close_session` | Cierra la sesión |
| `aichat_livechat_return_to_bot` | Devuelve control al bot |

---

## Archivos Principales

```
includes/add-ons/livechat/
├── loader.php                 # Carga el add-on, registra scripts
├── class-livechat-manager.php # Lógica de negocio (estados, mensajes)
├── integration.php            # Registra el tool para el bot
├── admin-ajax.php             # Handlers AJAX (frontend + backend)
├── admin-dashboard.php        # UI del dashboard del agente
├── admin-settings.php         # Configuración del add-on
└── assets/
    ├── livechat-frontend.js   # Polling del widget
    ├── livechat-frontend.css
    ├── livechat-admin.js      # UI del dashboard
    └── livechat-admin.css
```

---

## Simplificaciones Propuestas

### 1. Una sola tabla de mensajes
La tabla `wp_aichat_livechat_messages` almacena TODO el intercambio durante el modo livechat. El `sender_type` distingue quién envió cada mensaje.

### 2. Polling bidireccional
- **Frontend → `aichat_livechat_poll`**: Obtiene mensajes del agente (`sender_type = 'agent'`)
- **Backend → `aichat_livechat_get_messages`**: Obtiene mensajes del visitante (`sender_type = 'visitor'`)

### 3. Estado centralizado
El estado está SOLO en `wp_aichat_livechat_sessions.status`. Tanto frontend como backend consultan esta misma fuente de verdad.

### 4. Bloqueo del bot
Cuando `status = 'pending_agent'` o `status = 'agent_connected'`:
- El filtro `aichat_should_process_with_ai` devuelve `false`
- El mensaje del usuario se guarda en `wp_aichat_livechat_messages`
- NO se envía a la IA

---

## Diagrama de Estados

```
                    ┌─────────────┐
                    │             │
       ┌───────────►│     BOT     │◄───────────┐
       │            │             │            │
       │            └──────┬──────┘            │
       │                   │                   │
       │    [Usuario pide agente]              │
       │                   │                   │
       │                   ▼                   │
       │            ┌─────────────┐            │
       │            │             │            │
       │            │   PENDING   │            │
       │            │   AGENT     │            │
       │            │             │            │
       │            └──────┬──────┘            │
       │                   │                   │
       │     [Agente toma control]             │
       │                   │                   │
       │                   ▼                   │
       │            ┌─────────────┐            │
       │            │             │            │
       │            │   AGENT     │────────────┤
       │            │  CONNECTED  │            │
       │            │             │  [Return   │
       │            └──────┬──────┘  to bot]   │
       │                   │                   │
       │      [Agente cierra]                  │
       │                   │                   │
       │                   ▼                   │
       │            ┌─────────────┐            │
       │            │             │            │
       └────────────│   CLOSED    │────────────┘
    [Usuario pide   │             │   [Timeout]
     agente otra    └─────────────┘
     vez: reabre]
```

---

## Checklist de Implementación

- [ ] Tabla `sessions` creada correctamente
- [ ] Tabla `messages` creada correctamente
- [ ] Tool `escalate_to_livechat` registrado y funcional
- [ ] Frontend polling funciona en estados 2 y 3
- [ ] Backend polling funciona para mensajes del visitante
- [ ] Bloqueo del bot activo en estados 2 y 3
- [ ] Mensajes del visitante se guardan cuando agente tiene control
- [ ] Mensajes del agente llegan al widget
- [ ] Dashboard muestra sesiones activas
- [ ] Dashboard puede tomar/cerrar sesiones
