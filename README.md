# Authorize.net Backend Payments for WooCommerce

This plugins extends the functionality of the Authorize.net CIM for WooCommerce plugin by Cardpay Solutions to allow taking payments in the WP admin area while viewing or editing an order.

The plugin is especially helpful for sites that take orders and only authorize the payment and don't capture the payment immediately. This allows the store operator to adjust the order with additional products, modified prices, shipping costs, etc., and then process a new payment for the new order total.

*Note that if the order has a previous Authorize.net transaction ID then the plugin will attempt to void the previous transaction when a new payment is submitted.*

When viewing an individual order that has a status of "On Hold" or "Pending payment" a payment form will appear in the right sidebar.

If CIM is enabled in the plugin and the order has as customer assigned and the customer has a user account with saved cards those cards will appear as choices for use to take a payment. The form also allows entering a new card to process a payment.

The payment amount will be for the amount of the order as seen in the order total. 


