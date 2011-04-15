# SilverStripe Faceted Listing Module

The faceted listing module provides a framework for building complex record
listing pages with dynamic faceting and keyword search.

## Basic Usage
In order to create a basic listing page, all you need to do is subclass
`FacetedListingController`, and implement the `getItemClass` method, which
returns the class that is being listed. For example:

    :::php
    class ProductListingController extends FacetedListingController {
        public function getItemClass() {
            return 'Product';
        }
    }

Then, once you set up the relevant routes and visit the controller, you should
be presented with a simple listing page with a table listing all the objects
in question.

By default the summary fields of the object are displayed in the table - you can
overload `getListingFields` on your controller to customise this.

## Adding Sortable Fields
You can also allow users to sort the records by field names - in order to do
this you need to overload `getSortableFields`. This method should just return
an array of field names that the user can sort, corresponding to field names
returned by `getListingFields`.

The default sort when first loaded is read from the classes `$default_sort` value,
but you can change this by overloading `getDefaultSort`, which should return an
array with a `sort` and `dir` key.

## Adding Faceting
In order to add dynamic faceting all that needs to be done is overload the
`getFacetableFields` method. You can use dot syntax in the values as well to
allow filtering over any kind of relationship. For example:

    :::php
    class ProductListingController extends FacetedListingController {
        // ...
        
        public function getFacetableFields() {
            return array(
                'Type'       => 'Product type',
                'Tags.Title' => 'Product tag
            );
        }
    }

By default this will generate a dropdown listing each available option. You can
customise the faceeting process by overloading `getFacetFilters` and `getFacetMap`.

## Adding Fulltext Search
Before you add fulltext search to your controller, you need to set up a FULLTEXT
index on your model which contains the fields you wish to match against. Once
you have done this, all you need to do is overload `getFulltextFields` to return
an array of field names to match against. Once this is done the user will be
presented with a text field to enter keywords to search for.

    :::php
    class ProductListingController extends FacetedListingController {
        // ...
        
        public function getFulltextFields() {
            return array('Title', 'Description');
        }
    }

## Custom Item Controllers
When rendering individual items, the controller class specified in the
`$itemController` property is used. This defaults to `FacetedListingItemController`.
You can overload this property in your controller subclass to use a custom item
controller with additional functionality.

## Advanced Customisation

You can also overload almost any part of the system by overloading methods
on the `FacetedListingController` class. For example, you can control the number
of records a user can select to view at once by overloading the 
`getAllowedItemsPerPage` method. See API documentation for more details.
