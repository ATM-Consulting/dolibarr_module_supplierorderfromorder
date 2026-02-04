<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2013 ATM Consulting <support@atm-consulting.fr>
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
 */

/**
 * 	\defgroup	mymodule	MyModule module
 * 	\brief		MyModule module descriptor.
 * 	\file		core/modules/modMyModule.class.php
 * 	\ingroup	mymodule
 * 	\brief		Description and activation file for module MyModule
 */
include_once DOL_DOCUMENT_ROOT . "/core/modules/DolibarrModules.class.php";
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

/**
 * Description and activation class for module MyModule
 */
class modSupplierorderfromorder extends DolibarrModules
{

	/**
	 * 	Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * 	@param	DoliDB		$db	Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		$this->editor_name = 'ATM Consulting';
	    $this->editor_url = 'https://www.atm-consulting.fr';
        // Id for module (must be unique).
        // Use a free id here
        // (See in Home -> System information -> Dolibarr for list of used modules id).
        $this->numero = 104130; // 104000 to 104999 for ATM CONSULTING
        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'supplierorderfromorder';

        // Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
        // It is used to group modules in module setup page
        $this->family = "ATM Consulting - CRM";
        // Module label (no space allowed)
        // used if translation string 'ModuleXXXName' not found
        // (where XXX is value of numeric property 'numero' of module)
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        // Module description
        // used if translation string 'ModuleXXXDesc' not found
        // (where XXX is value of numeric property 'numero' of module)
        $this->description = "Module commande fournisseur à partir d'une commande client";
        // Possible values for version are: 'development', 'experimental' or version

        $this->version = '2.11.1';
		// Url to the file with your last numberversion of this module
		require_once __DIR__ . '/../../class/techatm.class.php';
		$this->url_last_version = \supplierorderfromorder\TechATM::getLastModuleVersionUrl($this);

		// Key used in llx_const table to save module status enabled/disabled
		// (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		// Where to store the module in setup page
		// (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png
		// use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png
		// use this->picto='pictovalue@module'
		$this->picto = 'module.svg@supplierorderfromorder'; // mypicto@mymodule
		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /mymodule/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /mymodule/core/modules/barcode)
		// for specific css file (eg: /mymodule/css/mymodule.css.php)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory
			'triggers' => 1,
			// Set this to 1 if module has its own login method directory
			//'login' => 0,
			// Set this to 1 if module has its own substitution function file
			//'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory
			//'menus' => 0,
			// Set this to 1 if module has its own barcode directory
			//'barcode' => 0,
			// Set this to 1 if module has its own models directory
			//'models' => 0,
			// Set this to relative path of css if module has its own css file
			//'css' => '/mymodule/css/mycss.css.php',
			// Set here all hooks context managed by module
			'hooks' => array(
				'ordercard'
				,'ordersuppliercard'
				,'supplierorderlist'
			)
			// Set here all workflow context managed by module
			//'workflow' => array('order' => array('WORKFLOW_ORDER_AUTOCREATE_INVOICE'))
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/mymodule/temp");
		$this->dirs = array();

		// Config pages. Put here list of php pages
		// stored into mymodule/admin directory, used to setup module.
		$this->config_page_url = array("supplierorderfromorder_setup.php@supplierorderfromorder");

		// Dependencies
		// List of modules id that must be enabled if this module is enabled
		$this->depends = array();
		// List of modules id to disable if this one is disabled
		$this->requiredby = array();
		// Minimum version of PHP required by module
		$this->phpmin = array(7, 0);
		// Minimum version of Dolibarr required by module
		$this->need_dolibarr_version = array(16, 0);
		$this->langfiles = array("supplierorderfromorder@supplierorderfromorder"); // langfiles@mymodule
		// Constants
		// List of particular constants to add when module is enabled
		// (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example:
		$this->const = array();

		// Array to add new pages in new tabs
		// Example:
		$this->tabs = array();

		// Dictionnaries
		if (!isset($conf->ordersupplierfromorder->enabled)) {
			$conf->ordersupplierfromorder=new stdClass();
			$conf->ordersupplierfromorder->enabled = 0;
		}
		$this->dictionnaries = array();


		// Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
		$this->boxes = array(); // Boxes list
		$r = 0;
		// Example:

		/*
		  $this->boxes[$r][1] = "myboxb.php";
		  $r++;
		 */

