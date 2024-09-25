# Class Dependency Resolver

## Description

The PHP Class Dependency Resolver is a library designed to facilitate providing context to Large Language Models by
resolving dependencies of PHP classes/files. It maps fully qualified class names, interfaces, and traits to their
corresponding file paths and manages the direct dependencies between files. The main entry point to the library is
the `Resolver` class.

## Table of Contents

- [Installation](#installation)
- [Basic Usage](#basic-usage)
- [Configuration](#configuration)
- [Examples](#examples)
- [Contributing](#contributing)
- [Testing](#testing)
- [License](#license)

## Installation

To install the Class Dependency Resolver library, use Composer:

```bash
composer require thomasmarinissen/class-dependency-resolver
```

Ensure that your PHP version is compatible (PHP 8.3 or higher) and that you have Composer installed on your system.

## Basic Usage

To use the Class Dependency Resolver, you need to initialize the `Resolver` class with the directories you want to scan
for PHP files. Here is a basic example:

```php
use Thomasmarinissen\ClassDependencyResolver\Resolver;

// Directories to scan
$directories = ['/path/to/your/php/files'];

// Initialize the Resolver
$resolver = new Resolver($directories);

// Get the file path for a specific class
$filePath = $resolver->filePathByName('Your\Namespace\YourClass');

// Get the dependencies for a specific file
$dependencies = $resolver->dependenciesByFile('/path/to/your/php/files/YourClass.php');
```

This example demonstrates how to initialize the resolver, build the dependency maps, and retrieve file paths and
dependencies.

## Configuration

The `Resolver` class allows you to specify the PHP version you want to target when parsing files. By default, it uses
the host's PHP version. You can specify a different version by passing a `PhpVersion` instance to the constructor:

```php
use PhpParser\PhpVersion;
use Thomasmarinissen\ClassDependencyResolver\Resolver;

// Specify PHP version
$phpVersion = PhpVersion::fromString('8.3');

// Initialize the Resolver with a specific PHP version
$resolver = new Resolver(['/path/to/your/php/files'], $phpVersion);
```

This allows you to ensure compatibility with different PHP versions when resolving dependencies.

## Examples

### Example 1: Resolving Class Dependencies

```php
use Thomasmarinissen\ClassDependencyResolver\Resolver;

$directories = ['/path/to/your/php/files'];
$resolver = new Resolver($directories);

$className = 'App\Controllers\HomeController';
$filePath = $resolver->filePathByName($className);
$dependencies = $resolver->dependenciesByName($className);

echo "File path for $className: $filePath\n";
echo "Dependencies: " . implode(', ', $dependencies) . "\n";
```

### Example 2: Handling Multiple Directories

```php
use Thomasmarinissen\ClassDependencyResolver\Resolver;

$directories = ['/path/to/first/directory', '/path/to/second/directory'];
$resolver = new Resolver($directories);

$filePath = $resolver->filePathByName('Another\Namespace\AnotherClass');

echo "File path: $filePath\n";
```

### Example 3: Resolving Class Dependencies by file path

```php
use Thomasmarinissen\ClassDependencyResolver\Resolver;

$directories = ['/path/to/first/directory'];
$resolver = new Resolver($directories);

$filePath = '/path/to/first/directory/AnotherClass.php';

$dependencies = $resolver->dependenciesByFile($filePath);

echo "Dependencies: " . implode(', ', $dependencies) . "\n";
```

These examples illustrate how to resolve dependencies for classes or files and handle multiple directories.

## Contributing

Contributions are welcome! If you wish to contribute to the Class Dependency Resolver project, please follow these
guidelines:

1. Fork the repository.
2. Create a new branch for your feature or bugfix.
3. Make your changes and ensure that all tests pass.
4. Submit a pull request with a clear description of your changes.

Please adhere to the coding standards and include tests for any new functionality.

## Testing

To run the tests for the Class Dependency Resolver library, you need to have PHPUnit installed. You can run the tests
using the following command:

```bash
composer test
```

This command will execute the PHPUnit tests defined in the `tests` directory. Ensure that all tests pass before
submitting any contributions.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
