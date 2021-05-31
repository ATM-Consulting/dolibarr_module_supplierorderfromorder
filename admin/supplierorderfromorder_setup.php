<?php

$res=@include("../../main.inc.php");						// For root directory
if (! $res) $res=@include("../../../main.inc.php");			// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

dol_include_once('/supplierorderfromorder/lib/function.lib.php');

$langs->load("admin");
$langs->load('supplierorderfromorder@supplierorderfromorder');

global $db;

// Security check
if (! $user->admin) accessforbidden();

$action=GETPOST('action', 'alpha');
$id=GETPOST('id', 'int');

/*
 * Action
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];

	$value = GETPOST($code, 'none');

	if($code == 'SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER') {

		if(is_array($value))
		{
			if(in_array(-1, $value) && count($value) > 1) {
				unset($value[array_search(-1, $value)]);
			}
			$TCategories = array_map('intval', $value);
		}
		elseif($value > 0)
		{
			$TCategories = array(intval($value));
		}
		else {
			$TCategories = array(-1);
		}

		$value = serialize($TCategories);
	}

	if (dolibarr_set_const($db, $code, $value, 'chaine', 0, '', $conf->entity) > 0)
	{

		if($code=='SOFO_USE_DELIVERY_TIME' && GETPOST($code, 'none') == 1) {

			dolibarr_set_const($db,'FOURN_PRODUCT_AVAILABILITY',1);
		}

		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */

llxHeader('',$langs->trans("SupplierOrderFromOrder"));


// Configuration header
$head = supplierorderfromorderAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104130Name"),
    0,
    "supplierorderfromorder@supplierorderfromorder"
    );

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("SupplierOrderFromOrder"),$linkback,'supplierorderfromorder@supplierorderfromorder');


dol_fiche_end();

print '<br>';

$form=new Form($db);
$var=true;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";


