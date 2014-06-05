<?php
class ActionsSupplierorderfromorder
{
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) 
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
	}
}