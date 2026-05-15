# WordPress Full Reset Plugin (Insaf)

A powerful WordPress plugin to completely reset a WordPress installation to its factory defaults.

## ⚠️ WARNING
This plugin is extremely dangerous. It will:
- Drop ALL database tables.
- Reinstall WordPress from scratch.
- Delete ALL uploads, plugins (except itself), and themes (except defaults).
- Create a new admin user: **admin / pass**.

**USE WITH EXTREME CAUTION!**

## Features
- Pure PHP/HTML interface (no JS dependencies for reliability).
- Multi-step confirmation (Type "RESET" to confirm).
- Cleans the entire `wp-content` directory.
- Preserves the existing Site URL and Home URL.
- Sets the default timezone to `Asia/Dhaka`.

## Installation
1. Upload the `insaf-reset` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin in WordPress Admin.
3. Go to **Tools -> 🔴 Full Reset**.

## Author
[Insaf Dev]
