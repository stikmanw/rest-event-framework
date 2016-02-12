<?php
namespace Common\Event\Definition;

class CrudEvents
{
    const BEFORE_CREATE = "resource.before.create";

    const AFTER_CREATE = "resource.after.create";

    const BEFORE_GET = "resource.before.get";

    const AFTER_GET = "resource.after.get";

    const BEFORE_UPDATE = "resource.before.update";

    const AFTER_UPDATE = "resource.after.update";

    const BEFORE_SEARCH = "resource.before.search";

    const AFTER_SEARCH = "resource.after.search";

    const BEFORE_DELETE = "resource.before.delete";

    const AFTER_DELETE = "resource.after.delete";

}