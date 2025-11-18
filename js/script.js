jQuery(function ($) {
 
  // Spéciale pour fiche SIT pour déplacer la classe à proxmité quand il s'agit d'un item  
  var className = 'proximite';
  var $from = $('.' + className);
  var $to   = $('.' + className).parents('.onglet');

  if ($from.length && $to.length) {
    $from.removeClass(className);
    $to.addClass(className);
  }
});

jQuery('document').ready(function($){
    // On déplace le loader de chargement sur les listes
    $('.bridge-loader').prependTo('.bridge-main-content-liste');
    
});

