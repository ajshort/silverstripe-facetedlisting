<?php
/**
 * A utility search filter that exposes useful relationship application
 * functionality.
 *
 * @package silverstripe-facetedlisting
 */
class FacetedUtilSearchFilter extends SearchFilter {

	/**
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @ignore
	 */
	public function apply(SQLQuery $query) { /* empty */ }

}