<?php

namespace PostCalendar\Event_Sources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Page {
	private const PAGE_SLUG    = 'post-calendar';
	private const OPTION_NAME  = 'post_calendar_post_types';
	private const REMOVE_EVENTS_ACTION = 'post_calendar_remove_events';
	private const REMOVE_EVENTS_NONCE  = 'post_calendar_remove_events_nonce';
	private const REMOVE_EVENTS_POST_FIELD = 'post_calendar_remove_post_type';
	private const NOTICE_POST_TYPE_ARG = 'post_calendar_post_type';
	private const NOTICE_REMOVED_ARG   = 'post_calendar_removed';
	private const EVENT_ENABLED_META = Event_Config::EVENT_HAS_EVENTS_META;
	private const EVENTS_META = Event_Config::EVENTS_META;
	private const EVENT_RANGE_START_META = Event_Config::EVENT_RANGE_START_META;
	private const EVENT_RANGE_END_META = Event_Config::EVENT_RANGE_END_META;
	private const EXCLUDED_POST_TYPES = array(
		'acf-field-group',
		'acf-post-type',
		'acf-taxonomy',
		'acf-ui-options-page',
		'bricks_fonts',
		'bricks_template',
		// Internal virtual type - must never appear as a selectable event source.
		'post_calendar_event',
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::REMOVE_EVENTS_ACTION, array( $this, 'handle_remove_events_action' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting(
			'post_calendar',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
				'default'           => self::get_default_post_types(),
			)
		);
	}

