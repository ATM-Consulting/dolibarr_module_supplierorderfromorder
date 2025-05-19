<?php

/**
 * Class ActionsSupplierorderfromorder
 *
 * Hook actions
 */
class ActionsSupplierorderfromorder
{
	public $resprints;

	/**
	 * Add options to the order form
	 *
	 * @param array $parameters Hook context
	 * @param Commande $object The current order
	 * @param string $action The current action
	 * @param HookManager $hookmanager The current hookmanager
	 *
	 * @return int Status
	 */
	function formObjectOptions( $parameters, &$object, &$action, $hookmanager )
	{
		global $user, $langs, $conf;

		if (in_array( 'ordercard', explode( ':', $parameters['context'] ) ) && $object->statut > 0 && !empty($user->rights->supplierorderfromorder->read)) {
		  $langs->load( 'supplierorderfromorder@supplierorderfromorder' );


		if ($action != 'presend') {
        	?>

			<a id="listeProd" class="butAction" href="<?php
			if(!empty($conf->global->INCLUDE_PRODUCT_LINES_WITH_ADEQUATE_STOCK) && $conf->global->INCLUDE_PRODUCT_LINES_WITH_ADEQUATE_STOCK == 1 )
			{
				echo dol_buildpath('/supplierorderfromorder/ordercustomer.php?id=' . $object->id.'&projectid='.$object->fk_project.'&show_stock_no_need=yes',1);
			}
			else
			{
				echo dol_buildpath('/supplierorderfromorder/ordercustomer.php?id=' . $object->id.'&projectid='.$object->fk_project,1);

			}
			?>"><?php echo $langs->trans( 'OrderToSuppliers' ); ?></a>

			<script type="text/javascript">
				$(document).ready(function () {
					$('#listeProd').prependTo('div.tabsAction');
				})
			</script>

			<?php
		}
		}

		return 0;
	}


	function printObjectLine($parameters, &$object, &$action, $hookmanager){

	    global $db, $form, $langs, $conf;

	    if (in_array('ordersuppliercard',explode(':',$parameters['context'])))
	    {
	        if( !empty($conf->global->SOFO_DISPLAY_LINKED_ELEMENT_ON_LINES))
	        {
    	        dol_include_once('supplierorderfromorder/lib/function.lib.php');

    	        //$parameters = array('line'=>$line,'var'=>$var,'num'=>$num,'i'=>$i,'dateSelector'=>$dateSelector,'seller'=>$seller,'buyer'=>$buyer,'selected'=>$selected, 'extrafieldsline'=>$extrafieldsline);

    	        extract($parameters, EXTR_SKIP);
    	        if ($action != 'editline' || $selected != $line->id)
    	        {

    	            $result = 0;

    	            // DISPLAY SUPPLIER REFERENCE
    	            if(!empty($line->ref_supplier)){
    	                $line->description = '<strong>(ref fourn : '.$line->ref_supplier.')</strong> '.$line->description;
    	                $result = 1;
    	            }

    	            // DISPLAY EXTRA INFORMATIONS ON DESCRIPTION FROM  ORDERS LINES
    	            $searchOrderLine = getLinkedOrderLineFromSupplierOrderLine($line->id);

    	            if(!empty($searchOrderLine))
    	            {
    	                dol_include_once('commande/class/commande.class.php');
    	                $commandeLigne = new OrderLine($db);
    	                if($commandeLigne->fetch($searchOrderLine)>0)
    	                {


    	                    $line->description .= '<div style="color:#666666; padding-top:5px;" >';
    	                    $commande = new Commande($db);
    	                    $commande->fetch($commandeLigne->fk_commande);

    	                    $line->description .= $action.'<small><strong style="color:#666666;"  >Commande client ';

    	                    $line->description .= ' x '.$commandeLigne->qty;

    	                    if(!empty($line->fk_unit)){
    	                        $productDefault = new Product($db);
    	                        $productDefault->fk_unit = $line->fk_unit;
    	                        $line->description .= ' <abbr title="'.$productDefault->getLabelOfUnit().'"  >'.$productDefault->getLabelOfUnit('short').'</abbr>';
    	                    }

    	                    $line->description .= '</strong> : ';

    	                    $commande->fetch_thirdparty();
    	                    $line->description .=  ' '.$commande->thirdparty->name ; //$commandeFournisseur->thirdparty->getNomUrl(1,'supplier');

    	                    $line->description .=  ' '.$commande->getNomUrl(1);

    	                    $line->description .= '</small></div>';

    	                    $result = 1;
    	                }
    	            }

    	            // OVERRIDE PRINT LINE
    	            if($result)
    	            {
    	                $object->printObjectLine($action,$line,$var,$num,$i,$dateSelector,$seller,$buyer,$selected,$extrafieldsline);
    	                return 1;
    	            }
    	        }

	        }
	    }

	    if (in_array('ordercard',explode(':',$parameters['context'])))
	    {
	        if( !empty($conf->global->SOFO_DISPLAY_LINKED_ELEMENT_ON_LINES))
	        {
    	        extract($parameters, EXTR_SKIP);
    	        dol_include_once('supplierorderfromorder/lib/function.lib.php');

    	        // DISPLAY EXTRA INFORMATIONS ON DESCRIPTION FROM SUPPLIER ORDERS LINES
    	        if ($action != 'editline' || $selected != $line->id)
    	        {

    	            $overridePrintLine = false;


    	            // GET SUPPLIER ORDER INFOS
    	            dol_include_once('fourn/class/fournisseur.commande.class.php');

    	            $searchSupplierOrderLine = getLinkedSupplierOrderLineFromElementLine($line->id);

    	            if(!empty($searchSupplierOrderLine))
    	            {

    	                if(!empty($line->description))
    	                {
    	                    //$line->description .= '</br>';
    	                }

    	                $commandeFournisseurLigne = new CommandeFournisseurLigne($db);
    	                if($commandeFournisseurLigne->fetch($searchSupplierOrderLine)>0)
    	                {


    	                    $line->description .= '<div style="color:#666666; padding-top:5px;" >';
    	                    $commandeFournisseur = new CommandeFournisseur($db);
    	                    $commandeFournisseur->fetch($commandeFournisseurLigne->fk_commande);

    	                    $line->description .= $action.'<small><strong style="color:#666666;"  >Commande fournisseur ';

    	                    $line->description .= $commandeFournisseurLigne->ref_supplier.' x '.$commandeFournisseurLigne->qty;

    	                    if(!empty($line->fk_unit)){
    	                        $productDefault = new Product($db);
    	                        $productDefault->fk_unit = $line->fk_unit;
    	                        $line->description .= ' <abbr title="'.$productDefault->getLabelOfUnit().'"  >'.$productDefault->getLabelOfUnit('short').'</abbr>';
    	                    }

    	                    $line->description .= '</strong> : ';

    	                    $commandeFournisseur->fetch_thirdparty();
    	                    $line->description .=  ' '.$commandeFournisseur->thirdparty->name ; //$commandeFournisseur->thirdparty->getNomUrl(1,'supplier');

    	                    $line->description .=  ' '.$commandeFournisseur->getNomUrl(1);

    	                    $line->description .= '</small></div>';
    	                }

    	                $overridePrintLine = true;
    	            }


    	            // OVERRIDE PRINT LINE
    	            if($overridePrintLine)
    	            {
    	                $object->printObjectLine($action,$line,$var,$num,$i,$dateSelector,$seller,$buyer,$selected,$extrafieldsline);

    	                return 1;
    	            }
    	        }
	        }
	    }


	}


