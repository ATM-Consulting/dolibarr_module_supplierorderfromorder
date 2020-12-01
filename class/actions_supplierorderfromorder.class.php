<?php

/**
 * Class ActionsSupplierorderfromorder
 *
 * Hook actions
 */
class ActionsSupplierorderfromorder
{
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
		global $user, $langs;
		
		if (in_array( 'ordercard', explode( ':', $parameters['context'] ) ) && $object->statut > 0 && !empty($user->rights->supplierorderfromorder->read)) {
		$langs->load( 'supplierorderfromorder@supplierorderfromorder' );
	

			?>
			<a id="listeProd" class="butAction" href="<?php 
			echo dol_buildpath('/supplierorderfromorder/ordercustomer.php?id=' . $object->id.'&projectid='.$object->fk_project,1);
			?>"><?php echo $langs->trans( 'OrderToSuppliers' ); ?></a>
			<script type="text/javascript">
				$(document).ready(function () {
					$('#listeProd').prependTo('div.tabsAction');
				})
			</script>

		<?php
		}

		return 0;
	}

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
	function printObjectLine($parameters, &$object, &$action, $hookmanager){
	    
	    global $db, $form, $langs, $conf;
	    
	    if (in_array('ordersuppliercard',explode(':',$parameters['context'])))
	    {
	        if( $conf->global->SOFO_DISPLAY_LINKED_ELEMENT_ON_LINES)
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
	        if( $conf->global->SOFO_DISPLAY_LINKED_ELEMENT_ON_LINES)
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
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		$massAction = $parameters['massaction'];

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('orderlist'))) {
			if ($parameters['currentcontext'] == 'orderlist') {
				if ($massAction == 'supplierorderfromorder') {
					$orders = [];
					foreach ($parameters['toselect'] as $objectid) {
						$orders[] = $objectid;
					}
					if (!empty($orders)) {
						$urltogo = dol_buildpath('/supplierorderfromorder/ordercustomer.php?id='.implode(',', $orders), 1);
						header("Location: ".$urltogo);
						exit;
					}
				}
			}
		}

		if (!$error) {
			//$this->results = array('myreturn' => 999);
			return 0; // or return 1 to replace standard code
		} else {
			return -1;
		}
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('orderlist', 'productlist', 'productservicelist')))		// do something only for the context 'somecontext1' or 'somecontext2'
		{
			if ($parameters['currentcontext'] == 'orderlist') {
				$langs->load("supplierorderfromorder@supplierorderfromorder");
				if ($user->rights->fournisseur->commande->creer) {
					$disabled = 0;
				} else {
					$disabled = 1;
				}
				$label = '<span class="fa fa-shopping-cart paddingrightonly"></span>' . $langs->trans("OrderToSuppliers");
				$this->resprints = '<option value="supplierorderfromorder"' . ($disabled ? ' disabled="disabled"' : '') . ' data-html="' . dol_escape_htmltag($label) . '">' . $label . '</option>';
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}
}
