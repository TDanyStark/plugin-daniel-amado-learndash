<?php

class My_functions
{

  public function __construct()
  {
    add_action('woocommerce_before_thankyou', [$this, 'customize_thank_you_page'], 10, 1);
    add_action('init', [$this, 'disable_password_strength_check']);
    add_filter('woocommerce_min_password_strength', [$this, 'reduce_min_password_strength']);
    add_action('woocommerce_customer_reset_password', [$this, 'custom_redirect_after_password_reset'], 10, 1);
    add_filter('woocommerce_checkout_fields', [$this, 'remove_additional_checkout_fields']);
    add_filter('woocommerce_login_redirect', [$this, 'wc_custom_user_redirect'], 10, 2);

    // sistema para crear la contraseña desde el checkout de compra
    add_action('woocommerce_after_checkout_billing_form', [$this, 'add_password_checkout_field']);
    add_action('woocommerce_checkout_process', [$this, 'check_password_field']);
    add_action('woocommerce_created_customer', [$this, 'save_password_checkout_field']);

  }

  // Personalizar la página de agradecimiento
  public function customize_thank_you_page($order_id)
  {
    // Obtener el objeto del pedido
    $order = wc_get_order($order_id);

    // Comprobar si el pedido está realizado
    if (!$order) {
      return;
    }

    // Obtener el método de pago utilizado
    $payment_method = $order->get_payment_method();

    // Verificar si el método de pago es Stripe
    if ($payment_method == 'stripe') {
      // Mostrar botón de unirse a WhatsApp
      echo '<p>Únete a nuestro grupo de WhatsApp para más información:</p>';
      echo '<a href="https://chat.whatsapp.com/EV2wRbuZTpV3lEpkHqHbX3" target="_blank" rel="noreferrer" class="button-add-whatsapp-da">Unirse a WhatsApp</a>';
    }
  }

  // Deshabilitar la comprobación de fuerza de la contraseña
  public function disable_password_strength_check()
  {
    if (is_account_page()) {
      wp_dequeue_script('wc-password-strength-meter');
    }
  }

  // Reducir la fuerza de la contraseña a 0
  public function reduce_min_password_strength($strength)
  {
    return 0; // 0 para permitir cualquier contraseña
  }

  // Redirigir a la página de inicio después de restablecer la contraseña
  public function custom_redirect_after_password_reset($user)
  {
    wp_safe_redirect(home_url());
    exit;
  }

  // Eliminar campos adicionales en la página de pago
  function remove_additional_checkout_fields($fields)
  {
    unset($fields['order']['order_comments']);
    return $fields;
  }

  // Redirigir a la página de inicio después de iniciar sesión - menos en el checkout
  public function wc_custom_user_redirect($redirect, $user)
  {
    // Log para verificar el valor de $redirect
    error_log("Valor de redirect: " . $redirect);

    // Verifica si el $redirect contiene "checkout"
    if (strpos($redirect, 'checkout') !== false) {
      return $redirect; // Mantén la redirección predeterminada
    }

    return home_url();
  }

  // sistema para crear la contraseña desde el checkout de compra
  public function add_password_checkout_field($checkout)
  {
    if (! is_user_logged_in()) {
      echo '<div id="daniel_custom_checkout_field_create_account"><h3>' . __('Crear una cuenta') . '</h3>';

      woocommerce_form_field('account_password', array(
        'type'          => 'password',
        'class'         => array('form-row-wide'),
        'label'         => __('Contraseña'),
        'placeholder'   => __('Escribe una contraseña'),
        'required'      => true,
      ), $checkout->get_value('account_password'));

      echo '</div>';
    }
  }

  public function check_password_field()
  {
    if (! is_user_logged_in() && ! $_POST['account_password'])
      wc_add_notice(__('Por favor, ingresa una contraseña para crear tu cuenta.'), 'error');
  }

  public function save_password_checkout_field($customer_id)
  {
    if (! empty($_POST['account_password'])) {
      wp_set_password($_POST['account_password'], $customer_id);
    }
  }
}



