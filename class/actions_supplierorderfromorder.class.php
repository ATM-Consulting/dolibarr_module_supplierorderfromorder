<?php
class ActionsSupplierorderfromorder
{
	// Fonction plus propre ne fonctionnant qu'à partir de la 3.5
	/*function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) 
	{
		global $langs;
		$langs->load('supplierorderfromorder@supplierorderfromorder');
		
		if (in_array('ordercard',explode(':',$parameters['context'])) && ($object->statut == 1 || $object->statut == 2))
		{
			print '<div class="inline-block divButAction">';
			print '<a class="butAction" href="' . dol_buildpath('/supplierorderfromorder/ordercustomer.php?id='.$_REQUEST['id'], 2) . '">' . $langs->trans('OrderToSuppliers') . '</a></div>';
		}
		
		$this->results=array('myreturn'=>$myvalue);
		$this->resprints='A text to show';
		
		return 0;
	}*/
	
	function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    {
    	global $langs;
		$langs->load('supplierorderfromorder@supplierorderfromorder');
        /*print_r($parameters); 
        echo "action: ".$action; 
        print_r($object);*/
 		if (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut > 0)
        {
          ?>
          	<a id="listeProd" class="butAction" href="<?php echo dol_buildpath('/supplierorderfromorder/ordercustomer.php?id='.$_REQUEST['id'], 2); ?>"><?php echo $langs->trans('OrderToSuppliers'); ?></a>
           <script type="text/javascript">
				$(document).ready(function() {
					$('#listeProd').prependTo('div.tabsAction');
				})
			</script>

          <?php
        }
 
        $this->results=array('myreturn'=>$myvalue);
        $this->resprints='A text to show';
 
        return 0;
    }
}