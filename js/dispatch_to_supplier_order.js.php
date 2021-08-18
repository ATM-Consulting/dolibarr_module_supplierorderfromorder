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
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK', 1);
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


include_once __DIR__ . '/../config.php';

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
         var checkBoxes = $(".checkboxToggle:not(.checkboxToggle-nomenclature)");
         checkBoxes.prop("checked", this.checked);
         checkBoxes.trigger("change");
    });

    $("#checkToggleNomenclature").click(function() {
        var checkBoxes = $(".checkboxToggle.checkboxToggle-nomenclature");
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
    	var isNomenclatureline = lineId.match(/[0-9]+\_[0-9]+/g) == null ? false : true;
		var nomLineId = 0;

    	if (isNomenclatureline)
		{
			nomLineId = lineId;
			lineId = lineId.split('_')[0];
		}
    	//console.log(lineId.match(/[0-9]+\_[0-9]+/g), nomLineId, lineId);

    	if (!isNomenclatureline)
		{
			var productfournpriceid = $("[name='productfournpriceid[" + lineId + "]']");
			var search_fk_soc_fourn = $("#search_fk_soc_fourn_" + lineId);
			var fournUnitPrice  = $("[name='fournUnitPrice[" + lineId + "]']");
			var inputqty = $("#qty-" + lineId);
		}
    	else
		{
			var productfournpriceid = $("[name='nomenclature_productfournpriceid[" + lineId + "][" + nomLineId + "]']");
			var search_fk_soc_fourn = $("#fk_soc_fourn_" + lineId + "_n" + nomLineId);
			var fournUnitPrice  = $("[name='nomenclature_fournUnitPrice[" + lineId + "][" + nomLineId + "]']");
			var inputqty = $("#qty-" + lineId + "-n" + nomLineId);
		}

        if(this.checked && !$('#bypassjstests').prop('checked')) {

			console.log(search_fk_soc_fourn);
			if( productfournpriceid.length ){
				if(isBlank(productfournpriceid.val()) || productfournpriceid.val() == 0 ){
					productfournpriceid.get(0).setCustomValidity("<?php print $langs->transnoentitiesnoconv('YouNeedToSelectAtLeastOneSupplierPrice'); ?>");
				}
    		}

    		if( search_fk_soc_fourn.length ){
				if(isBlank(search_fk_soc_fourn.val())){
					search_fk_soc_fourn.get(0).setCustomValidity("<?php print $langs->transnoentitiesnoconv('YouNeedToSelectAtLeastOneSupplierPrice'); ?>");
				}
    		}

    		if( fournUnitPrice.length ){
				if(isBlank(fournUnitPrice.val())  || fournUnitPrice.val() == 0  ){
					fournUnitPrice.get(0).setCustomValidity("<?php print $langs->transnoentitiesnoconv('YouNeedToGiveASupplierPrice'); ?>");
				}
    		}

    		if( inputqty.length ){
				if(isBlank(inputqty.val())  || inputqty.val() == 0  ){
					inputqty.get(0).setCustomValidity("<?php print $langs->transnoentitiesnoconv('YouNeedToGiveAQty'); ?>");
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


    $( ".toggle-display-nomenclature-detail" ).click(function() {
        console.log($(this).data('target'));
  		$('.nomenclature-row[data-parentlineid="' + $(this).data('target') + '"]').slideToggle();
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



	$( ".qtyform-nomenclature" ).change(function() {

    	if($( this ).val() > 0)
    	{
    		$( "#linecheckbox" + $( this ).data("nomenclature") + "-nomenclature" ).prop("checked", 1);
    		$( "#linecheckbox" + $( this ).data("nomenclature") + "-nomenclature" ).trigger("change");
    	}
    	else
    	{
    		$( "#linecheckbox" + $( this ).data("nomenclature") + "-nomenclature" ).prop("checked", 0);
    		$( "#linecheckbox" + $( this ).data("nomenclature") + "-nomenclature" ).trigger("change");
    	}

	});



	// MORE OPTION SLIDE
    $(".moreoptionbtn").click(function(){
        $($(this).data('target')).slideToggle();
    });


});
