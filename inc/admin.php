<?php // Permet de personnalisé l'administration 

// Design admin wordpress 

function dnc_custom_login() {
echo '<link rel="stylesheet" type="text/css" href="' . get_bloginfo('stylesheet_directory') . '/css/admin/login.css" />';
}
 
add_action('login_head', 'dnc_custom_login');
?>