// Add shipment as titles in invoice
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CreateNewSupplierOrderAnyTime").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME">';
print $form->selectyesno("SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME",$conf->global->SOFO_CREATE_NEW_SUPPLIER_ODER_ANY_TIME,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Create a new line in supplier order for each line having a different description in the customer order
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("UseOrderLineDescInSupplierOrder").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SUPPORDERFROMORDER_USE_ORDER_DESC">';
print $form->selectyesno("SUPPORDERFROMORDER_USE_ORDER_DESC",$conf->global->SUPPORDERFROMORDER_USE_ORDER_DESC,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Create identical supplier order to order
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("AddFreeLinesInSupplierOrder").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_ADD_FREE_LINES">';
print $form->selectyesno("SOFO_ADD_FREE_LINES",$conf->global->SOFO_ADD_FREE_LINES,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

if (!empty($conf->global->SOFO_ADD_FREE_LINES)) {
	//Use cost price as buying price for free lines
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("UseCostPriceAsBuyingPrice").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_SOFO_COST_PRICE_AS_BUYING">';
	print $form->selectyesno("SOFO_COST_PRICE_AS_BUYING",$conf->global->SOFO_COST_PRICE_AS_BUYING,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print '</td></tr>';
}


// Create distinct supplier order from order depending of project
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CreateDistinctSupplierOrderFromOrderDependingOfProject").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_DISTINCT_ORDER_BY_PROJECT">';
print $form->selectyesno("SOFO_DISTINCT_ORDER_BY_PROJECT",$conf->global->SOFO_DISTINCT_ORDER_BY_PROJECT,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

// Import Shipping contact in supplier order
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY">';
print $form->selectyesno("SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY",$conf->global->SUPPLIERORDER_FROM_ORDER_CONTACT_DELIVERY,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';


// Header to supplier order if only one supplier reported
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("SUPPLIERORDER_FROM_ORDER_HEADER_SUPPLIER_ORDER").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SUPPLIERORDER_FROM_ORDER_HEADER_SUPPLIER_ORDER">';
print $form->selectyesno("SUPPLIERORDER_FROM_ORDER_HEADER_SUPPLIER_ORDER",$conf->global->SUPPLIERORDER_FROM_ORDER_HEADER_SUPPLIER_ORDER,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("UseDeliveryTimeToReplenish").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_USE_DELIVERY_TIME">';
print $form->selectyesno("SOFO_USE_DELIVERY_TIME",$conf->global->SOFO_USE_DELIVERY_TIME,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("UseVirtualStockOfOrdersSkipDoliConfig").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_USE_VIRTUAL_ORDER_STOCK">';
print $form->selectyesno("SOFO_USE_VIRTUAL_ORDER_STOCK",$conf->global->SOFO_USE_VIRTUAL_ORDER_STOCK,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("SOFO_USE_ONLY_OF_FOR_NEEDED_PRODUCT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_USE_ONLY_OF_FOR_NEEDED_PRODUCT">';
print $form->selectyesno("SOFO_USE_ONLY_OF_FOR_NEEDED_PRODUCT",$conf->global->SOFO_USE_ONLY_OF_FOR_NEEDED_PRODUCT,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("SOFO_DO_NOT_USE_CUSTOMER_ORDER").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_DO_NOT_USE_CUSTOMER_ORDER">';
print $form->selectyesno("SOFO_DO_NOT_USE_CUSTOMER_ORDER",$conf->global->SOFO_DO_NOT_USE_CUSTOMER_ORDER,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("SOFO_DEFAUT_FILTER").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_DEFAUT_FILTER">';
$statutarray=array('1' => $langs->trans("Finished"), '0' => $langs->trans("RowMaterial"));
print $form->selectarray('SOFO_DEFAUT_FILTER',$statutarray,$conf->global->SOFO_DEFAUT_FILTER,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("SOFO_GET_INFOS_FROM_FOURN").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_GET_INFOS_FROM_FOURN">';
print $form->selectyesno("SOFO_GET_INFOS_FROM_FOURN",$conf->global->SOFO_GET_INFOS_FROM_FOURN,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("SOFO_USE_MAX_DELIVERY_DATE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_USE_MAX_DELIVERY_DATE">';
print $form->selectyesno("SOFO_USE_MAX_DELIVERY_DATE",$conf->global->SOFO_USE_MAX_DELIVERY_DATE,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("SOFO_DISPLAY_SERVICES").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_DISPLAY_SERVICES">';
print $form->selectyesno("SOFO_DISPLAY_SERVICES",$conf->global->SOFO_DISPLAY_SERVICES,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('SOFO_PRESELECT_SUPPLIER_PRICE_FROM_LINE_BUY_PRICE').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_SOFO_PRESELECT_SUPPLIER_PRICE_FROM_LINE_BUY_PRICE">';
print $form->selectyesno('SOFO_PRESELECT_SUPPLIER_PRICE_FROM_LINE_BUY_PRICE', $conf->global->SOFO_PRESELECT_SUPPLIER_PRICE_FROM_LINE_BUY_PRICE,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans('TAKE_CARE_OF_APPROUVED_SUPPLIER_ORDER_FOR_VIRTUAL_STOCK').'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_TAKE_CARE_OF_APPROUVED_SUPPLIER_ORDER_FOR_VIRTUAL_STOCK">';
print $form->selectyesno('TAKE_CARE_OF_APPROUVED_SUPPLIER_ORDER_FOR_VIRTUAL_STOCK', $conf->global->TAKE_CARE_OF_APPROUVED_SUPPLIER_ORDER_FOR_VIRTUAL_STOCK,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

if($conf->global->PRODUIT_SOUSPRODUITS) {
	$var = !$var;
	print '<tr ' . $bc[$var] . '>';
	print '<td>' . $langs->trans('SOFO_VIRTUAL_PRODUCTS') . '</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="set_SOFO_VIRTUAL_PRODUCTS">';
	print $form->selectyesno("SOFO_VIRTUAL_PRODUCTS", $conf->global->SOFO_VIRTUAL_PRODUCTS, 1);
	print '<input type="submit" class="button" value="' . $langs->trans("Modify") . '">';
	print '</form>';
	print '</td></tr>';
}

if ($conf->multicompany->enabled && ! empty($conf->global->MULTICOMPANY_STOCK_SHARING_ENABLED)) {
	$var = !$var;
	print '<tr ' . $bc[$var] . '>';
	print '<td>' . $langs->trans('SOFO_CHECK_STOCK_ON_SHARED_STOCK') . '</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="set_SOFO_CHECK_STOCK_ON_SHARED_STOCK">';
	print $form->selectyesno('SOFO_CHECK_STOCK_ON_SHARED_STOCK', $conf->global->SOFO_CHECK_STOCK_ON_SHARED_STOCK, 1);
	print '<input type="submit" class="button" value="' . $langs->trans("Modify") . '">';
	print '</form>';
	print '</td></tr>';
}

if(! empty($conf->categorie->enabled)) {
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="right" width="300">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="form_SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="action" value="set_SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER">';
	print getCatMultiselect("SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER", ! empty($conf->global->SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER) ? unserialize($conf->global->SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER) : array(-1));
	print '<a href="javascript:;" id="clearfilter">Supprimer le filtre</a>';
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
?>
	<script type="text/javascript">
	$('a#clearfilter').click(function() {
		$('option:selected', $('select#SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER')).prop('selected', false);
		$('option[value=-1]', $('select#SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER')).prop('selected', true);
		$('form#form_SOFO_DEFAULT_PRODUCT_CATEGORY_FILTER').submit();
		return false;
	})
	</script>
<?php
	print '</td></tr>';
}

print '</table>';

// Footer
llxFooter();
// Close database handler
$db->close();
