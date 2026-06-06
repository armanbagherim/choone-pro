# Choone Pro

A powerful WooCommerce bargaining and price negotiation plugin that allows customers to submit custom price offers for products and enables store owners to negotiate, accept, reject, or counter those offers directly from the WordPress admin panel.

## Overview

Choone Pro transforms traditional e-commerce pricing into an interactive negotiation experience.

Instead of forcing customers to either buy or leave, Choone Pro allows them to propose their own price and start a negotiation process with the store owner.

This creates higher conversion rates, improves customer engagement, and helps recover sales that would otherwise be lost due to pricing concerns.

---

## Features

### Customer Features

* Submit custom price offers on WooCommerce product pages
* Support for logged-in and guest users
* AJAX-powered offer submission
* Offer status tracking
* Offer timeline and history
* Customer offer dashboard inside WooCommerce My Account
* Accept seller counter-offers
* Purchase products using negotiated prices
* Mobile-friendly and RTL-ready interface

### Admin Features

* Professional negotiation dashboard
* Offer management system
* Accept offers
* Reject offers
* Send counter-offers
* Internal admin notes
* Offer conversation history
* Product-level bargaining settings
* Category-level bargaining settings
* Advanced reporting and analytics
* Conversion tracking

### Automation

* Auto Accept Rules
* Auto Reject Rules
* Offer Expiration Rules
* Automatic Checkout Link Generation
* Automated Notifications
* Scheduled Cleanup Tasks

### SMS Integration

Supports integration with:

* Kavenegar
* Melipayamak
* FarazSMS
* Ghasedak
* IPPanel
* Custom Webhook Providers

Notification events include:

* New Offer Created
* Offer Accepted
* Offer Rejected
* Counter Offer Sent
* Offer Expiring Soon
* Offer Expired
* Successful Negotiation Purchase

### WhatsApp Integration

* One-click WhatsApp communication
* Predefined negotiation templates
* Direct contact with customers
* Customizable WhatsApp messages

---

## WooCommerce Integration

Choone Pro does not modify the original WooCommerce product price.

Instead, negotiated prices are safely applied at cart level using WooCommerce hooks, ensuring complete compatibility with existing products, reports, and pricing structures.

---

## Key Benefits

* Increase conversion rates
* Recover abandoned customers
* Create a personalized shopping experience
* Improve customer engagement
* Increase sales opportunities
* Flexible pricing strategy
* Suitable for high-ticket products
* Suitable for B2B stores
* Suitable for custom-order businesses

---

## Technical Highlights

* Built using WordPress Coding Standards
* Object-Oriented Architecture
* WooCommerce Native Integration
* AJAX-Based Workflows
* Secure Nonce Validation
* Sanitized Inputs
* Escaped Outputs
* RTL Support
* Translation Ready
* Mobile Responsive
* Extensible Hook System

---

## Developer Hooks

Choone Pro provides action hooks for developers:

```php
do_action('choone_offer_created', $offer_id, $offer_data);

do_action('choone_offer_accepted', $offer_id, $offer_data);

do_action('choone_offer_rejected', $offer_id, $offer_data);

do_action('choone_offer_countered', $offer_id, $offer_data);

do_action('choone_offer_expired', $offer_id, $offer_data);

do_action('choone_offer_converted', $offer_id, $order_id);
```

These hooks make it easy to integrate third-party services such as CRM systems, SMS providers, email marketing tools, and custom workflows.

---

## Requirements

* WordPress 6.0+
* WooCommerce 8.0+
* PHP 8.1+
* MySQL 5.7+ or MariaDB equivalent

---

## Installation

1. Upload the plugin to the `/wp-content/plugins/` directory.
2. Activate the plugin through the WordPress Admin Panel.
3. Ensure WooCommerce is installed and activated.
4. Configure Choone Pro settings.
5. Enable bargaining on products or categories.
6. Start receiving customer offers.

---

## Roadmap

* AI-powered negotiation suggestions
* Multi-vendor marketplace support
* Telegram integration
* Advanced analytics dashboard
* Dynamic pricing recommendations
* REST API
* CRM integrations
* Advanced customer segmentation

---

## License

GPL v2 or later

---

## Author

Choone Pro Team

Built to bring real negotiation into WooCommerce.
