<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CKP_Licences_Table extends WP_List_Table {

	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox">',
			'licence_key'      => 'Licence Key',
			'email'            => 'Customer',
			'plan_code'        => 'Plan',
			'status'           => 'Status',
			'activations'      => 'Activations',
			'expires_at'       => 'Expires',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'status'     => array( 'status', false ),
			'expires_at' => array( 'expires_at', false ),
		);
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="licence_ids[]" value="' . esc_attr( $item->id ) . '">';
	}

	protected function column_licence_key( $item ) {
		$edit_url = admin_url( 'admin.php?page=ckp-licences&action=edit&id=' . $item->id );
		$masked   = 'CKP-BETA-****-****-' . esc_html( $item->licence_key_last4 );

		$actions = array(
			'edit' => '<a href="' . esc_url( $edit_url ) . '">Edit</a>',
		);

		if ( $item->status === 'active' ) {
			$actions['suspend'] = '<a href="' . esc_url( $this->action_url( 'suspend', $item->id ) ) . '">Suspend</a>';
			$actions['revoke']  = '<a href="' . esc_url( $this->action_url( 'revoke', $item->id ) ) . '" style="color:#a00;">Revoke</a>';
		} elseif ( $item->status === 'suspended' ) {
			$actions['reinstate'] = '<a href="' . esc_url( $this->action_url( 'reinstate', $item->id ) ) . '">Reinstate</a>';
			$actions['revoke']    = '<a href="' . esc_url( $this->action_url( 'revoke', $item->id ) ) . '" style="color:#a00;">Revoke</a>';
		}

		return '<strong><a href="' . esc_url( $edit_url ) . '"><code>' . $masked . '</code></a></strong>'
			. $this->row_actions( $actions );
	}

	protected function column_email( $item ) {
		$url = admin_url( 'admin.php?page=ckp-customers&action=edit&id=' . $item->account_id );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->email ) . '</a>';
	}

	protected function column_status( $item ) {
		$classes = array(
			'active'    => 'ckp-badge-active',
			'suspended' => 'ckp-badge-suspended',
			'revoked'   => 'ckp-badge-inactive',
			'expired'   => 'ckp-badge-inactive',
		);
		$class = $classes[ $item->status ] ?? 'ckp-badge-inactive';
		return '<span class="ckp-badge ' . $class . '">' . esc_html( $item->status ) . '</span>';
	}

	protected function column_activations( $item ) {
		$url = admin_url( 'admin.php?page=ckp-activations&licence_id=' . $item->id );
		return '<a href="' . esc_url( $url ) . '">' . (int) $item->active_count . ' / ' . (int) $item->activation_limit . '</a>';
	}

	protected function column_expires_at( $item ) {
		if ( empty( $item->expires_at ) || $item->expires_at === '0000-00-00 00:00:00' ) {
			return '—';
		}
		$ts      = strtotime( $item->expires_at );
		$expired = $ts < time();
		$label   = date_i18n( 'd M Y', $ts );
		return $expired ? '<span style="color:#a00;">' . esc_html( $label ) . '</span>' : esc_html( $label );
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '' );
	}

	private function action_url( $licence_action, $id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=ckp_licence_action&licence_action=' . $licence_action . '&id=' . $id ),
			'ckp_licence_action_' . $id
		);
	}

	public function prepare_items() {
		global $wpdb;

		$table   = CKP_DB::table( 'licences' );
		$atable  = CKP_DB::table( 'accounts' );
		$acttable = CKP_DB::table( 'activations' );
		$per_page = 20;
		$current  = $this->get_pagenum();
		$offset   = ( $current - 1 ) * $per_page;

		$search     = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$account_id = isset( $_REQUEST['account_id'] ) ? (int) $_REQUEST['account_id'] : 0;

		$allowed_order = array( 'email', 'status', 'expires_at' );
		$orderby = in_array( $_REQUEST['orderby'] ?? '', $allowed_order, true ) ? 'l.' . $_REQUEST['orderby'] : 'l.created_at';
		$order   = strtoupper( $_REQUEST['order'] ?? '' ) === 'ASC' ? 'ASC' : 'DESC';

		$conditions = array( '1=1' );
		if ( $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$conditions[]  = $wpdb->prepare( '(a.email LIKE %s)', $like );
		}
		if ( $account_id ) {
			$conditions[] = $wpdb->prepare( 'l.account_id = %d', $account_id );
		}
		$where = 'WHERE ' . implode( ' AND ', $conditions );

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `$table` l LEFT JOIN `$atable` a ON a.id = l.account_id $where"
		);

		// phpcs:ignore WordPress.DB.PreparedSQL
		$this->items = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.*, a.email,
			(SELECT COUNT(*) FROM `$acttable` ac WHERE ac.licence_id = l.id AND ac.status = 'active') AS active_count
			FROM `$table` l
			LEFT JOIN `$atable` a ON a.id = l.account_id
			$where ORDER BY $orderby $order LIMIT %d OFFSET %d",
			$per_page, $offset
		) );

		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page ) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}
}
