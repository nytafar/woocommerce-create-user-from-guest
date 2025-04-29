# WooCommerce Create User from Guest Orders

A lightweight, modular WooCommerce plugin that allows administrators to manually convert guest orders into registered customer accounts, directly from the Edit Order screen in wp-admin.

## Description

This plugin adds a "Create User from Guest" button to the customer field in the WooCommerce order edit screen for guest orders. When clicked, it allows administrators to create a new user account using the billing information from the order and optionally send login credentials to the customer.

### Key Features

- **One-Click User Creation**: Add a "Create User from Guest" button in the WooCommerce Edit Order admin screen.
- **Email Control**: Choose whether to send login credentials to the new user.
- **Smart Username Generation**: Creates usernames in the format `firstname.lastname` with proper handling of Norwegian characters.
- **Order Reassignment**: Automatically finds and reassigns other guest orders with the same email address.
- **Clear Feedback**: Shows success messages with details about the operation.

## Installation

1. Upload the `woocommerce-create-user-from-guest` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to any guest order in WooCommerce to see the "Create User from Guest" button

## Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher

## Usage

1. Navigate to a WooCommerce order that was placed by a guest (WooCommerce → Orders → Edit Order)
2. Look for the "Create User from Guest" link next to the customer field
3. Click the link and choose whether to send login credentials to the user
4. The system will create a new user account, assign it to the current order, and reassign any other guest orders with the same email

## Frequently Asked Questions

### What happens if a user already exists with the same email?

The plugin checks if a user with the email already exists. If one does, it will abort the operation and display an error message.

### How are usernames generated?

Usernames are created using the format `firstname.lastname` from the billing information. Norwegian characters are properly converted (æ→ae, ø→oe, å→aa). If a username already exists, a number is appended to ensure uniqueness.

### Can I customize the email sent to users?

The plugin uses WordPress's built-in `wp_new_user_notification()` function to send emails. You can use plugins like "Email Customizer for WooCommerce" to modify these emails.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

## Credits

Developed by [Lasse Jellum](https://jellum.net)

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```
