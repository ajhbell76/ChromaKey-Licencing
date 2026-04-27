<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CKP_Activations_Table extends WP_List_Table {

	public function get_columns() {
		return array(
			'computer_name'      => 'Machine',
			'email'              => 'Customer',
			'licence_key'        => 'Licence',
			'app_version'        => 'App',
			'os_name'            => 'OS',
			'status'             => 'Status',
			'first_activated_at' => 'First Activated',
			'last_validated_at'  => 'Last Validated',
			'next_validation_due_at' => 'Next Due',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'email'              => array( 'email', false ),
			'status'             => array( 'status', false ),
			'last_validated_at'  => array( 'last_validated_at', true ),
			'first_activated_at' => array( 'first_activated_at', false ),
		);
	}

	protected function column_computer_name( $item ) {
		$actions = array();

		if ( $item->status === 'active' ) {
			$actions['deactivate'] = '<a href="' . esc_url( $this->action_url( 'deactivate', $item->id ) ) . '">Deactivate</a>';
			$actions['revoke']     = '<a href="' . esc_url( $this->action_url( 'revoke', $item->id ) ) . '" style="color:#a00;">Revoke</a>';
		} elseif ( $item->status === 'deactivated' ) {
			$actions['revoke']     = '<a href="' . esc_url( $this->action_url( 'revoke', $item->id ) ) . '" style="color:#a00;">Revoke</a>';
			$actions['reactivate'] = '<a href="' . esc_url( $this->action_url( 'reactivate', $item->id ) ) . '">Reactivate</a>';
		}

		$actions['log'] = '<a href="' . esc_url( admin_url( 'admin.php?page=ckp-activations&view=log&activation_id=' . $item->id ) ) . '">View Log</a>';

		return '<strong>' . esc_html( $item->computer_name ?: '(unknown)' ) . '</strong>'
			. $this->row_actions( $actions );
	}

	protected function column_email( $item ) {
		$url = admin_url( 'admin.php?page=ckp-customers&action=edit&id=' . $item->account_id );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $item->email ) . '</a>';
	}

	protected function column_licence_key( $item ) {
		$url    = admin_url( 'admin.php?page=ckp-licences&action=edit&id=' . $item->licence_id );
		$masked = 'CKP-BETA-****-****-' . esc_html( $item->licence_key_last4 );
		return '<a href="' . esc_url( $url ) . '"><code>' . $masked . '</code></a>';
	}

	protected function column_status( $item ) {
		$classes = array(
			'active'      => 'ckp-badge-active',
			'deactivated' => 'ckp-badge-inactive',
			'revoked'     => 'ckp-badge-inactive',
		);
		$class = $classes[ $item->status ] ?? 'ckp-badge-inactive';
		return '<span class="ckp-badge ' . $class . '">' . esc_html( $item->status ) . '</span>';
	}

	protected function column_first_activated_at( $item ) {
		return $item->first_activated_at && $item->first_activated_at !== '0000-00-00 00:00:00'
			? esc_html( date_i18n( 'd M Y H:i', strtotime( $item->first_activated_at ) ) )
			: '—';
	}

	protected function column_last_validated_at( $item ) {
		return $item->last_validated_at && $item->last_validated_at !== '0000-00-00 00:00:00'
			? esc_html( date_i18n( 'd M Y H:i', strtotime( $item->last_validated_at ) ) )
			: '—';
	}

	protected function column_next_validation_due_at( $item ) {
		if ( ! $item->next_validation_due_at || $item->next_validation_due_at === '0000-00-00 00:00:00' ) {
			return '—';
		}
		$ts      = strtotime( $item->next_validation_due_at );
		$overdue = $ts < time() && $item->status === 'active';
		$label   = date_i18n( 'd M Y', $ts );
		return $overdue
			? '<span style="color:#a00;" title="Overdue">' . esc_html( $label ) . ' &#9888;</span>'
			: esc_html( $label );
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '—' );
	}

	private function action_url( $activation_action, $id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=ckp_activation_action&activation_action=' . $activation_action . '&id=' . $id ),
			'ckp_activation_action_' . $id
		);
	}

	public function prepare_items() {
		global $wpdb;

		$atbl  = CKP_DB::table( 'activations' );
		$ltbl  = CKP_DB::table( 'licences' );
		$acctbl = CKP_DB::table( 'accounts' );

		$per_page   = 25;
		$current    = $this->get_pagenum();
		$offset     = ( $current - 1 ) * $per_page;

		$licence_id  = isset( $_REQUEST['licence_id'] ) ? (int) $_REQUEST['licence_id'] : 0;
		$account_id  = isset( $_REQUEST['account_id'] ) ? (int) $_REQUEST['account_id'] : 0;
		$status_filter = isset( $_REQUEST['status'] ) ? sanitize_key( $_REQUEST['status'] ) : '';

		$allowed_order = array( 'email', 'status', 'last_validated_at', 'first_activated_at' );
		$orderby = in_array( $_REQUEST['orderby'] ?? '', $allowed_order, true ) ? $_REQUEST['orderby'] : 'last_validated_at';
		$order   = strtoupper( $_REQUEST['order'] ?? '' ) === 'ASC' ? 'ASC' : 'DESC';

		$conditions = array( '1=1' );
		if ( $licence_id ) {
			$conditions[] = $wpdb->prepare( 'a.licence_id = %d', $licence_id );
		}
		if ( $account_id ) {
			$conditions[] = $wpdb->prepare( 'a.account_id = %d', $account_id );
		}
		if ( $status_filter ) {
			$conditions[] = $wpdb->prepare( 'a.status = %s', $status_filter );
		}
		$where = 'WHERE ' . implode( ' AND ', $conditions );

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `$atbl` a LEFT JOIN `$acctbl` acc ON acc.id = a.account_id $where"
		);

		$order_col = $orderby === 'email' ? 'acc.email' : "a.$orderby";

		$this->items = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, acc.email, l.licence_key_last4
			FROM `$atbl` a
			LEFT JOIN `$acctbl` acc ON acc.id = a.account_id
			LEFT JOIN `$ltbl` l ON l.id = a.licence_id
			$where ORDER BY $order_col $order LIMIT %d OFFSET %d",
			$per_page, $offset
		) );

		$this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per_page ) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}
}
