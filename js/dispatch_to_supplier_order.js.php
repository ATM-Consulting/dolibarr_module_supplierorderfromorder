<?php
/* Copyright (C) 2018 John BOTELLA
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER'))  define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
if (!defined('NOREQUIRESOC'))   define('NOREQUIRESOC', '1');
//if (!defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');
//if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN'))        define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');


/**
 * \file    js/dispatch_to_supplier_order.js.php
 * \ingroup supplierorderfromorder
 * \brief   JavaScript file for module supplierorderfromorder.
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/../main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/../main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) header('Cache-Control: max-age=3600, public, must-revalidate');
else header('Cache-Control: no-cache');


// Load traductions files requiredby by page
$langs->loadLangs(array("supplierorderfromorder@supplierorderfromorder","other"));
?>
// <script > // Astuce pour coloriser le code ...

$( document ).ready(function() {

 	function isBlank(str) {
        return (!str || /^\s*$/.test(str));
    }


    function runFormValidation()
    {
        // int checkbox
        $('#crea_commande .checkboxToggle:checked').each(function() {
            if(this.checked) {
            	$( this ).trigger("change");
            }
    	});
    }
    runFormValidation();
	
    
    $( "#crea_commande input:not(.checkboxToggle),#crea_commande select" ).change(function() {
    	$(this).get(0).setCustomValidity('');
        runFormValidation();
    });
    
	
    $("#checkToggle").click(function() {
         var checkBoxes = $(".checkboxToggle");
         checkBoxes.prop("checked", this.checked);
         checkBoxes.trigger("change");
    });
    
    $("#crea_commande").submit(function(){

        var checked = $('#crea_commande .checkboxToggle:checked').length > 0;
        if (!checked){
            alert("<?php print $langs->transnoentitiesnoconv('PleaseCheckAtLeastOneCheckbox')?>");
            return false;
        }
        else
        {
        	if( $('#bypassjstests').prop('checked') )
        	{
				return true;
        	}
        }
        
    });

    $(".checkboxToggle").change(function() {
    	var lineId = $(this).val();

    	var productfournpriceid = $("[name='productfournpriceid[" + lineId + "]']");
		var search_fk_soc_fourn = $("#search_fk_soc_fourn_" + lineId);
		var fournUnitPrice  = $("[name='fournUnitPrice[" + lineId + "]']");
		var inputqty = $("#qty-" + lineId);
		
        if(this.checked && !$('#bypassjstests').prop('checked')) {

    		if( productfournpriceid.length ){
				if(isBlank(productfournpriceid.val()) || productfournpriceid.val() == 0 ){
					productfournpriceid.get(0).setCustomValidity('Vous devez selectionner un prix fournisseur');
				}
    		}
    		
    		if( search_fk_soc_fourn.length ){
				if(isBlank(search_fk_soc_fourn.val())){
					search_fk_soc_fourn.get(0).setCustomValidity('Vous devez selectionner un fournisseur');
				}
    		}

    		if( fournUnitPrice.length ){
				if(isBlank(fournUnitPrice.val())  || fournUnitPrice.val() == 0  ){
					fournUnitPrice.get(0).setCustomValidity('Vous devez saisir un prix unitaire');
				}
    		}

    		if( inputqty.length ){
				if(isBlank(inputqty.val())  || inputqty.val() == 0  ){
					inputqty.get(0).setCustomValidity('Vous devez saisir une quantité');
				}
    		}

        }
        else
        {

    		if( productfournpriceid.length ){
				productfournpriceid.get(0).setCustomValidity('');
    		}
    		
    		if( search_fk_soc_fourn.length ){
				search_fk_soc_fourn.get(0).setCustomValidity('');
    		}

    		if( fournUnitPrice.length ){
    			fournUnitPrice.get(0).setCustomValidity('');
    		}

    		if( inputqty.length ){
    			inputqty.get(0).setCustomValidity('');
    		}
        }
    });
    
    
    $( "#emptydelivery" ).click(function() {
  		$('[name^="shipping"]' ).val(0);
	});
	
	$( ".addvalue2target" ).click(function() {
  		$( $( this ).data("target") ).val( $( this ).data("value") ).change();
	});
	
	$( ".qtyform" ).change(function() {

    	if($( this ).val() > 0)
    	{
    		$( "#linecheckbox" + $( this ).data("lineid") ).prop("checked", 1);
    		$( "#linecheckbox" + $( this ).data("lineid") ).trigger("change");
    		
    	}
    	else
    	{
    		$( "#linecheckbox" + $( this ).data("lineid") ).prop("checked", 0);
    		$( "#linecheckbox" + $( this ).data("lineid") ).trigger("change");
    	}
  		
	});


});