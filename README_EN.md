# Hyperf DTO

[![Latest Stable Version](https://img.shields.io/packagist/v/tangwei/dto)](https://packagist.org/packages/tangwei/dto)
[![Total Downloads](https://img.shields.io/packagist/dt/tangwei/dto)](https://packagist.org/packages/tangwei/dto)
[![License](https://img.shields.io/packagist/l/tangwei/dto)](https://github.com/tw2066/dto)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://www.php.net)

English | [中文](./README.md)

A Data Transfer Object (DTO) mapping and validation library for the [Hyperf](https://github.com/hyperf/hyperf) framework, leveraging PHP 8.1+ Attributes to provide an elegant solution for request parameter binding and validation.

## ✨ Features

- 🚀 **Auto Mapping** - Automatically map request parameters to PHP DTO classes
- 🎯 **Type Safety** - Leverage PHP 8.1+ type system for complete type hints
- 🔄 **Recursive Support** - Support for arrays, nested objects, and recursive structures
- ✅ **Data Validation** - Integrate with Hyperf validator, providing rich validation annotations
- 📝 **Multiple Parameter Sources** - Support Body, Query, FormData, Header, and more
- 🎨 **Elegant Code** - Based on PHP 8 Attributes for clean and readable code
- 🔧 **Easy to Extend** - Support custom validation rules and type conversion

## 📋 Requirements

- PHP >= 8.1
- Hyperf >= 3.0
- Swoole >= 5.0

## 📦 Installation

```bash
composer require tangwei/dto
```

After installation, the component will be automatically registered without any additional configuration.

## 📖 Quick Start

### Basic Usage

#### 1. Create a DTO Class

```php
namespace App\Request;

use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Integer;
use Hyperf\DTO\Annotation\Validation\Between;

class DemoQuery
{
    public string $name;

    #[Required]
    #[Integer]
    #[Between(1, 100)]
    public int $age;
}
```

#### 2. Use in Controller

```php
namespace App\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\Valid;
use App\Request\DemoQuery;

#[Controller(prefix: '/user')]
class UserController
{
    #[GetMapping(path: 'info')]
    public function info(#[RequestQuery] #[Valid] DemoQuery $request): array
    {
        return [
            'name' => $request->name,
            'age' => $request->age,
        ];
    }
}
```

## 📚 Annotation Reference

### Parameter Source Annotations

> Namespace: `Hyperf\DTO\Annotation\Contracts`

#### RequestBody

Retrieve parameters from POST/PUT/PATCH request body

```php
use Hyperf\DTO\Annotation\Contracts\RequestBody;

#[PostMapping(path: 'create')]
public function create(#[RequestBody] CreateUserRequest $request)
{
    // $request will be automatically populated with data from the body
}
```

#### RequestQuery

Retrieve URL query parameters (GET parameters)

```php
use Hyperf\DTO\Annotation\Contracts\RequestQuery;

#[GetMapping(path: 'list')]
public function list(#[RequestQuery] QueryRequest $request)
{
    // $request will be automatically populated with query parameters
}
```

#### RequestFormData

Retrieve form request data (Content-Type: multipart/form-data)

```php
use Hyperf\DTO\Annotation\Contracts\RequestFormData;

#[PostMapping(path: 'upload')]
public function upload(#[RequestFormData] UploadRequest $formData)
{
    // $formData will be automatically populated with form data
    // File uploads need to be retrieved via $this->request->file('field_name')
}
```

#### RequestHeader

Retrieve request header information

```php
use Hyperf\DTO\Annotation\Contracts\RequestHeader;

#[GetMapping(path: 'info')]
public function info(#[RequestHeader] HeaderRequest $headers)
{
    // $headers will be automatically populated with request header data
}
```

#### Valid

Enable validation, must be used together with other parameter source annotations

```php
#[PostMapping(path: 'create')]
public function create(#[RequestBody] #[Valid] CreateUserRequest $request)
{
    // Request parameters will be validated first; validation failure will throw an exception
}
```

### Combined Usage

You can combine multiple parameter sources in the same method:

```php
#[PutMapping(path: 'update/{id}')]
public function update(
    int $id,
    #[RequestBody] #[Valid] UpdateRequest $body,
    #[RequestQuery] QueryRequest $query,
    #[RequestHeader] HeaderRequest $headers
) {
    // Retrieve Body, Query, and Header parameters simultaneously
}
```

> ⚠️ **Note**: The same method cannot use both `RequestBody` and `RequestFormData` annotations

## 📝 Complete Examples

### Controller Example

```php
namespace App\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\DTO\Annotation\Contracts\RequestBody;
use Hyperf\DTO\Annotation\Contracts\RequestQuery;
use Hyperf\DTO\Annotation\Contracts\RequestFormData;
use Hyperf\DTO\Annotation\Contracts\Valid;

#[Controller(prefix: '/demo')]
class DemoController
{
    #[GetMapping(path: 'query')]
    public function query(#[RequestQuery] #[Valid] DemoQuery $request): array
    {
        return [
            'name' => $request->name,
            'age' => $request->age,
        ];
    }

    #[PostMapping(path: 'create')]
    public function create(#[RequestBody] #[Valid] CreateRequest $request): array
    {
        // Handle creation logic
        return ['id' => 1, 'message' => 'Created successfully'];
    }

    #[PutMapping(path: 'update')]
    public function update(
        #[RequestBody] #[Valid] UpdateRequest $body,
        #[RequestQuery] QueryParams $query
    ): array {
        // Use both Body and Query parameters
        return ['message' => 'Updated successfully'];
    }

    #[PostMapping(path: 'upload')]
    public function upload(#[RequestFormData] UploadRequest $formData): array
    {
        $file = $this->request->file('photo');
        // Handle file upload
        return ['message' => 'Uploaded successfully'];
    }
}
```

### DTO Class Examples

#### Simple DTO

```php
namespace App\Request;

use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Integer;
use Hyperf\DTO\Annotation\Validation\Between;
use Hyperf\DTO\Annotation\Validation\Email;

class CreateRequest
{
    #[Required]
    public string $name;

    #[Required]
    #[Email]
    public string $email;

    #[Required]
    #[Integer]
    #[Between(18, 100)]
    public int $age;
}
```

#### Nested Object DTO

```php
namespace App\Request;

class UserRequest
{
    public string $name;
    
    public int $age;
    
    // Nested object
    public Address $address;
}

class Address
{
    public string $province;
    
    public string $city;
    
    public string $street;
}
```

#### Array Type DTO

```php
namespace App\Request;

use Hyperf\DTO\Annotation\ArrayType;

class BatchRequest
{
    /**
     * @var int[]
     */
    public array $ids;

    /**
     * @var User[]
     */
    public array $users;
    
    // Use ArrayType annotation to explicitly specify type
    #[ArrayType(User::class)]
    public array $members;
}
```

#### Custom Field Names

```php
namespace App\Request;

use Hyperf\DTO\Annotation\JSONField;

class ApiRequest
{
    // Map user_name from the request to userName
    #[JSONField('user_name')]
    public string $userName;
    
    #[JSONField('user_age')]
    public int $userAge;
}
```

## ✅ Data Validation

### Built-in Validation Annotations

> First, install the Hyperf validator: `composer require hyperf/validation`

This library provides rich validation annotations, including:

- `Required` - Required field
- `Integer` - Integer
- `Numeric` - Numeric
- `Between` - Range validation
- `Min` / `Max` - Minimum/Maximum value
- `Email` - Email format
- `Url` - URL format
- `Date` - Date format
- `DateFormat` - Specified date format
- `Boolean` - Boolean value
- `Alpha` - Alphabetic characters
- `AlphaNum` - Alphanumeric characters
- `AlphaDash` - Alphanumeric characters, dashes, and underscores
- `Image` - Image file
- `Json` - JSON format
- `Nullable` - Nullable
- `In` - In specified values
- `NotIn` - Not in specified values
- `Regex` - Regular expression
- `Unique` - Database unique
- `Exists` - Database exists

### Usage Examples

#### Basic Validation

```php
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Integer;
use Hyperf\DTO\Annotation\Validation\Between;

class DemoQuery
{
    #[Required]
    public string $name;

    #[Required]
    #[Integer]
    #[Between(1, 100)]
    public int $age;
}
```

Enable validation in the controller using the `#[Valid]` annotation:

```php
#[GetMapping(path: 'query')]
public function query(#[RequestQuery] #[Valid] DemoQuery $request)
{
    // Parameters have been validated
}
```

#### Custom Error Messages

```php
class UserRequest
{
    #[Required("Username cannot be empty")]
    public string $name;

    #[Between(18, 100, "Age must be between 18 and 100")]
    public int $age;
}
```

#### Using Validation Annotation

The `Validation` annotation supports Laravel-style validation rules:

```php
use Hyperf\DTO\Annotation\Validation\Validation;

class ComplexRequest
{
    // Use pipe separator for multiple rules
    #[Validation("required|string|min:3|max:50")]
    public string $username;

    // Array element validation
    #[Validation("integer", customKey: 'ids.*')]
    public array $ids;
}
```

### Custom Validation Rules

Create custom validation rules by extending the `BaseValidation` class:

```php
namespace App\Validation;

use Attribute;
use Hyperf\DTO\Annotation\Validation\BaseValidation;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Phone extends BaseValidation
{
    protected $rule = 'regex:/^1[3-9]\\d{9}$/';
    
    public function __construct(string $messages = 'Invalid phone number format')
    {
        parent::__construct($messages);
    }
}
```

Use custom validation:

```php
use App\Validation\Phone;

class RegisterRequest
{
    #[Required]
    #[Phone]
    public string $mobile;
}
```

## 🔧 Advanced Features

### RPC Support

To return PHP objects in JSON-RPC services, you need to configure serialization support.

#### 1. Install Dependencies

```bash
composer require symfony/serializer ^5.0|^6.0
composer require symfony/property-access ^5.0|^6.0
```

#### 2. Configure Aspect

Add to `config/autoload/aspects.php`:

```php
return [
    \Hyperf\DTO\Aspect\ObjectNormalizerAspect::class,
];
```

#### 3. Configure Dependencies

Add to `config/autoload/dependencies.php`:

```php
use Hyperf\Serializer\SerializerFactory;
use Hyperf\Serializer\Serializer;

return [
    Hyperf\Contract\NormalizerInterface::class => new SerializerFactory(Serializer::class),
];
```

### Custom Type Conversion

If you need custom type conversion logic, you can implement your own converter:

```php
namespace App\Convert;

use Hyperf\DTO\Type\ConvertCustom;

class CustomConvert implements ConvertCustom
{
    public function convert(mixed $value): mixed
    {
        // Custom conversion logic
        return $value;
    }
}
```

Use in DTO class:

```php
use Hyperf\DTO\Annotation\Dto;
use Hyperf\DTO\Type\Convert;

#[Dto(Convert::SNAKE)]
class UserResponse
{
    public string $name;
    public int $age;
}
```

## 💡 Best Practices

### 1. DTO Class Structure Design

- Create independent DTO classes for different request types
- Use meaningful class names, such as `CreateUserRequest`, `UpdateUserRequest`
- Store Request DTOs and Response DTOs separately

### 2. Validation Rules

- Prefer built-in validation annotations for code readability
- Use `Validation` annotation for complex validation
- Encapsulate common validation rules as custom annotations

### 3. Error Handling

Validation failures throw `Hyperf\Validation\ValidationException` exceptions, which can be handled uniformly through an exception handler:

```php
namespace App\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;

class ValidationExceptionHandler extends ExceptionHandler
{
    public function handle(\Throwable $throwable, ResponseInterface $response)
    {
        if ($throwable instanceof ValidationException) {
            $this->stopPropagation();
            return $response->withStatus(422)->withBody(
                new SwooleStream(json_encode([
                    'code' => 422,
                    'message' => 'Validation failed',
                    'errors' => $throwable->validator->errors()->toArray(),
                ]))
            );
        }
        return $response;
    }

    public function isValid(\Throwable $throwable): bool
    {
        return $throwable instanceof ValidationException;
    }
}
```

## 📚 FAQ

### Q: Why isn't validation working?

A: Please ensure:
1. The `hyperf/validation` component is installed
2. The `#[Valid]` annotation is added to the controller method parameter
3. Validation annotations are added to properties in the DTO class

### Q: How to handle nested arrays?

A: Use PHPDoc or the `ArrayType` annotation:

```php
/**
 * @var User[]
 */
public array $users;

// Or
#[ArrayType(User::class)]
public array $users;
```

### Q: Can RequestBody and RequestFormData be used together?

A: No. These two annotations are mutually exclusive as they handle different request types.

### Q: How to handle file uploads?

A: Use the `RequestFormData` annotation, then retrieve the file via `$this->request->file()`.

## 🔗 Related Links

- [Hyperf Official Documentation](https://hyperf.wiki)
- [Hyperf Validation](https://hyperf.wiki/3.1/#/en/validation)
- [PHP Attributes](https://www.php.net/manual/en/language.attributes.php)

## 📝 Contributing

Issues and Pull Requests are welcome!

1. Fork this repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📜 License

[MIT License](LICENSE)

## ❤️ Acknowledgments

- [Hyperf](https://github.com/hyperf/hyperf) - Excellent coroutine PHP framework
- [JsonMapper](https://github.com/cweiske/jsonmapper) - JSON to PHP object mapping library

---

If this project helps you, please give it a ⭐ Star!
