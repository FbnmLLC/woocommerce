<?php
/**
 * Plugin Name: 123PAY.IR - WooCommerce
 * Description: پلاگین پرداخت، سامانه پرداخت یک دو سه پی برای WooCommerce
 * Plugin URI: https://123pay.ir
 * Author: تیم فنی یک دو سه پی
 * Author URI: http://123pay.ir
 * Version: 1.0
 **/

add_action( 'plugins_loaded', 'woocommerce_ir123pay_init', 0 );

function woocommerce_ir123pay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	if ( isset( $_GET['msg'] ) ) {
		add_action( 'the_content', 'showMessageir123pay' );
	}

	function showMessageir123pay( $content ) {
		return '<div class="box ' . htmlentities( $_GET['type'] ) . '-box">' . base64_decode( $_GET['msg'] ) . '</div>' . $content;
	}

	class WC_ir123pay extends WC_Payment_Gateway {
		protected $msg = array();

		public function __construct() {
			$this->id           = 'ir123pay';
			$this->method_title = __( 'سامانه پرداخت یک دو سه پی', 'ir123pay' );
			$this->has_fields   = false;
			$this->init_form_fields();
			$this->init_settings();
			$this->title            = $this->settings['title'];
			$this->description      = $this->settings['description'];
			$this->merchant_id      = $this->settings['merchant_id'];
			$this->vahed            = $this->settings['vahed'];
			$this->redirect_page_id = $this->settings['redirect_page_id'];
			$this->msg['message']   = "";
			$this->msg['class']     = "";
			add_action( 'woocommerce_api_wc_ir123pay', array( $this, 'check_ir123pay_response' ) );
			add_action( 'valid-ir123pay-request', array( $this, 'successful_request' ) );
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			}
			add_action( 'woocommerce_receipt_ir123pay', array( $this, 'receipt_page' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'          => array(
					'title'   => __( 'فعال سازی/غیر فعال سازی', 'ir123pay' ),
					'type'    => 'checkbox',
					'label'   => __( 'فعال کردن سامانه پرداخت یک دو سه پی', 'ir123pay' ),
					'default' => 'yes'
				),
				'title'            => array(
					'title'       => __( 'عنوان:', 'ir123pay' ),
					'type'        => 'text',
					'description' => __( 'عنوانی که کاربر مشاهده خواهد کرد', 'ir123pay' ),
					'default'     => __( 'سامانه پرداخت یک دو سه پی', 'ir123pay' )
				),
				'description'      => array(
					'title'       => __( 'توضیحات:', 'ir123pay' ),
					'type'        => 'textarea',
					'description' => __( 'توضیحاتی در مورد درگاه پرداخت / کاربر این توضحیات را مشاهده خواهد کرد', 'ir123pay' ),
					'default'     => __( '', 'ir123pay' )
				),
				'merchant_id'      => array(
					'title'       => __( 'کد پذیرندگی', 'ir123pay' ),
					'type'        => 'text',
					'description' => __( 'کد پذیرندگی دریافت شده از یک دو سه پی', 'ir123pay' )
				),
				'vahed'            => array(
					'title'       => __( 'واحد پولی' ),
					'type'        => 'select',
					'options'     => array(
						'rial'  => 'ریال',
						'toman' => 'تومان',
					),
					'description' => "نیازمند افزونه ریال و تومان هست"
				),
				'redirect_page_id' => array(
					'title'       => __( 'صفحه بازگشت' ),
					'type'        => 'select',
					'options'     => $this->get_pages( '' ),
					'description' => "ادرس بازگشت بعد از پرداخت"
				)
			);
		}

		public function admin_options() {
			echo '<h3>' . __( 'سامانه پرداخت یک دو سه پی', 'ir123pay' ) . '</h3>';
			echo '<p>' . __( 'سامانه پرداخت یک دو سه پی' ) . '</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		public function payment_fields() {
			if ( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		}

		public function receipt_page( $order ) {
			echo '<p>' . __( 'Connection To Payment Terminal', 'ir123pay' ) . '</p>';
			echo $this->generate_ir123pay_form( $order );
		}

		public function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );

			return array( 'result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ) );
		}

		public function check_ir123pay_response() {
			global $woocommerce;
			$order_id = $woocommerce->session->zegersot;
			$order    = new WC_Order( $order_id );
			if ( $order_id != '' ) {
				if ( $order->status != 'completed' ) {
					$merchant_id = $this->merchant_id;
					$RefNum      = trim( $_REQUEST['RefNum'] );

					$ch = curl_init();
					curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/verify/payment' );
					curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&RefNum=$RefNum" );
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
					$response = curl_exec( $ch );
					curl_close( $ch );

					$result = json_decode( $response );
					if ( $result->status AND $woocommerce->session->zegersot_id == $_REQUEST['RefNum'] ) {
						$this->msg['message'] = 'Successful Payment<br/>Reference ID : ' . $_REQUEST['RefNum'];
						$this->msg['class']   = 'success';
						$order->payment_complete();
						$order->add_order_note( 'Successful Payment<br/>Reference ID : ' . $_REQUEST['RefNum'] . ' AND ' . $_REQUEST['RefNum'] );
						$order->add_order_note( $this->msg['message'] );
						$woocommerce->cart->empty_cart();
					} else {
						$this->msg['class']   = 'error';
						$this->msg['message'] = "Payment failed";
					}
				} else {
					$this->msg['class']   = 'error';
					$this->msg['message'] = 'We can not find order information';
				}
			}
			$redirect_url = ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) ? get_site_url() . "/" : get_permalink( $this->redirect_page_id );
			$redirect_url = add_query_arg( array(
				'msg'  => base64_encode( $this->msg['message'] ),
				'type' => $this->msg['class']
			), $redirect_url );
			wp_redirect( $redirect_url );
			exit();
		}

		public function showMessage( $content ) {
			return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
		}

		public function generate_ir123pay_form( $order_id ) {
			global $woocommerce;
			$order        = new WC_Order( $order_id );
			$redirect_url = ( $this->redirect_page_id == "" || $this->redirect_page_id == 0 ) ? get_site_url() . "/" : get_permalink( $this->redirect_page_id );
			$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			unset( $woocommerce->session->zegersot );
			unset( $woocommerce->session->zegersot_id );
			$woocommerce->session->zegersot = $order_id;
			$amount                         = $order->order_total;

			$merchant_id  = $this->merchant_id;
			$amount       = ( $this->vahed == 'toman' ) ? $amount * 10 : $amount;
			$callback_url = $redirect_url;

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, 'https://123pay.ir/api/v1/create/payment' );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, "merchant_id=$merchant_id&amount=$amount&callback_url=$callback_url" );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			$response = curl_exec( $ch );
			curl_close( $ch );

			$result = json_decode( $response );
			if ( $result->status ) {
				$woocommerce->session->zegersot_id = $result->RefNum;
				if ( ! headers_sent() ) {
					header( 'Location: ' . $result->payment_url );
					exit();
				} else {
					echo "<script type='text/javascript'>window.onload = function () { top.location.href = '" . $result->payment_url . "'; };</script>";
					exit();
				}
			} else {
				echo $result->message;
			}
		}

		public function get_pages( $title = false, $indent = true ) {
			$wp_pages  = get_pages( 'sort_column=menu_order' );
			$page_list = array();
			if ( $title ) {
				$page_list[] = $title;
			}
			foreach ( $wp_pages as $page ) {
				$prefix = '';
				if ( $indent ) {
					$has_parent = $page->post_parent;
					while ( $has_parent ) {
						$prefix     .= ' - ';
						$next_page  = get_page( $has_parent );
						$has_parent = $next_page->post_parent;
					}
				}
				$page_list[ $page->ID ] = $prefix . $page->post_title;
			}

			return $page_list;
		}
	}

	function woocommerce_add_ir123pay_gateway( $methods ) {
		$methods[] = 'WC_ir123pay';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_ir123pay_gateway' );
}