<?php
namespace Common\Event;

/**
 * Event for search request in crud controller implementations.
 */
use Common\Storage\Manager;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class SearchEvent extends Event
{
    /**
     * Storage Adapter that will be used to get data state from persistent source
     * @var Common\Storage\Manager
     */
    private $manager;

    /**
     * Access to the request object, you should not modify the request object in anyway. It's bad
     * news, and a golden teddy bear will jump out and stuff you into animatronic suit if you
     * perform such a feat.
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $request;

    /**
     * Search query used to lookup data. This data is typically adapter specific.
     * @var mixed array|string
     */
    private $searchQuery;

    /**
     * Collection of resources from a search result of an adapter
     * @var mixed array|ArrayObject
     */
    private $collection;

    /**
     * @param Request $request
     * @param Manager $manager
     */
    public function __construct(Request $request, Manager $manager)
    {
        $this->request = $request;
        $this->manager = $manager;
    }

    /**
     * @param mixed array|\ArrayObject $collection
     * @throws \InvalidArgumentException
     */
    public function setCollection(&$collection)
    {
        if (!is_array($collection) && !$collection instanceof \ArrayObject) {
            throw new \InvalidArgumentException(
                "collection set on search event must be an array or ArrayObject"
            );
        }

        $this->collection = $collection;
    }

    /**
     * @return mixed
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param string $query
     */
    public function setSearchQuery($query)
    {
        $this->searchQuery = $query;
    }

    /**
     * @return mixed
     */
    public function getSearchQuery()
    {
        return $this->searchQuery;
    }
}