<?php
/**
 * OpenAI Service Class
 *
 * Handles ChatGPT integration for intelligent intent detection
 * and conversational search in Podcast Prospector.
 *
 * @package Podcast_Prospector
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Podcast_Prospector_OpenAI_Service
 *
 * Provides secure proxying of OpenAI API requests with intent detection,
 * streaming responses, and cost tracking.
 */
class Podcast_Prospector_OpenAI_Service {

    /**
     * OpenAI API base URL.
     *
     * @var string
     */
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * Singleton instance.
     *
     * @var Podcast_Prospector_OpenAI_Service|null
     */
    private static ?Podcast_Prospector_OpenAI_Service $instance = null;

    /**
     * Settings instance.
     *
     * @var Podcast_Prospector_Settings
     */
    private Podcast_Prospector_Settings $settings;

    /**
     * API key.
     *
     * @var string
     */
    private string $api_key = '';

    /**
     * Default model.
     *
     * @var string
     */
    private string $model = 'gpt-4o-mini';

    /**
     * System prompt for intent detection.
     *
     * @var string
     */
    private const INTENT_SYSTEM_PROMPT = <<<'PROMPT'
You are a podcast search assistant. Your job is to analyze user queries and extract search intent for a podcast discovery tool.

Analyze the user's message and return a JSON response with:
- intent: One of "search_by_person", "search_by_topic", "search_by_title", "filter", "show_more", "clarify", "general"
- extracted_query: The actual search term to use (name, topic, or title)
- filters: Any filters mentioned (language, country, genre, date_range)
- confidence: "high", "medium", or "low"
- followup_question: Optional question if clarification is needed

Examples:
User: "Find podcasts featuring Tim Ferriss"
Response: {"intent": "search_by_person", "extracted_query": "Tim Ferriss", "filters": {}, "confidence": "high", "followup_question": null}

User: "I want podcasts about machine learning in Spanish"
Response: {"intent": "search_by_topic", "extracted_query": "machine learning", "filters": {"language": "es"}, "confidence": "high", "followup_question": null}

User: "show me more"
Response: {"intent": "show_more", "extracted_query": null, "filters": {}, "confidence": "high", "followup_question": null}

Always respond with valid JSON only, no markdown formatting.
PROMPT;

