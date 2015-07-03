# URL Connector

URL connector is an extension for [Symphony CMS][1] which performs frontend URL routing and URL redirection.

## How To Use
Symphony users will probably know what URL routing is. To view/edit routes select _Blueprints -> URL Connections_.

### Routes
Here is an example route:

Source URL: `/articles/{title}/`, destination URL: `article-page/{title}`.

Braces are used to enclose path parameters. Parameters can be subjected to regular expression matching or 'type' testing where the type can be either _numeric_ or _non-numeric_.

### Adding PHP Code
The optional PHP field allows PHP code to be run after a URL route has been matched. This allows the request variables to be tested or modified.

Example PHP code:

    // Allow connection only if this is an AJAX call.
    if (!isset($_POST['ajax'])) {
        return false; // Move on to next route
    }

    // Create new field.
    $_POST['fields']['fullname'] = "{$_POST['fields']['firstname']} {$_POST['fields']['surname']}";

### Router Execution
Routes are processed in the order that they are defined. If the source URL does not match any defined route then the URL is passed on to Symphony's standard page routing.

### Page Access
URL Connector creates a new page type, NDA, meaning "no direct access." A page of type NDA can be accessed only through URL Connector's routing system.

[1]: http://www.getsymphony.com
