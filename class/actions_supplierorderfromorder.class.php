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
}