		// Permissions
		$this->rights = array(); // Permission array used by this module
		$r = 0;

		$this->rights[$r][0] = $this->numero + $r;  // Permission id (must not be already used)
		$this->rights[$r][1] = 'Convertir les commandes clients en commandes fournisseurs';  // Permission label
		$this->rights[$r][3] = 0;                   // Permission by default for new user (0/1)
		$this->rights[$r][4] = 'read';              // In php code, permission will be checked by test if ($user->hasRight('permkey', 'level1', 'level2'))
		$this->rights[$r][5] = '';		    // In php code, permission will be checked by test if ($user->hasRight('permkey', 'level1', 'level2'))
		$r++;

		// Main menu entries
		$this->menus = array(); // List of menus to add
		$r = 0;

		$this->menu[]=array(
			'fk_menu'=>'fk_mainmenu=of',     // Use r=value where r is index key used for the parent menu entry (higher parent must be a top menu entry)
			'type'=>'left',         // This is a Left menu entry
			'titre'=>'ProductsToOrder',
			'mainmenu'=>'replenishGPAO',
			'leftmenu'=>'replenishGPAO',
			'url'=>'/supplierorderfromorder/ordercustomer.php',
			'langs'=>'supplierorderfromorder@supplierorderfromorder',
			'perms' => '',
			'position'=>300,
			'target'=>'',
			'user'=>2
		);


		// Exports
		$r = 1;
	}

	/**
	 * Function called when module is enabled.
	 * The init function add constants, boxes, permissions and menus
	 * (defined in constructor) into Dolibarr database.
	 * It also creates data directories
	 *
	 * 	@param		string	$options	Options when enabling module ('', 'noboxes')
	 * 	@return		int					1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();

		$result = $this->loadTables();

		// Create extrafields (idempotent) on supplier order lines and receptions
		global $langs, $conf;
		$langs->loadLangs(array('main', 'order', 'companies', 'supplierorderfromorder@supplierorderfromorder'));
		$extrafields = new ExtraFields($this->db);
		$elements = array('commande_fournisseurdet', 'receptiondet_batch');
		$linkOrderParams = array('options' => array('Commande:commande/class/commande.class.php' => null));
		$linkThirdpartyParams = array('options' => array('Societe:societe/class/societe.class.php' => null));
		$enabledCondition = 'isModEnabled("supplierorderfromorder") && getDolGlobalInt("SOFO_ENABLE_LINKED_EXTRAFIELDS")';
		foreach ($elements as $elementtype) {
			// Visibility/list = 2 (view only, hidden on create/edit forms)
			$extrafields->addExtraField('SOFO_linked_order', $langs->transnoentities('Order'), 'link', 101, '', $elementtype, 0, 0, '', $linkOrderParams, 0, '', 2, '', '', $conf->entity, 'supplierorderfromorder@supplierorderfromorder', $enabledCondition, 0, 0);
			$extrafields->addExtraField('SOFO_linked_thirdparty', $langs->transnoentities('ThirdParty'), 'link', 102, '', $elementtype, 0, 0, '', $linkThirdpartyParams, 0, '', 2, '', '', $conf->entity, 'supplierorderfromorder@supplierorderfromorder', $enabledCondition, 0, 0);
		}

        return $this->_init($sql, $options);
    }

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * 	@param		string	$options	Options when enabling module ('', 'noboxes')
	 * 	@return		int					1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}

	/**
	 * Create tables, keys and data required by module
	 * Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * and create data commands must be stored in directory /mymodule/sql/
	 * This function is called by this->init
	 *
	 * 	@return		int		<=0 if KO, >0 if OK
	 */
	private function loadTables()
	{
		return $this->_load_tables('/supplierorderfromorder/sql/');
	}
}
