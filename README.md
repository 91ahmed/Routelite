````markdown
# Routelite

Routelite is a lightweight **PHP** routing library for building flexible and maintainable web applications.  
It supports route parameters, middlewares, route groups, prefixes, and multi-language URLs.

---

## Features

- Supports **GET** and **POST** routes.
- Route-specific, group, and global **Middleware** support.
- Route parameters with **Regex validation**.
- **Prefix** support for route groups or individual routes.
- Multi-language URL support.
- Custom **404 Not Found** handling.
- **Singleton** pattern to ensure only one instance.
- Ability to **remove unwanted text** from URLs before processing.

---

## Installation

You can install Routelite manually or via Composer:

```bash
composer require 91ahmed/routelite
````

Use it in your project:

```php
use Router\Routelite;

$route = Routelite::collect();
```

---

## Basic Usage

### 1. Defining a simple route

```php
$route->get('/', 'Controller\HomeController@index')->add();
$route->post('/submit', 'Controller\FormController@submit')->add();
```

### 2. Route parameters with regex validation

```php
$route->get('/user/profile', 'Controller\UserController@profile')
      ->params(['id', 'username'])
      ->where('id', '/^[0-9]+$/')
      ->where('username', '/^[a-zA-Z0-9]+$/')
      ->add();
```

### 3. Using Middleware

* **Route-specific middleware:**

```php
$route->get('/dashboard', 'Controller\Dashboard@index')
      ->middleware(['auth', 'log'])
      ->add();
```

* **Group middleware:**

```php
$route->middlewareGroup(['auth'], function ($route) {
    $route->get('/users/all', 'Controller\UserController@index')->add();
});
```

* **Global middleware for all routes:**

```php
$route->middlewareGlobal(['session']);
```

---

### 4. Route Groups and Prefixes

```php
$route->prefixGroup('dashboard', function ($route) {
    $route->middlewareGroup(['auth'], function ($route) {
        $route->get('/users/all', 'Controller\UserController@index')->add();
        $route->get('/home', 'Controller\HomeController@home')->add();
    });
});
```

* All routes inside the `dashboard` prefix group will start with `/dashboard/...`.
* Middleware `auth` is applied to all routes inside the middleware group.

---

### 5. Multi-language support

```php
$route->setLanguage(['ar', 'en']);
```

* The first segment of the URL will be treated as the language:

  * `/ar/dashboard` → Arabic
  * `/en/dashboard` → English

---

### 6. Removing unwanted text from URLs

```php
$route->remove('Unwanted Word');
```

* Removes specified text from the URL before processing.

---

### 7. Handling 404 Not Found

```php
$route->notFound(function () {
    exit('404 Not Found Page');
});
```

* Executes the callback if no matching route is found or there is a parameter error.

---

### 8. Listing all routes

```php
$allRoutes = $route->getRoutes();
print_r($allRoutes);
```

---

### 9. Rendering Routes

After defining all your routes, you **must** call the `render()` method to process the current request and execute the matching route:

```php
$route->render();
```

* `render()` will match the current URL against all defined routes.
* It executes the associated **controller action** if a match is found.
* If no match is found, you can handle it using `notFound()`:

```php
$route->notFound(function () {
    exit('404 Not Found Page');
});
```

> **Important:** Always call `render()` **after all route definitions**. Without it, no routes will be processed.

---

## Full Example

```php
use Router\Routelite;

$route = Routelite::collect();

$route->remove('Unwanted Word');

$route->middlewareGlobal(['session']);

$route->setLanguage(['ar', 'en']);

$route->prefixGroup('dashboard', function ($route) {
    $route->middlewareGroup(['auth'], function ($route) {
        $route->get('/users/all/', 'Controller\HomeController@index')
              ->params(['id', 'username'])
              ->where('id', '/^[0-9]+$/')
              ->where('username', '/^[a-zA-Z0-9]+$/')
              ->add();

        $route->get('/home/', 'Controller\HomeController@home')->add();
    });
});

$route->get('/', 'Controller\HomeController@index')->params(['lang'])->middleware(['auth'])->add();
$route->get('/admin', 'Controller\HomeController@admin')->params(['id'])->add();
$route->post('/users', 'Controller\HomeController@admin')->add();

$route->render();

$route->notFound(function () {
    exit('404 Not Found Page');
});
```

---

## Important Notes

* Middleware names must contain only letters, numbers, underscores, or valid namespaces.
* All middleware classes must implement a `handle()` method.
* Magic methods in controllers (e.g., `__construct`, `__call`) are **not allowed**.
* Middleware execution order: **Global → Group → Route**.
* Always use `Routelite::collect()` to get the same instance. Do not create a new instance manually.
* `setLanguage([...])` should be called before defining routes that depend on language.
* When using `params([...])`, the order of parameter names must match their order in the URL.
* The `handle()` method in middleware must return **true** to continue or **false** to stop execution.
* After defining all routes, you **must** call `render()` to execute the matching route. Without `render()`, no routes will be processed.

---

## License

MIT License

---

> **Routelite** simplifies route management in PHP projects with full support for parameters, middleware, groups, prefixes, and multi-language URLs.