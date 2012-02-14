<?php
/**
 * The base listing controller class. This should be subclassed for each listing
 * controller, with methods being overloaded to build functionality.
 *
 * @package silverstripe-facetedlisting
 */
abstract class FacetedListingController extends Page_Controller {

	public static $url_handlers = array(
		''           => 'handleList',
		'FilterForm' => 'FilterForm',
		'facets'     => 'handleFacets',
		'$ItemID!'   => 'handleView'
	);

	public static $allowed_actions = array(
		'handleList',
		'handleView',
		'FilterForm'
	);

	/**
	 * @var DataObjectSet
	 */
	protected $sourceItems;

	/**
	 * The controller that is created to handle rendering individual items.
	 *
	 * @var string
	 */
	protected $itemController = 'FacetedListingItemController';

	public function init() {
		parent::init();

		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-metadata/jquery.metadata.js');
		Requirements::javascript('facetedlisting/javascript/FacetedListing.js');
	}

	/**
	 * @return string
	 */
	public function handleList() {
		return $this->getViewer('list')->process($this);
	}

	/**
	 * @return Controller
	 */
	public function handleView($request) {
		$id    = $request->param('ItemID');
		$class = $this->getItemClass();

		if (!ctype_digit($id)) {
			$this->httpError(400, 'Invalid item ID specified.');
		}

		if (!$item = DataObject::get_by_id($class, $id)) {
			$this->httpError(404, 'Item not found.');
		}

		return new $this->itemController($this, $item);
	}

	// OVERLOAD THESE ----------------------------------------------------------

	/**
	 * Returns the class that is listed on this listing controller.
	 *
	 * @return string
	 */
	abstract public function getItemClass();

	/**
	 * Returns the fields that are displayed as columns in the listing table.
	 * This defaults to the object's summary fields.
	 *
	 * @return array
	 */
	public function getListingFields() {
		return singleton($this->getItemClass())->summaryFields();
	}

	/**
	 * Returns the fields that the user can order by.
	 *
	 * @return array
	 */
	public function getSortableFields() {
		return array();
	}

	/**
	 * Returns the field names that should be filterable with a faceting
	 * dropdown.
	 *
	 * @return aray
	 */
	public function getFacetableFields() {
		return array();
	}

	/**
	 * Returns custom search filter types for faceting.
	 *
	 * @return array
	 */
	public function getFacetFilters() {
		return array();
	}

	/**
	 * Returns an array of fields that should be matched against for a freeform
	 * keyword search. If this is not set, then the user will not be able to
	 * search by keyword.
	 *
	 * @return array
	 */
	public function getFulltextFields() {
		return false;
	}

	/**
	 * @return array
	 */
	public function getDefaultSort() {
		$sort = Object::get_static($this->getItemClass(), 'default_sort');
		if (preg_match('/"?([a-zA-Z]+)"? ([Aa][Ss][Cc]|[Dd][Ee][Ss][Cc])/', $sort, $matches)) {
			return array('sort' => $matches[1], 'dir' => $matches[2]);
		}
		else {
			return array('sort' => trim($sort, '"'), 'dir' => 'DESC');
		}
	}

	/**
	 * @return array
	 */
	public function getAllowedItemsPerPage() {
		return array(10, 20, 50, 100);
	}

	/**
	 * @return int
	 */
	public function getDefaultItemsPerPage() {
		return 20;
	}

	// -------------------------------------------------------------------------

	/**
	 * @return Form
	 */
	public function FilterForm() {
		$fields = new FieldSet();

		if ($this->getFulltextFields()) {
			$fields->push(new TextField('Keywords'));
		}

		foreach ($this->getFacetableFields() as $name => $title) {
			$fieldName = $this->convertNameToRelationID($name);
			$fieldName = str_replace('.', '__', $fieldName);

			$fields->push(new DropdownField(
				$fieldName, $title, $this->getFacetMap($name), null, null, '(any)'
			));
		}

		$form = new Form($this, 'FilterForm', $fields, new FieldSet(
			new FormAction('doFilter', 'Filter'),
			$reset = new ResetFormAction('reset', 'Reset')
		));
		$reset->useButtonTag = true;

		// Pass along all the useful form GET params
		$fields->push(new HiddenField('sort', '', $this->request->getVar('sort')));
		$fields->push(new HiddenField('dir', '', $this->request->getVar('dir')));
		$fields->push(new HiddenField('perpage', '', $this->request->getVar('perpage')));

		$form->setFormMethod('GET');
		$form->disableSecurityToken();
		$form->addExtraClass(sprintf("{facetsLink: '%s'}", Convert::raw2js(
			Controller::join_links($this->Link(), 'facets')
		)));

		return $form;
	}

	/**
	 * Returns a cached version of the initial filter form.
	 *
	 * @return string
	 */
	public function CachedFilterForm() {
		$cache = SS_Cache::factory('ListingController', 'Core', array(
			'lifetime' => 3600
		));
		$key   = "{$this->class}_FilterForm";

		if (isset($_GET['flush']) || !$result = $cache->load($key)) {
			$result = $this->FilterForm()->forTemplate();
			$cache->save($result, $key);
		}

		return $result;
	}