    /**
     * Get singleton instance.
     *
     * @return Podcast_Prospector_OpenAI_Service
     */
    public static function get_instance(): Podcast_Prospector_OpenAI_Service {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton pattern.
     */
    private function __construct() {
        $this->settings = Podcast_Prospector_Settings::get_instance();
        $this->api_key  = $this->settings->get( 'openai_api_key', '' );
        $this->model    = $this->settings->get( 'chatgpt_model', 'gpt-4o-mini' );
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        register_rest_route( 'podcast-prospector/v1', '/chat/intent', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_intent_detection' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'message' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'context' => [
                    'required' => false,
                    'type'     => 'array',
                    'default'  => [],
                ],
            ],
        ] );

        register_rest_route( 'podcast-prospector/v1', '/chat/stream', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_streaming_response' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'message' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'context' => [
                    'required' => false,
                    'type'     => 'array',
                    'default'  => [],
                ],
            ],
        ] );
    }

    /**
     * Check if user has permission to access the API.
     *
     * @return bool|WP_Error
     */
    public function check_permission() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to use the chat feature.', 'podcast-prospector' ),
                [ 'status' => 401 ]
            );
        }

        // Check if ChatGPT feature is enabled
        if ( ! $this->is_enabled() ) {
            return new WP_Error(
                'chatgpt_disabled',
                __( 'ChatGPT integration is not enabled.', 'podcast-prospector' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Check if ChatGPT integration is enabled and configured.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        $enabled = (bool) $this->settings->get( 'chatgpt_enabled', false );
        $has_key = ! empty( $this->api_key );

        return $enabled && $has_key;
    }

    /**
     * Handle intent detection request.
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_intent_detection( WP_REST_Request $request ) {
        $message = $request->get_param( 'message' );
        $context = $request->get_param( 'context' ) ?? [];

        // Track usage
        $this->log_usage( 'intent_detection', strlen( $message ) );

        // Build messages array
        $messages = [
            [
                'role'    => 'system',
                'content' => self::INTENT_SYSTEM_PROMPT,
            ],
        ];

        // Add conversation context if provided
        if ( ! empty( $context ) && is_array( $context ) ) {
            foreach ( array_slice( $context, -4 ) as $ctx ) { // Last 4 messages for context
                if ( isset( $ctx['role'] ) && isset( $ctx['content'] ) ) {
                    $messages[] = [
                        'role'    => sanitize_text_field( $ctx['role'] ),
                        'content' => sanitize_text_field( $ctx['content'] ),
                    ];
                }
            }
        }

        // Add current user message
        $messages[] = [
            'role'    => 'user',
            'content' => $message,
        ];

        // Make API request
        $response = $this->make_api_request( $messages, [
            'max_tokens'  => 500,
            'temperature' => 0.3, // Low temperature for consistent intent detection
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Parse the response
        $content = $response['choices'][0]['message']['content'] ?? '';
        $parsed  = $this->parse_intent_response( $content );

        // Track token usage for cost logging
        $this->log_tokens(
            $response['usage']['prompt_tokens'] ?? 0,
            $response['usage']['completion_tokens'] ?? 0
        );

        return new WP_REST_Response( [
            'success' => true,
            'intent'  => $parsed,
            'raw'     => defined( 'WP_DEBUG' ) && WP_DEBUG ? $content : null,
        ], 200 );
    }

    /**
     * Handle streaming response (Server-Sent Events).
     *
     * @param WP_REST_Request $request REST request object.
     * @return void|WP_Error
     */
    public function handle_streaming_response( WP_REST_Request $request ) {
        $message = $request->get_param( 'message' );
        $context = $request->get_param( 'context' ) ?? [];

        // Set SSE headers
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'Connection: keep-alive' );
        header( 'X-Accel-Buffering: no' ); // Disable nginx buffering

        // Disable output buffering
        if ( ob_get_level() ) {
            ob_end_flush();
        }

        // Build messages
        $messages = [
            [
                'role'    => 'system',
                'content' => 'You are a helpful podcast search assistant. Help users find podcasts and answer questions about podcast discovery. Be concise and friendly.',
            ],
        ];

        // Add context
        if ( ! empty( $context ) ) {
            foreach ( array_slice( $context, -6 ) as $ctx ) {
                if ( isset( $ctx['role'] ) && isset( $ctx['content'] ) ) {
                    $messages[] = [
                        'role'    => sanitize_text_field( $ctx['role'] ),
                        'content' => sanitize_text_field( $ctx['content'] ),
                    ];
                }
            }
        }

        $messages[] = [
            'role'    => 'user',
            'content' => $message,
        ];

        // Make streaming request
        $this->stream_api_request( $messages );

        exit;
    }

    /**
     * Make a non-streaming API request.
     *
     * @param array $messages Messages array.
     * @param array $options  Additional options.
     * @return array|WP_Error
     */
    private function make_api_request( array $messages, array $options = [] ) {
        $body = wp_json_encode( array_merge( [
            'model'    => $this->model,
            'messages' => $messages,
            'stream'   => false,
        ], $options ) );

        $response = wp_remote_post( self::API_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code !== 200 ) {
            $error_message = $data['error']['message'] ?? 'Unknown API error';
            return new WP_Error(
                'openai_error',
                $error_message,
                [ 'status' => $status_code ]
            );
        }

        return $data;
    }

    /**
     * Stream API response using SSE.
     *
     * @param array $messages Messages array.
     * @return void
     */
    private function stream_api_request( array $messages ): void {
        $body = wp_json_encode( [
            'model'       => $this->model,
            'messages'    => $messages,
            'stream'      => true,
            'max_tokens'  => 1000,
            'temperature' => 0.7,
        ] );

        // Use cURL for streaming
        $ch = curl_init( self::API_URL );

        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
            ],
            CURLOPT_WRITEFUNCTION  => function ( $curl, $data ) {
                // Parse SSE data from OpenAI
                $lines = explode( "\n", $data );

                foreach ( $lines as $line ) {
                    $line = trim( $line );
                    if ( empty( $line ) || $line === 'data: [DONE]' ) {
                        continue;
                    }

                    if ( strpos( $line, 'data: ' ) === 0 ) {
                        $json = json_decode( substr( $line, 6 ), true );
                        if ( $json && isset( $json['choices'][0]['delta']['content'] ) ) {
                            $content = $json['choices'][0]['delta']['content'];
                            // Send SSE event to client
                            echo "data: " . wp_json_encode( [ 'content' => $content ] ) . "\n\n";
                            flush();
                        }
                    }
                }

                return strlen( $data );
            },
        ] );

        curl_exec( $ch );
        curl_close( $ch );

        // Send done event
        echo "data: [DONE]\n\n";
        flush();
    }

    /**
     * Parse intent detection response from GPT.
     *
     * @param string $content Raw GPT response.
     * @return array Parsed intent data.
     */
    private function parse_intent_response( string $content ): array {
        $default = [
            'intent'            => 'general',
            'extracted_query'   => null,
            'filters'           => [],
            'confidence'        => 'low',
            'followup_question' => null,
        ];

        // Try to parse as JSON
        $parsed = json_decode( $content, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $parsed ) ) {
            // If not valid JSON, try to extract from potential markdown
            if ( preg_match( '/```json?\s*(.*?)\s*```/s', $content, $matches ) ) {
                $parsed = json_decode( $matches[1], true );
            }
        }

        if ( ! is_array( $parsed ) ) {
            return $default;
        }

        // Validate and merge with defaults
        $valid_intents = [
            'search_by_person',
            'search_by_topic',
            'search_by_title',
            'filter',
            'show_more',
            'clarify',
            'general',
        ];

        return [
            'intent'            => in_array( $parsed['intent'] ?? '', $valid_intents, true )
                ? $parsed['intent']
                : 'general',
            'extracted_query'   => isset( $parsed['extracted_query'] )
                ? sanitize_text_field( $parsed['extracted_query'] )
                : null,
            'filters'           => is_array( $parsed['filters'] ?? null )
                ? array_map( 'sanitize_text_field', $parsed['filters'] )
                : [],
            'confidence'        => in_array( $parsed['confidence'] ?? '', [ 'high', 'medium', 'low' ], true )
                ? $parsed['confidence']
                : 'low',
            'followup_question' => isset( $parsed['followup_question'] )
                ? sanitize_text_field( $parsed['followup_question'] )
                : null,
        ];
    }

    /**
     * Log API usage for monitoring.
     *
     * @param string $type   Type of usage.
     * @param int    $chars  Character count.
     * @return void
     */
    private function log_usage( string $type, int $chars ): void {
        if ( ! $this->settings->get( 'debug_logging_enabled', false ) ) {
            return;
        }

        $user_id = get_current_user_id();
        $log     = get_option( 'prospector_openai_usage_log', [] );

        $log[] = [
            'timestamp' => current_time( 'mysql' ),
            'user_id'   => $user_id,
            'type'      => $type,
            'chars'     => $chars,
        ];

        // Keep only last 1000 entries
        if ( count( $log ) > 1000 ) {
            $log = array_slice( $log, -1000 );
        }

        update_option( 'prospector_openai_usage_log', $log );
    }

    /**
     * Log token usage for cost tracking.
     *
     * @param int $prompt_tokens     Input tokens.
     * @param int $completion_tokens Output tokens.
     * @return void
     */
    private function log_tokens( int $prompt_tokens, int $completion_tokens ): void {
        $total   = get_option( 'prospector_openai_token_total', 0 );
        $monthly = get_option( 'prospector_openai_token_monthly', [
            'month'  => gmdate( 'Y-m' ),
            'tokens' => 0,
        ] );

        // Reset monthly if new month
        if ( $monthly['month'] !== gmdate( 'Y-m' ) ) {
            $monthly = [
                'month'  => gmdate( 'Y-m' ),
                'tokens' => 0,
            ];
        }

        $tokens_used = $prompt_tokens + $completion_tokens;

        update_option( 'prospector_openai_token_total', $total + $tokens_used );
        update_option( 'prospector_openai_token_monthly', [
            'month'  => $monthly['month'],
            'tokens' => $monthly['tokens'] + $tokens_used,
        ] );
    }

    /**
     * Get usage statistics.
     *
     * @return array
     */
    public function get_usage_stats(): array {
        $monthly = get_option( 'prospector_openai_token_monthly', [
            'month'  => gmdate( 'Y-m' ),
            'tokens' => 0,
        ] );

        // Estimate cost (GPT-4o-mini: ~$0.15 per 1M input, ~$0.60 per 1M output)
        // Using average estimate of $0.30 per 1M tokens
        $estimated_cost = ( $monthly['tokens'] / 1000000 ) * 0.30;

        return [
            'total_tokens'         => get_option( 'prospector_openai_token_total', 0 ),
            'monthly_tokens'       => $monthly['tokens'],
            'current_month'        => $monthly['month'],
            'estimated_cost_usd'   => round( $estimated_cost, 4 ),
            'model'                => $this->model,
            'enabled'              => $this->is_enabled(),
        ];
    }
}

// Initialize the service
add_action( 'init', function() {
    if ( class_exists( 'Podcast_Prospector_Settings' ) ) {
        $service = Podcast_Prospector_OpenAI_Service::get_instance();
        $service->init();
    }
} );
