<?php
/**
 * Align Agent - User Management & Administration Tools
 *
 * Registers user management tools:
 * - list_users (read)
 * - create_user, update_user_role, update_user_profile, send_password_reset, delete_user (write)
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Users {

	/** Initialize user management tools. */
	public static function init(): void {
		self::register_read_tools();
		self::register_write_tools();
	}

	/** Register read user tools. */
	private static function register_read_tools(): void {
		Align_Agent_Tool_Registry::register( 'list_users', array(
			'type'        => 'read',
			'group'       => 'users',
			'capability'  => 'list_users',
			'description' => 'List WordPress users with optional role and search filter. Never exposes password hashes or activation keys.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'role'   => array( 'type' => 'string', 'description' => 'Optional user role (e.g. administrator, editor, author).' ),
					'search' => array( 'type' => 'string', 'description' => 'Optional keyword search in login, email, or display name.' ),
					'number' => array( 'type' => 'integer', 'description' => 'Maximum users to return (default 20, max 50).' ),
				),
			),
			'callback'    => array( __CLASS__, 'tool_list_users' ),
		) );
	}

	/** Register write user tools. */
	private static function register_write_tools(): void {
		Align_Agent_Tool_Registry::register( 'create_user', array(
			'type'             => 'write',
			'group'            => 'users',
			'capability'       => 'create_users',
			'undo'             => array( 'adapter' => 'create' ),
			'description'      => 'Create a new WordPress user account with a strong random password and notification email. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'user_login'   => array( 'type' => 'string', 'description' => 'Username for login.' ),
					'user_email'   => array( 'type' => 'string', 'description' => 'User email address.' ),
					'role'         => array( 'type' => 'string', 'description' => 'Role (subscriber, contributor, author, editor, administrator).' ),
					'display_name' => array( 'type' => 'string', 'description' => 'Optional display name.' ),
				),
				'required'   => array( 'user_login', 'user_email' ),
			),
			'callback'         => array( __CLASS__, 'tool_create_user' ),
			'summary_callback' => array( __CLASS__, 'summarize_create_user' ),
		) );

		Align_Agent_Tool_Registry::register( 'update_user_role', array(
			'type'             => 'write',
			'group'            => 'users',
			'capability'       => 'promote_users',
			'undo'             => array( 'adapter' => 'user_role' ),
			'description'      => 'Change the role of an existing WordPress user. Guarded against self-demotion and last-admin lockout. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'user_id' => array( 'type' => 'integer', 'description' => 'Numeric user ID.' ),
					'role'    => array( 'type' => 'string', 'description' => 'Target role slug.' ),
				),
				'required'   => array( 'user_id', 'role' ),
			),
			'callback'         => array( __CLASS__, 'tool_update_user_role' ),
			'summary_callback' => array( __CLASS__, 'summarize_update_user_role' ),
		) );

		Align_Agent_Tool_Registry::register( 'update_user_profile', array(
			'type'             => 'write',
			'group'            => 'users',
			'capability'       => 'edit_users',
			'undo'             => array( 'adapter' => 'user_role' ),
			'description'      => 'Update user first name, last name, display name, or email. Strictly rejects password changes. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'user_id'      => array( 'type' => 'integer', 'description' => 'Numeric user ID.' ),
					'first_name'   => array( 'type' => 'string', 'description' => 'Optional first name.' ),
					'last_name'    => array( 'type' => 'string', 'description' => 'Optional last name.' ),
					'display_name' => array( 'type' => 'string', 'description' => 'Optional display name.' ),
					'user_email'   => array( 'type' => 'string', 'description' => 'Optional email address.' ),
				),
				'required'   => array( 'user_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_update_user_profile' ),
			'summary_callback' => array( __CLASS__, 'summarize_update_user_profile' ),
		) );

		Align_Agent_Tool_Registry::register( 'send_password_reset', array(
			'type'             => 'write',
			'group'            => 'users',
			'capability'       => 'edit_users',
			'undo'             => array( 'adapter' => 'none' ),
			'description'      => 'Trigger standard WordPress password reset notification email to a user. Never reveals reset key. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'user_id'    => array( 'type' => 'integer', 'description' => 'Optional numeric user ID.' ),
					'user_login' => array( 'type' => 'string', 'description' => 'Optional username or email address.' ),
				),
			),
			'callback'         => array( __CLASS__, 'tool_send_password_reset' ),
			'summary_callback' => array( __CLASS__, 'summarize_send_password_reset' ),
		) );

		Align_Agent_Tool_Registry::register( 'delete_user', array(
			'type'             => 'write',
			'group'            => 'users',
			'capability'       => 'delete_users',
			'danger'           => true,
			'undo'             => array( 'adapter' => 'none' ),
			'description'      => 'Permanently delete a user account and reassign all their posts to another user. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'user_id'  => array( 'type' => 'integer', 'description' => 'Numeric user ID to delete.' ),
					'reassign' => array( 'type' => 'integer', 'description' => 'User ID to receive all posts and links from deleted user.' ),
				),
				'required'   => array( 'user_id', 'reassign' ),
			),
			'callback'         => array( __CLASS__, 'tool_delete_user' ),
			'summary_callback' => array( __CLASS__, 'summarize_delete_user' ),
		) );
	}

	/** Execute list_users tool. */
	public static function tool_list_users( array $args = array() ): array {
		$number = min( 50, max( 1, absint( $args['number'] ?? 20 ) ) );
		$role   = isset( $args['role'] ) ? sanitize_key( (string) $args['role'] ) : '';
		$search = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';

		$query_args = array(
			'number'  => $number,
			'orderby' => 'registered',
			'order'   => 'DESC',
		);
		if ( ! empty( $role ) ) {
			$query_args['role'] = $role;
		}
		if ( ! empty( $search ) ) {
			$query_args['search']         = '*' . $search . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$users = get_users( $query_args );
		$items = array();

		foreach ( $users as $u ) {
			$items[] = array(
				'user_id'         => (int) $u->ID,
				'user_login'      => (string) $u->user_login,
				'display_name'    => (string) $u->display_name,
				'user_email'      => (string) $u->user_email,
				'roles'           => (array) $u->roles,
				'registered_date' => (string) $u->user_registered,
			);
		}

		return array(
			'success' => true,
			'total'   => count( $items ),
			'users'   => $items,
		);
	}

	/** Summarize create_user for approval card. */
	public static function summarize_create_user( array $args ): array {
		$login = sanitize_user( (string) ( $args['user_login'] ?? '' ) );
		$email = sanitize_email( (string) ( $args['user_email'] ?? '' ) );
		$role  = sanitize_key( (string) ( $args['role'] ?? 'subscriber' ) );
		return array(
			'title'        => sprintf( __( 'Create User "%1$s" (%2$s)', 'align-agent' ), $login, $role ),
			'description'  => sprintf( __( 'Create new account %1$s <%2$s> with role %3$s.', 'align-agent' ), $login, $email, $role ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'user_login' => $login, 'user_email' => $email, 'role' => $role ),
			'danger'       => ( 'administrator' === $role ),
		);
	}

	/** Execute create_user tool. */
	public static function tool_create_user( array $args = array() ) {
		$login = sanitize_user( (string) ( $args['user_login'] ?? '' ) );
		$email = sanitize_email( (string) ( $args['user_email'] ?? '' ) );
		$role  = isset( $args['role'] ) ? sanitize_key( (string) $args['role'] ) : 'subscriber';
		$name  = isset( $args['display_name'] ) ? sanitize_text_field( (string) $args['display_name'] ) : $login;

		if ( empty( $login ) || empty( $email ) ) {
			return new WP_Error( 'missing_params', 'Username and email are required.' );
		}
		if ( username_exists( $login ) ) {
			return new WP_Error( 'username_exists', sprintf( 'Username "%s" already exists.', $login ) );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'email_exists', sprintf( 'Email "%s" is already registered.', $email ) );
		}

		$valid_roles = array_keys( wp_roles()->get_names() );
		if ( ! in_array( $role, $valid_roles, true ) ) {
			$role = 'subscriber';
		}

		$random_pass = wp_generate_password( 24, true, true );
		$userdata    = array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $random_pass,
			'role'         => $role,
			'display_name' => $name,
		);

		$new_id = wp_insert_user( $userdata );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		wp_new_user_notification( $new_id, null, 'user' );
		error_log( sprintf( '[Align Agent] Created user #%d (%s, role: %s).', $new_id, $login, $role ) );

		return array(
			'success'      => true,
			'user_id'      => (int) $new_id,
			'user_login'   => $login,
			'user_email'   => $email,
			'role'         => $role,
			'display_name' => $name,
			'message'      => sprintf( 'User "%s" created successfully (#%d). Notification sent.', $login, $new_id ),
			'ui_card'      => array(
				'title'   => sprintf( 'User Created (#%d)', $new_id ),
				'message' => sprintf( '%s <%s> (%s)', $login, $email, $role ),
				'url'     => admin_url( 'user-edit.php?user_id=' . $new_id ),
			),
		);
	}

	/** Summarize update_user_role for approval card. */
	public static function summarize_update_user_role( array $args ): array {
		$user_id = absint( $args['user_id'] ?? 0 );
		$role    = sanitize_key( (string) ( $args['role'] ?? '' ) );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : null;
		$login   = $user ? $user->user_login : '#' . $user_id;
		$is_admin_action = ( 'administrator' === $role ) || ( $user && in_array( 'administrator', (array) $user->roles, true ) );
		return array(
			'title'        => sprintf( __( 'Update Role for User "%1$s" -> %2$s', 'align-agent' ), $login, $role ),
			'description'  => sprintf( __( 'Change role for user #%1$d (%2$s) to %3$s.', 'align-agent' ), $user_id, $login, $role ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'user_id' => $user_id, 'role' => $role ),
			'danger'       => $is_admin_action,
		);
	}

	/** Execute update_user_role tool. */
	public static function tool_update_user_role( array $args = array() ) {
		$user_id = absint( $args['user_id'] ?? 0 );
		$role    = sanitize_key( (string) ( $args['role'] ?? '' ) );

		$user = $user_id > 0 ? get_userdata( $user_id ) : null;
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', sprintf( 'User #%d was not found.', $user_id ) );
		}

		$valid_roles = array_keys( wp_roles()->get_names() );
		if ( ! in_array( $role, $valid_roles, true ) ) {
			return new WP_Error( 'invalid_role', sprintf( 'Role "%s" is not a valid WordPress role.', $role ) );
		}

		if ( $user_id === get_current_user_id() ) {
			return new WP_Error( 'self_demotion_forbidden', 'Cannot change your own user role.' );
		}

		$is_admin = in_array( 'administrator', (array) $user->roles, true );
		if ( $is_admin && 'administrator' !== $role ) {
			$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
			if ( count( $admins ) <= 1 ) {
				return new WP_Error( 'last_admin_protection', 'Cannot demote the only remaining administrator account.' );
			}
		}

		$wp_user = new WP_User( $user_id );
		$wp_user->set_role( $role );
		clean_user_cache( $user_id );

		error_log( sprintf( '[Align Agent] Updated role for user #%d to %s.', $user_id, $role ) );

		return array(
			'success'    => true,
			'user_id'    => $user_id,
			'user_login' => $user->user_login,
			'new_role'   => $role,
			'message'    => sprintf( 'User "%1$s" role changed to "%2$s".', $user->user_login, $role ),
			'ui_card'    => array(
				'title'   => sprintf( 'User Role Updated (#%d)', $user_id ),
				'message' => sprintf( '%s -> %s', $user->user_login, $role ),
				'url'     => admin_url( 'user-edit.php?user_id=' . $user_id ),
			),
		);
	}

	/** Summarize update_user_profile for approval card. */
	public static function summarize_update_user_profile( array $args ): array {
		$user_id = absint( $args['user_id'] ?? 0 );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : null;
		$login   = $user ? $user->user_login : '#' . $user_id;
		return array(
			'title'        => sprintf( __( 'Update Profile for User "%s"', 'align-agent' ), $login ),
			'description'  => sprintf( __( 'Update profile fields for user #%1$d (%2$s).', 'align-agent' ), $user_id, $login ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'user_id' => $user_id ),
		);
	}

	/** Execute update_user_profile tool. */
	public static function tool_update_user_profile( array $args = array() ) {
		$user_id = absint( $args['user_id'] ?? 0 );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : null;
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', sprintf( 'User #%d was not found.', $user_id ) );
		}

		$userdata = array( 'ID' => $user_id );
		$updated  = array();

		if ( isset( $args['first_name'] ) ) {
			$userdata['first_name'] = sanitize_text_field( (string) $args['first_name'] );
			$updated['first_name']  = $userdata['first_name'];
		}
		if ( isset( $args['last_name'] ) ) {
			$userdata['last_name'] = sanitize_text_field( (string) $args['last_name'] );
			$updated['last_name']  = $userdata['last_name'];
		}
		if ( isset( $args['display_name'] ) ) {
			$userdata['display_name'] = sanitize_text_field( (string) $args['display_name'] );
			$updated['display_name']  = $userdata['display_name'];
		}
		if ( isset( $args['user_email'] ) ) {
			$email = sanitize_email( (string) $args['user_email'] );
			$exist = email_exists( $email );
			if ( $exist && (int) $exist !== $user_id ) {
				return new WP_Error( 'email_in_use', sprintf( 'Email "%s" is already used by another account.', $email ) );
			}
			$userdata['user_email'] = $email;
			$updated['user_email']  = $email;
		}

		unset( $userdata['user_pass'] );

		if ( count( $userdata ) > 1 ) {
			$res = wp_update_user( $userdata );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}

		clean_user_cache( $user_id );
		error_log( sprintf( '[Align Agent] Updated profile for user #%d.', $user_id ) );

		return array(
			'success'        => true,
			'user_id'        => $user_id,
			'updated_fields' => $updated,
			'message'        => sprintf( 'Updated profile for user "%s".', $user->user_login ),
		);
	}

	/** Summarize send_password_reset for approval card. */
	public static function summarize_send_password_reset( array $args ): array {
		$target = sanitize_text_field( (string) ( $args['user_login'] ?? $args['user_id'] ?? '' ) );
		return array(
			'title'        => sprintf( __( 'Send Password Reset Email to "%s"', 'align-agent' ), $target ),
			'description'  => sprintf( __( 'Trigger WordPress password reset notification email to "%s".', 'align-agent' ), $target ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'target' => $target ),
		);
	}

	/** Execute send_password_reset tool. */
	public static function tool_send_password_reset( array $args = array() ) {
		$user_id    = absint( $args['user_id'] ?? 0 );
		$user_login = isset( $args['user_login'] ) ? sanitize_text_field( (string) $args['user_login'] ) : '';

		$user = null;
		if ( $user_id > 0 ) {
			$user = get_userdata( $user_id );
		} elseif ( ! empty( $user_login ) ) {
			$user = get_user_by( 'login', $user_login ) ?: get_user_by( 'email', $user_login );
		}

		if ( ! $user ) {
			return new WP_Error( 'user_not_found', 'User was not found.' );
		}

		$res = retrieve_password( $user->user_login );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		error_log( sprintf( '[Align Agent] Sent password reset email for user #%d (%s).', $user->ID, $user->user_login ) );

		return array(
			'success'    => true,
			'user_id'    => (int) $user->ID,
			'user_login' => (string) $user->user_login,
			'message'    => sprintf( 'Password reset notification sent to "%s" (%s).', $user->user_login, $user->user_email ),
		);
	}

	/** Summarize delete_user for approval card. */
	public static function summarize_delete_user( array $args ): array {
		$user_id  = absint( $args['user_id'] ?? 0 );
		$reassign = absint( $args['reassign'] ?? 0 );
		$user     = $user_id > 0 ? get_userdata( $user_id ) : null;
		$login    = $user ? $user->user_login : '#' . $user_id;
		return array(
			'title'        => sprintf( __( 'Delete User "%1$s" (#%2$d)', 'align-agent' ), $login, $user_id ),
			'description'  => sprintf( __( 'Permanently delete user "%1$s" and reassign all content to user #%2$d.', 'align-agent' ), $login, $reassign ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'user_id' => $user_id, 'reassign' => $reassign ),
			'danger'       => true,
		);
	}

	/** Execute delete_user tool. */
	public static function tool_delete_user( array $args = array() ) {
		$user_id  = absint( $args['user_id'] ?? 0 );
		$reassign = absint( $args['reassign'] ?? 0 );

		if ( $user_id <= 0 ) {
			return new WP_Error( 'invalid_user_id', 'Valid numeric user_id is required.' );
		}
		if ( $reassign <= 0 || $reassign === $user_id ) {
			return new WP_Error( 'invalid_reassign', 'A valid reassignment user ID different from the target user is required.' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', sprintf( 'User #%d was not found.', $user_id ) );
		}

		$target_reassign = get_userdata( $reassign );
		if ( ! $target_reassign ) {
			return new WP_Error( 'reassign_user_not_found', sprintf( 'Reassignment target user #%d was not found.', $reassign ) );
		}

		if ( $user_id === get_current_user_id() ) {
			return new WP_Error( 'cannot_delete_self', 'Cannot delete your own user account.' );
		}

		$is_admin = in_array( 'administrator', (array) $user->roles, true );
		if ( $is_admin ) {
			$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
			if ( count( $admins ) <= 1 ) {
				return new WP_Error( 'last_admin_protection', 'Cannot delete the only remaining administrator account.' );
			}
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		$deleted_login = $user->user_login;
		$res           = wp_delete_user( $user_id, $reassign );
		if ( ! $res ) {
			return new WP_Error( 'delete_failed', sprintf( 'Failed to delete user #%d.', $user_id ) );
		}

		error_log( sprintf( '[Align Agent] Deleted user #%d (%s), reassigned to #%d.', $user_id, $deleted_login, $reassign ) );

		return array(
			'success'       => true,
			'deleted_id'    => $user_id,
			'deleted_login' => $deleted_login,
			'reassigned_to' => $reassign,
			'message'       => sprintf( 'User "%1$s" (#%2$d) was permanently deleted and content reassigned to #%3$d.', $deleted_login, $user_id, $reassign ),
		);
	}
}
