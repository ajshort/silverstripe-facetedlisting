<?php
/**
 * Handles displaying an individual item from a listing controller.
 *
 * @package silverstripe-facetedlisting
 */
class FacetedListingItemController extends Page_Controller {

	public static $allowed_actions = array(
		'index'
	);

	protected $parent;
	protected $item;

	public function __construct(FacetedListingController $parent, Dataobject $item) {
		$this->parent = $parent;
		$this->item   = $item;

		parent::__construct();
	}

	public function init() {
		if (!$this->item->canView()) {
			Security::permissionFailure($this);
		}

		parent::init();
	}

	/**
	 * @return string
	 */
	public function index() {
		return $this->parent->getViewer('view')->process($this);
	}

	/**
	 * @return FacetedListingController
	 */
	public function ParentController() {
		return $this->parent;
	}

	/**
	 * @return DataObject
	 */
	public function Item() {
		return $this->item;
	}

	/**
	 * @return string
	 */
	public function Title() {
		return $this->item->getTitle();
	}

	public function Link($action = null) {
		return Controller::join_links($this->parent->Link(), $this->item->ID, $action);
	}

}