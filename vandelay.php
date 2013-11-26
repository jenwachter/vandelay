<?php
/*
Plugin Name: Vandelay
Description: Export and import WordPress settings
Author: Jen Wachter
Version: 0.1
*/

$path = plugin_dir_path(__FILE__);

if (defined("WP_CLI") && WP_CLI) {
    WP_CLI::add_command("vandelay", "\\vandelay\\Vandelay");
}


// create vandelay settings page
add_action("admin_menu", function() {
	add_submenu_page("options-general.php", "Vandelay options", "Vandelay", "activate_plugins", "vandelay", "vandelay_options_display");
});

function vandelay_options_display() {  
?>
    <div class="wrap">

        <?php screen_icon(); ?>
        <h2>Vandelay Options</h2>
        <?php settings_errors(); ?>

        <form method="post" action="options.php">

            <?php settings_fields("vandelay"); ?>
            <?php do_settings_sections("vandelay"); ?>
            <?php submit_button(); ?>

        </form>

    </div>
<?php  
}


// load form helpers
include_once $path . "forms.php";


// load settings
$files = array_diff(scandir($path. "settings"), array("..", "."));
foreach ($files as $file) {
	include_once $path . "settings/{$file}";
}