	/**
	 * Returns the filtered facets to populate dynamically into the facet fields.
	 *
	 * @return string
	 */
	public function handleFacets($request) {
		$data = $request->getVars();
		$data = $this->generateFacetJson($data);

		$response = new SS_HTTPResponse();
		$response->addHeader('Content-Type', 'application/json');
		$response->setBody($data);
		return $response;
	}

	public function doFilter($data, $form) {
		$query  = $this->generateQuery($data, $form);
		$result = $query->execute();

		$this->sourceItems = singleton('DataObject')->buildDataObjectSet($result);

		if ($this->sourceItems) {
			$this->sourceItems->parseQueryLimit($query);
		} else {
			$this->sourceItems = new DataObjectSet();
		}

		// Add faceting data to the page.
		$metadata = sprintf(
			'<script id="listing-facets" type="data">%s</script>',
			$this->generateFacetJson($data)
		);
		Requirements::insertHeadTags($metadata, 'listing-facets');

		$controller = $this->customise(array(
			'Title'            => $this->PluralName(),
			'CachedFilterForm' => $form
		));
		return $this->getViewer('list')->process($controller);
	}

	/**
	 * @param  array $data
	 * @param  Form $form
	 * @return SQLQuery
	 */
	protected function generateQuery($data, $form) {
		$context = new SearchContext($this->getItemClass());

		foreach (array_keys($this->getFacetableFields()) as $name) {
			$context->addFilter($this->getFacetFilter($name));
		}

		$query = $context->getQuery($data);
		$query->orderby($this->getSqlSort());
		$query->limit(array(
			'start' => $this->getPaginationStart(),
			'limit' => $this->getItemsPerPage()
		));

		if ($indexes = $this->getFulltextFields()) {
			if (isset($data['Keywords']) && strlen($data['Keywords'])) {
				$query->where(sprintf(
					'MATCH(%s) AGAINST (\'%s\')',
					'"' . implode('", "', $indexes) . '"',
					Convert::raw2sql($data['Keywords'])
				));
			}
		}

		return $query;
	}

	/**
	 * @param  array $data
	 * @return string
	 */
	protected function generateFacetJson($data) {
		$context = new SearchContext($this->getItemClass());
		$facets  = array();

		foreach (array_keys($this->getFacetableFields()) as $name) {
			$context->addFilter($this->getFacetFilter($name));
		}

		$query = $context->getQuery($data);

		foreach (array_keys($this->getFacetableFields()) as $name) {
			$map   = $this->getFacetMap($name, $query);
			$data  = array();

			$fieldName = $this->convertNameToRelationID($name);
			$fieldName = str_replace('.', '__', $fieldName);

			foreach ($map as $key => $title) {
				$data[$key] = $title;
			}

			$facets[$fieldName] = $data;
		}

		return Convert::array2json($facets);
	}

	/**
	 * @param  string $name
	 * @return SearchFilter
	 */
	protected function getFacetFilter($name) {
		$custom = $this->getFacetFilters();
		$class  = 'ExactMatchFilter';

		if (array_key_exists($name, $custom)) {
			$class = $custom[$name];
		}

		return new $class($this->convertNameToRelationID($name));
	}

	/**
	 * @param  string $name
	 * @param  SQLQuery $query An optional base query to build off.
	 * @return FacetedSqlMap
	 */
	protected function getFacetMap($name, $query = null) {
		if (!$query) {
			$query  = new SQLQuery();
			$query->from($this->getItemClass());
		} else {
			$query = clone $query;
		}

		$filter = new FacetedUtilSearchFilter($name);
		$filter->setModel($this->getItemClass());
		$filter->applyRelation($query);

		if (!strpos($name, '.')) {
			$idField = $filter->getDbName();
		} else {
			$idFilter = clone $filter;
			$idFilter->setName('ID');
			$idField = $idFilter->getDbName();
		}

		$query->select(
			'COUNT(*)', "{$idField} AS \"ID\"", "{$filter->getDbName()} as \"Title\""
		);
		$query->orderby($filter->getDbName());
		$query->groupby($filter->getDbName());

		$map = new FacetedSqlMap($query);
		$map->setName($name);

		return $map;
	}

	// -------------------------------------------------------------------------

	/**
	 * @return DataObjectSet
	 */
	protected function getSourceItems() {
		if ($this->sourceItems) return $this->sourceItems;

		$class = $this->getItemClass();
		$sort  = $this->getSqlSort();

		$items = DataObject::get($class, null, $sort, null, array(
			'start' => $this->getPaginationStart(),
			'limit' => $this->getItemsPerPage()
		));
		return $this->sourceItems = $items;
	}