	public function register_menu(): void {
		add_options_page(
			esc_html__( 'Post Calendar', 'post-calendar' ),
			esc_html__( 'Post Calendar', 'post-calendar' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->maybe_add_action_notice();

		$post_types          = self::get_selectable_post_types();
		$selected_post_types = self::get_allowed_post_types();
		$event_counts        = self::get_event_counts_for_post_types( array_keys( $post_types ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Post Calendar', 'post-calendar' ); ?></h1>

			<?php settings_errors( 'post_calendar' ); ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'post_calendar' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Built-in event editor', 'post-calendar' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[]" value="">
								<p class="description"><?php echo esc_html__( 'Use this list to choose which source post types show the built-in event editor. Posts appear on the calendar when they contain event definitions, no matter how that meta was added.', 'post-calendar' ); ?></p>
								<br />

								<?php if ( empty( $post_types ) ) : ?>
									<p><?php echo esc_html__( 'No post types available.', 'post-calendar' ); ?></p>
								<?php else : ?>
									<fieldset>
										<?php foreach ( $post_types as $post_type ) : ?>
											<?php $field_id = 'post-calendar-post-type-' . $post_type->name; ?>
											<?php $event_count = $event_counts[ $post_type->name ] ?? 0; ?>
											<div style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #dcdcde;">
												<label for="<?php echo esc_attr( $field_id ); ?>">
													<input
														type="checkbox"
														name="<?php echo esc_attr( self::OPTION_NAME ); ?>[]"
														id="<?php echo esc_attr( $field_id ); ?>"
														value="<?php echo esc_attr( $post_type->name ); ?>"
														<?php checked( in_array( $post_type->name, $selected_post_types, true ) ); ?>
													>
													<strong><?php echo esc_html( $post_type->labels->singular_name ?: $post_type->label ); ?></strong>
													<code style="margin-left: 6px;"><?php echo esc_html( $post_type->name ); ?></code>
												</label>
												<span class="description" style="display: block; margin-left: 24px;"><?php echo esc_html( sprintf( _n( '%d post currently contains Post Calendar event definitions.', '%d posts currently contain Post Calendar event definitions.', $event_count, 'post-calendar' ), $event_count ) ); ?></span>
												<?php if ( $post_type->description ) : ?>
													<span class="description" style="display: block; margin-left: 24px;"><?php echo esc_html( $post_type->description ); ?></span>
												<?php endif; ?>
												<div style="margin: 8px 0 0 24px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
													<button
														type="submit"
														class="button button-secondary"
														form="<?php echo esc_attr( 'post-calendar-remove-events-' . $post_type->name ); ?>"
														onclick="return confirm('<?php echo esc_attr__( 'Remove all event data for this post type? This cannot be undone.', 'post-calendar' ); ?>');"
														<?php disabled( 0 === $event_count ); ?>
													>
														<?php echo esc_html__( 'Remove all event data', 'post-calendar' ); ?>
													</button>
													<span class="description"><?php echo esc_html__( 'Removes stored event definitions and the derived query meta that Post Calendar maintains for this post type.', 'post-calendar' ); ?></span>
												</div>
											</div>
										<?php endforeach; ?>
									</fieldset>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php if ( ! empty( $post_types ) ) : ?>
				<?php foreach ( $post_types as $post_type ) : ?>
					<form id="<?php echo esc_attr( 'post-calendar-remove-events-' . $post_type->name ); ?>" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display: none;">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::REMOVE_EVENTS_ACTION ); ?>">
						<input type="hidden" name="<?php echo esc_attr( self::REMOVE_EVENTS_POST_FIELD ); ?>" value="<?php echo esc_attr( $post_type->name ); ?>">
						<?php wp_nonce_field( self::REMOVE_EVENTS_ACTION, self::REMOVE_EVENTS_NONCE ); ?>
					</form>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_remove_events_action(): void {
		if ( ! isset( $_POST[ self::REMOVE_EVENTS_POST_FIELD ] ) ) {
			$this->redirect_with_notice( 'invalid-post-type' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to manage Post Calendar settings.', 'post-calendar' ),
				esc_html__( 'Forbidden', 'post-calendar' ),
				array(
					'response' => 403,
				)
			);
		}

		check_admin_referer( self::REMOVE_EVENTS_ACTION, self::REMOVE_EVENTS_NONCE );

		$post_type  = sanitize_key( wp_unslash( $_POST[ self::REMOVE_EVENTS_POST_FIELD ] ) );
		$post_types = self::get_selectable_post_types();

		if ( ! isset( $post_types[ $post_type ] ) ) {
			$this->redirect_with_notice( 'invalid-post-type' );
		}

		$removed_count = $this->clear_event_meta_for_post_type( $post_type );

		$this->redirect_with_notice(
			'events-cleared',
			array(
				self::NOTICE_REMOVED_ARG   => $removed_count,
				self::NOTICE_POST_TYPE_ARG => $post_type,
			)
		);
	}

	private function maybe_add_action_notice(): void {
		if ( ! isset( $_GET['post_calendar_notice'] ) ) {
			return;
		}

		$notice     = sanitize_key( wp_unslash( $_GET['post_calendar_notice'] ) );
		$post_type  = isset( $_GET[ self::NOTICE_POST_TYPE_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_POST_TYPE_ARG ] ) ) : '';
		$post_types = self::get_selectable_post_types();
		$label      = isset( $post_types[ $post_type ] ) ? ( $post_types[ $post_type ]->labels->singular_name ?: $post_types[ $post_type ]->label ) : $post_type;

		if ( 'events-cleared' === $notice ) {
			$removed = isset( $_GET[ self::NOTICE_REMOVED_ARG ] ) ? absint( wp_unslash( $_GET[ self::NOTICE_REMOVED_ARG ] ) ) : 0;

			add_settings_error(
				'post_calendar',
				'post_calendar_events_cleared',
				sprintf(
					/* translators: 1: number of posts cleared, 2: post type label. */
					esc_html__( 'Removed all event data from %1$d posts in %2$s.', 'post-calendar' ),
					$removed,
					$label
				),
				'updated'
			);

			return;
		}

		if ( 'invalid-post-type' === $notice ) {
			add_settings_error(
				'post_calendar',
				'post_calendar_invalid_post_type',
				esc_html__( 'That post type cannot be managed from Post Calendar.', 'post-calendar' ),
				'error'
			);
		}
	}

	/**
	 * Normalises an array or a comma-separated string into a clean list of post-type slugs.
	 * Each slug is run through sanitize_key; empty results are dropped.
	 */
	public static function sanitize_slug_list( $input ): array {
		if ( is_array( $input ) ) {
			return array_values( array_filter( array_map( 'sanitize_key', $input ) ) );
		}

		if ( ! is_string( $input ) || '' === trim( $input ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'sanitize_key', array_map( 'trim', explode( ',', $input ) ) )
			)
		);
	}

	public function sanitize_post_types( $post_types ): array {
		$available_types = array_keys( self::get_selectable_post_types() );

		return array_values( array_intersect( $available_types, self::sanitize_slug_list( $post_types ) ) );
	}

	public static function get_allowed_post_types(): array {
		$available_types = array_keys( self::get_selectable_post_types() );
		$saved_types     = get_option( self::OPTION_NAME, null );

		if ( null === $saved_types ) {
			return self::get_default_post_types();
		}

		return array_values( array_intersect( $available_types, self::sanitize_slug_list( $saved_types ) ) );
	}

	public static function get_event_source_post_types(): array {
		$selectable_types = array_keys( self::get_selectable_post_types() );
		$source_types     = apply_filters( 'post_calendar_event_source_post_types', $selectable_types );

		if ( ! is_array( $source_types ) ) {
			return array();
		}

		return array_values( array_intersect( $selectable_types, self::sanitize_slug_list( $source_types ) ) );
	}

	public static function resolve_event_source_post_types( $requested_post_types ): array {
		$source_types = self::get_event_source_post_types();

		if ( empty( $source_types ) ) {
			return array();
		}

		$requested = self::sanitize_slug_list( $requested_post_types );

		if ( empty( $requested ) ) {
			return $source_types;
		}

		return array_values( array_intersect( $source_types, $requested ) );
	}

	public static function get_selectable_post_types(): array {
		$post_types = get_post_types(
			array(
				'show_ui' => true,
			),
			'objects'
		);

		$selectable_post_types = array_filter(
			$post_types,
			static function ( $post_type ): bool {
				return self::is_selectable_post_type( $post_type );
			}
		);

		uksort(
			$selectable_post_types,
			static function ( string $left, string $right ): int {
				$order = array(
					'post' => 0,
					'page' => 1,
				);

				$left_order  = $order[ $left ] ?? 2;
				$right_order = $order[ $right ] ?? 2;

				if ( $left_order === $right_order ) {
					return strcmp( $left, $right );
				}

				return $left_order <=> $right_order;
			}
		);

		return $selectable_post_types;
	}

	private static function is_selectable_post_type( $post_type ): bool {
		if ( ! is_object( $post_type ) || empty( $post_type->name ) ) {
			return false;
		}

		if ( in_array( $post_type->name, array( 'post', 'page' ), true ) ) {
			return true;
		}

		if ( ! empty( $post_type->_builtin ) ) {
			return false;
		}

		if ( in_array( $post_type->name, self::get_excluded_post_types(), true ) ) {
			return false;
		}

		if ( ! self::post_type_supports_content( $post_type->name ) ) {
			return false;
		}

		return (bool) apply_filters( 'post_calendar_is_selectable_post_type', true, $post_type );
	}

	private static function post_type_supports_content( string $post_type ): bool {
		$content_features = array(
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'custom-fields',
			'author',
			'comments',
			'page-attributes',
		);

		foreach ( $content_features as $feature ) {
			if ( post_type_supports( $post_type, $feature ) ) {
				return true;
			}
		}

		return false;
	}

	private static function get_excluded_post_types(): array {
		$excluded_post_types = apply_filters( 'post_calendar_excluded_post_types', self::EXCLUDED_POST_TYPES );

		if ( ! is_array( $excluded_post_types ) ) {
			return self::EXCLUDED_POST_TYPES;
		}

		return array_values( array_filter( array_map( 'sanitize_key', $excluded_post_types ) ) );
	}

	private function clear_event_meta_for_post_type( string $post_type ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => self::get_bulk_action_post_statuses(),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'OR',
					array(
						'key'   => self::EVENT_ENABLED_META,
						'value' => '1',
					),
					array(
						'key'     => self::EVENTS_META,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$removed_count = 0;

		foreach ( $query->posts as $post_id ) {
			$this->clear_event_meta_for_post( (int) $post_id );
			++$removed_count;
		}

		return $removed_count;
	}

	private function clear_event_meta_for_post( int $post_id ): void {
		foreach ( self::get_event_meta_keys() as $meta_key ) {
			delete_post_meta( $post_id, $meta_key );
		}

		$post_meta = get_post_meta( $post_id );

		if ( ! is_array( $post_meta ) ) {
			return;
		}

		foreach ( array_keys( $post_meta ) as $meta_key ) {
			if ( 0 === strpos( (string) $meta_key, self::EVENTS_META . '_' ) || 0 === strpos( (string) $meta_key, '_' . self::EVENTS_META . '_' ) ) {
				delete_post_meta( $post_id, (string) $meta_key );
			}
		}
	}

	private static function get_event_counts_for_post_types( array $post_types ): array {
		global $wpdb;

		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
		$counts     = array_fill_keys( $post_types, 0 );

		if ( empty( $post_types ) ) {
			return $counts;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$query        = $wpdb->prepare(
			"SELECT posts.post_type, COUNT(DISTINCT posts.ID) AS event_count
			FROM {$wpdb->posts} AS posts
			INNER JOIN {$wpdb->postmeta} AS postmeta
				ON posts.ID = postmeta.post_id
			WHERE posts.post_type IN ($placeholders)
				AND postmeta.meta_key = %s
				AND postmeta.meta_value = %s
				AND posts.post_status NOT IN ('auto-draft', 'trash', 'inherit')
			GROUP BY posts.post_type",
			array_merge( $post_types, array( self::EVENT_ENABLED_META, '1' ) )
		);

		$results = $wpdb->get_results( $query );

		if ( ! is_array( $results ) ) {
			return $counts;
		}

		foreach ( $results as $result ) {
			if ( empty( $result->post_type ) ) {
				continue;
			}

			$counts[ $result->post_type ] = isset( $result->event_count ) ? (int) $result->event_count : 0;
		}

		return $counts;
	}

	private function redirect_with_notice( string $notice, array $args = array() ): void {
		wp_safe_redirect(
			self::get_settings_page_url(
				array_merge(
					$args,
					array(
						'post_calendar_notice' => $notice,
					)
				)
			)
		);
		exit;
	}

	private static function get_event_meta_keys(): array {
		return array(
			self::EVENT_ENABLED_META,
			self::EVENTS_META,
			self::EVENT_RANGE_START_META,
			self::EVENT_RANGE_END_META,
		);
	}

	private static function get_bulk_action_post_statuses(): array {
		$post_statuses = array_keys( get_post_stati() );

		return array_values( array_diff( $post_statuses, array( 'auto-draft', 'trash', 'inherit' ) ) );
	}

	private static function get_settings_page_url( array $args = array() ): string {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		if ( empty( $args ) ) {
			return $url;
		}

		return add_query_arg( $args, $url );
	}

	private static function get_default_post_types(): array {
		return array();
	}
}