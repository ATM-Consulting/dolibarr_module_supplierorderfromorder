 <?php 
 
define('INC_FROM_CRON_SCRIPT', 1);
 
 
require('../config.php');

dol_include_once('/product/class/product.class.php');
dol_include_once('/product/class/product.class.php');
dol_include_once('/fourn/class/fournisseur.commande.class.php');
dol_include_once("/fourn/class/fournisseur.class.php");
dol_include_once("/commande/class/commande.class.php");
dol_include_once('/product/stock/class/entrepot.class.php');
dol_include_once('/expedition/class/expedition.class.php');
dol_include_once('/supplierorderfromorder/class/sofo.class.php');
dol_include_once('/core/class/html.form.class.php');

$langs->load("products");
$langs->load("stocks");
$langs->load("orders");
$langs->load("supplierorderfromorder@supplierorderfromorder");
    
$action = GETPOST('action');
    
if($action='get-availability')
{
	$qty= GETPOST('stocktobuy');
	$fk_product= GETPOST('fk_product');
	$fk_soc= GETPOST('fk_soc');
	if($fk_product)
	{
		$nb_day = (int)TSOFO::getMinAvailability($fk_product, $qty,$fk_soc);
		print ($nb_day == 0 ? $langs->trans('Unknown') : $nb_day.' '.$langs->trans('Days'));
	}
    exit();
}