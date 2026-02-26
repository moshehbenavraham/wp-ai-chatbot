<?php
/**
 * Registro central de proveedores AI
 * 
 * Implementa patrón Singleton + Factory para gestionar proveedores disponibles.
 * Permite registro dinámico, instanciación con cache y validación de disponibilidad.
 * 
 * @package AIChat
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AIChat_Provider_Registry {
    
    /**
     * Instancia singleton
     * @var AIChat_Provider_Registry|null
     */
    private static $instance = null;
    
    /**
     * Proveedores registrados
     * @var array ['provider_id' => ['class' => 'ClassName', 'enabled' => bool]]
     */
    private $providers = [];
    
    /**
     * Cache de instancias de adapters
     * @var array ['cache_key' => AIChat_Provider_Interface]
     */
    private $adapter_instances = [];
    
    /**
     * Obtener instancia singleton
     * 
     * @return AIChat_Provider_Registry
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado (Singleton pattern)
     */
    private function __construct() {
        // Hook para que add-ons registren sus proveedores
        do_action( 'aichat_register_providers', $this );
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug( '[AIChat Registry] Initialized with ' . count($this->providers) . ' providers', [], true );
        }
    }
    
    /**
     * Prevenir clonación (Singleton pattern)
     */
    private function __clone() {}
    
    /**
     * Prevenir unserialize (Singleton pattern)
     */
    public function __wakeup() {
        throw new Exception( "Cannot unserialize singleton" );
    }
    
    /**
     * Registrar un proveedor
     * 
     * @param string $id ID único del proveedor ('openai', 'claude', 'ollama', etc.)
     * @param string $class_name Nombre de la clase que implementa AIChat_Provider_Interface
     * @param bool $enabled Si el proveedor está habilitado (default: true)
     * 
     * @return bool True si se registró correctamente, false si falló
     */
    public function register( $id, $class_name, $enabled = true ) {
        // Validar que la clase existe
        if ( ! class_exists( $class_name ) ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "[AIChat Registry] Class not found: {$class_name}", [], true );
            }
            return false;
        }
        
        // Registrar proveedor
        $this->providers[ $id ] = [
            'class'   => $class_name,
            'enabled' => $enabled
        ];
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug( "[AIChat Registry] Registered provider: {$id}", [
                'class' => $class_name,
                'enabled' => $enabled
            ], true );
        }
        
        return true;
    }
    
    /**
     * Obtener instancia de un proveedor (Factory Pattern)
     * 
     * @param string $id ID del proveedor
     * @param array $config Configuración a pasar al constructor del adapter
     * @param bool $cached Si true, reutiliza instancias cacheadas (recomendado para performance)
     * 
     * @return AIChat_Provider_Interface|null Instancia del adapter o null si no disponible
     */
    public function get( $id, $config = [], $cached = true ) {
        // Normalizar ID (anthropic → claude para compatibilidad)
        if ( $id === 'anthropic' ) {
            $id = 'claude';
        }
        
        // Verificar que el proveedor está registrado
        if ( ! isset( $this->providers[ $id ] ) ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "[AIChat Registry] Provider not registered: {$id}", [], true );
            }
            return null;
        }
        
        // Generar cache key basado en ID y config (para soportar múltiples API keys en futuro)
        $cache_key = $id . '_' . md5( wp_json_encode( $config ) );
        
        // Retornar instancia cacheada si existe y se permite cache
        if ( $cached && isset( $this->adapter_instances[ $cache_key ] ) ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "[AIChat Registry] Returning cached instance: {$id}", [], true );
            }
            return $this->adapter_instances[ $cache_key ];
        }
        
        // Crear nueva instancia
        $class = $this->providers[ $id ]['class'];
        
        try {
            $instance = new $class( $config );
            
            // Validar que implementa la interfaz correcta
            if ( ! $instance instanceof AIChat_Provider_Interface ) {
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug( "[AIChat Registry] Class doesn't implement interface: {$class}", [], true );
                }
                return null;
            }
            
            // Cachear instancia si está habilitado
            if ( $cached ) {
                $this->adapter_instances[ $cache_key ] = $instance;
                
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug( "[AIChat Registry] Cached new instance: {$id}", [], true );
                }
            }
            
            return $instance;
            
        } catch ( Exception $e ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "[AIChat Registry] Failed to instantiate provider: {$id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], true );
            }
            return null;
        }
    }
    
    /**
     * Verificar si un proveedor está disponible
     * 
     * @param string $id ID del proveedor
     * @return bool True si está registrado y habilitado
     */
    public function is_available( $id ) {
        // Normalizar ID
        if ( $id === 'anthropic' ) {
            $id = 'claude';
        }
        
        return isset( $this->providers[ $id ] ) && ! empty( $this->providers[ $id ]['enabled'] );
    }
    
    /**
     * Listar todos los proveedores registrados
     * 
     * @param bool $enabled_only Si true, solo retorna proveedores habilitados
     * @return array Array de proveedores ['id' => ['class' => '...', 'enabled' => bool]]
     */
    public function get_all( $enabled_only = false ) {
        if ( ! $enabled_only ) {
            return $this->providers;
        }
        
        return array_filter( $this->providers, function( $provider ) {
            return ! empty( $provider['enabled'] );
        });
    }
    
    /**
     * Habilitar o deshabilitar un proveedor
     * 
     * @param string $id ID del proveedor
     * @param bool $enabled True para habilitar, false para deshabilitar
     * @return bool True si se actualizó, false si el proveedor no existe
     */
    public function set_enabled( $id, $enabled ) {
        if ( ! isset( $this->providers[ $id ] ) ) {
            return false;
        }
        
        $this->providers[ $id ]['enabled'] = (bool) $enabled;
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            $status = $enabled ? 'enabled' : 'disabled';
            aichat_log_debug( "[AIChat Registry] Provider {$status}: {$id}", [], true );
        }
        
        return true;
    }
    
    /**
     * Limpiar cache de instancias
     * 
     * Útil para testing o cuando se cambia la configuración de un proveedor.
     * 
     * @param string|null $id ID del proveedor específico, o null para limpiar todo
     */
    public function clear_cache( $id = null ) {
        if ( $id === null ) {
            // Limpiar todo el cache
            $count = count( $this->adapter_instances );
            $this->adapter_instances = [];
            
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "[AIChat Registry] Cleared all cache ({$count} instances)", [], true );
            }
        } else {
            // Limpiar cache de un proveedor específico
            $cleared = 0;
            foreach ( $this->adapter_instances as $key => $instance ) {
                if ( strpos( $key, $id . '_' ) === 0 ) {
                    unset( $this->adapter_instances[ $key ] );
                    $cleared++;
                }
            }
            
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "[AIChat Registry] Cleared cache for provider: {$id} ({$cleared} instances)", [], true );
            }
        }
    }
    
    /**
     * Obtener estadísticas del registry
     * 
     * @return array ['total_providers' => int, 'enabled_providers' => int, 'cached_instances' => int]
     */
    public function get_stats() {
        $enabled_count = count( array_filter( $this->providers, function( $p ) {
            return ! empty( $p['enabled'] );
        }));
        
        return [
            'total_providers'   => count( $this->providers ),
            'enabled_providers' => $enabled_count,
            'cached_instances'  => count( $this->adapter_instances )
        ];
    }
}
