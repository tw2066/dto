## PHP Swagger DTO
[![Latest Stable Version](https://img.shields.io/packagist/v/tangwei/dto)](https://packagist.org/packages/tangwei/dto)
[![Total Downloads](https://img.shields.io/packagist/dt/tangwei/dto)](https://packagist.org/packages/tangwei/dto)
[![License](https://img.shields.io/packagist/l/tangwei/dto)](https://github.com/tw2066/dto)

基于 [Hyperf](https://github.com/hyperf/hyperf) 框架的 DTO 类映射

##### 优点

- 请求参数映射到PHP类
- 代码可维护性好，扩展性好
- 支持数组，递归，嵌套
- 支持框架数据验证器

## 注意

- 模型类需要手工编写
- php >= 8.1

## 安装

```
composer require tangwei/dto
```

## 使用

### 1. 使用

## 注解

> 命名空间:`Hyperf\DTO\Annotation\Contracts`

#### RequestBody

- 获取Body参数

```php
public function add(#[RequestBody] DemoBodyRequest $request){}
```

### RequestQuery

- 获取GET参数

```php
public function add(#[RequestQuery] DemoQuery $request){}
```

### RequestFormData

- 获取表单请求

```php
public function fromData(#[RequestFormData] DemoFormData $formData){}
```

- 获取文件(和表单一起使用)

```php
#[ApiFormData(name: 'photo', type: 'file')]
```

- 获取Body参数和GET参数

```php
public function add(#[RequestBody] DemoBodyRequest $request, #[RequestQuery] DemoQuery $query){}
```

> 注意: 同一个方法不能同时存在RequestBody和RequestFormData注解

## 示例

### 控制器

```php
#[Controller(prefix: '/demo')]
#[Api(tags: 'demo管理', position: 1)]
class DemoController extends AbstractController
{
    #[ApiOperation(summary: '查询')]
    #[PostMapping(path: 'index')]
    public function index(#[RequestQuery] #[Valid] DemoQuery $request): Contact
    {
        $contact = new Contact();
        $contact->name = $request->name;
        var_dump($request);
        return $contact;
    }

    #[PutMapping(path: 'add')]
    public function add(#[RequestBody] DemoBodyRequest $request, #[RequestQuery] DemoQuery $query)
    {
        var_dump($query);
        return json_encode($request, JSON_UNESCAPED_UNICODE);
    }

    #[PostMapping(path: 'fromData')]
    public function fromData(#[RequestFormData] DemoFormData $formData): bool
    {
        $file = $this->request->file('photo');
        var_dump($file);
        var_dump($formData);
        return true;
    }

    #[GetMapping(path: 'find/{id}/and/{in}')]
    public function find(int $id, float $in): array
    {
        return ['$id' => $id, '$in' => $in];
    }

}

```

## 验证器

### 基于框架的验证

> 安装hyperf框架验证器[hyperf/validation](https://github.com/hyperf/validation), 并配置(已安装忽略)

- 注解
  `Required` `Between` `Date` `Email` `Image` `Integer` `Nullable` `Numeric`  `Url` `Validation`
- 校验生效

> 只需在控制器方法中加上 #[Valid] 注解

```php
public function index(#[RequestQuery] #[Valid] DemoQuery $request){}
```

```php
class DemoQuery
{
    public string $name;

    #[Required]
    #[Integer]
    #[Between(1,5)]
    public int $num;
}
```

- Validation

> rule 支持框架所有验证
- 自定义验证注解
> 只需继承`Hyperf\DTO\Annotation\Validation\BaseValidation`即可
```php
#[Attribute(Attribute::TARGET_PROPERTY)]
class Image extends BaseValidation
{
    protected $rule = 'image';
}
```
## RPC [返回PHP对象](https://hyperf.wiki/3.1/#/zh-cn/json-rpc?id=%e8%bf%94%e5%9b%9e-php-%e5%af%b9%e8%b1%a1)
> aspects.php中配置
```php
return [
    \Hyperf\DTO\Aspect\ObjectNormalizerAspect::class
]
```
> 当框架导入 symfony/serializer (^5.0) 和 symfony/property-access (^5.0) 后，并在 dependencies.php 中配置一下映射关系
```php
use Hyperf\Serializer\SerializerFactory;
use Hyperf\Serializer\Serializer;

return [
    Hyperf\Contract\NormalizerInterface::class => new SerializerFactory(Serializer::class),
];
```