	/**
	 * @return array
	 */
	protected function getSort() {
		$sort = $this->request->getVar('sort');
		$dir  = $this->request->getVar('dir');

		if (in_array($sort, $this->getSortableFields())) {
			if (strtoupper($dir) == 'DESC') {
				return array('sort' => $sort, 'dir' => 'DESC');
			} else {
				return array('sort' => $sort, 'dir' => 'ASC');
			}
		} else {
			return $this->getDefaultSort();
		}
	}

	/**
	 * @return string
	 */
	public function getSqlSort() {
		$sort = $this->getSort();
		return "\"{$sort['sort']}\" {$sort['dir']}";
	}

	/**
	 * @return int
	 */
	protected function getPaginationStart() {
		if(!isset($_GET['start']) || !ctype_digit($_GET['start']) || (int) $_GET['start'] < 1) {
			return 0;
		} else {
			return (int) $_GET['start'];
		}
	}

	/**
	 * @return int
	 */
	protected function getItemsPerPage() {
		$allowed = $this->getAllowedItemsPerPage();
		$request = $this->request->getVar('perpage');

		if ((int) $request > 0 && in_array($request, $allowed)) {
			return (int) $request;
		} else {
			return $this->getDefaultItemsPerPage();
		}
	}

	/**
	 * @param  string $name
	 * @param  DataObject $item
	 * @return mixed
	 */
	protected function getValueFromItem($name, $item) {
		if (!strpos($name, '.')) {
			return $item->$name;
		}

		$parts = explode('.', $name);

		foreach ($parts as $k => $part) {
			if ($k == count($parts) - 1) {
				$item = $item->$part;
			} else {
				$item = $item->$part();
			}
		}

		return $item;
	}

	/**
	 * @param  string $name
	 * @return string
	 */
	protected function convertNameToRelationID($name) {
		return preg_replace('/\.(.*)$/', '.ID', $name);
	}

	/**
	 * @return string
	 */
	protected function getSortLink($name) {
		$current = $this->getSort();
		$dir     = 'ASC';

		if ($current['sort'] == $name && $current['dir'] == 'ASC') {
			$dir = 'DESC';
		}

		$link = HTTP::setGetVar('sort', $name, null, '&');
		$link = HTTP::setGetVar('dir', $dir, $link, '&');

		return $link;
	}

	/**
	 * @return string
	 */
	protected function getSortCssClasses($name) {
		$current = $this->getSort();
		$classes = array('listing-sortable');

		if ($current['sort'] == $name) {
			$classes[] = 'listing-sorted';
			$classes[] = 'listing-sort-' . strtolower($current['dir']);
		} else {
			$classes[] = 'listing-sort-asc';
		}

		return implode(' ', $classes);
	}

	// TEMPLATE METHODS --------------------------------------------------------

	/**
	 * @return string
	 */
	public function Title() {
		return $this->PluralName();
	}

	/**
	 * @return DataObjectSet
	 */
	public function HeaderRow() {
		$columns  = new DataObjectSet();
		$fields   = $this->getListingFields();
		$sortable = $this->getSortableFields();

		foreach ($fields as $name => $title) {
			if (in_array($name, $sortable)) {
				$sortLink  = $this->getSortLink($name);
				$sortClass = $this->getSortCssClasses($name);
			} else {
				$sortLink  = null;
				$sortClass = 'listing-not-sortable';
			}

			$columns->push(new ArrayData(array(
				'Name'      => $name,
				'Title'     => $title,
				'Sortable'  => in_array($name, $sortable),
				'SortLink'  => $sortLink,
				'SortClass' => $sortClass
			)));
		}

		return $columns;
	}

	/**
	 * @return DataObjectSet
	 */
	public function TableItems() {
		$result = new DataObjectSet();
		$items  = $this->getSourceItems();
		$fields = $this->getListingFields();

		if ($items) foreach ($items as $item) {
			$result->push($row = new DataObjectSet());

			foreach ($fields as $name => $title) {
				$row->push(new ArrayData(array(
					'Name'  => $name,
					'Link'  => Controller::join_links($this->Link(), $item->ID),
					'Value' => $this->getValueFromItem($name, $item)
				)));
			}
		}

		$limits = $items->getPageLimits();
		$result->setPageLimits(
			$limits['pageStart'], $limits['pageLength'], $limits['totalSize']
		);

		return $result;
	}

	/**
	 * @return DataObjectSet
	 */
	public function PerPageSummary() {
		$allowed = $this->getAllowedItemsPerPage();
		$current = $this->getItemsPerPage();
		$result  = new DataObjectSet();

		foreach ($allowed as $num) {
			$result->push(new ArrayData(array(
				'Num'     => $num,
				'Link'    => HTTP::setGetVar('perpage', $num),
				'Current' => $num == $current
			)));
		}

		return $result;
	}

	/**
	 * @return string
	 */
	public function SingularName() {
		return singleton($this->getItemClass())->singular_name();
	}

	/**
	 * @return string
	 */
	public function PluralName() {
		return singleton($this->getItemClass())->plural_name();
	}

}