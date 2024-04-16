# How to use translation

## In .php files:
Use __() method

E.g.

1. Simple: __('some text', 'text-domain');

2. Complex: sprintf(
   __('some %s', 'text-domain'),
   __('text', 'text-domain')
);

## In .js files:

When you use 'wp_enqueue_script' in .php files, add the 'wp-i18n' dependency

E.g. wp_enqueue_script('script-name', Plugin::get_url('/path/to/your/script-name.js'), ['wp-i18n']);

Next add 'wp_set_script_translations'

E.g. wp_set_script_translations('script-name', 'text-domain', WP_PLUGIN_DIR . 'plugin-dir-name/path/to/your/lang-dir');

Notes:
- you need WP_PLUGIN_DIR to get the right path

In the .js files you can now import the translation method

E.g.

const { __, sprintf } = wp.i18n;

1. Simple: __('some text', 'text-domain');

2. Complex: sprintf(
    __('some %s', 'text-domain'),
    __('text', 'text-domain')
);

# How to handle multiple languages

Create a language directory in your plugin directory (if there is none)

In your Plugin.php file, in init method you need to load your text domain

E.g. load_plugin_textdomain('text-domain', false, 'plugin-dir-name/path/to/lang-dir');

Install wp-cli: https://make.wordpress.org/cli/handbook/guides/installing/

Notes:
- directory name can be any name
- unlike the wp_set_script_translations method, load_plugin_textdomain method uses WP_PLUGIN_DIR under the hood,
thus you only need the path from your plugin directory to your language directory

## Create .pot file

Run: wp i18n make-pot . path/to/lang-dir/text-domain-locale.pot --domain=text-domain

E.g.

    lang-dir: languages
    
    text-domain: globalpayments-gateway-provider-for-woocommerce
    
    locale: fr_FR, fr_CA, etc.

Notes:
- after you create the .pot file you can add your translation
- you can translate formatted strings as well
- make-pot scans PHP, Blade-PHP and JavaScript files for translatable strings, as well as theme stylesheets and plugin files if the source directory is detected as either a plugin or theme

## Create .po file

1. Duplicate the .pot file and change the extension to .po

Or

2. Rename the .pot to .po

## Create .mo file

Run: wp i18n make-mo path/to/lang-dir/text-domain-locale.po path/to/lang-dir/text-domain-locale.mo

Notes:
- this file contains the translation strings compiled into binary

## Create .json file

Run: wp i18n make-json path/to/lang-dir/

Notes:
- this will look for .po files and will extract all .js strings translations in separated .json files
- all the .js strings will be removed from the .po file after this command runs
