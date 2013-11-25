<?php 
class ActionsSupplierorderfromorder
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */ 
    function formObjectOptions($parameters, &$object, &$action, $hookmanager) 
    { 
        /*print_r($parameters); 
        echo "action: ".$action; 
        print_r($object);*/
 		if (in_array('ordercard',explode(':',$parameters['context'])) && $object->statut > 0)
        {
          ?>
          	<a id="listeProd" class="butAction" href="<?=DOL_URL_ROOT."/custom/supplierorderfromorder/ordercustomer.php?id=".$_REQUEST['id']?>">Liste produits à commander</a>
           <script type="text/javascript">
				$(document).ready(function() {
					$('#listeProd').appendTo('div.tabsAction');
				})
			</script>

          <?
        }
 
        $this->results=array('myreturn'=>$myvalue);
        $this->resprints='A text to show';
 
        return 0;
    }
}