	/**
	 * 		On tri les commandes fournisseurs par commande client
	 *
	 * 		@param $parameters
	 * 		@param $object
	 * 		@param $action
	 * 		@param $hookmanager
	 */
	function printFieldListWhere($parameters, &$object, &$action, $hookmanager) {
		$this->resprints = '';
		dol_include_once('/supplierorderfromorder/class/sofo.class.php');
		$TContext = explode(':', $parameters['context']);
		if(in_array('supplierorderlist', $TContext)) {
			$origin_page = GETPOST('origin_page');
			if($origin_page === 'ordercustomer') {
                if(!empty($hookmanager->resPrint) && strpos(strtolower($hookmanager->resPrint), 'group by')) {
                    $hookmanager->resPrint = ' AND e.fk_source = '.GETPOST('id', 'int'). ' ' .  $hookmanager->resPrint;
				}
				else {
					$this->resprints = ' AND e.fk_source = '.GETPOST('id', 'int');
				}
			}
		}
		return 0;
	}

	/**
	 * 		Ajoute une jointure avec element_element qui permet de trier les factures fournisseur par id de commande client
	 *
	 * 		@param $parameters
	 * 		@param $object
	 * 		@param $action
	 * 		@param $hookmanage
	 */
	function printFieldListFrom($parameters, &$object, &$action, $hookmanager)
	{

		dol_include_once('/supplierorderfromorder/class/sofo.class.php');

		$TContext = explode(':', $parameters['context']);
		if(in_array('supplierorderlist', $TContext)) {
			$origin_page = GETPOST('origin_page');
			if($origin_page === 'ordercustomer') {
				$this->resprints = " LEFT JOIN ".MAIN_DB_PREFIX."element_element as e ON (cf.rowid = e.fk_target AND targettype = 'order_supplier' AND sourcetype = 'commande')";
			}
		}
		return 0;
	}

	/**
	 * @param $parameters
	 * @param $object
	 * @param $action
	 * @param $hookmanager
	 * @return int
	 */
	function printCommonFooter($parameters, &$object, &$action, $hookmanager){
		global $langs , $db;

		$TContext = explode(':', $parameters['context']);

		if(in_array('supplierorderlist', $TContext)) {
			// la page
			$origin_page = GETPOST('origin_page','alpha');
			$id = GETPOST('id','int');
			$ref = '';

			if($origin_page === 'ordercustomer'){
				$pos = strpos($_SERVER['SCRIPT_NAME'],DOL_URL_ROOT);
				if (is_int($pos)){
					$file = substr($_SERVER['SCRIPT_NAME'],$pos + strlen(DOL_URL_ROOT));
					if ($file == "/fourn/commande/list.php"){
						dol_include_once('/commande/class/commande.class.php');
						$cmd = NEW Commande($db);
						$res = $cmd->fetch($id);

						if ($res > 0 ){
							$ref = $langs->transnoentities('listOrderSupplierForCustomerCommand');
							$ref .= " ". $cmd->ref;
						}

						print '<script type="text/javascript">';
						// on remplace le titre original de la fiche par celui-ci
						print 'let nbElement = document.querySelector(".fiche .titre").firstChild.nodeValue.substring('.strlen($langs->trans('ListOfSupplierOrders')).'); ';
						print 'document.querySelector(".fiche .titre").firstChild.nodeValue = "'.$ref.'" + nbElement';
						print '</script>';
					}
				}
			}
            return 1;
        }

        return 0;
	}
}
